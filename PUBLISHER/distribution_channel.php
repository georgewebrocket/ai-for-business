<?php

require_once('php/config.php');
require_once('php/db.php');
require_once('php/dataobjects.php');
require_once('php/utils.php');
require_once('php/start.php');
require_once('php/session.php');
require_once('php/controls.php');

publisher_require_permission('channels');
publisher_require_property();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $rows = $dbo->getRS(
        'SELECT id FROM distribution_channels WHERE id = ? AND account_id = ? AND property_id = ? LIMIT 1',
        [$id, $current_account_id, $current_property_id]
    );
    if (!$rows) {
        http_response_code(403);
        die('Access denied');
    }
}

$item = new distribution_channels($dbo, $id);

if ($id == 0) {
    $item->account_id($current_account_id);
    $item->property_id($current_property_id);
    $item->type('wordpress');
    $item->status('active');
    $item->credentials_json(json_encode([
        'site_url' => 'https://example.com',
        'username' => '',
        'application_password' => '',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $item->settings_json(json_encode([
        'default_status' => 'draft',
        'author_id' => null,
        'category_ids' => [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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

$itemControl = new ITEMCONTROL($dbo, $item,
    [],
    [],
    [],
    "distribution_channel.php?id=" . $id,
    TRUE,
    TRUE);

$fields = [
    ["id", "ID", "ID"],
    ["account_id", "hidden", ""],
    ["property_id", "hidden", ""],
    ["name", "text", "Name"],
    ["type", "combobox", "Type"],
    ["credentials_json", "textarea", "Credentials JSON"],
    ["settings_json", "textarea", "Settings JSON"],
    ["status", "combobox", "Status"],
    ["created_at", "hidden", ""],
    ["updated_at", "hidden", ""],
];

$itemControl->setFields($fields);

$itemControl->setFieldAttr("type", [
    "SQL" => "",
    "ID-FIELD" => "id",
    "DESC-FIELD" => "description",
    "READONLY" => "",
    "RS" => [
        ["id" => "wordpress", "description" => "WordPress"],
        ["id" => "facebook", "description" => "Facebook Page"],
    ]
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
    <title><?php echo app::$project_name; ?> - Distribution channel</title>
    <?php include "_head.php"; ?>
    <style>
        body { background:#fff; }
        textarea.form-control { min-height:180px; font-family:Consolas, monospace; }
        .hint { color:#52606d; margin-bottom:14px; }
    </style>
</head>
<body>
    <div class="padding-20">
        <h1>Distribution channel</h1>
        <p class="hint">WordPress credentials: site_url, username, application_password. WordPress author_id can be null to publish as the application-password user. Facebook credentials: page_id, page_access_token, graph_version.</p>
        <?php $itemControl->ViewItem($saveRes, $delRes); ?>
    </div>
    <?php include "blocks/footer.php"; ?>
</body>
</html>
