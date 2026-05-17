<?php

require_once(__DIR__ . '/../php/config.php');
require_once(__DIR__ . '/../php/db.php');
require_once(__DIR__ . '/../php/dataobjects.php');
require_once(__DIR__ . '/../php/ai.php');
require_once(__DIR__ . '/../php/ai-settings.php');
require_once(__DIR__ . '/../php/editorial-context.php');

date_default_timezone_set("Europe/Athens");

$db1 = new DB(conn1::$connstr, conn1::$username, conn1::$password);
$dbo = $db1;

function cron_cci_run_start($dbo) {
    $now = date('Y-m-d H:i:s');
    $id = $dbo->execSQL(
        'INSERT INTO cron_job_runs (job_name, status, started_at, created_at) VALUES (?, ?, ?, ?)',
        ['cron-create-content-ideas', 'running', $now, $now]
    );
    return ['id' => (int)$id, 'started_at' => microtime(true)];
}

function cron_cci_run_finish($dbo, $run, $status, $message, $context = []) {
    if (empty($run['id'])) {
        return;
    }
    $dbo->execSQL(
        'UPDATE cron_job_runs
         SET account_id = ?, property_id = ?, content_idea_id = ?, status = ?, finished_at = ?, duration_seconds = ?, message = ?
         WHERE id = ?',
        [
            isset($context['account_id']) ? (int)$context['account_id'] : null,
            isset($context['property_id']) ? (int)$context['property_id'] : null,
            isset($context['content_idea_id']) ? (int)$context['content_idea_id'] : null,
            $status,
            date('Y-m-d H:i:s'),
            round(microtime(true) - (float)$run['started_at'], 3),
            $message,
            (int)$run['id'],
        ]
    );
}

function cron_cci_is_list_array($value) {
    return is_array($value) && (count($value) === 0 || array_keys($value) === range(0, count($value) - 1));
}

function cron_cci_get_section($settings, $sectionKey) {
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

function cron_cci_period_start($period) {
    if ($period === 'day') {
        return date('Y-m-d 00:00:00');
    }
    if ($period === 'month') {
        return date('Y-m-01 00:00:00');
    }
    return date('Y-m-d 00:00:00', strtotime('monday this week'));
}

function cron_cci_collect_mix_rows($dbo, $accountId, $propertyId, $mix) {
    $rows = [];
    foreach ($mix as $index => $item) {
        $categoryId = (int)($item['content_category_id'] ?? 0);
        $styleId = (int)($item['writing_style_id'] ?? 0);
        $templateId = (int)($item['content_template_id'] ?? 0);
        $imageStyleId = (int)($item['image_style_id'] ?? 0);
        $brief = trim((string)($item['brief'] ?? ''));

        $category = $categoryId > 0 ? $dbo->getRS('SELECT id, name, description FROM content_categories WHERE id = ? AND account_id = ? AND property_id = ? LIMIT 1', [$categoryId, $accountId, $propertyId]) : [];
        $style = $styleId > 0 ? $dbo->getRS('SELECT id, name, tone, language, instructions FROM writing_styles WHERE id = ? AND account_id = ? AND property_id = ? LIMIT 1', [$styleId, $accountId, $propertyId]) : [];
        $template = $templateId > 0 ? $dbo->getRS('SELECT id, name, description, content_type_id, structure_json FROM content_templates WHERE id = ? AND account_id = ? AND (property_id = ? OR property_id IS NULL) LIMIT 1', [$templateId, $accountId, $propertyId]) : [];
        $imageStyle = $imageStyleId > 0 ? $dbo->getRS('SELECT id, name, description FROM image_styles WHERE id = ? AND account_id = ? AND property_id = ? LIMIT 1', [$imageStyleId, $accountId, $propertyId]) : [];

        $rows[] = [
            'index' => $index + 1,
            'category' => $category ? $category[0] : null,
            'writing_style' => $style ? $style[0] : null,
            'template' => $template ? $template[0] : null,
            'image_style' => $imageStyle ? $imageStyle[0] : null,
            'brief' => $brief,
        ];
    }
    return $rows;
}

function cron_cci_select_mix_rows_for_run($mixRows, $position, $count = 1) {
    $mixRows = array_values($mixRows);
    if (!$mixRows) {
        return [];
    }

    $selected = [];
    $mixCount = count($mixRows);
    for ($i = 0; $i < max(1, (int)$count); $i++) {
        $selected[] = $mixRows[($position + $i) % $mixCount];
    }
    return $selected;
}

function cron_cci_frequent_tags($dbo, $accountId, $propertyId, $limit = 20, $days = 90) {
    $rows = $dbo->getRS(
        'SELECT tags
         FROM content_ideas
         WHERE account_id = ? AND property_id = ?
           AND tags IS NOT NULL AND tags <> ?
           AND created_at >= ?',
        [$accountId, $propertyId, '', date('Y-m-d H:i:s', strtotime("-{$days} days"))]
    ) ?: [];

    $counts = [];
    foreach ($rows as $row) {
        foreach (cron_cci_normalize_text_list($row['tags'] ?? '') as $tag) {
            $tag = preg_replace('/\s+/u', ' ', trim((string)$tag));
            $key = function_exists('mb_strtolower') ? mb_strtolower($tag, 'UTF-8') : strtolower($tag);
            if ($key !== '') {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }
    }

    arsort($counts);
    return array_slice($counts, 0, $limit, true);
}

function cron_cci_frequent_tags_cache_expired($config) {
    $frequencyHours = (int)($config['frequent_tags_frequency_hours'] ?? 24);
    if ($frequencyHours <= 0) {
        return true;
    }

    $cache = $config['frequent_tags_cache'] ?? [];
    $generatedAt = is_array($cache) ? strtotime((string)($cache['generated_at'] ?? '')) : false;
    if (!$generatedAt) {
        return true;
    }

    return (time() - $generatedAt) >= ($frequencyHours * 3600);
}

function cron_cci_get_cached_frequent_tags($dbo, $accountId, $propertyId, &$config) {
    $cache = $config['frequent_tags_cache'] ?? [];
    if (!cron_cci_frequent_tags_cache_expired($config) && is_array($cache) && isset($cache['tags']) && is_array($cache['tags'])) {
        return [$cache['tags'], false];
    }

    $tags = cron_cci_frequent_tags($dbo, $accountId, $propertyId);
    $config['frequent_tags_cache'] = [
        'generated_at' => date('Y-m-d H:i:s'),
        'days' => 90,
        'limit' => 20,
        'tags' => $tags,
    ];

    return [$tags, true];
}

function cron_cci_build_prompt($propertyName, $config, $mixRows, $count, $existingTitles, $editorialContext = [], $frequentTags = []) {
    $mixJson = json_encode(array_values($mixRows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $existingJson = json_encode(array_values($existingTitles), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $frequentTagsJson = json_encode($frequentTags, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $editorialContextBlock = editorial_context_prompt_block($editorialContext);
    return <<<PROMPT
Create {$count} content ideas for property "{$propertyName}".
Planning period: {$config['period']}.

Follow only the supplied content_mix item or items and return only valid JSON.
If the matching content_mix item includes a brief, use that brief as the primary direction for that specific idea. Respect it for topic focus, target audience, angle, constraints, writing guidance, image guidance, and things to avoid.
If an item brief conflicts with category/style/template/image style, keep the selected content_mix fields but adapt the idea angle to the brief.
For each generated idea, return content_mix_index exactly as the matching supplied content_mix index.
For every idea, generate category_id, summary, meta_title, meta_description, tags, language, writing instructions, sections, tone, and image_prompt.
The category_id must be the numeric id from the matching content_mix category. If the matching content_mix item has no category, return null.
The summary will be used as the WordPress excerpt and must be a concise article summary.
The meta_title must be SEO-friendly and no longer than 60 characters.
The meta_description must be SEO-friendly, no longer than 160 characters, and written as a natural search snippet.
Use the writing_style for language, tone, and instructions when available.
Use the template structure for sections when available. Each section must include a suggested title, specific writing instructions for that section, and a recommended word count.
Section titles must be specific to the article topic and must not use generic repeated labels such as "Introduction", "Main body", "Body", or "Conclusion".
Use the image_style to create a detailed image_prompt. The image_prompt must include both the image style and detailed subject guidance based on the idea title, summary, category, tags, and property context.
The image_prompt must be safe for image generation: avoid specific brand names, logos, copyrighted characters, fictional franchises, recognizable celebrities/public figures, exact product designs, posters, screenshots, album covers, and references to the style of living artists. If the idea topic involves a protected brand, person, franchise, or copyrighted work, describe the broader concept with fictional/generic visual elements instead. If any part of the image_prompt would violate safety rules related to similarity with third-party content, remove those elements and replace them with similar generic elements that do not have that issue.
Content mix:
{$mixJson}
Avoid these existing titles:
{$existingJson}
Frequently used tags for this property in recent content:
{$frequentTagsJson}
Avoid repeating content angles centered on these frequent tags. You may reuse a frequent tag only when the proposed idea has a clearly different audience, intent, format, search intent, or editorial angle.
{$editorialContextBlock}
Return:
{"articles":[{"title":"string","category_id":123,"category":"string","summary":"string","meta_title":"string","meta_description":"string","tags":["string"],"language":"string","instructions":"string","sections":[{"title":"string","instructions":"string","word_count":120}],"tone":"string","image_prompt":"string","content_mix_index":1}]}
PROMPT;
}

function cron_cci_normalize_text_list($value) {
    if (is_array($value)) {
        return array_values(array_filter(array_map(function($item) {
            if (is_array($item)) {
                return trim((string)($item['title'] ?? $item['name'] ?? $item['heading'] ?? json_encode($item, JSON_UNESCAPED_UNICODE)));
            }
            return trim((string)$item);
        }, $value), function($item) {
            return $item !== '';
        }));
    }

    return array_values(array_filter(array_map('trim', explode(',', (string)$value)), function($item) {
        return $item !== '';
    }));
}

function cron_cci_stringify_list($value) {
    return implode(', ', cron_cci_normalize_text_list($value));
}

function cron_cci_stringify_sections($value) {
    if (is_array($value)) {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    return trim((string)$value);
}

function cron_cci_limit_text($text, $maxLength) {
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

function cron_cci_parse_articles($content) {
    $decoded = json_decode((string)$content, true);
    $articles = is_array($decoded) && isset($decoded['articles']) && is_array($decoded['articles']) ? $decoded['articles'] : [];
    $result = [];
    foreach ($articles as $article) {
        if (!is_array($article) || trim((string)($article['title'] ?? '')) === '') {
            continue;
        }
        $article['category_id'] = isset($article['category_id']) ? (int)$article['category_id'] : 0;
        $article['meta_title'] = cron_cci_limit_text($article['meta_title'] ?? ($article['title'] ?? ''), 60);
        $article['meta_description'] = cron_cci_limit_text($article['meta_description'] ?? ($article['summary'] ?? ''), 160);
        $article['tags'] = cron_cci_normalize_text_list($article['tags'] ?? []);
        $article['language'] = trim((string)($article['language'] ?? ''));
        $article['instructions'] = trim((string)($article['instructions'] ?? ''));
        $article['sections'] = $article['sections'] ?? [];
        $article['tone'] = trim((string)($article['tone'] ?? ''));
        $article['image_prompt'] = trim((string)($article['image_prompt'] ?? ''));
        $article['content_mix_index'] = max(1, (int)($article['content_mix_index'] ?? count($result) + 1));
        $result[] = $article;
    }
    return $result;
}

function cron_cci_resolve_article_category_id($dbo, $accountId, $propertyId, $article, $mix) {
    $categoryId = (int)($article['category_id'] ?? 0);
    if ($categoryId <= 0) {
        $categoryId = (int)($mix['content_category_id'] ?? 0);
    }
    if ($categoryId <= 0) {
        return null;
    }

    $rows = $dbo->getRS(
        'SELECT id FROM content_categories WHERE id = ? AND account_id = ? AND property_id = ? AND status = ? LIMIT 1',
        [$categoryId, $accountId, $propertyId, 'active']
    );
    return $rows ? $categoryId : null;
}

function cron_cci_resolve_created_by($dbo, $accountId, $config) {
    $configuredUserId = (int)($config['created_by'] ?? 0);
    if ($configuredUserId > 0) {
        $rows = $dbo->getRS(
            "SELECT user_id
             FROM account_users
             WHERE account_id = ? AND user_id = ? AND status = 'active'
             LIMIT 1",
            [$accountId, $configuredUserId]
        );
        if ($rows) {
            return $configuredUserId;
        }
    }

    $rows = $dbo->getRS(
        "SELECT user_id
         FROM account_users
         WHERE account_id = ? AND status = 'active'
         ORDER BY FIELD(role, 'owner', 'admin', 'editor', 'author', 'viewer'), id
         LIMIT 1",
        [$accountId]
    );

    return $rows ? (int)$rows[0]['user_id'] : null;
}

function cron_cci_save_idea($dbo, $property, $config, $article, $prompt, $aiResponse) {
    $accountId = (int)$property['account_id'];
    $propertyId = (int)$property['id'];
    $mixIndex = max(1, (int)($article['content_mix_index'] ?? 1)) - 1;
    $mix = $config['mix'][$mixIndex] ?? ($config['mix'][0] ?? []);
    $templateId = (int)($mix['content_template_id'] ?? 0);
    $writingStyleId = (int)($mix['writing_style_id'] ?? 0);
    $contentTypeId = null;
    $writingStyle = null;

    if ($templateId > 0) {
        $templateRows = $dbo->getRS('SELECT content_type_id FROM content_templates WHERE id = ? AND account_id = ? AND (property_id = ? OR property_id IS NULL) LIMIT 1', [$templateId, $accountId, $propertyId]);
        if ($templateRows) {
            $contentTypeId = $templateRows[0]['content_type_id'];
        }
    }

    if ($writingStyleId > 0) {
        $styleRows = $dbo->getRS('SELECT tone, language, instructions FROM writing_styles WHERE id = ? AND account_id = ? AND property_id = ? LIMIT 1', [$writingStyleId, $accountId, $propertyId]);
        if ($styleRows) {
            $writingStyle = $styleRows[0];
        }
    }

    $tags = cron_cci_stringify_list($article['tags'] ?? []);
    $sections = cron_cci_stringify_sections($article['sections'] ?? []);
    $tone = trim((string)($article['tone'] ?? ''));
    $language = trim((string)($article['language'] ?? ''));
    $instructions = trim((string)($article['instructions'] ?? ''));
    $imagePrompt = trim((string)($article['image_prompt'] ?? ''));

    if ($tone === '' && $writingStyle) {
        $tone = (string)$writingStyle['tone'];
    }
    if ($language === '' && $writingStyle) {
        $language = (string)$writingStyle['language'];
    }
    if ($instructions === '' && $writingStyle) {
        $instructions = (string)$writingStyle['instructions'];
    }

    $categoryId = cron_cci_resolve_article_category_id($dbo, $accountId, $propertyId, $article, $mix);

    $metadata = [
        'article' => $article,
        'content_mix' => $mix,
        'brief' => $mix['brief'] ?? '',
        'ai_models' => [
            'text_model' => publisher_ai_normalize_text_model($config['text_model'] ?? 'gpt-5.2'),
            'image_model' => publisher_ai_normalize_image_model($config['image_model'] ?? 'gpt-image-1.5'),
            'content_text_model' => publisher_ai_normalize_text_model($config['text_model'] ?? 'gpt-5.2'),
            'content_image_model' => publisher_ai_normalize_image_model($config['image_model'] ?? 'gpt-image-1.5'),
        ],
        'ai_response' => json_decode($aiResponse, true) ?: $aiResponse,
        'source' => 'cron-create-content-ideas',
    ];
    $now = date('Y-m-d H:i:s');
    $createdBy = cron_cci_resolve_created_by($dbo, $accountId, $config);

    return $dbo->execSQL(
        'INSERT INTO content_ideas
         (account_id, property_id, content_type_id, category_id, title, summary, tags, sections, tone, language, instructions, image_prompt, prompt, ai_response_json, similarity_score, status, created_by, content_item_id, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $accountId,
            $propertyId,
            $contentTypeId,
            $categoryId,
            $article['title'],
            $article['summary'] ?? '',
            $tags,
            $sections,
            $tone,
            $language,
            $instructions,
            $imagePrompt,
            $prompt,
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            null,
            'suggested',
            $createdBy,
            null,
            $now,
            $now,
        ]
    );
}

$cronRun = cron_cci_run_start($dbo);
$properties = $dbo->getRS("SELECT * FROM properties WHERE status = 'active' AND settings_json IS NOT NULL AND settings_json <> ''") ?: [];
$created = 0;

foreach ($properties as $property) {
    $settings = json_decode((string)$property['settings_json'], true);
    if (!is_array($settings)) {
        continue;
    }
    $config = cron_cci_get_section($settings, 'create_content_ideas');
    $isAutomatic = ($config['mode'] ?? '') === 'automatic';
    $isQueued = !empty($config['schedule_pending']) && (int)($config['schedule_remaining'] ?? 0) > 0;
    if (!$isAutomatic && !$isQueued) {
        continue;
    }
    if (empty($config['mix']) || !is_array($config['mix'])) {
        continue;
    }
    $legacyUserBrief = trim((string)($config['user_brief'] ?? ''));
    foreach ($config['mix'] as $mixIndex => $mixItem) {
        if (is_array($mixItem) && !isset($mixItem['brief']) && $legacyUserBrief !== '') {
            $config['mix'][$mixIndex]['brief'] = $legacyUserBrief;
        }
    }
    unset($config['user_brief']);

    $propertyAiDefaults = publisher_property_ai_defaults($settings);
    $config['text_model'] = publisher_ai_normalize_text_model($config['text_model'] ?? null, $propertyAiDefaults['text_model']);
    $config['image_model'] = publisher_ai_normalize_image_model($config['image_model'] ?? null, $propertyAiDefaults['image_model']);

    $targetCount = $isQueued ? 1 : max(1, (int)($config['article_count'] ?? 1));
    $period = in_array(($config['period'] ?? 'week'), ['day', 'week', 'month'], true) ? $config['period'] : 'week';
    $config['period'] = $period;
    $periodStart = cron_cci_period_start($period);
    if ($isQueued) {
        $existingRows = [];
    } else {
        $existingRows = $dbo->getRS(
            'SELECT title FROM content_ideas WHERE account_id = ? AND property_id = ? AND created_at >= ? ORDER BY created_at DESC',
            [$property['account_id'], $property['id'], $periodStart]
        ) ?: [];
    }
    $existingCount = count($existingRows);
    if ($existingCount >= $targetCount) {
        continue;
    }

    $needed = 1;
    $existingTitles = array_map(function($row) { return $row['title']; }, $existingRows);
    $mixRows = cron_cci_collect_mix_rows($dbo, (int)$property['account_id'], (int)$property['id'], $config['mix']);
    $mixCount = count($mixRows);
    if ($mixCount <= 0) {
        continue;
    }
    if ($isQueued) {
        $scheduleCursor = isset($config['schedule_cursor'])
            ? max(0, (int)$config['schedule_cursor'])
            : max(0, (int)($config['article_count'] ?? 1) - (int)($config['schedule_remaining'] ?? 1));
    } else {
        $scheduleCursor = $existingCount;
    }
    $selectedMixRows = cron_cci_select_mix_rows_for_run($mixRows, $scheduleCursor, $needed);
    $editorialContext = editorial_context_get($dbo, (int)$property['account_id'], (int)$property['id']);
    [$frequentTags, $frequentTagsCacheUpdated] = cron_cci_get_cached_frequent_tags($dbo, (int)$property['account_id'], (int)$property['id'], $config);
    if ($frequentTagsCacheUpdated) {
        $settings = publisher_ai_set_settings_section($settings, 'create_content_ideas', 'Create Content Ideas', $config);
        $dbo->execSQL(
            'UPDATE properties SET settings_json = ?, updated_at = ? WHERE id = ? AND account_id = ?',
            [json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), date('Y-m-d H:i:s'), (int)$property['id'], (int)$property['account_id']]
        );
    }
    $prompt = cron_cci_build_prompt($property['name'], $config, $selectedMixRows, $needed, $existingTitles, $editorialContext, $frequentTags);
    $createdBy = cron_cci_resolve_created_by($dbo, (int)$property['account_id'], $config);
    $ai = new ai(publisher_require_ai_api_key($dbo, (int)$property['account_id']));
    $ai->text_model($config['text_model'] ?? 'gpt-5.2');
    $ai->log_context($dbo, [
        'account_id' => (int)$property['account_id'],
        'property_id' => (int)$property['id'],
        'action_type' => 'suggest_title',
        'created_by' => $createdBy,
    ]);
    $ai->instructions('You are an editorial planning assistant. Return only valid JSON, no markdown.');
    $ai->prompt($prompt);
    $response = $ai->send_request();

    if (($response['result'] ?? '') !== 'success') {
        $message = "Property {$property['id']}: AI error";
        cron_cci_run_finish($dbo, $cronRun, 'failed', $message, [
            'account_id' => (int)$property['account_id'],
            'property_id' => (int)$property['id'],
        ]);
        echo $message . "\n";
        exit;
    }

    $articles = cron_cci_parse_articles($response['content'] ?? '');
    foreach ($articles as $article) {
        if ($needed === 1 && isset($selectedMixRows[0])) {
            $article['content_mix_index'] = (int)$selectedMixRows[0]['index'];
            if ((int)($article['category_id'] ?? 0) <= 0 && !empty($selectedMixRows[0]['category']['id'])) {
                $article['category_id'] = (int)$selectedMixRows[0]['category']['id'];
            }
        }
        $contentIdeaId = cron_cci_save_idea($dbo, $property, $config, $article, $prompt, $response['content'] ?? '');
        if ($contentIdeaId !== false) {
            $created++;
            if ($isQueued) {
                $config['schedule_remaining'] = max(0, (int)($config['schedule_remaining'] ?? 1) - 1);
                $config['schedule_cursor'] = ($scheduleCursor + 1) % $mixCount;
                $config['schedule_pending'] = $config['schedule_remaining'] > 0;
                $settings = publisher_ai_set_settings_section($settings, 'create_content_ideas', 'Create Content Ideas', $config);
                $dbo->execSQL(
                    'UPDATE properties SET settings_json = ?, updated_at = ? WHERE id = ? AND account_id = ?',
                    [json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), date('Y-m-d H:i:s'), (int)$property['id'], (int)$property['account_id']]
                );
            }
            $message = "Created {$created} content ideas";
            cron_cci_run_finish($dbo, $cronRun, 'success', $message, [
                'account_id' => (int)$property['account_id'],
                'property_id' => (int)$property['id'],
                'content_idea_id' => (int)$contentIdeaId,
            ]);
            echo $message . "\n";
            exit;
        }
    }

    $message = "Property {$property['id']}: no valid content idea was created";
    cron_cci_run_finish($dbo, $cronRun, 'failed', $message, [
        'account_id' => (int)$property['account_id'],
        'property_id' => (int)$property['id'],
    ]);
    echo $message . "\n";
    exit;
}

$message = "No scheduled content idea jobs";
cron_cci_run_finish($dbo, $cronRun, 'skipped', $message);
echo $message . "\n";
