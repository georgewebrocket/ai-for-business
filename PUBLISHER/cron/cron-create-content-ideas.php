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
        ];
    }
    return $rows;
}

function cron_cci_build_prompt($propertyName, $config, $mixRows, $count, $existingTitles, $editorialContext = []) {
    $mixJson = json_encode(array_slice($mixRows, 0, max(1, $count)), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $existingJson = json_encode(array_values($existingTitles), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $editorialContextBlock = editorial_context_prompt_block($editorialContext);
    return <<<PROMPT
Create {$count} content ideas for property "{$propertyName}".
Planning period: {$config['period']}.
Follow the content mix by index and return only valid JSON.
For every idea, generate tags, language, writing instructions, sections, tone, and image_prompt.
Use the writing_style for language, tone, and instructions when available.
Use the template structure for sections when available. Each section must include a suggested title, specific writing instructions for that section, and a recommended word count.
Section titles must be specific to the article topic and must not use generic repeated labels such as "Introduction", "Main body", "Body", or "Conclusion".
Use the image_style to create a detailed image_prompt. The image_prompt must include both the image style and detailed subject guidance based on the idea title, summary, category, tags, and property context.
The image_prompt must be safe for image generation: avoid specific brand names, logos, copyrighted characters, fictional franchises, recognizable celebrities/public figures, exact product designs, posters, screenshots, album covers, and references to the style of living artists. If the idea topic involves a protected brand, person, franchise, or copyrighted work, describe the broader concept with fictional/generic visual elements instead. If any part of the image_prompt would violate safety rules related to similarity with third-party content, remove those elements and replace them with similar generic elements that do not have that issue.
Content mix:
{$mixJson}
Avoid these existing titles:
{$existingJson}
{$editorialContextBlock}
Return:
{"articles":[{"title":"string","category":"string","summary":"string","tags":["string"],"language":"string","instructions":"string","sections":[{"title":"string","instructions":"string","word_count":120}],"tone":"string","image_prompt":"string","content_mix_index":1}]}
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

function cron_cci_parse_articles($content) {
    $decoded = json_decode((string)$content, true);
    $articles = is_array($decoded) && isset($decoded['articles']) && is_array($decoded['articles']) ? $decoded['articles'] : [];
    $result = [];
    foreach ($articles as $article) {
        if (!is_array($article) || trim((string)($article['title'] ?? '')) === '') {
            continue;
        }
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

    $metadata = [
        'article' => $article,
        'content_mix' => $mix,
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

    return $dbo->execSQL(
        'INSERT INTO content_ideas
         (account_id, property_id, content_type_id, category_id, title, summary, tags, sections, tone, language, instructions, image_prompt, prompt, ai_response_json, similarity_score, status, created_by, content_item_id, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $accountId,
            $propertyId,
            $contentTypeId,
            (int)($mix['content_category_id'] ?? 0) ?: null,
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
            null,
            null,
            $now,
            $now,
        ]
    );
}

$properties = $dbo->getRS("SELECT * FROM properties WHERE status = 'active' AND settings_json IS NOT NULL AND settings_json <> ''") ?: [];
$created = 0;

foreach ($properties as $property) {
    $settings = json_decode((string)$property['settings_json'], true);
    if (!is_array($settings)) {
        continue;
    }
    $config = cron_cci_get_section($settings, 'create_content_ideas');
    if (($config['mode'] ?? '') !== 'automatic') {
        continue;
    }
    if (empty($config['mix']) || !is_array($config['mix'])) {
        continue;
    }

    $propertyAiDefaults = publisher_property_ai_defaults($settings);
    $config['text_model'] = publisher_ai_normalize_text_model($config['text_model'] ?? null, $propertyAiDefaults['text_model']);
    $config['image_model'] = publisher_ai_normalize_image_model($config['image_model'] ?? null, $propertyAiDefaults['image_model']);

    $targetCount = max(1, (int)($config['article_count'] ?? 1));
    $period = in_array(($config['period'] ?? 'week'), ['day', 'week', 'month'], true) ? $config['period'] : 'week';
    $config['period'] = $period;
    $periodStart = cron_cci_period_start($period);
    $existingRows = $dbo->getRS(
        'SELECT title FROM content_ideas WHERE account_id = ? AND property_id = ? AND created_at >= ? ORDER BY created_at DESC',
        [$property['account_id'], $property['id'], $periodStart]
    ) ?: [];
    $existingCount = count($existingRows);
    if ($existingCount >= $targetCount) {
        continue;
    }

    $needed = $targetCount - $existingCount;
    $existingTitles = array_map(function($row) { return $row['title']; }, $existingRows);
    $mixRows = cron_cci_collect_mix_rows($dbo, (int)$property['account_id'], (int)$property['id'], $config['mix']);
    $editorialContext = editorial_context_get($dbo, (int)$property['account_id'], (int)$property['id']);
    $prompt = cron_cci_build_prompt($property['name'], $config, $mixRows, $needed, $existingTitles, $editorialContext);
    $ai = new ai(openai::$key);
    $ai->text_model($config['text_model'] ?? 'gpt-5.2');
    $ai->instructions('You are an editorial planning assistant. Return only valid JSON, no markdown.');
    $ai->prompt($prompt);
    $response = $ai->send_request();

    if (($response['result'] ?? '') !== 'success') {
        echo "Property {$property['id']}: AI error\n";
        continue;
    }

    $articles = cron_cci_parse_articles($response['content'] ?? '');
    foreach ($articles as $article) {
        if (cron_cci_save_idea($dbo, $property, $config, $article, $prompt, $response['content'] ?? '') !== false) {
            $created++;
        }
    }
}

echo "Created {$created} content ideas\n";
