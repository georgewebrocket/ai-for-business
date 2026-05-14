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
        body { background:#fff; }
        .property-form { max-width:1100px; padding-bottom:50px; }
        .form-row { margin-bottom:12px; }
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .form-grid .wide { grid-column:1 / -1; }
        .builder-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin:18px 0 12px; }
        .settings-section-card { border:1px solid #d9e2ec; border-radius:8px; padding:14px; margin-bottom:12px; background:#fff; box-shadow:0 6px 18px rgba(15,23,42,.05); }
        .settings-section-head { display:flex; justify-content:space-between; gap:12px; align-items:center; margin-bottom:12px; }
        .settings-section-title { font-weight:700; font-size:16px; }
        .settings-section-actions { display:flex; flex-wrap:wrap; gap:6px; }
        .settings-section-grid { display:grid; grid-template-columns:1fr; gap:12px; }
        .settings-options { margin-top:12px; }
        .settings-option-row { border:1px solid #edf2f7; border-radius:8px; padding:12px; margin-bottom:10px; background:#fbfdff; }
        .settings-option-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; align-items:start; }
        .settings-option-actions { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
        .json-preview { background:#0f172a; color:#dbeafe; padding:14px; border-radius:8px; max-height:320px; overflow:auto; white-space:pre-wrap; }
        .advanced-json { display:none; }
        .error-list { margin:0; padding-left:18px; }
        textarea.form-control { min-height:90px; font-family:Consolas, Monaco, monospace; }
        @media (max-width:800px) {
            .form-grid { grid-template-columns:1fr; }
            .settings-section-grid { grid-template-columns:1fr; }
            .settings-option-grid { grid-template-columns:1fr; }
            .settings-section-head { align-items:flex-start; flex-direction:column; }
        }
    </style>
</head>
<body>
    <div class="padding-20 property-form">
        <h1>Property</h1>
        <?php if ($deleted) { ?>
            <div class="alert alert-success">Item was deleted.</div>
            <script>if (parent && parent.SetDataRefresh) parent.SetDataRefresh(1);</script>
        <?php } else { ?>
            <?php if ($errors) { ?>
                <div class="alert alert-danger"><ul class="error-list"><?php foreach ($errors as $error) { ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php } ?></ul></div>
            <?php } ?>
            <?php if ($success) { ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
                <script>if (parent && parent.SetDataRefresh) parent.SetDataRefresh(1);</script>
            <?php } ?>

            <form id="property-form" class="item-form" action="property.php?id=<?php echo (int)$id; ?>" method="post">
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

                <div class="builder-head">
                    <h3>Settings sections</h3>
                    <div>
                        <button class="btn btn-default" type="button" id="toggle-advanced">Advanced JSON</button>
                        <button class="btn btn-primary" type="button" id="add-section">Add section</button>
                    </div>
                </div>

                <div id="settings-sections"></div>

                <h3>JSON preview</h3>
                <pre id="json-preview" class="json-preview"></pre>

                <div id="advanced-json" class="advanced-json">
                    <h3>Raw JSON</h3>
                    <textarea class="form-control" id="raw-json" style="min-height:260px;"></textarea>
                    <button class="btn btn-default" type="button" id="apply-raw-json" style="margin-top:8px;">Apply raw JSON</button>
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

    <?php include "blocks/footer.php"; ?>
    <script>
        const defaultSettings = <?php echo json_encode($defaultSettings, JSON_UNESCAPED_UNICODE); ?>;
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
            container.innerHTML = '';

            settingsSections.forEach((section, index) => {
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
                                <textarea class="form-control" data-option-index="${optionIndex}" data-option-field="value" required>${escapeHtml(option.value || '')}</textarea>
                            </div>
                        </div>
                        <div class="settings-option-actions">
                            <button class="btn btn-default btn-sm" type="button" data-option-index="${optionIndex}" data-option-action="up">Up</button>
                            <button class="btn btn-default btn-sm" type="button" data-option-index="${optionIndex}" data-option-action="down">Down</button>
                            <button class="btn btn-default btn-sm" type="button" data-option-index="${optionIndex}" data-option-action="duplicate">Duplicate</button>
                            <button class="btn btn-danger btn-sm" type="button" data-option-index="${optionIndex}" data-option-action="delete">Delete</button>
                        </div>
                    </div>
                `).join('');

                card.innerHTML = `
                    <div class="settings-section-head">
                        <div class="settings-section-title">${index + 1}. ${section.title || section.key || 'Untitled section'}</div>
                        <div class="settings-section-actions">
                            <button class="btn btn-default btn-sm" type="button" data-action="up">Up</button>
                            <button class="btn btn-default btn-sm" type="button" data-action="down">Down</button>
                            <button class="btn btn-default btn-sm" type="button" data-action="duplicate">Duplicate</button>
                            <button class="btn btn-danger btn-sm" type="button" data-action="delete">Delete</button>
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
                            <button class="btn btn-primary btn-sm" type="button" data-action="add-option">Add option</button>
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
                container.appendChild(card);
            });

            updateJsonFields();
        }

        function buildSettings() {
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
                const settings = buildSettings();
                const json = JSON.stringify(settings, null, 2);
                document.getElementById('json-preview').textContent = json;
                document.getElementById('raw-json').value = json;
                document.getElementById('settings_json').value = json;
            } catch (error) {
                document.getElementById('json-preview').textContent = 'Invalid section JSON: ' + error.message;
            }
        }

        document.getElementById('add-section').addEventListener('click', () => {
            settingsSections.push(emptySection());
            renderSections();
        });

        document.getElementById('toggle-advanced').addEventListener('click', () => {
            const advanced = document.getElementById('advanced-json');
            advanced.style.display = advanced.style.display === 'block' ? 'none' : 'block';
        });

        document.getElementById('apply-raw-json').addEventListener('click', () => {
            try {
                const parsed = JSON.parse(document.getElementById('raw-json').value || '{}');
                if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
                    alert('JSON must be a settings object.');
                    return;
                }
                settingsSections = objectToSections(parsed);
                renderSections();
            } catch (error) {
                alert('Invalid JSON: ' + error.message);
            }
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
        renderSections();
    </script>
</body>
</html>
