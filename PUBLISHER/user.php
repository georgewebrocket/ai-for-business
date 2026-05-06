<?php

require_once('php/config.php');
require_once('php/db.php');
require_once('php/dataobjects.php');
require_once('php/utils.php');
require_once('php/start.php');
require_once('php/session.php');
require_once('php/controls.php');

publisher_require_permission('users');

$t = [
    'ID' => ['en' => 'ID', 'gr' => 'ID'],
    'NAME' => ['en' => 'NAME', 'gr' => 'ΟΝΟΜΑ'],
    'EMAIL' => ['en' => 'EMAIL', 'gr' => 'EMAIL'],
    'PASSWORD' => ['en' => 'PASSWORD', 'gr' => 'ΚΩΔΙΚΟΣ'],
    'STATUS' => ['en' => 'STATUS', 'gr' => 'ΚΑΤΑΣΤΑΣΗ'],
];
$lang = $user_language;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$item = new users($dbo, $id);

$canSave = TRUE;
$canDelete = TRUE;

$itemControl = new ITEMCONTROL($dbo, $item,
    array(),
    array(),
    array(),
    "user.php",
    $canSave,
    $canDelete);

$fields = [
    ["id", "ID", func::tr('ID', $lang, $t)],
    ["name", "text", func::tr('NAME', $lang, $t)],
    ["email", "text", func::tr('EMAIL', $lang, $t)],
    ["password", "password", func::tr('PASSWORD', $lang, $t)],
    ["status", "combobox", func::tr('STATUS', $lang, $t)],
];

$itemControl->setFields($fields);
$itemControl->setFieldAttr("status", [
    "SQL" => "",
    "ID-FIELD" => "id",
    "DESC-FIELD" => "description",
    "READONLY" => "",
    "RS" => [
        ["id" => "active", "description" => "active"],
        ["id" => "inactive", "description" => "inactive"],
        ["id" => "blocked", "description" => "blocked"],
    ]
]);

$saveRes = $itemControl->SaveItem($_POST);
$delRes = $itemControl->DeleteItem($_GET);

$id = $id==0 ? $saveRes : $id;

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <title><?php echo app::$project_name; ?></title>

        <?php include "_head.php"; ?>
    </head>

    <body>
        <div class="padding-20">
            <?php $itemControl->ViewItem($saveRes, $delRes); ?>
        </div>

        <?php include "blocks/footer.php"; ?>
    </body>
</html>
