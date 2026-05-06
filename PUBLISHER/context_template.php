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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];
$success = '';
$deleted = false;

$formatOptions = ['paragraphs', 'bullet_points', 'numbered_list', 'faq', 'quote', 'table', 'summary', 'cta', 'sources'];
$defaultStructure = [
    'sections' => [
        [
            'key' => 'intro',
            'title' => 'Introduction',
            'instructions' => 'Introduce the topic clearly and briefly.',
            'min_words' => 80,
            'max_words' => 120,
            'format' => 'paragraphs',
            'required' => true,
            'order' => 1,
        ],
        [
            'key' => 'main_body',
            'title' => 'Main Body',
            'instructions' => 'Analyze the topic with clear arguments and examples.',
            'min_words' => 400,
            'max_words' => 700,
            'format' => 'paragraphs',
            'required' => true,
            'order' => 2,
        ],
        [
            'key' => 'conclusion',
            'title' => 'Conclusion',
            'instructions' => 'Summarize the main points and close with a clear final thought.',
            'min_words' => 80,
            'max_words' => 150,
            'format' => 'summary',
            'required' => true,
            'order' => 3,
        ],
    ],
];

function template_normalize_structure($json, $formatOptions, &$errors) {
    $decoded = json_decode((string)$json, true);
    if (!is_array($decoded) || !isset($decoded['sections']) || !is_array($decoded['sections'])) {
        $errors[] = 'Το structure JSON πρέπει να περιέχει array sections.';
        return null;
    }

    $keys = [];
    $sections = [];
    foreach (array_values($decoded['sections']) as $index => $section) {
        $key = trim((string)($section['key'] ?? ''));
        $title = trim((string)($section['title'] ?? ''));
        $instructions = trim((string)($section['instructions'] ?? ''));
        $format = trim((string)($section['format'] ?? 'paragraphs'));
        $minWords = !isset($section['min_words']) || $section['min_words'] === '' ? null : (int)$section['min_words'];
        $maxWords = !isset($section['max_words']) || $section['max_words'] === '' ? null : (int)$section['max_words'];
        $required = filter_var($section['required'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if ($key === '' || !preg_match('/^[a-z0-9_]+$/', $key)) {
            $errors[] = 'Κάθε section χρειάζεται μοναδικό key με lowercase λατινικά, αριθμούς ή underscore.';
        }
        if (isset($keys[$key])) {
            $errors[] = 'Το key "' . $key . '" χρησιμοποιείται πάνω από μία φορά.';
        }
        $keys[$key] = true;
        if ($title === '' || mb_strlen($title) > 150) {
            $errors[] = 'Κάθε section χρειάζεται title έως 150 χαρακτήρες.';
        }
        if (mb_strlen($instructions) < 10) {
            $errors[] = 'Κάθε section χρειάζεται instructions τουλάχιστον 10 χαρακτήρων.';
        }
        if ($minWords !== null && $minWords < 0) {
            $errors[] = 'Το min_words πρέπει να είναι >= 0.';
        }
        if ($maxWords !== null && $maxWords < 0) {
            $errors[] = 'Το max_words πρέπει να είναι >= 0.';
        }
        if ($minWords !== null && $maxWords !== null && $maxWords < $minWords) {
            $errors[] = 'Το max_words πρέπει να είναι μεγαλύτερο ή ίσο του min_words.';
        }
        if (!in_array($format, $formatOptions, true)) {
            $errors[] = 'Μη έγκυρο format section.';
        }

        $sections[] = [
            'key' => $key,
            'title' => $title,
            'instructions' => $instructions,
            'min_words' => $minWords,
            'max_words' => $maxWords,
            'format' => $format,
            'required' => $required,
            'order' => $index + 1,
        ];
    }

    if (!$sections) {
        $errors[] = 'Προσθέστε τουλάχιστον ένα section.';
    }

    return ['sections' => $sections];
}

if ($id > 0) {
    $templateRows = $dbo->getRS(
        'SELECT * FROM content_templates WHERE id = ? AND account_id = ? AND (property_id = ? OR property_id IS NULL) LIMIT 1',
        [$id, $current_account_id, $current_property_id]
    );
    if (!$templateRows) {
        http_response_code(403);
        die('Access denied');
    }
    $template = $templateRows[0];
} else {
    $template = [
        'id' => 0,
        'account_id' => $current_account_id,
        'property_id' => $current_property_id,
        'content_type_id' => null,
        'name' => '',
        'description' => '',
        'structure_json' => json_encode($defaultStructure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        'is_default' => 0,
        'status' => 'active',
        'created_at' => null,
        'updated_at' => null,
    ];
}

if (isset($_GET['delete']) && $_GET['delete'] == 1 && $id > 0) {
    $dbo->execSQL('DELETE FROM content_templates WHERE id = ? AND account_id = ? AND (property_id = ? OR property_id IS NULL)', [$id, $current_account_id, $current_property_id]);
    $deleted = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$deleted) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $contentTypeId = (int)($_POST['content_type_id'] ?? 0);
    $contentTypeId = $contentTypeId > 0 ? $contentTypeId : null;
    $isDefault = isset($_POST['is_default']) ? 1 : 0;
    $status = $_POST['status'] ?? 'active';
    $structureJson = $_POST['structure_json'] ?? '';

    if ($name === '') {
        $errors[] = 'Συμπληρώστε όνομα template.';
    }
    if (!in_array($status, ['active', 'inactive'], true)) {
        $errors[] = 'Μη έγκυρο status.';
    }

    $normalizedStructure = template_normalize_structure($structureJson, $formatOptions, $errors);
    if (!$errors) {
        $now = date('Y-m-d H:i:s');
        $encodedStructure = json_encode($normalizedStructure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($id > 0) {
            $dbo->execSQL(
                'UPDATE content_templates
                 SET content_type_id = ?, name = ?, description = ?, structure_json = ?, is_default = ?, status = ?, updated_at = ?
                 WHERE id = ? AND account_id = ? AND (property_id = ? OR property_id IS NULL)',
                [$contentTypeId, $name, $description, $encodedStructure, $isDefault, $status, $now, $id, $current_account_id, $current_property_id]
            );
        } else {
            $id = $dbo->execSQL(
                'INSERT INTO content_templates
                 (account_id, property_id, content_type_id, name, description, structure_json, is_default, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$current_account_id, $current_property_id, $contentTypeId, $name, $description, $encodedStructure, $isDefault, $status, $now, $now]
            );
        }

        if ($isDefault) {
            $dbo->execSQL(
                'UPDATE content_templates SET is_default = 0 WHERE account_id = ? AND property_id = ? AND id <> ?',
                [$current_account_id, $current_property_id, $id]
            );
        }

        $success = 'Το template αποθηκεύτηκε.';
        $templateRows = $dbo->getRS('SELECT * FROM content_templates WHERE id = ? LIMIT 1', [$id]);
        if ($templateRows) {
            $template = $templateRows[0];
        }
    } else {
        $template['name'] = $name;
        $template['description'] = $description;
        $template['content_type_id'] = $contentTypeId;
        $template['is_default'] = $isDefault;
        $template['status'] = $status;
        $template['structure_json'] = $structureJson;
    }
}

$contentTypes = $dbo->getRS(
    'SELECT id, name FROM content_types WHERE account_id = ? AND (property_id = ? OR property_id IS NULL) AND status = ? ORDER BY name',
    [$current_account_id, $current_property_id, 'active']
);

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <title><?php echo app::$project_name; ?> - Content template</title>
    <?php include "_head.php"; ?>
    <style>
        body { background:#fff; }
        .template-form { max-width:1100px; }
        .form-row { margin-bottom:12px; }
        .builder-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin:18px 0 12px; }
        .section-card { border:1px solid #d9e2ec; border-radius:8px; padding:14px; margin-bottom:12px; background:#fff; box-shadow:0 6px 18px rgba(15,23,42,.05); }
        .section-card-head { display:flex; justify-content:space-between; gap:12px; align-items:center; margin-bottom:12px; }
        .section-title { font-weight:700; font-size:16px; }
        .section-actions { display:flex; flex-wrap:wrap; gap:6px; }
        .section-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .section-grid .wide { grid-column:1 / -1; }
        .json-preview { background:#0f172a; color:#dbeafe; padding:14px; border-radius:8px; max-height:320px; overflow:auto; white-space:pre-wrap; }
        .advanced-json { display:none; }
        .error-list { margin:0; padding-left:18px; }
        @media (max-width:800px) { .section-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <div class="padding-20 template-form">
        <h1>Content template</h1>
        <p style="color:#52606d;">Property: <?php echo htmlspecialchars($current_property_name, ENT_QUOTES, 'UTF-8'); ?></p>

        <?php if ($deleted) { ?>
            <div class="alert alert-success">Το template διαγράφηκε.</div>
            <script>if (parent && parent.SetDataRefresh) parent.SetDataRefresh(1);</script>
        <?php } else { ?>
            <?php if ($errors) { ?>
                <div class="alert alert-danger"><ul class="error-list"><?php foreach ($errors as $error) { ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php } ?></ul></div>
            <?php } ?>
            <?php if ($success) { ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
                <script>if (parent && parent.SetDataRefresh) parent.SetDataRefresh(1);</script>
            <?php } ?>

            <form id="template-form" method="post" action="context_template.php?id=<?php echo (int)$id; ?>">
                <div class="form-row">
                    <label for="name">Name</label>
                    <input class="form-control" type="text" id="name" name="name" value="<?php echo htmlspecialchars($template['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="form-row">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description"><?php echo htmlspecialchars((string)$template['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div class="form-row">
                    <label for="content_type_id">Content type</label>
                    <select class="form-control" id="content_type_id" name="content_type_id">
                        <option value="0">...</option>
                        <?php if ($contentTypes) { foreach ($contentTypes as $contentType) { ?>
                            <option value="<?php echo (int)$contentType['id']; ?>" <?php echo (int)$template['content_type_id'] === (int)$contentType['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($contentType['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php }} ?>
                    </select>
                </div>

                <div class="form-row">
                    <label for="status">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="active" <?php echo $template['status'] === 'active' ? 'selected' : ''; ?>>active</option>
                        <option value="inactive" <?php echo $template['status'] === 'inactive' ? 'selected' : ''; ?>>inactive</option>
                    </select>
                </div>

                <label style="font-weight:400;">
                    <input type="checkbox" name="is_default" value="1" <?php echo (int)$template['is_default'] === 1 ? 'checked' : ''; ?>> Default template
                </label>

                <div class="builder-head">
                    <h3>Structure sections</h3>
                    <div>
                        <button class="btn btn-default" type="button" id="toggle-advanced">Advanced JSON</button>
                        <button class="btn btn-primary" type="button" id="add-section">Add section</button>
                    </div>
                </div>

                <div id="sections"></div>

                <h3>JSON preview</h3>
                <pre id="json-preview" class="json-preview"></pre>

                <div id="advanced-json" class="advanced-json">
                    <h3>Raw JSON</h3>
                    <textarea class="form-control" id="raw-json" style="min-height:260px;"></textarea>
                    <button class="btn btn-default" type="button" id="apply-raw-json" style="margin-top:8px;">Apply raw JSON</button>
                </div>

                <input type="hidden" id="structure_json" name="structure_json">

                <div style="margin-top:18px;">
                    <button class="btn btn-primary" type="submit" id="submit-button">Save</button>
                    <?php if ($id > 0) { ?>
                        <a class="btn btn-danger" href="context_template.php?id=<?php echo (int)$id; ?>&delete=1" onclick="return confirm('Delete this template?');">Delete</a>
                    <?php } ?>
                </div>
            </form>
        <?php } ?>
    </div>

    <?php include "blocks/footer.php"; ?>

    <script>
        const formatOptions = <?php echo json_encode($formatOptions); ?>;
        let sections = [];

        function slugify(text) {
            return (text || '')
                .toString()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '');
        }

        function normalizeOrders() {
            sections = sections.map((section, index) => ({ ...section, order: index + 1 }));
        }

        function emptySection() {
            return {
                key: '',
                title: '',
                instructions: '',
                min_words: '',
                max_words: '',
                format: 'paragraphs',
                required: true,
                order: sections.length + 1
            };
        }

        function updateSection(index, field, value) {
            sections[index][field] = value;
            if (field === 'title' && !sections[index].key) {
                sections[index].key = slugify(value);
            }
            renderSections();
        }

        function moveSection(index, direction) {
            const target = index + direction;
            if (target < 0 || target >= sections.length) return;
            const temp = sections[index];
            sections[index] = sections[target];
            sections[target] = temp;
            renderSections();
        }

        function duplicateSection(index) {
            const copy = { ...sections[index] };
            copy.key = copy.key ? copy.key + '_copy' : '';
            copy.title = copy.title ? copy.title + ' Copy' : '';
            sections.splice(index + 1, 0, copy);
            renderSections();
        }

        function deleteSection(index) {
            if (!confirm('Delete this section?')) return;
            sections.splice(index, 1);
            renderSections();
        }

        function renderSections() {
            normalizeOrders();
            const container = document.getElementById('sections');
            container.innerHTML = '';

            sections.forEach((section, index) => {
                const card = document.createElement('div');
                card.className = 'section-card';
                const formats = formatOptions.map(format => `<option value="${format}" ${section.format === format ? 'selected' : ''}>${format}</option>`).join('');
                card.innerHTML = `
                    <div class="section-card-head">
                        <div class="section-title">${index + 1}. ${section.title || 'Untitled section'}</div>
                        <div class="section-actions">
                            <button class="btn btn-default btn-sm" type="button" data-action="up">Up</button>
                            <button class="btn btn-default btn-sm" type="button" data-action="down">Down</button>
                            <button class="btn btn-default btn-sm" type="button" data-action="duplicate">Duplicate</button>
                            <button class="btn btn-danger btn-sm" type="button" data-action="delete">Delete</button>
                        </div>
                    </div>
                    <div class="section-grid">
                        <div>
                            <label>Title</label>
                            <input class="form-control" data-field="title" maxlength="150" value="${escapeHtml(section.title || '')}" required>
                        </div>
                        <div>
                            <label>Key</label>
                            <input class="form-control" data-field="key" value="${escapeHtml(section.key || '')}" required>
                        </div>
                        <div class="wide">
                            <label>Instructions</label>
                            <textarea class="form-control" data-field="instructions" required>${escapeHtml(section.instructions || '')}</textarea>
                        </div>
                        <div>
                            <label>Min words</label>
                            <input class="form-control" type="number" min="0" data-field="min_words" value="${section.min_words ?? ''}">
                        </div>
                        <div>
                            <label>Max words</label>
                            <input class="form-control" type="number" min="0" data-field="max_words" value="${section.max_words ?? ''}">
                        </div>
                        <div>
                            <label>Format</label>
                            <select class="form-control" data-field="format">${formats}</select>
                        </div>
                        <div>
                            <label style="display:block;">Required</label>
                            <label style="font-weight:400;"><input type="checkbox" data-field="required" ${section.required ? 'checked' : ''}> Required</label>
                        </div>
                    </div>
                `;

                card.querySelectorAll('[data-field]').forEach(input => {
                    const field = input.getAttribute('data-field');
                    input.addEventListener('change', () => {
                        let value = input.type === 'checkbox' ? input.checked : input.value;
                        if (field === 'key') value = slugify(value);
                        if (field === 'min_words' || field === 'max_words') value = value === '' ? '' : parseInt(value, 10);
                        updateSection(index, field, value);
                    });
                    input.addEventListener('blur', () => {
                        if (field === 'title' && !sections[index].key) {
                            updateSection(index, 'key', slugify(input.value));
                        }
                    });
                });

                card.querySelector('[data-action="up"]').addEventListener('click', () => moveSection(index, -1));
                card.querySelector('[data-action="down"]').addEventListener('click', () => moveSection(index, 1));
                card.querySelector('[data-action="duplicate"]').addEventListener('click', () => duplicateSection(index));
                card.querySelector('[data-action="delete"]').addEventListener('click', () => deleteSection(index));
                container.appendChild(card);
            });

            updateJsonFields();
        }

        function buildStructure() {
            normalizeOrders();
            return {
                sections: sections.map((section, index) => ({
                    key: slugify(section.key),
                    title: (section.title || '').trim(),
                    instructions: (section.instructions || '').trim(),
                    min_words: section.min_words === '' || section.min_words === null ? null : parseInt(section.min_words, 10),
                    max_words: section.max_words === '' || section.max_words === null ? null : parseInt(section.max_words, 10),
                    format: section.format || 'paragraphs',
                    required: !!section.required,
                    order: index + 1
                }))
            };
        }

        function validateStructure(structure) {
            const errors = [];
            const keys = new Set();
            structure.sections.forEach(section => {
                if (!section.key || !/^[a-z0-9_]+$/.test(section.key)) errors.push('Invalid key: ' + section.key);
                if (keys.has(section.key)) errors.push('Duplicate key: ' + section.key);
                keys.add(section.key);
                if (!section.title || section.title.length > 150) errors.push('Invalid title for ' + section.key);
                if (!section.instructions || section.instructions.length < 10) errors.push('Instructions too short for ' + section.key);
                if (section.min_words !== null && section.min_words < 0) errors.push('Invalid min_words for ' + section.key);
                if (section.max_words !== null && section.max_words < 0) errors.push('Invalid max_words for ' + section.key);
                if (section.min_words !== null && section.max_words !== null && section.max_words < section.min_words) errors.push('max_words must be >= min_words for ' + section.key);
                if (!formatOptions.includes(section.format)) errors.push('Invalid format for ' + section.key);
            });
            if (!structure.sections.length) errors.push('Add at least one section.');
            return errors;
        }

        function updateJsonFields() {
            const structure = buildStructure();
            const json = JSON.stringify(structure, null, 2);
            document.getElementById('json-preview').textContent = json;
            document.getElementById('raw-json').value = json;
            document.getElementById('structure_json').value = json;
        }

        function escapeHtml(value) {
            return (value || '').toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        document.getElementById('add-section').addEventListener('click', () => {
            sections.push(emptySection());
            renderSections();
        });

        document.getElementById('toggle-advanced').addEventListener('click', () => {
            const advanced = document.getElementById('advanced-json');
            advanced.style.display = advanced.style.display === 'block' ? 'none' : 'block';
        });

        document.getElementById('apply-raw-json').addEventListener('click', () => {
            try {
                const parsed = JSON.parse(document.getElementById('raw-json').value);
                if (!parsed.sections || !Array.isArray(parsed.sections)) {
                    alert('JSON must contain sections array.');
                    return;
                }
                sections = parsed.sections;
                renderSections();
            } catch (error) {
                alert('Invalid JSON: ' + error.message);
            }
        });

        document.getElementById('template-form').addEventListener('submit', event => {
            const structure = buildStructure();
            const errors = validateStructure(structure);
            if (errors.length) {
                event.preventDefault();
                alert(errors.join('\n'));
                return;
            }
            document.getElementById('structure_json').value = JSON.stringify(structure, null, 2);
        });

        try {
            const initial = <?php echo json_encode($template['structure_json']); ?>;
            const parsed = JSON.parse(initial || '{"sections":[]}');
            sections = Array.isArray(parsed.sections) ? parsed.sections : [];
        } catch (error) {
            sections = [];
        }
        if (!sections.length) {
            sections = <?php echo json_encode($defaultStructure['sections'], JSON_UNESCAPED_UNICODE); ?>;
        }
        renderSections();
    </script>
</body>
</html>
