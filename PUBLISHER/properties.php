<?php

require_once('php/config.php');
require_once('php/db.php');
require_once('php/dataobjects.php');
require_once('php/utils.php');
require_once('php/start.php');
require_once('php/session.php');
require_once('php/controls.php');

publisher_require_permission('properties');

$message = '';

if (isset($_GET['selected'])) {
    $message = 'Property selected.';
}
if (isset($_GET['deleted'])) {
    $message = 'Property deleted.';
}

$properties = $dbo->getRS(
    'SELECT * FROM properties WHERE account_id = ? ORDER BY name',
    [$current_account_id]
);

$typeLabels = [
    'website' => 'Website',
    'facebook_page' => 'Facebook Page',
    'instagram_account' => 'Instagram Account',
    'linkedin_page' => 'LinkedIn Page',
    'newsletter' => 'Newsletter',
    'other' => 'Other',
];

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <title><?php echo app::$project_name; ?> - Properties</title>
    <?php include "_head.php"; ?>
    <script>
        function refresh() {
            window.location.href = "properties.php";
        }
    </script>
    <style>
        .page-head {
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:16px;
            margin-bottom:20px;
        }
        .properties-grid {
            display:grid;
            grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));
            gap:16px;
        }
        .property-card {
            background:#fff;
            border:1px solid #d9e2ec;
            border-radius:8px;
            padding:18px;
            min-height:190px;
            display:flex;
            flex-direction:column;
            justify-content:space-between;
            box-shadow:0 8px 22px rgba(15, 23, 42, .06);
        }
        .property-card h3 {
            margin:0 0 8px;
            font-size:20px;
        }
        .property-meta {
            color:#52606d;
            margin-bottom:6px;
            word-break:break-word;
        }
        .property-badges {
            display:flex;
            gap:8px;
            flex-wrap:wrap;
            margin:12px 0;
        }
        .property-badge {
            display:inline-block;
            padding:4px 8px;
            border-radius:14px;
            background:#eef2f7;
            color:#243b53;
            font-size:12px;
            text-transform:capitalize;
        }
        .property-badge.active { background:#e3fcef; color:#0b6b3a; }
        .property-badge.inactive { background:#ffe3e3; color:#9b1c1c; }
        .empty-state {
            background:#fff;
            border:1px dashed #bcccdc;
            border-radius:8px;
            padding:28px;
            text-align:center;
            color:#52606d;
        }
    </style>
</head>
<body>
    <?php include "blocks/header.php"; ?>

    <div class="padding-20">
        <div class="page-head">
            <div>
                <h1>Properties</h1>
                <div class="property-meta"><?php echo htmlspecialchars($current_account_name, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <a class="btn btn-primary" href="property.php?id=0">New property</a>
        </div>

        <?php if ($message) { ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php } ?>

        <?php if (!$properties) { ?>
            <div class="empty-state">
                <p>No properties yet.</p>
                <a class="btn btn-primary" href="property.php?id=0">Create first property</a>
            </div>
        <?php } else { ?>
            <div class="properties-grid">
                <?php foreach ($properties as $property) { ?>
                    <?php $isCurrentProperty = (int)$property['id'] === (int)$current_property_id; ?>
                    <div class="property-card" style="<?php echo $isCurrentProperty ? 'border-color:#185adb;' : ''; ?>">
                        <div>
                            <h3><?php echo htmlspecialchars($property['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <div class="property-meta"><?php echo htmlspecialchars($property['primary_url'] ?: 'No URL', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="property-badges">
                                <span class="property-badge"><?php echo htmlspecialchars($typeLabels[$property['type']] ?? $property['type'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="property-badge <?php echo htmlspecialchars($property['status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($property['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="property-badge"><?php echo htmlspecialchars($property['default_language'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($isCurrentProperty) { ?>
                                    <span class="property-badge active">In use</span>
                                <?php } ?>
                            </div>
                            <div class="property-meta">Timezone: <?php echo htmlspecialchars($property['timezone'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="property-meta">Updated: <?php echo htmlspecialchars((string)$property['updated_at'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div>
                            <?php if (!$isCurrentProperty && $property['status'] === 'active') { ?>
                                <form method="post" action="use-property.php" style="display:inline;">
                                    <input type="hidden" name="property_id" value="<?php echo (int)$property['id']; ?>">
                                    <button class="btn btn-primary" type="submit">Use</button>
                                </form>
                            <?php } ?>
                            <a class="btn btn-default" href="property.php?id=<?php echo (int)$property['id']; ?>">Manage</a>
                            <?php if ($isCurrentProperty) { ?>
                                <a class="btn btn-default" href="content_categories.php">Categories</a>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>

    <?php include "blocks/footer.php"; ?>
</body>
</html>
