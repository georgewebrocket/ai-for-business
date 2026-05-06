<?php

require_once('php/config.php');
require_once('php/db.php');
require_once('php/dataobjects.php');
require_once('php/utils.php');
require_once('php/start.php');
require_once('php/session.php');
require_once('php/controls.php');

publisher_require_permission('image_styles');
publisher_require_property();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $styleRows = $dbo->getRS(
        'SELECT id FROM image_styles WHERE id = ? AND account_id = ? AND property_id = ? LIMIT 1',
        [$id, $current_account_id, $current_property_id]
    );
    if (!$styleRows) {
        http_response_code(403);
        die('Access denied');
    }
}

$item = new image_styles($dbo, $id);

if ($id == 0) {
    $item->account_id($current_account_id);
    $item->property_id($current_property_id);
    $item->active(1);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $now = date('Y-m-d H:i:s');
    $_POST['account_id'] = $current_account_id;
    $_POST['property_id'] = $current_property_id;
    $_POST['updated_at'] = $now;
    if ($id == 0 || !$item->created_at()) {
        $_POST['created_at'] = $now;
    } else {
        $_POST['created_at'] = $item->created_at();
    }
}

$canSave = TRUE;
$canDelete = TRUE;

$itemControl = new ITEMCONTROL($dbo, $item,
    [],
    [],
    [],
    "image_style.php?id=" . $id,
    $canSave,
    $canDelete);

$fields = [
    ["id", "ID", "ID"],
    ["property_id", "hidden", ""],
    ["name", "text", "Name"],
    ["description", "textarea", "Description"],
    ["active", "checkbox", "Active"],
    ["account_id", "hidden", ""],
    ["created_at", "hidden", ""],
    ["updated_at", "hidden", ""],
];

$itemControl->setFields($fields);

$saveRes = $itemControl->SaveItem($_POST);
$delRes = $itemControl->DeleteItem($_GET);

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <title><?php echo app::$project_name; ?> - Image style</title>
    <?php include "_head.php"; ?>
    <style>
        body { background:#fff; }
        textarea.form-control { min-height:180px; }
    </style>
</head>
<body>
    <div class="padding-20">
        <h1>Image style</h1>
        <p style="color:#52606d;">Property: <?php echo htmlspecialchars($current_property_name, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php $itemControl->ViewItem($saveRes, $delRes); ?>
    </div>

    <?php include "blocks/footer.php"; ?>
</body>
</html>
