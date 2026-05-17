<?php

// ini_set('display_errors',1); 
// error_reporting(E_ALL);

require_once('php/config.php');
require_once('php/db.php');
require_once('php/dataobjects.php');
require_once('php/utils.php');
require_once('php/start.php');
require_once('php/session.php');
require_once('php/controls.php');
require_once('php/publishing.php');

publisher_require_permission('content');
publisher_require_property();

$accountId = (int)$current_account_id;
$propertyId = (int)$current_property_id;
$errors = [];
$success = '';
$contentItemStatusOptions = ['draft', 'review', 'approved', 'published', 'archived'];

function content_items_selected_ids($post) {
    $ids = [];
    foreach ($post as $key => $value) {
        if (strpos((string)$key, 'chkRow') === 0 && (int)$value > 0) {
            $ids[] = (int)$value;
        }
    }
    return array_values(array_unique($ids));
}

function content_items_media_url($filePath, $externalUrl) {
    $url = trim((string)($filePath ?: $externalUrl));
    if ($url === '') {
        return '';
    }
    $url = str_replace('\\', '/', $url);
    if (stripos($url, 'http') === 0) {
        return $url;
    }
    $marker = '/PUBLISHER/';
    $pos = strpos($url, $marker);
    if ($pos !== false) {
        return substr($url, $pos + strlen($marker));
    }
    return ltrim($url, '/');
}

$wordpressChannels = $dbo->getRS(
    'SELECT id, name FROM distribution_channels WHERE account_id = ? AND property_id = ? AND type = ? AND status = ? ORDER BY name',
    [$accountId, $propertyId, 'wordpress', 'active']
) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_update_status') {
    $selectedIds = content_items_selected_ids($_POST);
    $newStatus = trim((string)($_POST['bulk_status'] ?? ''));

    if (!$selectedIds) {
        $errors[] = 'Please select at least one content item.';
    } elseif (!in_array($newStatus, $contentItemStatusOptions, true)) {
        $errors[] = 'Please select a valid status.';
    } else {
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $now = date('Y-m-d H:i:s');
        $params = array_merge([$newStatus, $now, $newStatus, $now], $selectedIds, [$accountId, $propertyId]);
        $dbo->execSQL(
            "UPDATE content_items
             SET status = ?, updated_at = ?, published_at = IF(? = 'published' AND published_at IS NULL, ?, published_at)
             WHERE id IN ({$placeholders}) AND account_id = ? AND property_id = ?",
            $params
        );
        $success = count($selectedIds) . ' content item(s) updated.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_publish_wordpress') {
    $selectedIds = content_items_selected_ids($_POST);
    $channelId = (int)($_POST['wordpress_channel_id'] ?? 0);

    if (!$selectedIds) {
        $errors[] = 'Please select at least one content item.';
    } elseif ($channelId <= 0) {
        $errors[] = 'Please select a WordPress channel.';
    } else {
        $channelRows = $dbo->getRS(
            'SELECT id FROM distribution_channels WHERE id = ? AND account_id = ? AND property_id = ? AND type = ? AND status = ? LIMIT 1',
            [$channelId, $accountId, $propertyId, 'wordpress', 'active']
        );
        if (!$channelRows) {
            $errors[] = 'WordPress channel not found or inactive.';
        } else {
            $publishedCount = 0;
            foreach ($selectedIds as $contentItemId) {
                try {
                    publisher_publish_content_item($dbo, $contentItemId, $channelId, $accountId, $propertyId);
                    $publishedCount++;
                } catch (Exception $ex) {
                    $errors[] = 'Content item #' . $contentItemId . ': ' . $ex->getMessage();
                }
            }
            if ($publishedCount > 0) {
                $success = $publishedCount . ' content item(s) published to WordPress.';
            }
        }
    }
}

$canAdd = TRUE;
$canView = TRUE;

$searchDefaults = [
    'search_title' => '',
    'search_category_id' => 0,
    'search_date_d1' => '',
    'search_date_d2' => '',
    'search_status' => 0,
];
$_GET = array_merge($searchDefaults, $_GET);
if (!in_array((string)$_GET['search_status'], array_merge(['0'], $contentItemStatusOptions), true)) {
    $_GET['search_status'] = 0;
}

$searchGet = $_GET;
$searchGet['search_category_id'] = (int)$searchGet['search_category_id'];
$searchGet['search_title'] = trim((string)$searchGet['search_title']) !== ''
    ? $dbo->getConn()->quote('%' . trim((string)$searchGet['search_title']) . '%')
    : '';

$list = new LISTCONTROL($dbo,
    "SELECT ci.id, ci.title, ci.slug, ci.status, ci.language, ci.published_at, ci.updated_at,
            '' AS category_names,
            ci.source_idea_id,
            '' AS source_idea_link,
            u.name AS created_by_name,
            '' AS media_file_path,
            '' AS media_external_url,
            '' AS media_preview
     FROM content_items ci
     LEFT JOIN users u ON u.id = ci.created_by
     WHERE ci.account_id = {$accountId} AND ci.property_id = {$propertyId}",
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
    ["category_names", "text", "Category"],
    ["source_idea_link", "text", "Source idea"],
    ["status", "text", "Status"],
    ["language", "text", "Language"],
    ["media_preview", "text", "Media"],
    ["created_by_name", "text", "Created by"],
    ["updated_at", "text", "Updated"],
];

$list->setFields($fields);
$list->setSearch(
    ["search_title", "search_category_id", "search_date", "search_status"],
    ["text", "combobox", "datefromto", "combobox"],
    ["Title", "Category", "Date", "Status"]
);
$list->setSearchFieldAttr("search_title", [
    "SEARCH-TYPE" => "ANY",
    "READONLY" => "",
    "CRITERIA" => " AND ci.title LIKE [val] ",
]);
$list->setSearchFieldAttr("search_category_id", [
    "SQL" => "SELECT id, name FROM content_categories WHERE account_id = {$accountId} AND property_id = {$propertyId} ORDER BY name",
    "ID-FIELD" => "id",
    "DESC-FIELD" => "name",
    "READONLY" => "",
    "CRITERIA" => " AND EXISTS (SELECT 1 FROM content_item_categories cic_search WHERE cic_search.content_item_id = ci.id AND cic_search.category_id = [val]) ",
]);
$list->setSearchFieldAttr("search_date", [
    "READONLY" => "",
    "CRITERIA" => " AND (ci.created_at >= '[val1]' AND ci.created_at <= '[val2]') ",
]);
$list->setSearchFieldAttr("search_status", [
    "SQL" => "",
    "ID-FIELD" => "id",
    "DESC-FIELD" => "description",
    "READONLY" => "",
    "RS" => array_map(function($status) {
        return ["id" => $status, "description" => $status];
    }, $contentItemStatusOptions),
    "CRITERIA" => " AND ci.status = '[val]' ",
]);
$list->set_select("Select");
$list->SearchList($searchGet, TRUE, TRUE, "ci.updated_at DESC", 50);

$rs = $list->getRS();
if ($rs) {
    $contentItemIds = array_map(function($row) {
        return (int)$row['id'];
    }, $rs);
    $contentItemIds = array_values(array_filter(array_unique($contentItemIds)));
    $categoryMap = [];
    $mediaMap = [];
    if ($contentItemIds) {
        $placeholders = implode(',', array_fill(0, count($contentItemIds), '?'));
        $categoryRows = $dbo->getRS(
            "SELECT cic.content_item_id, GROUP_CONCAT(DISTINCT cc.name ORDER BY cc.name SEPARATOR ', ') AS category_names
             FROM content_item_categories cic
             INNER JOIN content_categories cc ON cc.id = cic.category_id
             WHERE cic.content_item_id IN ({$placeholders})
               AND cc.account_id = ?
               AND cc.property_id = ?
             GROUP BY cic.content_item_id",
            array_merge($contentItemIds, [$accountId, $propertyId])
        ) ?: [];
        foreach ($categoryRows as $categoryRow) {
            $categoryMap[(int)$categoryRow['content_item_id']] = (string)$categoryRow['category_names'];
        }

        $mediaRows = $dbo->getRS(
            "SELECT ma.content_item_id, ma.file_path, ma.external_url
             FROM media_assets ma
             INNER JOIN (
                SELECT content_item_id, MAX(id) AS id
                FROM media_assets
                WHERE content_item_id IN ({$placeholders})
                  AND account_id = ?
                  AND property_id = ?
                  AND COALESCE(file_path, external_url, '') <> ''
                GROUP BY content_item_id
             ) latest_media ON latest_media.id = ma.id",
            array_merge($contentItemIds, [$accountId, $propertyId])
        ) ?: [];
        foreach ($mediaRows as $mediaRow) {
            $mediaMap[(int)$mediaRow['content_item_id']] = $mediaRow;
        }
    }
    for ($i = 0; $i < count($rs); $i++) {
        $rs[$i]['category_names'] = $categoryMap[(int)$rs[$i]['id']] ?? '';
        $sourceIdeaId = (int)($rs[$i]['source_idea_id'] ?? 0);
        $rs[$i]['source_idea_link'] = $sourceIdeaId > 0
            ? '<a style="cursor:pointer" class="modalBtn" data-title="Content idea" data-href="content_idea.php?id=' . $sourceIdeaId . '&l=GR" data-height="780" data-width="1200">' . $sourceIdeaId . '</a>'
            : '';
        $mediaRow = $mediaMap[(int)$rs[$i]['id']] ?? [];
        $mediaUrl = content_items_media_url($mediaRow['file_path'] ?? '', $mediaRow['external_url'] ?? '');
        $rs[$i]['media_preview'] = $mediaUrl !== ''
            ? '<a href="' . htmlspecialchars($mediaUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank"><img src="' . htmlspecialchars($mediaUrl, ENT_QUOTES, 'UTF-8') . '" class="content-item-thumb" alt="image"></a>'
            : '';
    }
    $list->setRS($rs);
}

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

        function submitContentItemsAction(actionName) {
            document.getElementById("content-items-action").value = actionName;
            return true;
        }
    </script>
    <style>
        #grid { max-width:1300px; }
        .property-context { color:#52606d; margin-bottom:18px; }
        .batch-actions { margin:0 0 14px; display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; }
        .batch-actions .action-field { min-width:220px; }
        .message-list { margin:0; padding-left:18px; }
        .content-item-thumb { width:70px; height:44px; object-fit:cover; border:1px solid #d9e2ec; border-radius:4px; background:#eef2f7; }

        #search_date_d1, #search_date_d2 {
            width:45%;
            display:inline-block;
        }
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

        <?php if ($errors) { ?>
            <div class="alert alert-danger"><ul class="message-list"><?php foreach ($errors as $error) { ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php } ?></ul></div>
        <?php } ?>
        <?php if ($success) { ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php } ?>

        <?php
        $list->searchForm();
        $list->setSearch([], [], []);
        ?>

        <form method="post" id="content-items-form">
            <input type="hidden" name="action" id="content-items-action" value="bulk_update_status">
            <div class="batch-actions">
                <div class="action-field">
                    <label for="bulk_status">Bulk status</label>
                    <select class="form-control" id="bulk_status" name="bulk_status">
                        <?php foreach ($contentItemStatusOptions as $statusOption) { ?>
                            <option value="<?php echo htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <button class="btn btn-default" type="submit" onclick="return submitContentItemsAction('bulk_update_status');">Update status</button>

                <div class="action-field">
                    <label for="wordpress_channel_id">WordPress channel</label>
                    <select class="form-control" id="wordpress_channel_id" name="wordpress_channel_id" <?php echo $wordpressChannels ? '' : 'disabled'; ?>>
                        <?php if (!$wordpressChannels) { ?>
                            <option value="">No active WordPress channel</option>
                        <?php } ?>
                        <?php foreach ($wordpressChannels as $channel) { ?>
                            <option value="<?php echo (int)$channel['id']; ?>"><?php echo htmlspecialchars($channel['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <button class="btn btn-primary" type="submit" <?php echo $wordpressChannels ? '' : 'disabled'; ?> onclick="return submitContentItemsAction('bulk_publish_wordpress');">Publish to WordPress</button>
            </div>
            <?php $list->ViewList("Open", 50, 1200, 780); ?>
        </form>
    </div>

    <?php include "blocks/footer.php"; ?>
</body>
</html>
