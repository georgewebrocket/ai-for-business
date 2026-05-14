<?php

require_once('php/config.php');
require_once('php/db.php');
require_once('php/dataobjects.php');
require_once('php/utils.php');
require_once('php/start.php');
require_once('php/session.php');
require_once('php/controls.php');
require_once('php/ai.php');
require_once('php/ai-settings.php');
require_once('php/editorial-context.php');

publisher_require_permission('content');
publisher_require_property();

$errors = [];
$success = '';
$accountId = (int)$current_account_id;
$propertyId = (int)$current_property_id;
$userId = (int)$userid;
$sessionKey = app::$slug . '_CREATE_CONTENT_IDEAS_' . $propertyId;

function cci_title_from_key($key) {
    return ucwords(str_replace('_', ' ', (string)$key));
}

function cci_is_list_array($value) {
    return is_array($value) && (count($value) === 0 || array_keys($value) === range(0, count($value) - 1));
}

function cci_settings_to_sections($settings) {
    if (isset($settings['sections']) && is_array($settings['sections'])) {
        return $settings;
    }

    $sections = [];
    foreach ($settings as $sectionKey => $sectionData) {
        $options = [];
        if (is_array($sectionData) && !cci_is_list_array($sectionData)) {
            foreach ($sectionData as $optionKey => $optionValue) {
                $options[] = [
                    'title' => cci_title_from_key($optionKey),
                    'key' => (string)$optionKey,
                    'value' => $optionValue,
                ];
            }
        }
        $sections[] = [
            'title' => cci_title_from_key($sectionKey),
            'key' => (string)$sectionKey,
            'options' => $options,
        ];
    }

    return ['sections' => $sections];
}

function cci_get_settings_section($settings, $sectionKey) {
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

function cci_set_settings_section($settings, $sectionKey, $title, $values) {
    $settings = cci_settings_to_sections(is_array($settings) ? $settings : []);
    $section = [
        'title' => $title,
        'key' => $sectionKey,
        'options' => [],
    ];

    foreach ($values as $key => $value) {
        $section['options'][] = [
            'title' => cci_title_from_key($key),
            'key' => $key,
            'value' => $value,
        ];
    }

    $found = false;
    foreach ($settings['sections'] as $index => $existingSection) {
        if (($existingSection['key'] ?? '') === $sectionKey) {
            $settings['sections'][$index] = $section;
            $found = true;
            break;
        }
    }

    if (!$found) {
        $settings['sections'][] = $section;
    }

    return $settings;
}

function cci_decode_property_settings($json) {
    $settings = json_decode((string)$json, true);
    if (!is_array($settings)) {
        return ['sections' => []];
    }
    return cci_settings_to_sections($settings);
}

function cci_save_config($dbo, $propertyId, $accountId, $settingsJson, $config) {
    $settings = cci_decode_property_settings($settingsJson);
    $settings = cci_set_settings_section($settings, 'create_content_ideas', 'Create Content Ideas', $config);
    $encodedSettings = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $dbo->execSQL(
        'UPDATE properties SET settings_json = ?, updated_at = ? WHERE id = ? AND account_id = ?',
        [$encodedSettings, date('Y-m-d H:i:s'), $propertyId, $accountId]
    );
    return $encodedSettings;
}

function cci_normalize_config($post, $aiDefaults = []) {
    $articleCount = max(1, min(50, (int)($post['article_count'] ?? 5)));
    $period = in_array(($post['period'] ?? 'week'), ['day', 'week', 'month'], true) ? $post['period'] : 'week';
    $mode = in_array(($post['mode'] ?? 'manual'), ['manual', 'automatic'], true) ? $post['mode'] : 'manual';
    $mix = [];

    for ($i = 0; $i < $articleCount; $i++) {
        $mix[] = [
            'content_category_id' => (int)($post['content_category_id'][$i] ?? 0),
            'writing_style_id' => (int)($post['writing_style_id'][$i] ?? 0),
            'content_template_id' => (int)($post['content_template_id'][$i] ?? 0),
            'image_style_id' => (int)($post['image_style_id'][$i] ?? 0),
        ];
    }

    return [
        'article_count' => $articleCount,
        'period' => $period,
        'mode' => $mode,
        'text_model' => publisher_ai_normalize_text_model($post['text_model'] ?? ($aiDefaults['text_model'] ?? 'gpt-5.2'), $aiDefaults['text_model'] ?? 'gpt-5.2'),
        'image_model' => publisher_ai_normalize_image_model($post['image_model'] ?? ($aiDefaults['image_model'] ?? 'gpt-image-1.5'), $aiDefaults['image_model'] ?? 'gpt-image-1.5'),
        'mix' => $mix,
    ];
}

function cci_collect_mix_rows($dbo, $accountId, $propertyId, $mix) {
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

function cci_build_prompt($propertyName, $config, $mixRows, $existingTitles = [], $replacementCount = null, $editorialContext = []) {
    $count = $replacementCount !== null ? (int)$replacementCount : (int)$config['article_count'];
    $mixRows = array_slice($mixRows, 0, max(1, $count));
    $mixJson = json_encode($mixRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $existingJson = json_encode(array_values($existingTitles), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $editorialContextBlock = editorial_context_prompt_block($editorialContext);

    return <<<PROMPT
Create {$count} content ideas for property "{$propertyName}".

Planning period: {$config['period']}.

For each idea, follow the matching content_mix item by index. If fewer mix items are provided than requested ideas, reuse the mix items cyclically.
For every idea, generate tags, language, writing instructions, sections, tone, and image_prompt.
Use the writing_style for language, tone, and instructions when available.
Use the template structure for sections when available. Each section must include a suggested title, specific writing instructions for that section, and a recommended word count.
Section titles must be specific to the article topic and must not use generic repeated labels such as "Introduction", "Main body", "Body", or "Conclusion".
Use the image_style to create a detailed image_prompt. The image_prompt must include both the image style and detailed subject guidance based on the idea title, summary, category, tags, and property context.
The image_prompt must be safe for image generation: avoid specific brand names, logos, copyrighted characters, fictional franchises, recognizable celebrities/public figures, exact product designs, posters, screenshots, album covers, and references to the style of living artists. If the idea topic involves a protected brand, person, franchise, or copyrighted work, describe the broader concept with fictional/generic visual elements instead. If any part of the image_prompt would violate safety rules related to similarity with third-party content, remove those elements and replace them with similar generic elements that do not have that issue.

Content mix:
{$mixJson}

Avoid titles that are identical or too similar to these existing or rejected titles:
{$existingJson}

{$editorialContextBlock}

Return only valid JSON with this exact shape:
{
  "articles": [
    {
      "title": "string",
      "category": "string",
      "summary": "string",
      "tags": ["string"],
      "language": "string",
      "instructions": "string",
      "sections": [
        {
          "title": "string",
          "instructions": "string",
          "word_count": 120
        }
      ],
      "tone": "string",
      "image_prompt": "string",
      "content_mix_index": 1
    }
  ]
}
PROMPT;
}

function cci_normalize_text_list($value) {
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

function cci_stringify_list($value) {
    return implode(', ', cci_normalize_text_list($value));
}

function cci_stringify_sections($value) {
    if (is_array($value)) {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    return trim((string)$value);
}

function cci_parse_ai_articles($content) {
    $decoded = json_decode((string)$content, true);
    if (!is_array($decoded)) {
        return [];
    }

    $articles = isset($decoded['articles']) && is_array($decoded['articles']) ? $decoded['articles'] : $decoded;
    if (!is_array($articles)) {
        return [];
    }

    $result = [];
    foreach ($articles as $article) {
        if (!is_array($article)) {
            continue;
        }
        $title = trim((string)($article['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $result[] = [
            'title' => $title,
            'category' => trim((string)($article['category'] ?? '')),
            'summary' => trim((string)($article['summary'] ?? '')),
            'tags' => cci_normalize_text_list($article['tags'] ?? []),
            'language' => trim((string)($article['language'] ?? '')),
            'instructions' => trim((string)($article['instructions'] ?? '')),
            'sections' => $article['sections'] ?? [],
            'tone' => trim((string)($article['tone'] ?? '')),
            'image_prompt' => trim((string)($article['image_prompt'] ?? '')),
            'content_mix_index' => max(1, (int)($article['content_mix_index'] ?? count($result) + 1)),
        ];
    }

    return $result;
}

function cci_generate_articles($dbo, $accountId, $propertyId, $propertyName, $config, $existingTitles = [], $replacementCount = null, &$errors = []) {
    $mixRows = cci_collect_mix_rows($dbo, $accountId, $propertyId, $config['mix']);
    $editorialContext = editorial_context_get($dbo, $accountId, $propertyId);
    $prompt = cci_build_prompt($propertyName, $config, $mixRows, $existingTitles, $replacementCount, $editorialContext);
    $ai = new ai(openai::$key);
    $ai->text_model($config['text_model'] ?? 'gpt-5.2');
    $ai->instructions('You are an editorial planning assistant. Return only valid JSON, no markdown.');
    $ai->prompt($prompt);
    $response = $ai->send_request();

    if (($response['result'] ?? '') !== 'success') {
        $errors[] = $response['message'] ?? 'AI request failed.';
        return [[], $prompt, ''];
    }

    $articles = cci_parse_ai_articles($response['content'] ?? '');
    if (!$articles) {
        $errors[] = 'AI did not return valid articles JSON.';
    }

    return [$articles, $prompt, $response['content'] ?? ''];
}

function cci_save_idea($dbo, $accountId, $propertyId, $userId, $config, $article, $prompt, $aiResponse) {
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

    $tags = cci_stringify_list($article['tags'] ?? []);
    $sections = cci_stringify_sections($article['sections'] ?? []);
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
            $userId > 0 ? $userId : null,
            null,
            $now,
            $now,
        ]
    );
}

$propertyRows = $dbo->getRS('SELECT * FROM properties WHERE id = ? AND account_id = ? LIMIT 1', [$propertyId, $accountId]);
if (!$propertyRows) {
    http_response_code(403);
    die('Access denied');
}
$property = $propertyRows[0];
$propertySettings = cci_decode_property_settings($property['settings_json'] ?? '');
$propertyAiDefaults = publisher_property_ai_defaults($propertySettings);
$savedConfig = cci_get_settings_section($propertySettings, 'create_content_ideas');
$defaultConfig = [
    'article_count' => 5,
    'period' => 'week',
    'mode' => 'manual',
    'text_model' => $propertyAiDefaults['text_model'],
    'image_model' => $propertyAiDefaults['image_model'],
    'mix' => [],
];
$config = array_merge($defaultConfig, is_array($savedConfig) ? $savedConfig : []);
$config['text_model'] = publisher_ai_normalize_text_model($config['text_model'] ?? null, $propertyAiDefaults['text_model']);
$config['image_model'] = publisher_ai_normalize_image_model($config['image_model'] ?? null, $propertyAiDefaults['image_model']);
if (!isset($config['mix']) || !is_array($config['mix'])) {
    $config['mix'] = [];
}

$categories = $dbo->getRS('SELECT id, name FROM content_categories WHERE account_id = ? AND property_id = ? ORDER BY name', [$accountId, $propertyId]) ?: [];
$writingStyles = $dbo->getRS('SELECT id, name FROM writing_styles WHERE account_id = ? AND property_id = ? ORDER BY name', [$accountId, $propertyId]) ?: [];
$templates = $dbo->getRS('SELECT id, name FROM content_templates WHERE account_id = ? AND (property_id = ? OR property_id IS NULL) ORDER BY name', [$accountId, $propertyId]) ?: [];
$imageStyles = $dbo->getRS('SELECT id, name FROM image_styles WHERE account_id = ? AND property_id = ? AND active = 1 ORDER BY name', [$accountId, $propertyId]) ?: [];
$textModelOptions = publisher_ai_text_model_options();
$imageModelOptions = publisher_ai_image_model_options();

$suggestions = $_SESSION[$sessionKey]['articles'] ?? [];
$lastPrompt = $_SESSION[$sessionKey]['prompt'] ?? '';
$lastAiResponse = $_SESSION[$sessionKey]['ai_response'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'continue') {
        $config = cci_normalize_config($_POST, $propertyAiDefaults);
        $property['settings_json'] = cci_save_config($dbo, $propertyId, $accountId, $property['settings_json'] ?? '', $config);
        $success = 'Οι επιλογές αποθηκεύτηκαν.';

        if ($config['mode'] === 'automatic') {
            unset($_SESSION[$sessionKey]);
            $suggestions = [];
            $success = 'Οι επιλογές αποθηκεύτηκαν για automatic δημιουργία από cron job.';
        } else {
            [$suggestions, $lastPrompt, $lastAiResponse] = cci_generate_articles($dbo, $accountId, $propertyId, $current_property_name, $config, [], null, $errors);
            $_SESSION[$sessionKey] = [
                'config' => $config,
                'articles' => $suggestions,
                'prompt' => $lastPrompt,
                'ai_response' => $lastAiResponse,
                'rejected_titles' => [],
            ];
        }
    }

    if ($action === 'save_selected') {
        $session = $_SESSION[$sessionKey] ?? null;
        if (!$session || empty($session['articles'])) {
            $errors[] = 'Δεν υπάρχουν άρθρα για αποθήκευση.';
        } else {
            $config = $session['config'];
            $selectedIndexes = array_map('intval', $_POST['selected'] ?? []);
            $newRejectedTitles = $session['rejected_titles'] ?? [];
            $unselected = [];
            $savedCount = 0;

            foreach ($session['articles'] as $index => $article) {
                if (in_array((int)$index, $selectedIndexes, true)) {
                    $saved = cci_save_idea($dbo, $accountId, $propertyId, $userId, $config, $article, $session['prompt'], $session['ai_response']);
                    if ($saved !== false) {
                        $savedCount++;
                    }
                } else {
                    $unselected[] = $article;
                    $newRejectedTitles[] = $article['title'];
                }
            }

            if ($unselected) {
                $replacementConfig = $config;
                $replacementConfig['article_count'] = count($unselected);
                $replacementConfig['mix'] = [];
                foreach ($unselected as $article) {
                    $mixIndex = max(1, (int)($article['content_mix_index'] ?? 1)) - 1;
                    $replacementConfig['mix'][] = $config['mix'][$mixIndex] ?? ($config['mix'][0] ?? []);
                }
                [$suggestions, $lastPrompt, $lastAiResponse] = cci_generate_articles($dbo, $accountId, $propertyId, $current_property_name, $replacementConfig, $newRejectedTitles, count($unselected), $errors);
                $_SESSION[$sessionKey] = [
                    'config' => $replacementConfig,
                    'articles' => $suggestions,
                    'prompt' => $lastPrompt,
                    'ai_response' => $lastAiResponse,
                    'rejected_titles' => $newRejectedTitles,
                ];
                $success = $savedCount . ' άρθρα αποθηκεύτηκαν. Δημιουργήθηκαν νέες προτάσεις για όσα δεν επιλέχθηκαν.';
            } else {
                unset($_SESSION[$sessionKey]);
                $suggestions = [];
                $success = $savedCount . ' άρθρα αποθηκεύτηκαν.';
            }
        }
    }
}

function cci_options($rows, $selectedId) {
    $html = '<option value="0">...</option>';
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $selected = $id === (int)$selectedId ? ' selected' : '';
        $html .= '<option value="' . $id . '"' . $selected . '>' . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $html;
}

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <title><?php echo app::$project_name; ?> - Δημιουργία Content Ideas</title>
    <?php include "_head.php"; ?>
    <style>
        #content-ideas-form { max-width:1200px; }
        .form-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:12px; }
        .mix-card { border:1px solid #d9e2ec; border-radius:8px; padding:12px; margin-bottom:10px; background:#fff; }
        .mix-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:10px; }
        .ideas-grid { width:100%; border-collapse:collapse; margin-top:14px; }
        .ideas-grid th, .ideas-grid td { border:1px solid #d9e2ec; padding:8px; vertical-align:top; }
        .ideas-grid th { background:#f7fafc; text-align:left; }
        .tag-list { color:#52606d; font-size:12px; }
        .error-list { margin:0; padding-left:18px; }
        @media (max-width:900px) {
            .form-grid, .mix-grid { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>
    <?php include "blocks/header.php"; ?>

    <div class="padding-20" id="content-ideas-form">
        <h1>Δημιουργία Content Ideas</h1>
        <p style="color:#52606d;">Property: <strong><?php echo htmlspecialchars($current_property_name, ENT_QUOTES, 'UTF-8'); ?></strong></p>

        <?php if ($errors) { ?>
            <div class="alert alert-danger"><ul class="error-list"><?php foreach ($errors as $error) { ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php } ?></ul></div>
        <?php } ?>
        <?php if ($success) { ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php } ?>

        <form method="post" id="config-form">
            <input type="hidden" name="action" value="continue">
            <div class="form-grid">
                <div>
                    <label for="article_count">Αριθμός άρθρων</label>
                    <input class="form-control" type="number" min="1" max="50" id="article_count" name="article_count" value="<?php echo (int)$config['article_count']; ?>">
                </div>
                <div>
                    <label for="period">Χρονική περίοδος</label>
                    <select class="form-control" id="period" name="period">
                        <option value="day" <?php echo $config['period'] === 'day' ? 'selected' : ''; ?>>Ημέρα</option>
                        <option value="week" <?php echo $config['period'] === 'week' ? 'selected' : ''; ?>>Εβδομάδα</option>
                        <option value="month" <?php echo $config['period'] === 'month' ? 'selected' : ''; ?>>Μήνας</option>
                    </select>
                </div>
                <div>
                    <label for="mode">Mode</label>
                    <select class="form-control" id="mode" name="mode">
                        <option value="manual" <?php echo $config['mode'] === 'manual' ? 'selected' : ''; ?>>Manual</option>
                        <option value="automatic" <?php echo $config['mode'] === 'automatic' ? 'selected' : ''; ?>>Automatic</option>
                    </select>
                </div>
                <div>
                    <label for="text_model">Text model</label>
                    <select class="form-control" id="text_model" name="text_model">
                        <?php foreach ($textModelOptions as $value => $label) { ?>
                            <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $config['text_model'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div>
                    <label for="image_model">Default image model</label>
                    <select class="form-control" id="image_model" name="image_model">
                        <?php foreach ($imageModelOptions as $value => $label) { ?>
                            <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $config['image_model'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <h3>Content mix</h3>
            <div id="mix-container"></div>

            <div style="margin-top:16px;">
                <button class="btn btn-primary" type="submit">Συνέχεια</button>
            </div>
        </form>

        <?php if ($suggestions) { ?>
            <form method="post" style="margin-top:26px;">
                <input type="hidden" name="action" value="save_selected">
                <h3>Προτάσεις άρθρων</h3>
                <table class="ideas-grid">
                    <thead>
                        <tr>
                            <th style="width:44px;">✓</th>
                            <th>Τίτλος</th>
                            <th>Κατηγορία</th>
                            <th>Summary</th>
                            <th>Tags</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suggestions as $index => $idea) { ?>
                            <tr>
                                <td><input type="checkbox" name="selected[]" value="<?php echo (int)$index; ?>" checked></td>
                                <td><?php echo htmlspecialchars($idea['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($idea['category'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($idea['summary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="tag-list"><?php echo htmlspecialchars(implode(', ', $idea['tags'] ?? []), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>

                <div style="margin-top:16px;">
                    <button class="btn btn-primary" type="submit">Αποθήκευση</button>
                </div>
            </form>
        <?php } ?>
    </div>

    <?php include "blocks/footer.php"; ?>

    <script>
        const categories = <?php echo json_encode($categories, JSON_UNESCAPED_UNICODE); ?>;
        const writingStyles = <?php echo json_encode($writingStyles, JSON_UNESCAPED_UNICODE); ?>;
        const templates = <?php echo json_encode($templates, JSON_UNESCAPED_UNICODE); ?>;
        const imageStyles = <?php echo json_encode($imageStyles, JSON_UNESCAPED_UNICODE); ?>;
        const savedMix = <?php echo json_encode($config['mix'], JSON_UNESCAPED_UNICODE); ?>;

        function options(rows, selectedId) {
            return '<option value="0">...</option>' + rows.map(row => {
                const selected = parseInt(row.id, 10) === parseInt(selectedId || 0, 10) ? ' selected' : '';
                return `<option value="${row.id}"${selected}>${escapeHtml(row.name)}</option>`;
            }).join('');
        }

        function escapeHtml(value) {
            return (value || '').toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function renderMix() {
            const count = Math.max(1, parseInt(document.getElementById('article_count').value || '1', 10));
            const container = document.getElementById('mix-container');
            container.innerHTML = '';

            for (let index = 0; index < count; index++) {
                const row = savedMix[index] || savedMix[0] || {};
                const card = document.createElement('div');
                card.className = 'mix-card';
                card.innerHTML = `
                    <strong>Άρθρο ${index + 1}</strong>
                    <div class="mix-grid">
                        <div>
                            <label>Content category</label>
                            <select class="form-control" name="content_category_id[]">${options(categories, row.content_category_id)}</select>
                        </div>
                        <div>
                            <label>Writing style</label>
                            <select class="form-control" name="writing_style_id[]">${options(writingStyles, row.writing_style_id)}</select>
                        </div>
                        <div>
                            <label>Content template</label>
                            <select class="form-control" name="content_template_id[]">${options(templates, row.content_template_id)}</select>
                        </div>
                        <div>
                            <label>Image style</label>
                            <select class="form-control" name="image_style_id[]">${options(imageStyles, row.image_style_id)}</select>
                        </div>
                    </div>
                `;
                container.appendChild(card);
            }
        }

        document.getElementById('article_count').addEventListener('change', renderMix);
        renderMix();
    </script>
</body>
</html>
