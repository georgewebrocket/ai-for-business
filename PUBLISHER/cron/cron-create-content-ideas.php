<?php

require_once(__DIR__ . '/../php/config.php');
require_once(__DIR__ . '/../php/db.php');
require_once(__DIR__ . '/../php/dataobjects.php');
require_once(__DIR__ . '/../php/ai.php');

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

function cron_cci_build_prompt($propertyName, $config, $mixRows, $count, $existingTitles) {
    $mixJson = json_encode(array_slice($mixRows, 0, max(1, $count)), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $existingJson = json_encode(array_values($existingTitles), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return <<<PROMPT
Create {$count} content ideas for property "{$propertyName}".
Planning period: {$config['period']}.
Follow the content mix by index and return only valid JSON.
Content mix:
{$mixJson}
Avoid these existing titles:
{$existingJson}
Return:
{"articles":[{"title":"string","category":"string","summary":"string","tags":["string"],"content_mix_index":1}]}
PROMPT;
}

function cron_cci_parse_articles($content) {
    $decoded = json_decode((string)$content, true);
    $articles = is_array($decoded) && isset($decoded['articles']) && is_array($decoded['articles']) ? $decoded['articles'] : [];
    $result = [];
    foreach ($articles as $article) {
        if (!is_array($article) || trim((string)($article['title'] ?? '')) === '') {
            continue;
        }
        $tags = $article['tags'] ?? [];
        if (!is_array($tags)) {
            $tags = array_filter(array_map('trim', explode(',', (string)$tags)));
        }
        $article['tags'] = array_values($tags);
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
    $contentTypeId = null;

    if ($templateId > 0) {
        $templateRows = $dbo->getRS('SELECT content_type_id FROM content_templates WHERE id = ? AND account_id = ? AND (property_id = ? OR property_id IS NULL) LIMIT 1', [$templateId, $accountId, $propertyId]);
        if ($templateRows) {
            $contentTypeId = $templateRows[0]['content_type_id'];
        }
    }

    $metadata = [
        'article' => $article,
        'content_mix' => $mix,
        'ai_response' => json_decode($aiResponse, true) ?: $aiResponse,
        'source' => 'cron-create-content-ideas',
    ];
    $now = date('Y-m-d H:i:s');

    return $dbo->execSQL(
        'INSERT INTO content_ideas
         (account_id, property_id, content_type_id, category_id, title, summary, prompt, ai_response_json, similarity_score, status, created_by, content_item_id, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $accountId,
            $propertyId,
            $contentTypeId,
            (int)($mix['content_category_id'] ?? 0) ?: null,
            $article['title'],
            $article['summary'] ?? '',
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
    $prompt = cron_cci_build_prompt($property['name'], $config, $mixRows, $needed, $existingTitles);
    $ai = new ai(openai::$key);
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
