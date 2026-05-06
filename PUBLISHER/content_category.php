<?php

require_once('php/config.php');
require_once('php/db.php');
require_once('php/dataobjects.php');
require_once('php/utils.php');
require_once('php/start.php');
require_once('php/session.php');
require_once('php/controls.php');

publisher_require_permission('content_categories');
publisher_require_property();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $categoryRows = $dbo->getRS(
        'SELECT id FROM content_categories WHERE id = ? AND account_id = ? AND property_id = ? LIMIT 1',
        [$id, $current_account_id, $current_property_id]
    );
    if (!$categoryRows) {
        http_response_code(403);
        die('Access denied');
    }
}

$item = new content_categories($dbo, $id);

if ($id == 0) {
    $item->account_id($current_account_id);
    $item->property_id($current_property_id);
    $item->status('active');
}

function publisher_slugify($text) {
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
    $text = trim($text, '-');

    return strtolower($text);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $now = date('Y-m-d H:i:s');
    $_POST['account_id'] = $current_account_id;
    $_POST['property_id'] = $current_property_id;
    $_POST['updated_at'] = $now;
    if (trim($_POST['slug'] ?? '') === '') {
        $_POST['slug'] = publisher_slugify($_POST['name'] ?? '');
    }
    if ($id == 0 || !$item->created_at()) {
        $_POST['created_at'] = $now;
    } else {
        $_POST['created_at'] = $item->created_at();
    }
    if (isset($_POST['parent_id']) && (int)$_POST['parent_id'] === 0) {
        $_POST['parent_id'] = null;
    }
    if (isset($_POST['parent_id']) && (int)$_POST['parent_id'] === $id) {
        $_POST['parent_id'] = null;
    }
}

$canSave = TRUE;
$canDelete = TRUE;

$itemControl = new ITEMCONTROL($dbo, $item,
    [],
    [],
    [],
    "content_category.php?id=" . $id,
    $canSave,
    $canDelete);

$fields = [
    ["id", "ID", "ID"],
    ["account_id", "hidden", ""],
    ["property_id", "hidden", ""],
    ["parent_id", "combobox", "Parent"],
    ["name", "text", "Name"],
    ["slug", "text", "Slug"],
    ["description", "textarea", "Description"],
    ["status", "combobox", "Status"],
    ["created_at", "hidden", ""],
    ["updated_at", "hidden", ""],
];

$itemControl->setFields($fields);

$itemControl->setFieldAttr("parent_id", [
    "SQL" => "SELECT id, name FROM content_categories WHERE account_id = " . (int)$current_account_id
        . " AND property_id = " . (int)$current_property_id
        . " AND id <> " . (int)$id
        . " ORDER BY name",
    "ID-FIELD" => "id",
    "DESC-FIELD" => "name",
    "READONLY" => "",
]);

$itemControl->setFieldAttr("status", [
    "SQL" => "",
    "ID-FIELD" => "id",
    "DESC-FIELD" => "description",
    "READONLY" => "",
    "RS" => [
        ["id" => "active", "description" => "active"],
        ["id" => "inactive", "description" => "inactive"],
    ]
]);

$saveRes = $itemControl->SaveItem($_POST);
$delRes = $itemControl->DeleteItem($_GET);

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <title><?php echo app::$project_name; ?> - Content category</title>
    <?php include "_head.php"; ?>
    <style>
        body { background:#fff; }
        textarea.form-control { min-height:120px; }
    </style>
</head>
<body>
    <div class="padding-20">
        <h1>Content category</h1>
        <p style="color:#52606d;">Property: <?php echo htmlspecialchars($current_property_name, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php $itemControl->ViewItem($saveRes, $delRes); ?>
    </div>

    <?php include "blocks/footer.php"; ?>
</body>
</html>
