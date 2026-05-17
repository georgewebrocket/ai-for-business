<?php

require_once(__DIR__ . '/../php/config.php');
require_once(__DIR__ . '/../php/db.php');
require_once(__DIR__ . '/../php/dataobjects.php');
require_once(__DIR__ . '/../php/ai.php');
require_once(__DIR__ . '/../php/ai-settings.php');
require_once(__DIR__ . '/../php/editorial-context.php');
require_once(__DIR__ . '/../php/publishing.php');

date_default_timezone_set("Europe/Athens");

$db1 = new DB(conn1::$connstr, conn1::$username, conn1::$password);
$dbo = $db1;

function cron_ci_run_start($dbo) {
    $now = date('Y-m-d H:i:s');
    $id = $dbo->execSQL(
        'INSERT INTO cron_job_runs (job_name, status, started_at, created_at) VALUES (?, ?, ?, ?)',
        ['cron-create-items', 'running', $now, $now]
    );
    return ['id' => (int)$id, 'started_at' => microtime(true)];
}

function cron_ci_run_finish($dbo, $run, $status, $message, $context = []) {
    if (empty($run['id'])) {
        return;
    }
    $dbo->execSQL(
        'UPDATE cron_job_runs
         SET account_id = ?, property_id = ?, content_item_id = ?, content_idea_id = ?, status = ?, finished_at = ?, duration_seconds = ?, message = ?
         WHERE id = ?',
        [
            isset($context['account_id']) ? (int)$context['account_id'] : null,
            isset($context['property_id']) ? (int)$context['property_id'] : null,
            isset($context['content_item_id']) ? (int)$context['content_item_id'] : null,
            isset($context['content_idea_id']) ? (int)$context['content_idea_id'] : null,
            $status,
            date('Y-m-d H:i:s'),
            round(microtime(true) - (float)$run['started_at'], 3),
            $message,
            (int)$run['id'],
        ]
    );
}

function cron_ci_get_section($settings, $sectionKey) {
    if (isset($settings[$sectionKey]) && is_array($settings[$sectionKey])) {
        return $settings[$sectionKey];
    }
    if (!isset($settings['sections']) || !is_array($settings['sections'])) {
        return [];
    }
    foreach ($settings['sections'] as $section) {
        if (($section['key'] ?? '') !== $sectionKey || !isset($section['options']) || !is_array($section['options'])) {
            continue;
        }
        $values = [];
        foreach ($section['options'] as $option) {
            if (isset($option['key'])) {
                $values[$option['key']] = $option['value'] ?? null;
            }
        }
        return $values;
    }
    return [];
}

function cron_ci_period_start($period) {
    if ($period === 'day') {
        return date('Y-m-d 00:00:00');
    }
    if ($period === 'month') {
        return date('Y-m-01 00:00:00');
    }
    return date('Y-m-d 00:00:00', strtotime('monday this week'));
}

function cron_ci_slugify($text) {
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }
    if (function_exists('transliterator_transliterate')) {
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
    } else {
        $text = strtolower($text);
    }
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    return strtolower(trim($text, '-'));
}

function cron_ci_limit_text($text, $maxLength) {
    $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > $maxLength) {
        return rtrim(mb_substr($text, 0, $maxLength, 'UTF-8'));
    }
    if (!function_exists('mb_strlen') && strlen($text) > $maxLength) {
        return rtrim(substr($text, 0, $maxLength));
    }
    return $text;
}

function cron_ci_decode_sections($sectionsJson, $summary) {
    $decoded = json_decode((string)$sectionsJson, true);
    if (!is_array($decoded)) {
        $decoded = [];
    }
    $sections = [];
    foreach ($decoded as $section) {
        if (is_array($section)) {
            $title = trim((string)($section['title'] ?? $section['name'] ?? $section['heading'] ?? ''));
            $instructions = trim((string)($section['instructions'] ?? $section['description'] ?? ''));
            $wordCount = max(80, (int)($section['word_count'] ?? $section['words'] ?? 180));
        } else {
            $title = trim((string)$section);
            $instructions = '';
            $wordCount = 180;
        }
        if ($title !== '') {
            $sections[] = ['title' => $title, 'instructions' => $instructions, 'word_count' => $wordCount];
        }
    }
    if (!$sections) {
        $sections[] = ['title' => 'Main article', 'instructions' => $summary, 'word_count' => 600];
    }
    return $sections;
}

function cron_ci_article_seo($idea) {
    $metadata = json_decode((string)($idea['ai_response_json'] ?? ''), true);
    $article = is_array($metadata) && isset($metadata['article']) && is_array($metadata['article']) ? $metadata['article'] : [];
    return [
        'meta_title' => cron_ci_limit_text($article['meta_title'] ?? ($idea['title'] ?? ''), 60),
        'meta_description' => cron_ci_limit_text($article['meta_description'] ?? ($idea['summary'] ?? ''), 160),
    ];
}

function cron_ci_section_prompt($idea, $section, $sections, $index, $editorialContext = []) {
    $sectionsJson = json_encode($sections, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $editorialContextBlock = editorial_context_prompt_block($editorialContext);
    return <<<PROMPT
Write section {$index} for a content article.

Content idea:
Title: {$idea['title']}
Summary: {$idea['summary']}
Category: {$idea['category_name']}
Tags: {$idea['tags']}
Language: {$idea['language']}
Tone: {$idea['tone']}
General writing instructions: {$idea['instructions']}

Full section plan:
{$sectionsJson}

{$editorialContextBlock}

Current section:
Title: {$section['title']}
Instructions: {$section['instructions']}
Recommended word count: {$section['word_count']}

Return only clean HTML for this section. Use an h2 for the section title and paragraphs/lists where useful. Do not wrap the answer in markdown fences.
PROMPT;
}

function cron_ci_generate_section($dbo, $idea, $section, $sections, $index, $editorialContext, $aiSettings) {
    $ai = new ai(publisher_require_ai_api_key($dbo, (int)$idea['account_id']));
    $ai->text_model($aiSettings['text_model'] ?? 'gpt-5.2');
    $ai->log_context($dbo, [
        'account_id' => (int)$idea['account_id'],
        'property_id' => (int)$idea['property_id'],
        'content_idea_id' => (int)$idea['id'],
        'action_type' => 'generate_article',
    ]);
    $ai->instructions('You are an expert editorial writer. Return only clean HTML for the requested section.');
    $ai->prompt(cron_ci_section_prompt($idea, $section, $sections, $index, $editorialContext));
    $response = $ai->send_request();
    if (($response['result'] ?? '') !== 'success') {
        throw new Exception($response['message'] ?? 'AI section generation failed.');
    }
    return trim((string)($response['content'] ?? ''));
}

function cron_ci_safe_image_prompt($idea) {
    $prompt = trim((string)($idea['image_prompt'] ?? ''));
    if ($prompt === '') {
        return '';
    }
    $prompt = preg_replace('/\b(in|by|like|similar to|inspired by)\s+the\s+style\s+of\s+[^,.]+[,.]?/i', 'with an original editorial visual style, ', $prompt);
    $prompt = preg_replace('/\b(style of|inspired by|like)\s+[^,.]+[,.]?/i', 'original visual direction, ', $prompt);
    $safety = 'Create an original, generic, non-infringing editorial image. Do not include logos, trademarks, brand names, copyrighted characters, recognizable celebrities or public figures, movie/game/book characters, exact product designs, screenshots, album covers, posters, or imitation of any living artist. If the topic references a known brand, person, franchise, product, or protected work, represent only the broader concept with fictional/generic elements and no readable text. If any part of this prompt violates safety rules related to similarity with third-party content, remove those elements and replace them with similar generic elements that do not have that issue.';
    return trim($prompt . "\n\n" . $safety);
}

function cron_ci_relative_media_path($path) {
    $publisherRoot = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/') . '/';
    $normalizedPath = str_replace('\\', '/', $path);
    if (strpos($normalizedPath, $publisherRoot) === 0) {
        return substr($normalizedPath, strlen($publisherRoot));
    }
    return $path;
}

function cron_ci_create_article_from_idea($dbo, $idea, $aiSettings) {
    $generationStartedAt = date('Y-m-d H:i:s');
    $sections = cron_ci_decode_sections($idea['sections'] ?? '', $idea['summary'] ?? '');
    $editorialContext = editorial_context_get($dbo, (int)$idea['account_id'], (int)$idea['property_id']);
    $bodyParts = [];
    foreach ($sections as $index => $section) {
        $bodyParts[] = cron_ci_generate_section($dbo, $idea, $section, $sections, $index + 1, $editorialContext, $aiSettings);
    }

    $now = date('Y-m-d H:i:s');
    $seo = cron_ci_article_seo($idea);
    $dbo->execSQL(
        'INSERT INTO content_items
         (account_id, property_id, content_type_id, source_idea_id, title, slug, summary, meta_title, meta_description, body, status, language, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $idea['account_id'],
            $idea['property_id'],
            $idea['content_type_id'],
            $idea['id'],
            $idea['title'],
            cron_ci_slugify($idea['title']),
            $idea['summary'],
            $seo['meta_title'],
            $seo['meta_description'],
            implode("\n\n", $bodyParts),
            'draft',
            $idea['language'],
            null,
            $now,
            $now,
        ]
    );
    $itemRows = $dbo->getRS('SELECT id FROM content_items WHERE account_id = ? AND property_id = ? AND source_idea_id = ? ORDER BY id DESC LIMIT 1', [$idea['account_id'], $idea['property_id'], $idea['id']]);
    if (!$itemRows) {
        throw new Exception('Content item was not created.');
    }
    $contentItemId = (int)$itemRows[0]['id'];

    if ((int)($idea['category_id'] ?? 0) > 0) {
        $dbo->execSQL('INSERT IGNORE INTO content_item_categories (content_item_id, category_id) VALUES (?, ?)', [$contentItemId, (int)$idea['category_id']]);
    }

    $safeImagePrompt = cron_ci_safe_image_prompt($idea);
    if ($safeImagePrompt !== '') {
        try {
            $ai = new ai(publisher_require_ai_api_key($dbo, (int)$idea['account_id']));
            $ai->image_model($aiSettings['image_model'] ?? 'gpt-image-1.5');
            $ai->log_context($dbo, [
                'account_id' => (int)$idea['account_id'],
                'property_id' => (int)$idea['property_id'],
                'content_item_id' => $contentItemId,
                'content_idea_id' => (int)$idea['id'],
                'action_type' => 'generate_article',
            ]);
            $ai->prompt($safeImagePrompt);
            $imagePath = cron_ci_relative_media_path($ai->create_image(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'media'));
            $dbo->execSQL(
                'INSERT INTO media_assets
                 (account_id, property_id, content_item_id, type, source, prompt, file_path, external_url, alt_text, caption, metadata_json, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $idea['account_id'],
                    $idea['property_id'],
                    $contentItemId,
                    'image',
                    'openai',
                    $safeImagePrompt,
                    $imagePath,
                    null,
                    $idea['title'],
                    $idea['summary'],
                    json_encode(['content_idea_id' => (int)$idea['id'], 'ai_models' => $aiSettings, 'source' => 'cron-create-items'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                    $now,
                ]
            );
        } catch (Exception $ex) {
        }
    }

    $dbo->execSQL(
        'UPDATE ai_generation_logs
         SET content_item_id = ?
         WHERE account_id = ? AND property_id = ? AND content_idea_id = ? AND content_item_id IS NULL AND created_at >= ?',
        [$contentItemId, (int)$idea['account_id'], (int)$idea['property_id'], (int)$idea['id'], $generationStartedAt]
    );
    $dbo->execSQL('UPDATE content_ideas SET status = ?, content_item_id = ?, updated_at = ? WHERE id = ?', ['converted', $contentItemId, date('Y-m-d H:i:s'), $idea['id']]);
    return $contentItemId;
}

$cronRun = cron_ci_run_start($dbo);
$properties = $dbo->getRS("SELECT * FROM properties WHERE status = 'active' AND settings_json IS NOT NULL AND settings_json <> '' ORDER BY id") ?: [];

foreach ($properties as $property) {
    $settings = json_decode((string)$property['settings_json'], true);
    if (!is_array($settings)) {
        continue;
    }
    $config = cron_ci_get_section($settings, 'content_generation');
    $isAutomatic = ($config['mode'] ?? '') === 'automatic';
    $isQueued = !empty($config['schedule_pending']);
    if (!$isAutomatic && !$isQueued) {
        continue;
    }

    $targetCount = max(1, (int)($config['article_count'] ?? 1));
    $period = in_array(($config['period'] ?? 'day'), ['day', 'week', 'month'], true) ? $config['period'] : 'day';
    if ($isAutomatic) {
        $existingRows = $dbo->getRS(
            'SELECT id FROM content_items WHERE account_id = ? AND property_id = ? AND created_at >= ? ORDER BY id DESC',
            [(int)$property['account_id'], (int)$property['id'], cron_ci_period_start($period)]
        ) ?: [];
        if (count($existingRows) >= $targetCount) {
            continue;
        }
    }

    $propertyAiDefaults = publisher_property_ai_defaults($settings);
    $ideasDefaults = publisher_stage_ai_settings($settings, 'create_content_ideas', $propertyAiDefaults);
    $aiSettings = publisher_stage_ai_settings($settings, 'content_generation', $ideasDefaults);
    $publishingConfig = cron_ci_get_section($settings, 'publishing');

    $ideaRows = $dbo->getRS(
        "SELECT ci.*, cc.name AS category_name
         FROM content_ideas ci
         LEFT JOIN content_categories cc ON cc.id = ci.category_id
         WHERE ci.account_id = ? AND ci.property_id = ? AND ci.status = ? AND ci.content_item_id IS NULL
         ORDER BY ci.updated_at ASC, ci.created_at ASC, ci.id ASC
         LIMIT 1",
        [(int)$property['account_id'], (int)$property['id'], 'accepted']
    );
    if (!$ideaRows) {
        continue;
    }

    try {
        $ideaAiSettings = publisher_idea_ai_settings($ideaRows[0], $aiSettings);
        $contentItemId = cron_ci_create_article_from_idea($dbo, $ideaRows[0], $ideaAiSettings);
        if ($isQueued) {
            $config['schedule_pending'] = false;
            $settings = publisher_ai_set_settings_section($settings, 'content_generation', 'Content Generation', $config);
            $dbo->execSQL(
                'UPDATE properties SET settings_json = ?, updated_at = ? WHERE id = ? AND account_id = ?',
                [json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), date('Y-m-d H:i:s'), (int)$property['id'], (int)$property['account_id']]
            );
        }

        $message = "Created content item {$contentItemId} from idea {$ideaRows[0]['id']}";
        if (($publishingConfig['mode'] ?? '') === 'automatic') {
            $channelId = (int)($publishingConfig['distribution_channel_id'] ?? 0);
            $wordpressStatus = in_array(($publishingConfig['wordpress_status'] ?? 'draft'), ['draft', 'publish'], true)
                ? $publishingConfig['wordpress_status']
                : 'draft';
            if ($channelId > 0) {
                try {
                    publisher_publish_content_item(
                        $dbo,
                        $contentItemId,
                        $channelId,
                        (int)$ideaRows[0]['account_id'],
                        (int)$ideaRows[0]['property_id'],
                        ['default_status' => $wordpressStatus]
                    );
                    $message .= " and published to WordPress channel {$channelId} as {$wordpressStatus}";
                } catch (Exception $publishEx) {
                    $message .= "; WordPress publish failed: " . $publishEx->getMessage();
                }
            }
        }
        cron_ci_run_finish($dbo, $cronRun, 'success', $message, [
            'account_id' => (int)$ideaRows[0]['account_id'],
            'property_id' => (int)$ideaRows[0]['property_id'],
            'content_item_id' => (int)$contentItemId,
            'content_idea_id' => (int)$ideaRows[0]['id'],
        ]);
        echo $message . "\n";
    } catch (Exception $ex) {
        $dbo->execSQL('UPDATE content_ideas SET status = ?, updated_at = ? WHERE id = ?', ['suggested', date('Y-m-d H:i:s'), $ideaRows[0]['id']]);
        $message = "Idea {$ideaRows[0]['id']}: " . $ex->getMessage();
        cron_ci_run_finish($dbo, $cronRun, 'failed', $message, [
            'account_id' => (int)$ideaRows[0]['account_id'],
            'property_id' => (int)$ideaRows[0]['property_id'],
            'content_idea_id' => (int)$ideaRows[0]['id'],
        ]);
        echo $message . "\n";
    }
    exit;
}

$message = "No scheduled content item jobs";
cron_ci_run_finish($dbo, $cronRun, 'skipped', $message);
echo $message . "\n";
