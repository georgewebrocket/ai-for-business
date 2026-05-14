<?php

require_once('php/config.php');
require_once('php/db.php');
require_once('php/dataobjects.php');
require_once('php/utils.php');
require_once('php/start.php');
require_once('php/session.php');
require_once('php/controls.php');

publisher_require_permission('content');
publisher_require_property();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $ideaRows = $dbo->getRS(
        'SELECT id FROM content_ideas WHERE id = ? AND account_id = ? AND property_id = ? LIMIT 1',
        [$id, $current_account_id, $current_property_id]
    );
    if (!$ideaRows) {
        http_response_code(403);
        die('Access denied');
    }
}

$item = new content_ideas($dbo, $id);

if ($id == 0) {
    $item->account_id($current_account_id);
    $item->property_id($current_property_id);
    $item->status('suggested');
    $item->created_by($userid);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $now = date('Y-m-d H:i:s');
    $_POST['account_id'] = $current_account_id;
    $_POST['property_id'] = $current_property_id;
    $_POST['updated_at'] = $now;

    if ($id == 0 || !$item->created_at()) {
        $_POST['created_at'] = $now;
        $_POST['created_by'] = $userid > 0 ? $userid : null;
    } else {
        $_POST['created_at'] = $item->created_at();
        $_POST['created_by'] = $item->created_by();
    }

    foreach (['content_type_id', 'category_id', 'content_item_id'] as $nullableField) {
        if (isset($_POST[$nullableField]) && (int)$_POST[$nullableField] === 0) {
            $_POST[$nullableField] = null;
        }
    }

    if (trim($_POST['similarity_score'] ?? '') === '') {
        $_POST['similarity_score'] = null;
    }
}

$canSave = TRUE;
$canDelete = TRUE;

$itemControl = new ITEMCONTROL($dbo, $item,
    [],
    [],
    [],
    "content_idea.php?id=" . $id,
    $canSave,
    $canDelete);

$fields = [
    ["id", "ID", "ID"],
    ["account_id", "hidden", ""],
    ["property_id", "hidden", ""],
    ["content_type_id", "combobox", "Content type"],
    ["category_id", "combobox", "Category"],
    ["title", "text", "Title"],
    ["summary", "textarea", "Summary"],
    ["tags", "text", "Tags"],
    ["sections", "textarea", "Sections"],
    ["tone", "text", "Tone"],
    ["language", "text", "Language"],
    ["instructions", "textarea", "Instructions"],
    ["image_prompt", "textarea", "Image prompt"],
    ["prompt", "hidden-area", ""],
    ["ai_response_json", "hidden-area", ""],
    ["similarity_score", "text", "Similarity score"],
    ["status", "combobox", "Status"],
    ["created_by", "hidden", ""],
    ["content_item_id", "combobox", "Content item"],
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

$itemControl->setFieldAttr("category_id", [
    "SQL" => "SELECT id, name FROM content_categories WHERE account_id = " . (int)$current_account_id
        . " AND property_id = " . (int)$current_property_id
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
        ["id" => "suggested", "description" => "suggested"],
        ["id" => "accepted", "description" => "accepted"],
        ["id" => "rejected", "description" => "rejected"],
        ["id" => "converted", "description" => "converted"],
    ]
]);

$itemControl->setFieldAttr("content_item_id", [
    "SQL" => "SELECT id, title FROM content_items WHERE account_id = " . (int)$current_account_id
        . " AND property_id = " . (int)$current_property_id
        . " ORDER BY title",
    "ID-FIELD" => "id",
    "DESC-FIELD" => "title",
    "READONLY" => "",
]);

$saveRes = $itemControl->SaveItem($_POST);
$delRes = $itemControl->DeleteItem($_GET);

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <title><?php echo app::$project_name; ?> - Content idea</title>
    <?php include "_head.php"; ?>
    <style>
        body { background:#fff; }
        textarea.form-control { min-height:140px; }
    </style>
</head>
<body>
    <div class="padding-20">
        <h1>Content idea</h1>
        <p style="color:#52606d;">Property: <?php echo htmlspecialchars($current_property_name, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php $itemControl->ViewItem($saveRes, $delRes); ?>
    </div>

    <?php include "blocks/footer.php"; ?>
</body>
</html>
