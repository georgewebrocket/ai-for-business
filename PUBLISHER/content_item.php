<?php

require_once('php/config.php');
require_once('php/db.php');
require_once('php/dataobjects.php');
require_once('php/utils.php');
require_once('php/start.php');
require_once('php/session.php');
require_once('php/controls.php');
require_once('php/publishing.php');

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
$isPublishAction = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'publish_channel';

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
        return substr($url, $pos + strlen($marker));
    }
    return $url;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isPublishAction) {
    $now = date('Y-m-d H:i:s');
    $_POST['account_id'] = $current_account_id;
    $_POST['property_id'] = $current_property_id;
    $_POST['updated_at'] = $now;

    if (trim($_POST['slug'] ?? '') === '') {
        $_POST['slug'] = publisher_content_item_slugify($_POST['title'] ?? '');
    }

    foreach (['content_type_id', 'source_idea_id', 'writing_style_id', 'template_id', 'ai_profile_id', 'approved_by'] as $nullableField) {
        if (isset($_POST[$nullableField]) && (int)$_POST[$nullableField] === 0) {
            $_POST[$nullableField] = null;
        }
    }

    if (trim($_POST['published_at'] ?? '') === '') {
        $_POST['published_at'] = null;
    }

    if ($id == 0 || !$item->created_at()) {
        $_POST['created_at'] = $now;
        $_POST['created_by'] = $userid > 0 ? $userid : null;
    } else {
        $_POST['created_at'] = $item->created_at();
        $_POST['created_by'] = $item->created_by();
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

$saveRes = $isPublishAction ? '' : $itemControl->SaveItem($_POST);
$delRes = $itemControl->DeleteItem($_GET);

$mediaAssets = [];
$featuredImage = null;
$channels = [];
$publications = [];
if ($id > 0) {
    $mediaAssets = $dbo->getRS(
        'SELECT * FROM media_assets WHERE account_id = ? AND property_id = ? AND content_item_id = ? ORDER BY created_at DESC',
        [$current_account_id, $current_property_id, $id]
    ) ?: [];
    foreach ($mediaAssets as $asset) {
        if (publisher_content_item_media_url($asset) !== '') {
            $featuredImage = $asset;
            break;
        }
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
    <title><?php echo app::$project_name; ?> - Content item</title>
    <?php include "_head.php"; ?>
    <style>
        body { background:#fff; }
        textarea.form-control { min-height:120px; }
        .featured-image { margin:18px 0 24px; max-width:760px; }
        .featured-image a { display:block; }
        .featured-image img { display:block; width:100%; max-height:420px; object-fit:contain; background:#eef2f7; border:1px solid #d9e2ec; border-radius:6px; }
        .featured-image .media-meta { margin-top:8px; }
        .media-meta { color:#52606d; font-size:12px; word-break:break-word; }
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
    </style>
</head>
<body>
    <div class="padding-20">
        <h1>Content item</h1>
        <p style="color:#52606d;">Property: <?php echo htmlspecialchars($current_property_name, ENT_QUOTES, 'UTF-8'); ?></p>

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
                <h2>Image</h2>
                <a href="<?php echo htmlspecialchars($featuredImageUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                    <img src="<?php echo htmlspecialchars($featuredImageUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($featuredImage['alt_text'] ?? $item->title(), ENT_QUOTES, 'UTF-8'); ?>">
                </a>
                <div class="media-meta"><?php echo htmlspecialchars($featuredImageUrl, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        <?php } ?>

        <?php if ($id > 0) { ?>
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
