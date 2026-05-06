<?php

require_once('php/config.php');
require_once('php/db.php');
require_once('php/dataobjects.php');
require_once('php/utils.php');
require_once('php/start.php');
require_once('php/session.php');
require_once('php/controls.php');

publisher_require_permission('content_templates');
publisher_require_property();

$accountId = (int)$current_account_id;
$propertyId = (int)$current_property_id;

$list = new LISTCONTROL($dbo,
    "SELECT ct.id, ct.name, ctype.name AS content_type_name, ct.is_default, ct.status, ct.updated_at
     FROM content_templates ct
     LEFT JOIN content_types ctype ON ctype.id = ct.content_type_id
     WHERE ct.account_id = {$accountId} AND (ct.property_id = {$propertyId} OR ct.property_id IS NULL)",
    [],
    [],
    [],
    "content_templates.php",
    "context_template.php",
    "Content template",
    TRUE,
    TRUE);

$fields = [
    ["id", "text", "ID"],
    ["name", "text", "Name"],
    ["content_type_name", "text", "Content type"],
    ["is_default", "text", "Default"],
    ["status", "text", "Status"],
    ["updated_at", "text", "Updated"],
];

$list->setFields($fields);
$list->SearchList($_GET, TRUE, FALSE, "ct.name", 1000);

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <title><?php echo app::$project_name; ?> - Content templates</title>
    <?php include "_head.php"; ?>
    <script>
        function refresh() {
            window.location.href = "content_templates.php";
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
        <h1>Content templates</h1>
        <div class="property-context">
            Property: <strong><?php echo htmlspecialchars($current_property_name, ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>

        <?php $list->ViewList("Open", 50, 1200, 780); ?>
    </div>

    <?php include "blocks/footer.php"; ?>
</body>
</html>
