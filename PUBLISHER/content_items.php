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

$accountId = (int)$current_account_id;
$propertyId = (int)$current_property_id;

$canAdd = TRUE;
$canView = TRUE;

$mediaUrlExpr = "CASE
    WHEN COALESCE(media_thumb.file_path, media_thumb.external_url, '') = '' THEN ''
    WHEN COALESCE(media_thumb.file_path, media_thumb.external_url, '') LIKE 'http%' THEN COALESCE(media_thumb.file_path, media_thumb.external_url)
    WHEN REPLACE(COALESCE(media_thumb.file_path, media_thumb.external_url), '\\\\', '/') LIKE '%/PUBLISHER/%' THEN SUBSTRING_INDEX(REPLACE(COALESCE(media_thumb.file_path, media_thumb.external_url), '\\\\', '/'), '/PUBLISHER/', -1)
    ELSE COALESCE(media_thumb.file_path, media_thumb.external_url)
END";

$list = new LISTCONTROL($dbo,
    "SELECT ci.id, ci.title, ci.slug, ci.status, ci.language, ci.published_at, ci.updated_at,
            ctype.name AS content_type_name,
            source_idea.title AS source_idea_title,
            u.name AS created_by_name,
            media_thumb.file_path AS media_file_path,
            media_thumb.external_url AS media_external_url,
            {$mediaUrlExpr} AS media_url,
            CASE
                WHEN {$mediaUrlExpr} <> '' THEN
                    CONCAT('<a href=\"', {$mediaUrlExpr}, '\" target=\"_blank\"><img src=\"', {$mediaUrlExpr}, '\" class=\"content-item-thumb\" alt=\"image\"></a>')
                WHEN COUNT(DISTINCT ma.id) > 0 THEN COUNT(DISTINCT ma.id)
                ELSE ''
            END AS media_preview
     FROM content_items ci
     LEFT JOIN content_types ctype ON ctype.id = ci.content_type_id
     LEFT JOIN content_ideas source_idea ON source_idea.id = ci.source_idea_id
     LEFT JOIN users u ON u.id = ci.created_by
     LEFT JOIN media_assets ma ON ma.content_item_id = ci.id
     LEFT JOIN media_assets media_thumb ON media_thumb.id = (
        SELECT ma2.id
        FROM media_assets ma2
        WHERE ma2.content_item_id = ci.id
          AND COALESCE(ma2.file_path, ma2.external_url, '') <> ''
        ORDER BY ma2.created_at DESC, ma2.id DESC
        LIMIT 1
     )
     WHERE ci.account_id = {$accountId} AND ci.property_id = {$propertyId}
     GROUP BY ci.id, ci.title, ci.slug, ci.status, ci.language, ci.published_at, ci.updated_at,
              ctype.name, source_idea.title, u.name, media_thumb.file_path, media_thumb.external_url",
    [],
    [],
    [],
    "content_items.php",
    "content_item.php",
    "Content item",
    $canAdd,
    $canView);

$fields = [
    ["id", "text", "ID"],
    ["title", "text", "Title"],
    ["content_type_name", "text", "Content type"],
    ["source_idea_title", "text", "Source idea"],
    ["status", "text", "Status"],
    ["language", "text", "Language"],
    ["media_preview", "text", "Media"],
    ["created_by_name", "text", "Created by"],
    ["updated_at", "text", "Updated"],
];

$list->setFields($fields);
$list->SearchList($_GET, TRUE, FALSE, "ci.updated_at DESC", 1000);

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <title><?php echo app::$project_name; ?> - Content items</title>
    <?php include "_head.php"; ?>
    <script>
        function refresh() {
            window.location.href = "content_items.php";
        }
    </script>
    <style>
        #grid { max-width:1200px; }
        .property-context { color:#52606d; margin-bottom:18px; }
        .content-item-thumb { width:70px; height:44px; object-fit:cover; border:1px solid #d9e2ec; border-radius:4px; background:#eef2f7; }
    </style>
    <?php $list->refreshScript(); ?>
</head>
<body>
    <?php include "blocks/header.php"; ?>

    <div class="padding-20">
        <h1>Content items</h1>
        <div class="property-context">
            Property: <strong><?php echo htmlspecialchars($current_property_name, ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>

        <?php $list->ViewList("Open", 50, 1200, 780); ?>
    </div>

    <?php include "blocks/footer.php"; ?>
</body>
</html>
