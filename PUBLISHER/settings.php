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
        'page_title' => 'Settings',
        'list_item' => 'Setting',
        'field_id' => 'ID',
        'field_code' => 'Code',
        'field_title' => 'Title',
        'field_value' => 'Value',
        'field_type' => 'Type',
        'field_date_modified' => 'Modified',
        'view_edit' => 'View/Edit',
    ],
    'gr' => [
        'page_title' => 'Ρυθμίσεις',
        'list_item' => 'Ρύθμιση',
        'field_id' => 'ID',
        'field_code' => 'Κωδικός',
        'field_title' => 'Τίτλος',
        'field_value' => 'Τιμή',
        'field_type' => 'Τύπος',
        'field_date_modified' => 'Τροποποιήθηκε',
        'view_edit' => 'Προβολή/Επεξεργασία',
    ],
];

$translation = $translations[$lang] ?? $translations['gr'];
$accountId = (int)$current_account_id;
$canAdd = true;
$canView = true;

$settingTypeLabels = [
    1 => 'Textarea',
    2 => 'Text',
    3 => 'Rich text',
    4 => 'File',
];

$list = new LISTCONTROL(
    $dbo,
    "SELECT id, key_code, title, key_value, s_type, date_modified
     FROM settings
     WHERE account_id = {$accountId}
     ORDER BY title",
    [],
    [],
    [],
    'settings.php',
    'setting.php',
    $translation['list_item'],
    $canAdd,
    $canView
);

$fields = [
    ['id', 'text', $translation['field_id']],
    ['key_code', 'text', $translation['field_code']],
    ['title', 'text', $translation['field_title']],
    ['key_value', 'text', $translation['field_value']],
    ['s_type', 'text', $translation['field_type']],
    ['date_modified', 'text', $translation['field_date_modified']],
];

$list->setFields($fields);
$list->setSearch([], [], []);
$list->SearchList($_GET, true, false, '', 1000);

$rs = $list->getRS();
if ($rs) {
    for ($i = 0; $i < count($rs); $i++) {
        if ($rs[$i]['key_code'] === 'ai-api-key') {
            $value = trim((string)$rs[$i]['key_value']) !== '' ? 'Configured' : 'Not configured';
        } elseif (strpos((string)$rs[$i]['key_code'], 'dashboard-user-') === 0) {
            $value = 'Dashboard layout';
        } else {
            $value = strip_tags((string)$rs[$i]['key_value']);
            $value = str_replace('&nbsp;', ' ', $value);
            if (function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > 40) {
                $value = trim(mb_substr($value, 0, 40, 'UTF-8')) . '...';
            } elseif (!function_exists('mb_strlen') && strlen($value) > 40) {
                $value = trim(substr($value, 0, 40)) . '...';
            }
        }
        $rs[$i]['key_value'] = $value;
        $rs[$i]['s_type'] = $settingTypeLabels[(int)$rs[$i]['s_type']] ?? $settingTypeLabels[1];
    }
}
$list->setRS($rs);

?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="utf-8">
    <title><?php echo app::$project_name; ?> - Settings</title>
    <?php include "_head.php"; ?>
    <style>
        #grid { max-width: 1000px; }
    </style>
    <?php $list->refreshScript(); ?>
</head>
<body>
    <?php include "blocks/header.php"; ?>

    <div class="padding-20">
        <h1><?php echo htmlspecialchars($translation['page_title'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php $list->ViewList($translation['view_edit'], 50, 1000, 750, 'TOP', 'grid'); ?>
    </div>

    <?php include "blocks/footer.php"; ?>
</body>
</html>
