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

$accountId = (int)$current_account_id;
$propertyId = (int)$current_property_id;

$list = new LISTCONTROL($dbo,
    "SELECT * FROM distribution_channels WHERE account_id = {$accountId} AND property_id = {$propertyId}",
    [],
    [],
    [],
    "distribution_channels.php",
    "distribution_channel.php",
    "Distribution channel",
    TRUE,
    TRUE);

$fields = [
    ["id", "text", "ID"],
    ["name", "text", "Name"],
    ["type", "text", "Type"],
    ["status", "text", "Status"],
    ["updated_at", "text", "Updated"],
];

$list->setFields($fields);
$list->SearchList($_GET, TRUE, FALSE, "name", 1000);

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <title><?php echo app::$project_name; ?> - Distribution channels</title>
    <?php include "_head.php"; ?>
    <script>
        function refresh() {
            window.location.href = "distribution_channels.php";
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
        <h1>Distribution channels</h1>
        <div class="property-context">
            Property: <strong><?php echo htmlspecialchars($current_property_name, ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>
        <?php $list->ViewList("Open", 50, 1200, 760); ?>
    </div>
    <?php include "blocks/footer.php"; ?>
</body>
</html>
