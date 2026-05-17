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
require_once('php/publishing.php');

publisher_require_permission('content');
publisher_require_property();

$accountId = (int)$current_account_id;
$propertyId = (int)$current_property_id;
$errors = [];
$success = '';

$propertyRows = $dbo->getRS('SELECT settings_json FROM properties WHERE id = ? AND account_id = ? LIMIT 1', [$propertyId, $accountId]);
$propertySettings = $propertyRows ? json_decode((string)$propertyRows[0]['settings_json'], true) : [];
$propertyAiDefaults = publisher_property_ai_defaults(is_array($propertySettings) ? $propertySettings : []);
$contentIdeasGenerationDefaults = publisher_stage_ai_settings(is_array($propertySettings) ? $propertySettings : [], 'create_content_ideas', $propertyAiDefaults);
$contentGenerationDefaults = publisher_stage_ai_settings(is_array($propertySettings) ? $propertySettings : [], 'content_generation', $contentIdeasGenerationDefaults);
$contentGenerationSaved = publisher_ai_get_settings_section(is_array($propertySettings) ? $propertySettings : [], 'content_generation');
$publishingConfig = publisher_ai_get_settings_section(is_array($propertySettings) ? $propertySettings : [], 'publishing');
$textModelOptions = publisher_ai_text_model_options();
$imageModelOptions = publisher_ai_image_model_options();
$contentIdeaStatusOptions = ['suggested', 'accepted', 'rejected', 'converted'];

function content_ideas_selected_ids($post) {
    $ids = [];
    foreach ($post as $key => $value) {
        if (strpos((string)$key, 'chkRow') === 0 && (int)$value > 0) {
            $ids[] = (int)$value;
        }
    }
    return array_values(array_unique($ids));
}

function content_ideas_slugify($text) {
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

function content_ideas_decode_sections($sectionsJson, $summary) {
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
            $sections[] = [
                'title' => $title,
                'instructions' => $instructions,
                'word_count' => $wordCount,
            ];
        }
    }
    if (!$sections) {
        $sections[] = [
            'title' => 'Main article',
            'instructions' => $summary,
            'word_count' => 600,
        ];
    }
    return $sections;
}

function content_ideas_limit_text($text, $maxLength) {
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

function content_ideas_article_seo($idea) {
    $metadata = json_decode((string)($idea['ai_response_json'] ?? ''), true);
    $article = is_array($metadata) && isset($metadata['article']) && is_array($metadata['article']) ? $metadata['article'] : [];
    return [
        'meta_title' => content_ideas_limit_text($article['meta_title'] ?? ($idea['title'] ?? ''), 60),
        'meta_description' => content_ideas_limit_text($article['meta_description'] ?? ($idea['summary'] ?? ''), 160),
    ];
}

function content_ideas_section_prompt($idea, $section, $sections, $index, $editorialContext = []) {
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

function content_ideas_generate_section($dbo, $idea, $section, $sections, $index, $editorialContext = [], $aiSettings = [], $userId = null) {
    $ai = new ai(publisher_require_ai_api_key($dbo, (int)$idea['account_id']));
    $ai->text_model($aiSettings['text_model'] ?? 'gpt-5.2');
    $ai->log_context($dbo, [
        'account_id' => (int)$idea['account_id'],
        'property_id' => (int)$idea['property_id'],
        'content_idea_id' => (int)$idea['id'],
        'action_type' => 'generate_article',
        'created_by' => $userId,
    ]);
    $ai->instructions('You are an expert editorial writer. Return only clean HTML for the requested section.');
    $ai->prompt(content_ideas_section_prompt($idea, $section, $sections, $index, $editorialContext));
    $response = $ai->send_request();
    if (($response['result'] ?? '') !== 'success') {
        throw new Exception($response['message'] ?? 'AI section generation failed.');
    }
    return trim((string)($response['content'] ?? ''));
}

function content_ideas_safe_image_prompt($idea) {
    $prompt = trim((string)($idea['image_prompt'] ?? ''));
    if ($prompt === '') {
        return '';
    }

    $prompt = preg_replace('/\b(in|by|like|similar to|inspired by)\s+the\s+style\s+of\s+[^,.]+[,.]?/i', 'with an original editorial visual style, ', $prompt);
    $prompt = preg_replace('/\b(style of|inspired by|like)\s+[^,.]+[,.]?/i', 'original visual direction, ', $prompt);
    $safety = 'Create an original, generic, non-infringing editorial image. Do not include logos, trademarks, brand names, copyrighted characters, recognizable celebrities or public figures, movie/game/book characters, exact product designs, screenshots, album covers, posters, or imitation of any living artist. If the topic references a known brand, person, franchise, product, or protected work, represent only the broader concept with fictional/generic elements and no readable text. If any part of this prompt violates safety rules related to similarity with third-party content, remove those elements and replace them with similar generic elements that do not have that issue.';

    return trim($prompt . "\n\n" . $safety);
}

function content_ideas_create_article_from_idea($dbo, $idea, $userId, $aiSettings = []) {
    $generationStartedAt = date('Y-m-d H:i:s');
    $sections = content_ideas_decode_sections($idea['sections'] ?? '', $idea['summary'] ?? '');
    $editorialContext = editorial_context_get($dbo, (int)$idea['account_id'], (int)$idea['property_id']);
    $bodyParts = [];
    foreach ($sections as $index => $section) {
        $bodyParts[] = content_ideas_generate_section($dbo, $idea, $section, $sections, $index + 1, $editorialContext, $aiSettings, $userId);
    }

    $imagePath = '';
    $safeImagePrompt = content_ideas_safe_image_prompt($idea);
    if ($safeImagePrompt !== '') {
        try {
            $ai = new ai(publisher_require_ai_api_key($dbo, (int)$idea['account_id']));
            $ai->image_model($aiSettings['image_model'] ?? 'gpt-image-1.5');
            $ai->log_context($dbo, [
                'account_id' => (int)$idea['account_id'],
                'property_id' => (int)$idea['property_id'],
                'content_idea_id' => (int)$idea['id'],
                'action_type' => 'generate_article',
                'created_by' => $userId,
            ]);
            $ai->prompt($safeImagePrompt);
            $mediaDir = __DIR__ . DIRECTORY_SEPARATOR . 'media';
            $imagePath = $ai->create_image($mediaDir);
            $publisherRoot = rtrim(str_replace('\\', '/', __DIR__), '/') . '/';
            $normalizedImagePath = str_replace('\\', '/', $imagePath);
            if (strpos($normalizedImagePath, $publisherRoot) === 0) {
                $imagePath = substr($normalizedImagePath, strlen($publisherRoot));
            }
        } catch (Exception $ex) {
            $imagePath = '';
        }
    }

    $now = date('Y-m-d H:i:s');
    $slug = content_ideas_slugify($idea['title']);
    $body = implode("\n\n", $bodyParts);
    $seo = content_ideas_article_seo($idea);

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
            $slug,
            $idea['summary'],
            $seo['meta_title'],
            $seo['meta_description'],
            $body,
            'draft',
            $idea['language'],
            $userId > 0 ? $userId : null,
            $now,
            $now,
        ]
    );
    $itemRows = $dbo->getRS('SELECT id FROM content_items WHERE account_id = ? AND property_id = ? AND source_idea_id = ? ORDER BY id DESC LIMIT 1', [$idea['account_id'], $idea['property_id'], $idea['id']]);
    if (!$itemRows) {
        throw new Exception('Content item was not created.');
    }
    $contentItemId = (int)$itemRows[0]['id'];
    $dbo->execSQL(
        'UPDATE ai_generation_logs
         SET content_item_id = ?
         WHERE account_id = ? AND property_id = ? AND content_idea_id = ? AND content_item_id IS NULL AND created_at >= ?',
        [$contentItemId, (int)$idea['account_id'], (int)$idea['property_id'], (int)$idea['id'], $generationStartedAt]
    );

    if ((int)($idea['category_id'] ?? 0) > 0) {
        $dbo->execSQL(
            'INSERT IGNORE INTO content_item_categories (content_item_id, category_id) VALUES (?, ?)',
            [$contentItemId, (int)$idea['category_id']]
        );
    }

    if ($imagePath !== '') {
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
                json_encode(['content_idea_id' => (int)$idea['id'], 'ai_models' => $aiSettings], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                $now,
            ]
        );
    }

    $dbo->execSQL(
        'UPDATE content_ideas SET status = ?, content_item_id = ?, updated_at = ? WHERE id = ?',
        ['converted', $contentItemId, date('Y-m-d H:i:s'), $idea['id']]
    );

    return $contentItemId;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_articles') {
    $selectedIds = content_ideas_selected_ids($_POST);
    $generationMode = ($_POST['generation_mode'] ?? 'generate') === 'schedule' ? 'schedule' : 'generate';
    $postedAiSettings = [
        'text_model' => trim((string)($_POST['text_model'] ?? '')),
        'image_model' => trim((string)($_POST['image_model'] ?? '')),
    ];
    $settingsToSave = is_array($contentGenerationSaved) ? $contentGenerationSaved : [];
    $settingsToSave['schedule_pending'] = $generationMode === 'schedule';
    if ($postedAiSettings['text_model'] !== '') {
        $settingsToSave['text_model'] = publisher_ai_normalize_text_model($postedAiSettings['text_model'], $contentGenerationDefaults['text_model']);
    }
    if ($postedAiSettings['image_model'] !== '') {
        $settingsToSave['image_model'] = publisher_ai_normalize_image_model($postedAiSettings['image_model'], $contentGenerationDefaults['image_model']);
    }
    $propertySettings = publisher_ai_set_settings_section(is_array($propertySettings) ? $propertySettings : [], 'content_generation', 'Content Generation', $settingsToSave);
    $dbo->execSQL(
        'UPDATE properties SET settings_json = ?, updated_at = ? WHERE id = ? AND account_id = ?',
        [json_encode($propertySettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), date('Y-m-d H:i:s'), $propertyId, $accountId]
    );
    if (!$selectedIds) {
        $errors[] = 'Please select at least one content idea.';
    } elseif ($generationMode === 'schedule') {
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $params = array_merge(['accepted', date('Y-m-d H:i:s')], $selectedIds, [$accountId, $propertyId]);
        $dbo->execSQL(
            "UPDATE content_ideas SET status = ?, updated_at = ?
             WHERE id IN ({$placeholders}) AND account_id = ? AND property_id = ? AND content_item_id IS NULL",
            $params
        );
        $success = count($selectedIds) . ' content idea(s) scheduled for article generation.';
    } else {
        $createdCount = 0;
        foreach ($selectedIds as $ideaId) {
            $ideaRows = $dbo->getRS(
                "SELECT ci.*, cc.name AS category_name
                 FROM content_ideas ci
                 LEFT JOIN content_categories cc ON cc.id = ci.category_id
                 WHERE ci.id = ? AND ci.account_id = ? AND ci.property_id = ? LIMIT 1",
                [$ideaId, $accountId, $propertyId]
            );
            if (!$ideaRows) {
                $errors[] = 'Content idea #' . $ideaId . ' was not found or is not available.';
                continue;
            }
            try {
                $ideaAiSettings = publisher_idea_ai_settings($ideaRows[0], $contentIdeasGenerationDefaults);
                if ($postedAiSettings['text_model'] !== '') {
                    $ideaAiSettings['text_model'] = publisher_ai_normalize_text_model($postedAiSettings['text_model'], $ideaAiSettings['text_model']);
                }
                if ($postedAiSettings['image_model'] !== '') {
                    $ideaAiSettings['image_model'] = publisher_ai_normalize_image_model($postedAiSettings['image_model'], $ideaAiSettings['image_model']);
                }
                $contentItemId = content_ideas_create_article_from_idea($dbo, $ideaRows[0], (int)$userid, $ideaAiSettings);
                if (($publishingConfig['mode'] ?? '') === 'automatic') {
                    $channelId = (int)($publishingConfig['distribution_channel_id'] ?? 0);
                    $wordpressStatus = in_array(($publishingConfig['wordpress_status'] ?? 'draft'), ['draft', 'publish'], true)
                        ? $publishingConfig['wordpress_status']
                        : 'draft';
                    if ($channelId > 0) {
                        try {
                            publisher_publish_content_item($dbo, $contentItemId, $channelId, $accountId, $propertyId, ['default_status' => $wordpressStatus]);
                        } catch (Exception $publishEx) {
                            $errors[] = 'Content item #' . $contentItemId . ' WordPress publish failed: ' . $publishEx->getMessage();
                        }
                    }
                }
                $createdCount++;
            } catch (Exception $ex) {
                $errors[] = 'Content idea #' . $ideaId . ': ' . $ex->getMessage();
            }
        }
        if ($createdCount > 0) {
            $success = $createdCount . ' article(s) created.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_update_status') {
    $selectedIds = content_ideas_selected_ids($_POST);
    $newStatus = trim((string)($_POST['bulk_status'] ?? ''));

    if (!$selectedIds) {
        $errors[] = 'Please select at least one content idea.';
    } elseif (!in_array($newStatus, $contentIdeaStatusOptions, true)) {
        $errors[] = 'Please select a valid status.';
    } else {
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $params = array_merge([$newStatus, date('Y-m-d H:i:s')], $selectedIds, [$accountId, $propertyId]);
        $dbo->execSQL(
            "UPDATE content_ideas
             SET status = ?, updated_at = ?
             WHERE id IN ({$placeholders}) AND account_id = ? AND property_id = ?",
            $params
        );
        $success = count($selectedIds) . ' content idea(s) updated.';
    }
}

$canAdd = TRUE;
$canView = TRUE;

$searchDefaults = [
    'search_category_id' => 0,
    'search_title' => '',
    'search_date_d1' => '',
    'search_date_d2' => '',
    'search_status' => 0,
];
$_GET = array_merge($searchDefaults, $_GET);
if (!in_array((string)$_GET['search_status'], ['0', 'suggested', 'accepted', 'rejected', 'converted'], true)) {
    $_GET['search_status'] = 0;
}

$searchGet = $_GET;
$searchGet['search_category_id'] = (int)$searchGet['search_category_id'];
$searchGet['search_title'] = trim((string)$searchGet['search_title']) !== ''
    ? $dbo->getConn()->quote('%' . trim((string)$searchGet['search_title']) . '%')
    : '';

$list = new LISTCONTROL($dbo,
    "SELECT ci.id, ci.title, ci.summary, ci.tags, ci.language, ci.tone, ci.status, ci.similarity_score, ci.created_at, ci.updated_at,
            cc.name AS category_name,
            CASE
                WHEN content_item.id IS NULL THEN ''
                ELSE CONCAT('<a style=\"cursor:pointer\" class=\"modalBtn\" data-title=\"Content item\" data-href=\"content_item.php?id=', content_item.id, '&l=GR\" data-height=\"780\" data-width=\"1200\">', content_item.id, '</a>')
            END AS content_item_link,
            u.name AS created_by_name
     FROM content_ideas ci
     LEFT JOIN content_categories cc ON cc.id = ci.category_id
     LEFT JOIN content_items content_item ON content_item.id = ci.content_item_id AND content_item.account_id = ci.account_id AND content_item.property_id = ci.property_id
     LEFT JOIN users u ON u.id = ci.created_by
     WHERE ci.account_id = {$accountId} AND ci.property_id = {$propertyId}",
    [],
    [],
    [],
    "content_ideas.php",
    "content_idea.php",
    "Content idea",
    $canAdd,
    $canView);

$fields = [
    ["id", "text", "ID"],
    ["title", "text", "Title"],
    ["category_name", "text", "Category"],
    ["tags", "text", "Tags"],
    ["language", "text", "Language"],
    ["tone", "text", "Tone"],
    ["status", "text", "Status"],
    ["content_item_link", "text", "Content item"],
    ["created_by_name", "text", "Created by"],
    ["updated_at", "text", "Updated"],
];

$list->setFields($fields);
$list->setSearch(
    ["search_category_id", "search_title", "search_date", "search_status"],
    ["combobox", "text", "datefromto", "combobox"],
    ["Category", "Title", "Date", "Status"]
);
$list->setSearchFieldAttr("search_category_id", [
    "SQL" => "SELECT id, name FROM content_categories WHERE account_id = {$accountId} AND property_id = {$propertyId} ORDER BY name",
    "ID-FIELD" => "id",
    "DESC-FIELD" => "name",
    "READONLY" => "",
    "CRITERIA" => " AND ci.category_id = [val] ",
]);
$list->setSearchFieldAttr("search_title", [
    "SEARCH-TYPE" => "ANY",
    "READONLY" => "",
    "CRITERIA" => " AND ci.title LIKE [val] ",
]);
$list->setSearchFieldAttr("search_date", [
    "READONLY" => "",
    "CRITERIA" => " AND (ci.created_at >= '[val1]' AND ci.created_at <= '[val2]') ",
]);
$list->setSearchFieldAttr("search_status", [
    "SQL" => "",
    "ID-FIELD" => "id",
    "DESC-FIELD" => "description",
    "READONLY" => "",
    "RS" => [
        ["id" => "suggested", "description" => "suggested"],
        ["id" => "accepted", "description" => "accepted"],
                ["id" => "rejected", "description" => "rejected"],
                ["id" => "converted", "description" => "converted"],
    ],
    "CRITERIA" => " AND ci.status = '[val]' ",
]);
$list->set_select("Select");
$list->SearchList($searchGet, TRUE, TRUE, "ci.created_at DESC", 50);

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <title><?php echo app::$project_name; ?> - Content ideas</title>
    <?php include "_head.php"; ?>
    <script>
        function refresh() {
            window.location.href = "content_ideas.php";
        }

        function submitContentIdeasAction(actionName) {
            document.getElementById("content-ideas-action").value = actionName;
            return true;
        }
    </script>
    <style>
        #grid { max-width:1300px; }
        .property-context { color:#52606d; margin-bottom:18px; }
        .batch-actions { margin:0 0 14px; display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; }
        .batch-actions .model-field { min-width:220px; }
        .message-list { margin:0; padding-left:18px; }

        #search_date_d1, #search_date_d2 {
            width:45%;
            display:inline-block;
        }
    </style>
    <?php $list->refreshScript(); ?>
</head>
<body>
    <?php include "blocks/header.php"; ?>

    <div class="padding-20">
        <h1>Content ideas</h1>
        <div class="property-context">
            Property: <strong><?php echo htmlspecialchars($current_property_name, ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>

        <?php if ($errors) { ?>
            <div class="alert alert-danger"><ul class="message-list"><?php foreach ($errors as $error) { ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php } ?></ul></div>
        <?php } ?>
        <?php if ($success) { ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php } ?>

        <?php
        $list->searchForm();
        $list->setSearch([], [], []);
        ?>

        <form method="post" id="generate-articles-form">
            <input type="hidden" name="action" id="content-ideas-action" value="generate_articles">
            <div class="batch-actions">
                <div class="model-field">
                    <label for="bulk_status">Bulk status</label>
                    <select class="form-control" id="bulk_status" name="bulk_status">
                        <?php foreach ($contentIdeaStatusOptions as $statusOption) { ?>
                            <option value="<?php echo htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <button class="btn btn-default" type="submit" onclick="return submitContentIdeasAction('bulk_update_status');">Update status</button>
                <div class="model-field">
                    <label for="generation_mode">Generation</label>
                    <select class="form-control" id="generation_mode" name="generation_mode">
                        <option value="generate" <?php echo empty($contentGenerationSaved['schedule_pending']) ? 'selected' : ''; ?>>Generate articles</option>
                        <option value="schedule" <?php echo !empty($contentGenerationSaved['schedule_pending']) ? 'selected' : ''; ?>>Schedule article generation</option>
                    </select>
                </div>
                <div class="model-field">
                    <label for="text_model">Text model</label>
                    <select class="form-control" id="text_model" name="text_model">
                        <option value="">Use previous stage default</option>
                        <?php foreach ($textModelOptions as $value => $label) { ?>
                            <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($contentGenerationSaved['text_model'] ?? '') === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="model-field">
                    <label for="image_model">Image model</label>
                    <select class="form-control" id="image_model" name="image_model">
                        <option value="">Use previous stage default</option>
                        <?php foreach ($imageModelOptions as $value => $label) { ?>
                            <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($contentGenerationSaved['image_model'] ?? '') === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <button class="btn btn-primary" type="submit" onclick="return submitContentIdeasAction('generate_articles');">Continue</button>
            </div>
            <?php $list->ViewList("Open", 50, 1200, 780); ?>
        </form>
    </div>

    <?php include "blocks/footer.php"; ?>
</body>
</html>
