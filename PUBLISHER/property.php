<?php

require_once('php/config.php');
require_once('php/db.php');
require_once('php/dataobjects.php');
require_once('php/utils.php');
require_once('php/start.php');
require_once('php/session.php');
require_once('php/controls.php');

publisher_require_permission('properties');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];
$success = '';
$deleted = false;

$item = new properties($dbo, $id);

$propertyTypes = [
    'website' => 'Website',
    'facebook_page' => 'Facebook Page',
    'instagram_account' => 'Instagram Account',
    'linkedin_page' => 'LinkedIn Page',
    'newsletter' => 'Newsletter',
    'other' => 'Other',
];

$languageOptions = [
    'el' => 'Greek',
    'en' => 'English',
];

$statusOptions = [
    'active' => 'active',
    'inactive' => 'inactive',
];

$aiProfileRows = $dbo->getRS(
    'SELECT id, name FROM ai_profiles WHERE account_id = ? AND (property_id = ? OR property_id IS NULL) ORDER BY name',
    [$current_account_id, $id]
) ?: [];
$writingStyleRows = $dbo->getRS(
    'SELECT id, name FROM writing_styles WHERE account_id = ? AND (property_id = ? OR property_id IS NULL) ORDER BY name',
    [$current_account_id, $id]
) ?: [];
$templateRows = $dbo->getRS(
    'SELECT id, name FROM content_templates WHERE account_id = ? AND (property_id = ? OR property_id IS NULL) ORDER BY name',
    [$current_account_id, $id]
) ?: [];
$imageStyleRows = $dbo->getRS(
    'SELECT id, name FROM image_styles WHERE account_id = ? AND (property_id = ? OR property_id IS NULL) ORDER BY name',
    [$current_account_id, $id]
) ?: [];
$categoryRows = $dbo->getRS(
    'SELECT id, name FROM content_categories WHERE account_id = ? AND (property_id = ? OR property_id IS NULL) ORDER BY name',
    [$current_account_id, $id]
) ?: [];
$wordpressChannelRows = $id > 0 ? ($dbo->getRS(
    'SELECT id, name FROM distribution_channels WHERE account_id = ? AND property_id = ? AND type = ? AND status = ? ORDER BY name',
    [$current_account_id, $id, 'wordpress', 'active']
) ?: []) : [];

$settingsSelectOptions = [
    'default_ai_profile_id' => $aiProfileRows,
    'ai_profile_id' => $aiProfileRows,
    'default_writing_style_id' => $writingStyleRows,
    'writing_style_id' => $writingStyleRows,
    'default_template_id' => $templateRows,
    'content_template_id' => $templateRows,
    'template_id' => $templateRows,
    'default_image_style_id' => $imageStyleRows,
    'image_style_id' => $imageStyleRows,
    'default_category_id' => $categoryRows,
    'content_category_id' => $categoryRows,
    'category_id' => $categoryRows,
];

$defaultSettings = [
    'branding' => [
        'brand_name' => $item->name() ?: 'My Brand',
        'brand_description' => 'A short description of the brand.',
        'target_audience' => 'A description of the target audience.',
        'avoid_topics' => 'topics to avoid, separated by comma',
    ],
    'ai' => [
        'default_ai_profile_id' => 0,
        'default_text_model' => 'gpt-5.2',
        'default_image_model' => 'gpt-image-1.5',
        'default_writing_style_id' => 0,
        'default_template_id' => 0,
        'default_language' => 'el',
    ],        
    'social' => [
        'default_hashtags' => 'comma, separated, default, hashtags',
        'max_hashtags' => 3,
    ],
    'wordpress' => [
        'default_author_id' => 1,
    ],
    'image_generation' => [
        'enabled' => true,
        'provider' => 'openai',
        'default_style' => 'Photorealistic',
        'default_size' => '1792x1024',
    ],
    'create_content_ideas' => [
        'mode' => 'manual',
        'article_count' => 5,
        'period' => 'week',
    ],
    'content_generation' => [
        'mode' => 'manual',
        'article_count' => 1,
        'period' => 'day',
    ],
    'publishing' => [
        'mode' => 'manual',
        'distribution_channel_id' => 0,
        'channel_type' => 'wordpress',
        'wordpress_status' => 'draft',
    ],
    
];

function property_is_list_array($value) {
    return is_array($value) && (count($value) === 0 || array_keys($value) === range(0, count($value) - 1));
}

function property_title_from_key($key) {
    return ucwords(str_replace('_', ' ', (string)$key));
}

function property_settings_object_to_sections($settings) {
    $sections = [];
    foreach ($settings as $sectionKey => $sectionData) {
        $options = [];
        if (is_array($sectionData) && !property_is_list_array($sectionData)) {
            foreach ($sectionData as $optionKey => $optionValue) {
                $options[] = [
                    'title' => property_title_from_key($optionKey),
                    'key' => (string)$optionKey,
                    'value' => $optionValue,
                ];
            }
        }
        $sections[] = [
            'title' => property_title_from_key($sectionKey),
            'key' => (string)$sectionKey,
            'options' => $options,
        ];
    }
    return ['sections' => $sections];
}

function property_normalize_settings($json, &$errors) {
    $decoded = json_decode((string)$json, true);
    if (!is_array($decoded)) {
        $errors[] = 'Settings JSON must be a JSON object.';
        return null;
    }

    if (!isset($decoded['sections'])) {
        $decoded = property_settings_object_to_sections($decoded);
    }

    if (!isset($decoded['sections']) || !is_array($decoded['sections']) || !property_is_list_array($decoded['sections'])) {
        $errors[] = 'Settings JSON must contain a sections array.';
        return null;
    }

    $sectionKeys = [];
    $sections = [];
    foreach ($decoded['sections'] as $section) {
        if (!is_array($section)) {
            $errors[] = 'Each section must be a JSON object.';
            continue;
        }

        $title = trim((string)($section['title'] ?? ''));
        $key = trim((string)($section['key'] ?? ''));
        if ($key === '' || !preg_match('/^[a-z0-9_]+$/', $key)) {
            $errors[] = 'Each section needs a unique key with lowercase latin letters, numbers or underscore.';
            continue;
        }
        if ($title === '') {
            $errors[] = 'Section "' . $key . '" needs a title.';
        }
        if (isset($sectionKeys[$key])) {
            $errors[] = 'Duplicate section key "' . $key . '".';
        }
        $sectionKeys[$key] = true;

        $options = [];
        $optionKeys = [];
        $sectionOptions = $section['options'] ?? [];
        if (!is_array($sectionOptions) || !property_is_list_array($sectionOptions)) {
            $errors[] = 'Section "' . $key . '" must contain an options array.';
            $sectionOptions = [];
        }

        foreach ($sectionOptions as $option) {
            if (!is_array($option)) {
                $errors[] = 'Each option in section "' . $key . '" must be a JSON object.';
                continue;
            }
            $optionTitle = trim((string)($option['title'] ?? ''));
            $optionKey = trim((string)($option['key'] ?? ''));
            if ($optionKey === '' || !preg_match('/^[a-z0-9_]+$/', $optionKey)) {
                $errors[] = 'Each option in section "' . $key . '" needs a valid key.';
                continue;
            }
            if ($optionTitle === '') {
                $errors[] = 'Option "' . $optionKey . '" in section "' . $key . '" needs a title.';
            }
            if (isset($optionKeys[$optionKey])) {
                $errors[] = 'Duplicate option key "' . $optionKey . '" in section "' . $key . '".';
            }
            $optionKeys[$optionKey] = true;
            $options[] = [
                'title' => $optionTitle,
                'key' => $optionKey,
                'value' => $option['value'] ?? null,
            ];
        }

        $sections[] = [
            'title' => $title,
            'key' => $key,
            'options' => $options,
        ];
    }

    if (!$sections) {
        $errors[] = 'Add at least one settings section.';
    }

    return ['sections' => $sections];
}

if ($id > 0) {
    $propertyRows = $dbo->getRS(
        'SELECT id FROM properties WHERE id = ? AND account_id = ? LIMIT 1',
        [$id, $current_account_id]
    );
    if (!$propertyRows) {
        http_response_code(403);
        die('Access denied');
    }
}



if ($id == 0) {
    $item->account_id($current_account_id);
    $item->type('website');
    $item->default_language('el');
    $item->timezone('Europe/Athens');
    $item->settings_json(json_encode(property_settings_object_to_sections($defaultSettings), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $item->status('active');
}

if (isset($_GET['delete']) && $_GET['delete'] == 1 && $id > 0) {
    $item->Delete();
    $deleted = true;
    header('Location: properties.php?deleted=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$deleted) {
    $now = date('Y-m-d H:i:s');
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? 'website';
    $primaryUrl = trim($_POST['primary_url'] ?? '');
    $defaultLanguage = $_POST['default_language'] ?? 'el';
    $timezone = trim($_POST['timezone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $settingsJson = $_POST['settings_json'] ?? '';

    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if (!isset($propertyTypes[$type])) {
        $errors[] = 'Invalid type.';
    }
    if (!isset($languageOptions[$defaultLanguage])) {
        $errors[] = 'Invalid default language.';
    }
    if ($timezone === '') {
        $errors[] = 'Timezone is required.';
    }
    if (!isset($statusOptions[$status])) {
        $errors[] = 'Invalid status.';
    }

    $normalizedSettings = property_normalize_settings($settingsJson, $errors);

    $item->account_id($current_account_id);
    $item->name($name);
    $item->type($type);
    $item->primary_url($primaryUrl);
    $item->default_language($defaultLanguage);
    $item->timezone($timezone);
    $item->settings_json($settingsJson);
    $item->status($status);

    if (!$errors) {
        $item->settings_json(json_encode($normalizedSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $item->updated_at($now);
        if ($id == 0 || !$item->created_at()) {
            $item->created_at($now);
        }

        $saved = $item->Savedata();
        if ($saved !== false) {
            $id = (int)$item->get_id();
            $success = 'Property was saved.';
        } else {
            $errors[] = 'An error occured. Please try again';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <title><?php echo app::$project_name; ?> - Property</title>
    <?php include "_head.php"; ?>
    <style>
        .property-page { max-width:1180px; padding-bottom:50px; }
        .page-head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:18px; }
        .page-kicker { color:#52606d; margin-top:-8px; }
        .form-row { margin-bottom:12px; }
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .form-grid .wide { grid-column:1 / -1; }
        .builder-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin:0 0 12px; }
        .tab-pane { padding-top:18px; }
        .settings-section-card { border:1px solid #d9e2ec; border-radius:8px; padding:14px; margin-bottom:12px; background:#fff; box-shadow:0 6px 18px rgba(15,23,42,.05); }
        .settings-section-head { display:flex; justify-content:space-between; gap:12px; align-items:center; margin-bottom:12px; }
        .settings-section-title { font-weight:700; font-size:16px; }
        .settings-section-actions { display:flex; flex-wrap:wrap; gap:6px; }
        .settings-section-grid { display:grid; grid-template-columns:1fr; gap:12px; }
        .settings-options { margin-top:12px; }
        .settings-option-row { border:1px solid #edf2f7; border-radius:8px; padding:12px; margin-bottom:10px; background:#fbfdff; }
        .settings-option-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; align-items:start; }
        .settings-option-actions { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
        .operation-grid { display:grid; grid-template-columns:repeat(3, minmax(180px, 1fr)); gap:12px; align-items:end; }
        .operation-card { border:1px solid #d9e2ec; border-radius:8px; padding:16px; margin-bottom:14px; background:#fff; box-shadow:0 6px 18px rgba(15,23,42,.05); }
        .operation-card h3 { margin:0 0 12px; font-size:18px; }
        .operation-hint { color:#52606d; margin-top:8px; }
        .icon-btn { width:34px; height:30px; padding:5px 0; text-align:center; }
        .page-head .icon-btn { width:38px; height:34px; padding-top:7px; }
        .settings-empty { color:#52606d; padding:16px; border:1px dashed #bcccdc; border-radius:8px; background:#fbfdff; }
        .error-list { margin:0; padding-left:18px; }
        textarea.form-control { min-height:90px; font-family:Consolas, Monaco, monospace; }
        @media (max-width:800px) {
            .page-head { flex-direction:column; }
            .form-grid { grid-template-columns:1fr; }
            .settings-section-grid { grid-template-columns:1fr; }
            .settings-option-grid { grid-template-columns:1fr; }
            .operation-grid { grid-template-columns:1fr; }
            .settings-section-head { align-items:flex-start; flex-direction:column; }
        }
    </style>
</head>
<body>
    <?php include "blocks/header.php"; ?>

    <div class="padding-20 property-page">
        <div class="page-head">
            <div>
                <h1><?php echo $id > 0 ? htmlspecialchars((string)$item->name(), ENT_QUOTES, 'UTF-8') : 'New property'; ?></h1>
                <div class="page-kicker">Manage the basic setup and editorial defaults for this property.</div>
            </div>
            <a class="btn btn-default icon-btn" href="properties.php" title="Back to properties" aria-label="Back to properties">
                <span class="glyphicon glyphicon-arrow-left" aria-hidden="true"></span>
            </a>
        </div>
        <?php if ($deleted) { ?>
            <div class="alert alert-success">Item was deleted.</div>
        <?php } else { ?>
            <?php if ($errors) { ?>
                <div class="alert alert-danger"><ul class="error-list"><?php foreach ($errors as $error) { ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php } ?></ul></div>
            <?php } ?>
            <?php if ($success) { ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php } ?>

            <form id="property-form" class="item-form" action="property.php?id=<?php echo (int)$id; ?>" method="post">
                <ul class="nav nav-tabs" role="tablist">
                    <li role="presentation" class="active"><a href="#tab-basic" aria-controls="tab-basic" role="tab" data-toggle="tab">Basic</a></li>
                    <li role="presentation"><a href="#tab-operation" aria-controls="tab-operation" role="tab" data-toggle="tab">Τρόπος λειτουργίας</a></li>
                    <li role="presentation"><a href="#tab-settings" aria-controls="tab-settings" role="tab" data-toggle="tab">Settings</a></li>
                </ul>

                <div class="tab-content">
                    <div role="tabpanel" class="tab-pane active" id="tab-basic">
                        <div class="form-grid">
                            <div>
                                <label for="name">Name</label>
                                <input class="form-control" type="text" id="name" name="name" value="<?php echo htmlspecialchars((string)$item->name(), ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div>
                                <label for="type">Type</label>
                                <select class="form-control" id="type" name="type">
                                    <?php foreach ($propertyTypes as $value => $label) { ?>
                                        <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $item->type() === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="wide">
                                <label for="primary_url">Primary URL</label>
                                <input class="form-control" type="text" id="primary_url" name="primary_url" value="<?php echo htmlspecialchars((string)$item->primary_url(), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div>
                                <label for="default_language">Default language</label>
                                <select class="form-control" id="default_language" name="default_language">
                                    <?php foreach ($languageOptions as $value => $label) { ?>
                                        <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $item->default_language() === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div>
                                <label for="timezone">Timezone</label>
                                <input class="form-control" type="text" id="timezone" name="timezone" value="<?php echo htmlspecialchars((string)$item->timezone(), ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div>
                                <label for="status">Status</label>
                                <select class="form-control" id="status" name="status">
                                    <?php foreach ($statusOptions as $value => $label) { ?>
                                        <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $item->status() === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div role="tabpanel" class="tab-pane" id="tab-operation">
                        <div class="operation-card">
                            <h3>Δημιουργία content ideas</h3>
                            <div class="operation-grid">
                                <div>
                                    <label for="op_ideas_mode">Τρόπος δημιουργίας</label>
                                    <select class="form-control operation-control" id="op_ideas_mode">
                                        <option value="automatic">Αυτόματα</option>
                                        <option value="manual">Manually scheduled</option>
                                    </select>
                                </div>
                            </div>
                            <div class="operation-hint">Στο manually scheduled οι ιδέες παράγονται όταν ο χρήστης τις προγραμματίσει από τη σελίδα δημιουργίας content ideas.</div>
                        </div>

                        <div class="operation-card">
                            <h3>Δημιουργία άρθρων</h3>
                            <div class="operation-grid">
                                <div>
                                    <label for="op_articles_mode">Τρόπος δημιουργίας</label>
                                    <select class="form-control operation-control" id="op_articles_mode">
                                        <option value="automatic">Αυτόματα</option>
                                        <option value="manual">Manually scheduled</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="op_articles_count">Πλήθος άρθρων</label>
                                    <input class="form-control operation-control" type="number" min="1" max="50" id="op_articles_count" value="1">
                                </div>
                                <div>
                                    <label for="op_articles_period">Συχνότητα</label>
                                    <select class="form-control operation-control" id="op_articles_period">
                                        <option value="day">Καθημερινά</option>
                                        <option value="week">Κάθε εβδομάδα</option>
                                        <option value="month">Κάθε μήνα</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="operation-card">
                            <h3>Δημοσίευση άρθρων</h3>
                            <div class="operation-grid">
                                <div>
                                    <label for="op_publishing_channel_id">WordPress channel</label>
                                    <select class="form-control operation-control" id="op_publishing_channel_id">
                                        <option value="0">Χωρίς προεπιλεγμένο channel</option>
                                        <?php foreach ($wordpressChannelRows as $channel) { ?>
                                            <option value="<?php echo (int)$channel['id']; ?>"><?php echo htmlspecialchars($channel['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="op_publishing_mode">Τρόπος δημοσίευσης</label>
                                    <select class="form-control operation-control" id="op_publishing_mode">
                                        <option value="automatic">Αυτόματα</option>
                                        <option value="manual">Manually</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="op_wordpress_status">WordPress status</label>
                                    <select class="form-control operation-control" id="op_wordpress_status">
                                        <option value="draft">Draft</option>
                                        <option value="publish">Publish</option>
                                    </select>
                                </div>
                            </div>
                            <div class="operation-hint">Το status αφορά το post που δημιουργείται στο WordPress. Το draft επιτρέπει τελικό έλεγχο μέσα στο WordPress πριν τη δημοσίευση.</div>
                        </div>
                    </div>

                    <div role="tabpanel" class="tab-pane" id="tab-settings">
                        <div class="builder-head">
                            <!-- <h3>Settings</h3> -->
                            <button class="btn btn-primary icon-btn" type="button" id="add-section" title="Add section" aria-label="Add section">
                                <span class="glyphicon glyphicon-plus" aria-hidden="true"></span>
                            </button>
                        </div>
                        <div id="settings-tabs"></div>
                        <div id="settings-sections"></div>
                    </div>
                </div>

                <input type="hidden" id="settings_json" name="settings_json">

                <div style="margin-top:18px;">
                    <button class="btn btn-primary" type="submit" id="submit-button">Save</button>
                    <?php if ($id > 0) { ?>
                        <a class="btn btn-danger" href="property.php?id=<?php echo (int)$id; ?>&delete=1" onclick="return confirm('Delete this property?');">Delete</a>
                    <?php } ?>
                </div>
            </form>
        <?php } ?>
    </div>

    <div class="modal fade" id="section-type-modal" tabindex="-1" role="dialog" aria-labelledby="section-type-title">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="section-type-title">Choose section type</h4>
                </div>
                <div class="modal-body">
                    <label for="new-section-type">Section type</label>
                    <select class="form-control" id="new-section-type"></select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirm-add-section">Add section</button>
                </div>
            </div>
        </div>
    </div>

    <?php include "blocks/footer.php"; ?>
    <script>
        const defaultSettings = <?php echo json_encode($defaultSettings, JSON_UNESCAPED_UNICODE); ?>;
        const settingsSelectOptions = <?php echo json_encode($settingsSelectOptions, JSON_UNESCAPED_UNICODE); ?>;
        const hiddenSectionKeys = new Set(['create_content_ideas', 'content_generation', 'publishing']);
        let settingsSections = [];

        function slugify(text) {
            return (text || '')
                .toString()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '');
        }

        function escapeHtml(value) {
            return (value || '').toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function titleFromKey(key) {
            return (key || '')
                .toString()
                .split('_')
                .filter(Boolean)
                .map(part => part.charAt(0).toUpperCase() + part.slice(1))
                .join(' ');
        }

        function valueToText(value) {
            if (value === null) return 'null';
            if (typeof value === 'object') return JSON.stringify(value, null, 2);
            return String(value);
        }

        function parseValue(value) {
            const trimmed = (value || '').trim();
            if (trimmed === '') return '';
            if (/^(true|false|null)$/i.test(trimmed)) return JSON.parse(trimmed.toLowerCase());
            if (/^-?\d+(\.\d+)?$/.test(trimmed)) return Number(trimmed);
            if (trimmed.startsWith('{') || trimmed.startsWith('[') || trimmed.startsWith('"')) return JSON.parse(trimmed);
            return value;
        }

        function getSelectOptions(optionKey) {
            return settingsSelectOptions[optionKey] || [];
        }

        function renderValueControl(option, optionIndex) {
            const choices = getSelectOptions(option.key);
            if (choices.length) {
                const currentValue = String(option.value || '0');
                const optionsHtml = [
                    `<option value="0"${currentValue === '0' || currentValue === '' ? ' selected' : ''}>None</option>`
                ].concat(choices.map(choice => {
                    const value = String(choice.id);
                    const selected = value === currentValue ? ' selected' : '';
                    return `<option value="${escapeHtml(value)}"${selected}>${escapeHtml(choice.name || ('ID ' + value))}</option>`;
                })).join('');
                return `<select class="form-control" data-option-index="${optionIndex}" data-option-field="value">${optionsHtml}</select>`;
            }

            if (/_id$/.test(option.key || '')) {
                return `<input class="form-control" type="number" min="0" data-option-index="${optionIndex}" data-option-field="value" value="${escapeHtml(option.value || '')}">`;
            }

            return `<textarea class="form-control" data-option-index="${optionIndex}" data-option-field="value" required>${escapeHtml(option.value || '')}</textarea>`;
        }

        function objectToSections(settings) {
            if (settings && Array.isArray(settings.sections)) {
                return settings.sections.map(section => ({
                    title: section.title || titleFromKey(section.key),
                    key: section.key || '',
                    keyTouched: false,
                    options: Array.isArray(section.options) ? section.options.map(option => ({
                        title: option.title || titleFromKey(option.key),
                        key: option.key || '',
                        keyTouched: false,
                        value: valueToText(option.value)
                    })) : []
                }));
            }

            return Object.keys(settings || {}).map(sectionKey => ({
                title: titleFromKey(sectionKey),
                key: sectionKey,
                keyTouched: false,
                options: objectToOptions(settings[sectionKey])
            }));
        }

        function objectToOptions(sectionData) {
            const data = sectionData && typeof sectionData === 'object' && !Array.isArray(sectionData) ? sectionData : {};
            return Object.keys(data).map(optionKey => ({
                title: titleFromKey(optionKey),
                key: optionKey,
                keyTouched: false,
                value: valueToText(data[optionKey])
            }));
        }

        function emptySection() {
            return { title: '', key: '', keyTouched: false, options: [] };
        }

        function emptyOption() {
            return { title: '', key: '', keyTouched: false, value: '' };
        }

        function findSection(sectionKey) {
            return settingsSections.find(section => section.key === sectionKey);
        }

        function sectionValues(sectionKey) {
            const section = findSection(sectionKey);
            const values = {};
            if (!section || !Array.isArray(section.options)) {
                return values;
            }
            section.options.forEach(option => {
                if (option.key) {
                    values[option.key] = parseValue(option.value);
                }
            });
            return values;
        }

        function setSectionValues(sectionKey, title, values) {
            let section = findSection(sectionKey);
            if (!section) {
                section = {
                    title,
                    key: sectionKey,
                    keyTouched: true,
                    options: []
                };
                settingsSections.push(section);
            }
            section.title = section.title || title;
            section.key = sectionKey;
            section.keyTouched = true;

            Object.keys(values).forEach(key => {
                let option = section.options.find(item => item.key === key);
                if (!option) {
                    option = {
                        title: titleFromKey(key),
                        key,
                        keyTouched: true,
                        value: ''
                    };
                    section.options.push(option);
                }
                option.value = valueToText(values[key]);
            });
        }

        function setSelectValue(id, value, fallback) {
            const el = document.getElementById(id);
            if (!el) return;
            const target = String(value ?? fallback ?? '');
            const hasOption = Array.from(el.options).some(option => option.value === target);
            el.value = hasOption ? target : String(fallback ?? '');
        }

        function renderOperationControls() {
            const ideas = sectionValues('create_content_ideas');
            const articles = sectionValues('content_generation');
            const publishing = sectionValues('publishing');

            setSelectValue('op_ideas_mode', ideas.mode, 'manual');
            setSelectValue('op_articles_mode', articles.mode, 'manual');
            document.getElementById('op_articles_count').value = Math.max(1, parseInt(articles.article_count || 1, 10));
            setSelectValue('op_articles_period', articles.period, 'day');
            setSelectValue('op_publishing_channel_id', publishing.distribution_channel_id, '0');
            setSelectValue('op_publishing_mode', publishing.mode, 'manual');
            setSelectValue('op_wordpress_status', publishing.wordpress_status, 'draft');
        }

        function syncOperationSettings() {
            const ideas = sectionValues('create_content_ideas');
            const articles = sectionValues('content_generation');
            const publishing = sectionValues('publishing');
            const articleCount = Math.max(1, Math.min(50, parseInt(document.getElementById('op_articles_count').value || '1', 10)));

            setSectionValues('create_content_ideas', 'Create Content Ideas', {
                ...ideas,
                mode: document.getElementById('op_ideas_mode').value
            });
            setSectionValues('content_generation', 'Content Generation', {
                ...articles,
                mode: document.getElementById('op_articles_mode').value,
                article_count: articleCount,
                period: document.getElementById('op_articles_period').value
            });
            setSectionValues('publishing', 'Publishing', {
                ...publishing,
                mode: document.getElementById('op_publishing_mode').value,
                distribution_channel_id: parseInt(document.getElementById('op_publishing_channel_id').value || '0', 10),
                channel_type: 'wordpress',
                wordpress_status: document.getElementById('op_wordpress_status').value
            });
        }

        function availableSectionTypes() {
            const existingKeys = new Set(settingsSections.map(section => section.key));
            const defaults = Object.keys(defaultSettings || {})
                .filter(key => !hiddenSectionKeys.has(key))
                .map(key => ({
                    key,
                    title: titleFromKey(key),
                    disabled: existingKeys.has(key)
                }));
            defaults.push({ key: '__custom__', title: 'Custom section', disabled: false });
            return defaults;
        }

        function populateSectionTypeSelect() {
            const select = document.getElementById('new-section-type');
            select.innerHTML = availableSectionTypes().map(type => (
                `<option value="${escapeHtml(type.key)}"${type.disabled ? ' disabled' : ''}>${escapeHtml(type.title)}${type.disabled ? ' (already added)' : ''}</option>`
            )).join('');
            const firstAvailable = Array.from(select.options).find(option => !option.disabled);
            if (firstAvailable) {
                select.value = firstAvailable.value;
            }
        }

        function addSectionByType(type) {
            if (type === '__custom__') {
                settingsSections.push(emptySection());
                renderSections();
                return;
            }
            if (!defaultSettings[type]) {
                return;
            }
            const section = objectToSections({ [type]: defaultSettings[type] })[0];
            if (!section) {
                return;
            }
            settingsSections.push(section);
            renderSections();
        }

        function updateSection(index, field, value) {
            settingsSections[index][field] = value;
            if (field === 'key') {
                settingsSections[index].keyTouched = true;
            }
            if (field === 'title' && !settingsSections[index].keyTouched) {
                settingsSections[index].key = slugify(value);
            }
            renderSections();
        }

        function moveSection(index, direction) {
            const target = index + direction;
            if (target < 0 || target >= settingsSections.length) return;
            const current = settingsSections[index];
            settingsSections[index] = settingsSections[target];
            settingsSections[target] = current;
            renderSections();
        }

        function duplicateSection(index) {
            const copy = {
                ...settingsSections[index],
                options: settingsSections[index].options.map(option => ({ ...option }))
            };
            copy.key = copy.key ? copy.key + '_copy' : '';
            copy.title = copy.title ? copy.title + ' Copy' : '';
            settingsSections.splice(index + 1, 0, copy);
            renderSections();
        }

        function deleteSection(index) {
            if (!confirm('Delete this section?')) return;
            settingsSections.splice(index, 1);
            renderSections();
        }

        function addOption(sectionIndex) {
            settingsSections[sectionIndex].options.push(emptyOption());
            renderSections();
        }

        function updateOption(sectionIndex, optionIndex, field, value) {
            const option = settingsSections[sectionIndex].options[optionIndex];
            option[field] = value;
            if (field === 'key') {
                option.keyTouched = true;
            }
            if (field === 'title' && !option.keyTouched) {
                option.key = slugify(value);
            }
            renderSections();
        }

        function moveOption(sectionIndex, optionIndex, direction) {
            const options = settingsSections[sectionIndex].options;
            const target = optionIndex + direction;
            if (target < 0 || target >= options.length) return;
            const current = options[optionIndex];
            options[optionIndex] = options[target];
            options[target] = current;
            renderSections();
        }

        function duplicateOption(sectionIndex, optionIndex) {
            const copy = { ...settingsSections[sectionIndex].options[optionIndex] };
            copy.key = copy.key ? copy.key + '_copy' : '';
            copy.title = copy.title ? copy.title + ' Copy' : '';
            settingsSections[sectionIndex].options.splice(optionIndex + 1, 0, copy);
            renderSections();
        }

        function deleteOption(sectionIndex, optionIndex) {
            if (!confirm('Delete this option?')) return;
            settingsSections[sectionIndex].options.splice(optionIndex, 1);
            renderSections();
        }

        function renderSections() {
            const container = document.getElementById('settings-sections');
            const tabs = document.getElementById('settings-tabs');
            container.innerHTML = '';
            tabs.innerHTML = '';

            const visibleSections = settingsSections
                .map((section, index) => ({ section, index }))
                .filter(item => !hiddenSectionKeys.has(item.section.key));

            if (!visibleSections.length) {
                container.innerHTML = '<div class="settings-empty">No user-facing settings yet.</div>';
                updateJsonFields();
                return;
            }

            tabs.innerHTML = `
                <ul class="nav nav-pills" role="tablist">
                    ${visibleSections.map((item, position) => `
                        <li role="presentation" class="${position === 0 ? 'active' : ''}">
                            <a href="#settings-section-${item.index}" aria-controls="settings-section-${item.index}" role="tab" data-toggle="tab">${escapeHtml(item.section.title || item.section.key || 'Section')}</a>
                        </li>
                    `).join('')}
                </ul>
            `;

            const tabContent = document.createElement('div');
            tabContent.className = 'tab-content';
            container.appendChild(tabContent);

            visibleSections.forEach(({ section, index }, position) => {
                const pane = document.createElement('div');
                pane.className = 'tab-pane' + (position === 0 ? ' active' : '');
                pane.id = `settings-section-${index}`;
                const card = document.createElement('div');
                card.className = 'settings-section-card';
                const optionsHtml = section.options.map((option, optionIndex) => `
                    <div class="settings-option-row">
                        <div class="settings-option-grid">
                            <div>
                                <label>Option title</label>
                                <input class="form-control" data-option-index="${optionIndex}" data-option-field="title" value="${escapeHtml(option.title || '')}" required>
                            </div>
                            <div>
                                <label>Value</label>
                                ${renderValueControl(option, optionIndex)}
                            </div>
                        </div>
                        <div class="settings-option-actions">
                            <button class="btn btn-default btn-sm icon-btn" type="button" data-option-index="${optionIndex}" data-option-action="up" title="Move up" aria-label="Move up"><span class="glyphicon glyphicon-arrow-up" aria-hidden="true"></span></button>
                            <button class="btn btn-default btn-sm icon-btn" type="button" data-option-index="${optionIndex}" data-option-action="down" title="Move down" aria-label="Move down"><span class="glyphicon glyphicon-arrow-down" aria-hidden="true"></span></button>
                            <button class="btn btn-default btn-sm icon-btn" type="button" data-option-index="${optionIndex}" data-option-action="duplicate" title="Duplicate" aria-label="Duplicate"><span class="glyphicon glyphicon-duplicate" aria-hidden="true"></span></button>
                            <button class="btn btn-danger btn-sm icon-btn" type="button" data-option-index="${optionIndex}" data-option-action="delete" title="Delete" aria-label="Delete"><span class="glyphicon glyphicon-trash" aria-hidden="true"></span></button>
                        </div>
                    </div>
                `).join('');

                card.innerHTML = `
                    <div class="settings-section-head">
                        <div class="settings-section-title">${index + 1}. ${section.title || section.key || 'Untitled section'}</div>
                        <div class="settings-section-actions">
                            <button class="btn btn-default btn-sm icon-btn" type="button" data-action="up" title="Move up" aria-label="Move up"><span class="glyphicon glyphicon-arrow-up" aria-hidden="true"></span></button>
                            <button class="btn btn-default btn-sm icon-btn" type="button" data-action="down" title="Move down" aria-label="Move down"><span class="glyphicon glyphicon-arrow-down" aria-hidden="true"></span></button>
                            <button class="btn btn-default btn-sm icon-btn" type="button" data-action="duplicate" title="Duplicate" aria-label="Duplicate"><span class="glyphicon glyphicon-duplicate" aria-hidden="true"></span></button>
                            <button class="btn btn-danger btn-sm icon-btn" type="button" data-action="delete" title="Delete" aria-label="Delete"><span class="glyphicon glyphicon-trash" aria-hidden="true"></span></button>
                        </div>
                    </div>
                    <div class="settings-section-grid">
                        <div>
                            <label>Section title</label>
                            <input class="form-control" data-field="title" value="${escapeHtml(section.title || '')}" required>
                        </div>
                        <input type="hidden" data-field="key" value="${escapeHtml(section.key || '')}">
                    </div>
                    <div class="settings-options">
                        <div class="builder-head" style="margin:10px 0;">
                            <h4>Options</h4>
                            <button class="btn btn-primary btn-sm icon-btn" type="button" data-action="add-option" title="Add option" aria-label="Add option"><span class="glyphicon glyphicon-plus" aria-hidden="true"></span></button>
                        </div>
                        ${optionsHtml || '<p style="color:#52606d;">No options yet.</p>'}
                    </div>
                `;

                card.querySelector('[data-field="key"]').addEventListener('change', event => {
                    updateSection(index, 'key', slugify(event.target.value));
                });
                card.querySelector('[data-field="title"]').addEventListener('change', event => {
                    updateSection(index, 'title', event.target.value);
                });
                card.querySelector('[data-action="up"]').addEventListener('click', () => moveSection(index, -1));
                card.querySelector('[data-action="down"]').addEventListener('click', () => moveSection(index, 1));
                card.querySelector('[data-action="add-option"]').addEventListener('click', () => addOption(index));
                card.querySelectorAll('[data-option-field]').forEach(input => {
                    const optionIndex = parseInt(input.getAttribute('data-option-index'), 10);
                    const field = input.getAttribute('data-option-field');
                    input.addEventListener('change', event => {
                        const value = field === 'key' ? slugify(event.target.value) : event.target.value;
                        updateOption(index, optionIndex, field, value);
                    });
                });
                card.querySelectorAll('[data-option-action]').forEach(button => {
                    const optionIndex = parseInt(button.getAttribute('data-option-index'), 10);
                    const action = button.getAttribute('data-option-action');
                    button.addEventListener('click', () => {
                        if (action === 'up') moveOption(index, optionIndex, -1);
                        if (action === 'down') moveOption(index, optionIndex, 1);
                        if (action === 'duplicate') duplicateOption(index, optionIndex);
                        if (action === 'delete') deleteOption(index, optionIndex);
                    });
                });
                card.querySelector('[data-action="duplicate"]').addEventListener('click', () => duplicateSection(index));
                card.querySelector('[data-action="delete"]').addEventListener('click', () => deleteSection(index));
                pane.appendChild(card);
                tabContent.appendChild(pane);
            });

            updateJsonFields();
        }

        function buildSettings() {
            syncOperationSettings();
            return {
                sections: settingsSections.map(section => ({
                    title: (section.title || '').trim(),
                    key: slugify(section.key),
                    options: section.options.map(option => ({
                        title: (option.title || '').trim(),
                        key: slugify(option.key),
                        value: parseValue(option.value)
                    }))
                }))
            };
        }

        function validateSettings() {
            const errors = [];
            const keys = new Set();

            settingsSections.forEach(section => {
                const key = slugify(section.key);
                if (!key || !/^[a-z0-9_]+$/.test(key)) errors.push('Invalid section key: ' + (section.key || '(empty)'));
                if (!section.title || !section.title.trim()) errors.push('Section title is required for ' + (key || '(empty)'));
                if (keys.has(key)) errors.push('Duplicate section key: ' + key);
                keys.add(key);
                const optionKeys = new Set();
                section.options.forEach(option => {
                    const optionKey = slugify(option.key);
                    if (!option.title || !option.title.trim()) errors.push('Option title is required in section "' + key + '".');
                    if (!optionKey || !/^[a-z0-9_]+$/.test(optionKey)) errors.push('Invalid option key in section "' + key + '": ' + (option.key || '(empty)'));
                    if (optionKeys.has(optionKey)) errors.push('Duplicate option key in section "' + key + '": ' + optionKey);
                    optionKeys.add(optionKey);
                    try {
                        parseValue(option.value);
                    } catch (error) {
                        errors.push('Invalid value for option "' + optionKey + '" in section "' + key + '": ' + error.message);
                    }
                });
            });

            if (!settingsSections.length) errors.push('Add at least one settings section.');
            return errors;
        }

        function updateJsonFields() {
            try {
                document.getElementById('settings_json').value = JSON.stringify(buildSettings(), null, 2);
            } catch (error) {
                document.getElementById('settings_json').value = '';
            }
        }

        document.getElementById('add-section').addEventListener('click', () => {
            populateSectionTypeSelect();
            $('#section-type-modal').modal('show');
        });

        document.getElementById('confirm-add-section').addEventListener('click', () => {
            addSectionByType(document.getElementById('new-section-type').value);
            $('#section-type-modal').modal('hide');
        });

        document.getElementById('property-form').addEventListener('submit', event => {
            const errors = validateSettings();
            if (errors.length) {
                event.preventDefault();
                alert(errors.join('\n'));
                return;
            }
            document.getElementById('settings_json').value = JSON.stringify(buildSettings(), null, 2);
        });

        document.querySelectorAll('.operation-control').forEach(control => {
            control.addEventListener('change', updateJsonFields);
        });

        try {
            const initial = <?php echo json_encode($item->settings_json()); ?>;
            const parsed = JSON.parse(initial || '{}');
            settingsSections = objectToSections(parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : defaultSettings);
        } catch (error) {
            settingsSections = objectToSections(defaultSettings);
        }
        if (!settingsSections.length) {
            settingsSections = objectToSections(defaultSettings);
        }
        renderOperationControls();
        renderSections();
    </script>
</body>
</html>
