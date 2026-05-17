<?php

require_once('php/config.php');
require_once('php/db.php');
require_once('php/dataobjects.php');
require_once('php/utils.php');
require_once('php/start.php');
require_once('php/session.php');
require_once('php/controls.php');
require_once('php/publishing.php');
require_once('php/ai.php');
require_once('php/ai-settings.php');

publisher_require_permission('content');
publisher_require_property();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $itemRows = $dbo->getRS(
        'SELECT id FROM content_items WHERE id = ? AND account_id = ? AND property_id = ? LIMIT 1',
        [$id, $current_account_id, $current_property_id]
    );
    if (!$itemRows) {
        http_response_code(403);
        die('Access denied');
    }
}

$item = new content_items($dbo, $id);
$errors = [];
$success = '';
$postAction = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['action'] ?? '') : '';
$isPublishAction = $postAction === 'publish_channel';
$isGenerateImageAction = $postAction === 'generate_image';
$isRestoreImageAction = $postAction === 'restore_image';

if ($id == 0) {
    $item->account_id($current_account_id);
    $item->property_id($current_property_id);
    $item->status('draft');
    $item->created_by($userid);
}

function publisher_content_item_slugify($text) {
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

function publisher_content_item_media_url($asset) {
    $url = trim((string)($asset['file_path'] ?: $asset['external_url']));
    if ($url === '') {
        return '';
    }
    $url = str_replace('\\', '/', $url);
    if (stripos($url, 'http') === 0) {
        return $url;
    }
    $marker = '/PUBLISHER/';
    $pos = strpos($url, $marker);
    if ($pos !== false) {
        return ltrim(substr($url, $pos + strlen($marker)), '/');
    }
    $marker = '/publisher/';
    $pos = stripos($url, $marker);
    if ($pos !== false) {
        return ltrim(substr($url, $pos + strlen($marker)), '/');
    }
    if (strpos($url, '/') === 0) {
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/') : '';
        if ($docRoot !== '' && strpos($url, $docRoot . '/') === 0) {
            return ltrim(substr($url, strlen($docRoot)), '/');
        }
        return ltrim($url, '/');
    }
    return ltrim($url, '/');
}

function publisher_content_item_limit_text($text, $maxLength) {
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

function publisher_content_item_safe_image_prompt($prompt) {
    $prompt = trim((string)$prompt);
    if ($prompt === '') {
        return '';
    }

    $prompt = preg_replace('/\b(in|by|like|similar to|inspired by)\s+the\s+style\s+of\s+[^,.]+[,.]?/i', 'with an original editorial visual style, ', $prompt);
    $prompt = preg_replace('/\b(style of|inspired by|like)\s+[^,.]+[,.]?/i', 'original visual direction, ', $prompt);
    $safety = 'Create an original, generic, non-infringing editorial image. Do not include logos, trademarks, brand names, copyrighted characters, recognizable celebrities or public figures, movie/game/book characters, exact product designs, screenshots, album covers, posters, or imitation of any living artist. If the topic references a known brand, person, franchise, product, or protected work, represent only the broader concept with fictional/generic elements and no readable text. If any part of this prompt violates safety rules related to similarity with third-party content, remove those elements and replace them with similar generic elements that do not have that issue.';

    return trim($prompt . "\n\n" . $safety);
}

function publisher_content_item_editable_image_prompt($prompt) {
    $prompt = trim((string)$prompt);
    $marker = 'Create an original, generic, non-infringing editorial image.';
    $pos = strpos($prompt, $marker);
    if ($pos !== false) {
        $prompt = trim(substr($prompt, 0, $pos));
    }
    return $prompt;
}

function publisher_content_item_relative_media_path($path) {
    $publisherRoot = rtrim(str_replace('\\', '/', __DIR__), '/') . '/';
    $normalizedPath = str_replace('\\', '/', $path);
    if (strpos($normalizedPath, $publisherRoot) === 0) {
        return substr($normalizedPath, strlen($publisherRoot));
    }
    return $path;
}

function publisher_content_item_image_model($dbo, $accountId, $propertyId) {
    $propertyRows = $dbo->getRS('SELECT settings_json FROM properties WHERE id = ? AND account_id = ? LIMIT 1', [$propertyId, $accountId]);
    $propertySettings = $propertyRows ? json_decode((string)$propertyRows[0]['settings_json'], true) : [];
    $propertyAiDefaults = publisher_property_ai_defaults(is_array($propertySettings) ? $propertySettings : []);
    $contentGenerationDefaults = publisher_stage_ai_settings(is_array($propertySettings) ? $propertySettings : [], 'content_generation', $propertyAiDefaults);
    return $contentGenerationDefaults['image_model'] ?? 'gpt-image-1.5';
}

function publisher_content_item_source_defaults($dbo, $sourceIdeaId, $accountId, $propertyId) {
    $sourceIdeaId = (int)$sourceIdeaId;
    if ($sourceIdeaId <= 0) {
        return [];
    }
    $rows = $dbo->getRS(
        'SELECT content_type_id, ai_response_json FROM content_ideas WHERE id = ? AND account_id = ? AND property_id = ? LIMIT 1',
        [$sourceIdeaId, $accountId, $propertyId]
    );
    if (!$rows) {
        return [];
    }

    $metadata = json_decode((string)($rows[0]['ai_response_json'] ?? ''), true);
    $mix = is_array($metadata) && isset($metadata['content_mix']) && is_array($metadata['content_mix'])
        ? $metadata['content_mix']
        : [];

    return [
        'content_type_id' => (int)($rows[0]['content_type_id'] ?? 0),
        'writing_style_id' => (int)($mix['writing_style_id'] ?? 0),
        'template_id' => (int)($mix['content_template_id'] ?? ($mix['template_id'] ?? 0)),
    ];
}

function publisher_content_item_category_names($dbo, $contentItemId, $sourceIdeaId, $accountId, $propertyId) {
    $contentItemId = (int)$contentItemId;
    $sourceIdeaId = (int)$sourceIdeaId;

    if ($contentItemId > 0) {
        $rows = $dbo->getRS(
            'SELECT cc.name
             FROM content_item_categories cic
             INNER JOIN content_categories cc ON cc.id = cic.category_id
             WHERE cic.content_item_id = ?
               AND cc.account_id = ?
               AND cc.property_id = ?
             ORDER BY cc.name',
            [$contentItemId, $accountId, $propertyId]
        ) ?: [];
        if ($rows) {
            return implode(', ', array_map(function($row) {
                return (string)$row['name'];
            }, $rows));
        }
    }

    if ($sourceIdeaId > 0) {
        $rows = $dbo->getRS(
            'SELECT cc.name
             FROM content_ideas ci
             INNER JOIN content_categories cc ON cc.id = ci.category_id
             WHERE ci.id = ?
               AND ci.account_id = ?
               AND ci.property_id = ?
             LIMIT 1',
            [$sourceIdeaId, $accountId, $propertyId]
        );
        if ($rows) {
            return (string)$rows[0]['name'];
        }
    }

    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isPublishAction && !$isGenerateImageAction && !$isRestoreImageAction) {
    $now = date('Y-m-d H:i:s');
    $_POST['account_id'] = $current_account_id;
    $_POST['property_id'] = $current_property_id;
    $_POST['updated_at'] = $now;

    if (trim($_POST['slug'] ?? '') === '') {
        $_POST['slug'] = publisher_content_item_slugify($_POST['title'] ?? '');
    }
    $_POST['meta_title'] = publisher_content_item_limit_text($_POST['meta_title'] ?? ($_POST['title'] ?? ''), 60);
    $_POST['meta_description'] = publisher_content_item_limit_text($_POST['meta_description'] ?? ($_POST['summary'] ?? ''), 160);

    foreach (['content_type_id', 'source_idea_id', 'writing_style_id', 'template_id', 'ai_profile_id', 'approved_by'] as $nullableField) {
        if (isset($_POST[$nullableField]) && (int)$_POST[$nullableField] === 0) {
            $_POST[$nullableField] = null;
        }
    }

    if (trim($_POST['published_at'] ?? '') === '') {
        $_POST['published_at'] = null;
    }

    $sourceDefaults = publisher_content_item_source_defaults($dbo, (int)($_POST['source_idea_id'] ?? 0), (int)$current_account_id, (int)$current_property_id);
    if ((int)($_POST['writing_style_id'] ?? 0) === 0 && !empty($sourceDefaults['writing_style_id'])) {
        $_POST['writing_style_id'] = $sourceDefaults['writing_style_id'];
    }
    if ((int)($_POST['template_id'] ?? 0) === 0 && !empty($sourceDefaults['template_id'])) {
        $_POST['template_id'] = $sourceDefaults['template_id'];
    }
    if ((int)($_POST['content_type_id'] ?? 0) === 0 && !empty($sourceDefaults['content_type_id'])) {
        $_POST['content_type_id'] = $sourceDefaults['content_type_id'];
    }

    if ($id == 0 || !$item->created_at()) {
        $_POST['created_at'] = $now;
        $_POST['created_by'] = $userid > 0 ? $userid : null;
    } else {
        $_POST['created_at'] = $item->created_at();
        $_POST['created_by'] = $item->created_by();
    }
}

if ($id > 0 && (int)$item->source_idea_id() > 0) {
    $sourceDefaults = publisher_content_item_source_defaults($dbo, (int)$item->source_idea_id(), (int)$current_account_id, (int)$current_property_id);
    if ((int)$item->writing_style_id() === 0 && !empty($sourceDefaults['writing_style_id'])) {
        $item->writing_style_id($sourceDefaults['writing_style_id']);
    }
    if ((int)$item->template_id() === 0 && !empty($sourceDefaults['template_id'])) {
        $item->template_id($sourceDefaults['template_id']);
    }
    if ((int)$item->content_type_id() === 0 && !empty($sourceDefaults['content_type_id'])) {
        $item->content_type_id($sourceDefaults['content_type_id']);
    }
}

if ($isRestoreImageAction) {
    $assetId = (int)($_POST['media_asset_id'] ?? 0);
    if ($id <= 0) {
        $errors[] = 'Save the content item before restoring an image.';
    } elseif ($assetId <= 0) {
        $errors[] = 'Select an image to restore.';
    } else {
        $assetRows = $dbo->getRS(
            'SELECT id FROM media_assets WHERE id = ? AND account_id = ? AND property_id = ? AND content_item_id = ? AND type = ? LIMIT 1',
            [$assetId, $current_account_id, $current_property_id, $id, 'image']
        );
        if (!$assetRows) {
            $errors[] = 'Image was not found for this content item.';
        } else {
            $dbo->execSQL(
                'UPDATE media_assets SET created_at = ? WHERE id = ? AND account_id = ? AND property_id = ? AND content_item_id = ?',
                [date('Y-m-d H:i:s'), $assetId, $current_account_id, $current_property_id, $id]
            );
            $success = 'Image restored as current.';
        }
    }
}

if ($isGenerateImageAction) {
    $imagePrompt = trim((string)($_POST['image_prompt'] ?? ''));
    if ($id <= 0) {
        $errors[] = 'Save the content item before generating an image.';
    } elseif ($imagePrompt === '') {
        $errors[] = 'Image prompt is required.';
    } else {
        try {
            $safeImagePrompt = publisher_content_item_safe_image_prompt($imagePrompt);
            $imageModel = publisher_content_item_image_model($dbo, (int)$current_account_id, (int)$current_property_id);
            $ai = new ai(publisher_require_ai_api_key($dbo, $accountId));
            $ai->image_model($imageModel);
            $ai->log_context($dbo, [
                'account_id' => (int)$current_account_id,
                'property_id' => (int)$current_property_id,
                'content_item_id' => (int)$id,
                'content_idea_id' => (int)$item->source_idea_id(),
                'action_type' => 'generate_article',
                'created_by' => (int)$userid,
            ]);
            $ai->prompt($safeImagePrompt);
            $mediaDir = __DIR__ . DIRECTORY_SEPARATOR . 'media';
            $imagePath = publisher_content_item_relative_media_path($ai->create_image($mediaDir));
            $now = date('Y-m-d H:i:s');
            $dbo->execSQL(
                'INSERT INTO media_assets
                 (account_id, property_id, content_item_id, type, source, prompt, file_path, external_url, alt_text, caption, metadata_json, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    (int)$current_account_id,
                    (int)$current_property_id,
                    (int)$id,
                    'image',
                    'openai',
                    $safeImagePrompt,
                    $imagePath,
                    null,
                    $item->title(),
                    $item->summary(),
                    json_encode(['manual_regeneration' => true, 'image_model' => $imageModel, 'user_prompt' => $imagePrompt], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                    $now,
                ]
            );
            $success = 'Image generated successfully.';
        } catch (Exception $ex) {
            $errors[] = 'Image generation failed: ' . $ex->getMessage();
        }
    }
}

if ($isPublishAction) {
    $channelId = (int)($_POST['channel_id'] ?? 0);
    if ($id <= 0) {
        $errors[] = 'Save the content item before publishing.';
    } elseif ($channelId <= 0) {
        $errors[] = 'Select a distribution channel.';
    } else {
        try {
            $publishResult = publisher_publish_content_item($dbo, $id, $channelId, $current_account_id, $current_property_id);
            $success = 'Published successfully.';
            if (!empty($publishResult['external_url'])) {
                $success .= ' URL: ' . $publishResult['external_url'];
            }
            $item = new content_items($dbo, $id);
        } catch (Exception $ex) {
            $errors[] = $ex->getMessage();
        }
    }
}

$canSave = TRUE;
$canDelete = TRUE;

$itemControl = new ITEMCONTROL($dbo, $item,
    [],
    [],
    [],
    "content_item.php?id=" . $id,
    $canSave,
    $canDelete);

$fields = [
    ["id", "ID", "ID"],
    ["account_id", "hidden", ""],
    ["property_id", "hidden", ""],
    ["content_type_id", "combobox", "Content type"],
    ["source_idea_id", "combobox", "Source idea"],
    ["title", "text", "Title"],
    ["slug", "text", "Slug"],
    ["summary", "textarea", "Summary"],
    ["meta_title", "text", "Meta title"],
    ["meta_description", "textarea", "Meta description"],
    ["body", "richtextbox", "Body"],
    ["status", "combobox", "Status"],
    ["language", "text", "Language"],
    ["writing_style_id", "combobox", "Writing style"],
    ["template_id", "combobox", "Template"],
    ["ai_profile_id", "hidden", ""],
    ["created_by", "hidden", ""],
    ["approved_by", "hidden", ""],
    ["published_at", "text", "Published at"],
    ["created_at", "hidden", ""],
    ["updated_at", "hidden", ""],
];

$itemControl->setFields($fields);

$itemControl->setFieldAttr("content_type_id", [
    "SQL" => "SELECT id, name FROM content_types WHERE account_id = " . (int)$current_account_id
        . " AND (property_id = " . (int)$current_property_id . " OR property_id IS NULL) ORDER BY name",
    "ID-FIELD" => "id",
    "DESC-FIELD" => "name",
    "READONLY" => "",
]);

$itemControl->setFieldAttr("source_idea_id", [
    "SQL" => "SELECT id, title FROM content_ideas WHERE account_id = " . (int)$current_account_id
        . " AND property_id = " . (int)$current_property_id
        . " ORDER BY title",
    "ID-FIELD" => "id",
    "DESC-FIELD" => "title",
    "READONLY" => "",
]);

$itemControl->setFieldAttr("status", [
    "SQL" => "",
    "ID-FIELD" => "id",
    "DESC-FIELD" => "description",
    "READONLY" => "",
    "RS" => [
        ["id" => "draft", "description" => "draft"],
        ["id" => "review", "description" => "review"],
        ["id" => "approved", "description" => "approved"],
        ["id" => "published", "description" => "published"],
        ["id" => "archived", "description" => "archived"],
    ]
]);

$itemControl->setFieldAttr("writing_style_id", [
    "SQL" => "SELECT id, name FROM writing_styles WHERE account_id = " . (int)$current_account_id
        . " AND property_id = " . (int)$current_property_id
        . " ORDER BY name",
    "ID-FIELD" => "id",
    "DESC-FIELD" => "name",
    "READONLY" => "",
]);

$itemControl->setFieldAttr("template_id", [
    "SQL" => "SELECT id, name FROM content_templates WHERE account_id = " . (int)$current_account_id
        . " AND (property_id = " . (int)$current_property_id . " OR property_id IS NULL) ORDER BY name",
    "ID-FIELD" => "id",
    "DESC-FIELD" => "name",
    "READONLY" => "",
]);

$saveRes = ($isPublishAction || $isGenerateImageAction || $isRestoreImageAction) ? '' : $itemControl->SaveItem($_POST);
$delRes = $itemControl->DeleteItem($_GET);

$mediaAssets = [];
$featuredImage = null;
$previousImages = [];
$imagePromptValue = '';
$channels = [];
$publications = [];
$pageHeading = $id > 0 && trim((string)$item->title()) !== '' ? $item->title() : 'New content item';
$showImagePrompt = $isGenerateImageAction && $errors;
$categoryNames = publisher_content_item_category_names($dbo, $id, (int)$item->source_idea_id(), (int)$current_account_id, (int)$current_property_id);
if ($id > 0) {
    $mediaAssets = $dbo->getRS(
        'SELECT * FROM media_assets
         WHERE account_id = ? AND property_id = ? AND content_item_id = ? AND COALESCE(file_path, external_url, "") <> ""
         ORDER BY CASE WHEN type = ? THEN 0 ELSE 1 END, created_at DESC, id DESC',
        [$current_account_id, $current_property_id, $id, 'image']
    ) ?: [];
    foreach ($mediaAssets as $asset) {
        if (publisher_content_item_media_url($asset) !== '') {
            $featuredImage = $asset;
            break;
        }
    }
    if ($featuredImage) {
        foreach ($mediaAssets as $asset) {
            if ((int)$asset['id'] !== (int)$featuredImage['id'] && publisher_content_item_media_url($asset) !== '') {
                $previousImages[] = $asset;
            }
        }
    }
    if ($featuredImage && trim((string)($featuredImage['prompt'] ?? '')) !== '') {
        $metadata = json_decode((string)($featuredImage['metadata_json'] ?? ''), true);
        $imagePromptValue = is_array($metadata) && isset($metadata['user_prompt'])
            ? (string)$metadata['user_prompt']
            : publisher_content_item_editable_image_prompt($featuredImage['prompt']);
    } elseif ((int)$item->source_idea_id() > 0) {
        $ideaRows = $dbo->getRS(
            'SELECT image_prompt FROM content_ideas WHERE id = ? AND account_id = ? AND property_id = ? LIMIT 1',
            [(int)$item->source_idea_id(), $current_account_id, $current_property_id]
        );
        if ($ideaRows && trim((string)($ideaRows[0]['image_prompt'] ?? '')) !== '') {
            $imagePromptValue = (string)$ideaRows[0]['image_prompt'];
        }
    }
    if ($imagePromptValue === '') {
        $imagePromptValue = trim((string)$item->title() . "\n\n" . (string)$item->summary());
    }

    $channels = $dbo->getRS(
        'SELECT * FROM distribution_channels WHERE account_id = ? AND property_id = ? AND status = ? ORDER BY name',
        [$current_account_id, $current_property_id, 'active']
    ) ?: [];

    $publications = $dbo->getRS(
        'SELECT cp.*, dc.name AS channel_name, dc.type AS channel_type
         FROM content_publications cp
         LEFT JOIN distribution_channels dc ON dc.id = cp.distribution_channel_id
         WHERE cp.account_id = ? AND cp.property_id = ? AND cp.content_item_id = ?
         ORDER BY cp.created_at DESC, cp.id DESC',
        [$current_account_id, $current_property_id, $id]
    ) ?: [];
}

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <title><?php echo app::$project_name; ?> - <?php echo htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php include "_head.php"; ?>
    <style>
        body { background:#fff; }
        textarea.form-control { min-height:120px; }
        .featured-image { margin:18px 0 24px; max-width:760px; }
        .featured-image a { display:block; }
        .featured-image img { display:block; width:100%; max-height:420px; object-fit:contain; background:#eef2f7; border:1px solid #d9e2ec; border-radius:6px; }
        .featured-image .media-meta { margin-top:8px; }
        .media-meta { color:#52606d; font-size:12px; word-break:break-word; }
        .image-panel { margin:18px 0 24px; padding:14px; border:1px solid #d9e2ec; border-radius:6px; max-width:920px; }
        .image-panel textarea { min-height:150px; font-family:Consolas, monospace; }
        .image-panel .btn { margin-top:10px; }
        .image-prompt-fields { display:none; margin-top:12px; }
        .image-prompt-fields.open { display:block; }
        .image-history { max-width:1100px; margin:18px 0 24px; }
        .image-history-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:12px; }
        .image-history-card { border:1px solid #d9e2ec; border-radius:6px; padding:8px; background:#fff; }
        .image-history-card.current { border-color:#2f80ed; box-shadow:0 0 0 1px #2f80ed inset; }
        .image-history-card img { display:block; width:100%; aspect-ratio:4 / 3; object-fit:cover; background:#eef2f7; border-radius:4px; }
        .image-history-card form { margin:8px 0 0; }
        .image-history-card .btn { width:100%; }
        .alert { padding:10px 12px; margin:12px 0; border-radius:4px; }
        .alert-success { background:#e3f8e9; border:1px solid #a8ddb5; color:#1f5130; }
        .alert-error { background:#fdecea; border:1px solid #f5b7b1; color:#7b241c; }
        .publish-panel { margin:18px 0 24px; padding:14px; border:1px solid #d9e2ec; border-radius:6px; max-width:920px; }
        .publish-actions { display:flex; flex-wrap:wrap; gap:8px; margin:10px 0 0; }
        .publish-actions form { margin:0; }
        .publication-history { width:100%; max-width:1100px; border-collapse:collapse; margin:14px 0 24px; }
        .publication-history th,
        .publication-history td { border-bottom:1px solid #d9e2ec; padding:8px; text-align:left; vertical-align:top; }
        .publication-history th { font-weight:bold; }
        .publication-error { max-width:360px; word-break:break-word; color:#7b241c; }
        .content-item-meta { color:#52606d; margin:-6px 0 16px; }
    </style>
    <script>
        function revealImagePrompt(event) {
            var fields = document.getElementById('image-prompt-fields');
            if (fields && !fields.classList.contains('open')) {
                event.preventDefault();
                fields.classList.add('open');
            }
        }
    </script>
</head>
<body>
    <div class="padding-20">
        <h1><?php echo htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if ($categoryNames !== '') { ?>
            <div class="content-item-meta">Category: <strong><?php echo htmlspecialchars($categoryNames, ENT_QUOTES, 'UTF-8'); ?></strong></div>
        <?php } ?>

        <?php foreach ($errors as $error) { ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php } ?>
        <?php if ($success !== '') { ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php } ?>

        <?php if ($featuredImage) {
            $featuredImageUrl = publisher_content_item_media_url($featuredImage);
        ?>
            <div class="featured-image">
                <a href="<?php echo htmlspecialchars($featuredImageUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                    <img src="<?php echo htmlspecialchars($featuredImageUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($featuredImage['alt_text'] ?? $item->title(), ENT_QUOTES, 'UTF-8'); ?>">
                </a>
                <div class="media-meta"><?php echo htmlspecialchars($featuredImageUrl, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        <?php } ?>

        <?php if ($id > 0) { ?>
            <div class="image-panel">
                <form method="post" action="content_item.php?id=<?php echo (int)$id; ?>">
                    <input type="hidden" name="action" value="generate_image">
                    <button type="submit" class="btn btn-primary" onclick="revealImagePrompt(event);">Regenerate Image</button>
                    <div class="image-prompt-fields <?php echo $showImagePrompt ? 'open' : ''; ?>" id="image-prompt-fields">
                        <label>Image prompt</label>
                        <textarea class="form-control" name="image_prompt"><?php echo htmlspecialchars($imagePromptValue, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </form>
            </div>

            <?php if ($previousImages) { ?>
                <div class="image-history">
                    <h2>Previous images</h2>
                    <div class="image-history-grid">
                        <?php foreach ($previousImages as $asset) {
                            $assetUrl = publisher_content_item_media_url($asset);
                            if ($assetUrl === '') {
                                continue;
                            }
                        ?>
                            <div class="image-history-card">
                                <a href="<?php echo htmlspecialchars($assetUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                                    <img src="<?php echo htmlspecialchars($assetUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($asset['alt_text'] ?: $item->title(), ENT_QUOTES, 'UTF-8'); ?>">
                                </a>
                                <div class="media-meta">
                                    Previous image<br>
                                    <?php echo htmlspecialchars($asset['created_at'] ?: '', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <form method="post" action="content_item.php?id=<?php echo (int)$id; ?>">
                                    <input type="hidden" name="action" value="restore_image">
                                    <input type="hidden" name="media_asset_id" value="<?php echo (int)$asset['id']; ?>">
                                    <button type="submit" class="btn btn-default">Make current</button>
                                </form>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>

            <div class="publish-panel">
                <h2>Publish</h2>
                <?php if (!$channels) { ?>
                    <div class="media-meta">No active distribution channels found for this property.</div>
                <?php } else { ?>
                    <div class="publish-actions">
                        <?php foreach ($channels as $channel) { ?>
                            <form method="post" action="content_item.php?id=<?php echo (int)$id; ?>">
                                <input type="hidden" name="action" value="publish_channel">
                                <input type="hidden" name="channel_id" value="<?php echo (int)$channel['id']; ?>">
                                <button type="submit" class="btn btn-primary">
                                    Publish to <?php echo htmlspecialchars($channel['name'] . ' (' . $channel['type'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                            </form>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>

            <?php if ($publications) { ?>
                <h2>Publication history</h2>
                <table class="publication-history">
                    <thead>
                        <tr>
                            <th>Channel</th>
                            <th>Status</th>
                            <th>External</th>
                            <th>Published</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($publications as $publication) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars(($publication['channel_name'] ?: 'Channel #' . $publication['distribution_channel_id']) . ' (' . ($publication['channel_type'] ?: '-') . ')', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($publication['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php if (!empty($publication['external_url'])) { ?>
                                        <a href="<?php echo htmlspecialchars($publication['external_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank"><?php echo htmlspecialchars($publication['external_id'] ?: $publication['external_url'], ENT_QUOTES, 'UTF-8'); ?></a>
                                    <?php } else { ?>
                                        <?php echo htmlspecialchars($publication['external_id'] ?: '-', ENT_QUOTES, 'UTF-8'); ?>
                                    <?php } ?>
                                </td>
                                <td><?php echo htmlspecialchars($publication['published_at'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="publication-error"><?php echo htmlspecialchars($publication['error_message'] ?: '', ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        <?php } ?>

        <?php $itemControl->ViewItem($saveRes, $delRes); ?>
    </div>

    <?php include "blocks/footer.php"; ?>
</body>
</html>
