<?php

require_once('php/config.php');
require_once('php/db.php');
require_once('php/dataobjects.php');
require_once('php/utils.php');
require_once('php/start.php');
require_once('php/session.php');
require_once('php/controls.php');

publisher_require_permission('settings');

$translations = [
    'en' => [
        'page_title' => 'Setting',
        'field_id' => 'ID',
        'field_code' => 'Code',
        'field_title' => 'Title',
        'field_value' => 'Value',
        'field_type' => 'Field type',
    ],
    'gr' => [
        'page_title' => 'Ρύθμιση',
        'field_id' => 'ID',
        'field_code' => 'Κωδικός',
        'field_title' => 'Τίτλος',
        'field_value' => 'Τιμή',
        'field_type' => 'Τύπος πεδίου',
    ],
];

$translation = $translations[$lang] ?? $translations['gr'];
$accountId = (int)$current_account_id;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $settingRows = $dbo->getRS(
        'SELECT id FROM settings WHERE id = ? AND account_id = ? LIMIT 1',
        [$id, $accountId]
    );
    if (!$settingRows) {
        http_response_code(403);
        die('Access denied');
    }
}

$settingTypes = [
    ['id' => 1, 'description' => 'Textarea'],
    ['id' => 2, 'description' => 'Text'],
    ['id' => 3, 'description' => 'Rich text'],
    ['id' => 4, 'description' => 'File'],
];

function publisher_setting_field_type($sType) {
    switch ((int)$sType) {
        case 2:
            return 'text';
        case 3:
            return 'richtextbox';
        case 4:
            return 'filecontrol';
        case 1:
        default:
            return 'textarea';
    }
}

$item = new settings($dbo, $id);

if ($id === 0) {
    $item->account_id($accountId);
    $item->date_modified(date('YmdHis'));
    $item->s_type(1);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_POST['account_id'] = $accountId;
    $_POST['date_modified'] = date('YmdHis');
}

$postedSType = isset($_POST['s_type']) ? (int)$_POST['s_type'] : null;
$currentSType = $postedSType ?: (int)($item->s_type() ?: 1);
$valueFieldType = publisher_setting_field_type($currentSType);

$canSave = true;
$canDelete = true;

$itemControl = new ITEMCONTROL(
    $dbo,
    $item,
    [],
    [],
    [],
    'setting.php?id=' . $id,
    $canSave,
    $canDelete
);

$fields = [
    ['id', 'ID', $translation['field_id']],
    ['account_id', 'hidden', ''],
    ['key_code', 'text', $translation['field_code']],
    ['title', 'text', $translation['field_title']],
    ['key_value', $valueFieldType, $translation['field_value']],
    ['s_type', 'combobox', $translation['field_type']],
    ['date_modified', 'hidden', ''],
];

$itemControl->setFields($fields);

$itemControl->setFieldAttr('s_type', [
    'SQL' => '',
    'ID-FIELD' => 'id',
    'DESC-FIELD' => 'description',
    'READONLY' => '',
    'RS' => $settingTypes,
]);

if ($valueFieldType === 'filecontrol') {
    $itemControl->setFieldAttr('key_value', [
        'BASEURL' => app::$host,
        'INIT-FOLDER' => 'media/settings/account-' . $accountId . '/',
        'SCRIPT-FOLDER-DEPTH' => 1,
        'WIDTH' => 150,
        'HEIGHT' => 150,
    ]);
}

$saveRes = $itemControl->SaveItem($_POST);
$delRes = $itemControl->DeleteItem($_GET);

?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="utf-8">
    <title><?php echo app::$project_name; ?> - <?php echo htmlspecialchars($translation['page_title'], ENT_QUOTES, 'UTF-8'); ?></title>
    <?php include "_head.php"; ?>
    <?php if (file_exists(__DIR__ . '/_popup_script.php')) { include "_popup_script.php"; } ?>
    <style>
        body { background:#fff; }
        textarea.form-control { min-height:140px; }
    </style>
</head>
<body>
    <div class="padding-20">
        <?php $itemControl->ViewItem($saveRes, $delRes, 'item-form', 'post', $lang); ?>
    </div>

    <?php include "blocks/footer.php"; ?>
</body>
</html>
