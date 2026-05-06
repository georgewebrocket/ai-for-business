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

$accountId = (int)$current_account_id;
$propertyId = (int)$current_property_id;

$canAdd = TRUE;
$canView = TRUE;

$list = new LISTCONTROL($dbo,
    "SELECT *,
        CASE WHEN active = 1 THEN 'Yes' ELSE 'No' END AS active_label
     FROM image_styles
     WHERE account_id = {$accountId} AND property_id = {$propertyId}",
    [],
    [],
    [],
    "image_styles.php",
    "image_style.php",
    "Image style",
    $canAdd,
    $canView);

$fields = [
    ["id", "text", "ID"],
    ["name", "text", "Name"],
    ["active_label", "text", "Active"],
    ["updated_at", "text", "Updated"],
];

$list->setFields($fields);
$list->SearchList($_GET, TRUE, FALSE, "name", 1000);

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <title><?php echo app::$project_name; ?> - Image styles</title>
    <?php include "_head.php"; ?>
    <script>
        function refresh() {
            window.location.href = "image_styles.php";
        }
    </script>
    <style>
        #grid { max-width:1200px; }
        .property-context { color:#52606d; margin-bottom:18px; }
    </style>
    <?php $list->refreshScript(); ?>
</head>
<body>
    <?php include "blocks/header.php"; ?>

    <div class="padding-20">
        <h1>Image styles</h1>
        <div class="property-context">
            Property: <strong><?php echo htmlspecialchars($current_property_name, ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>

        <?php $list->ViewList("Open", 50, 1200, 750); ?>
    </div>

    <?php include "blocks/footer.php"; ?>
</body>
</html>
