<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once "php/config.php";
require_once "php/db.php";
require_once "php/utils.php";
require_once "php/controls.php";
require_once "php/start.php";
require_once "php/session.php";

publisher_require_permission('dashboard');

$accountId = (int)$current_account_id;
$propertyId = (int)$current_property_id;
$userId = (int)$userid;
$dashboardSettingKey = 'dashboard-user-' . $userId;

function dashboard_default_cards() {
    return [
        [
            'id' => 'card-' . uniqid(),
            'type' => 'stat_new_ideas',
            'title' => 'New content ideas',
        ],
        [
            'id' => 'card-' . uniqid(),
            'type' => 'stat_draft_items',
            'title' => 'Draft content items',
        ],
        [
            'id' => 'card-' . uniqid(),
            'type' => 'links',
            'title' => 'Useful links',
            'links' => [
                ['label' => 'Content ideas', 'url' => 'content_ideas.php'],
                ['label' => 'Content items', 'url' => 'content_items.php'],
                ['label' => 'Create ideas', 'url' => 'create_content_ideas.php'],
            ],
        ],
        [
            'id' => 'card-' . uniqid(),
            'type' => 'todo',
            'title' => 'To-do',
            'items' => [
                ['text' => 'Review new content ideas', 'done' => false],
                ['text' => 'Check draft articles', 'done' => false],
            ],
        ],
        [
            'id' => 'card-' . uniqid(),
            'type' => 'notes',
            'title' => 'Notes',
            'text' => '',
        ],
    ];
}

function dashboard_load_config($dbo, $accountId, $key) {
    $rows = $dbo->getRS(
        'SELECT key_value FROM settings WHERE account_id = ? AND key_code = ? ORDER BY id DESC LIMIT 1',
        [$accountId, $key]
    );
    if (!$rows) {
        return ['cards' => dashboard_default_cards()];
    }

    $decoded = json_decode((string)$rows[0]['key_value'], true);
    if (!is_array($decoded) || !isset($decoded['cards']) || !is_array($decoded['cards'])) {
        return ['cards' => dashboard_default_cards()];
    }

    return $decoded;
}

function dashboard_normalize_text($value, $maxLength = 500) {
    $value = trim((string)$value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }
    return substr($value, 0, $maxLength);
}

function dashboard_normalize_config($config) {
    $allowedTypes = ['stat_new_ideas', 'stat_draft_items', 'stat_converted_ideas', 'stat_scheduled_ideas', 'links', 'todo', 'notes'];
    $cards = [];
    if (!is_array($config) || !isset($config['cards']) || !is_array($config['cards'])) {
        return ['cards' => dashboard_default_cards()];
    }

    foreach ($config['cards'] as $index => $card) {
        if (!is_array($card)) {
            continue;
        }
        $type = in_array(($card['type'] ?? ''), $allowedTypes, true) ? $card['type'] : 'notes';
        $normalized = [
            'id' => preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($card['id'] ?? 'card-' . $index . '-' . uniqid())),
            'type' => $type,
            'title' => dashboard_normalize_text($card['title'] ?? dashboard_card_default_title($type), 120),
        ];

        if ($type === 'links') {
            $normalized['links'] = [];
            $links = isset($card['links']) && is_array($card['links']) ? $card['links'] : [];
            foreach (array_slice($links, 0, 12) as $link) {
                if (!is_array($link)) {
                    continue;
                }
                $label = dashboard_normalize_text($link['label'] ?? '', 120);
                $url = dashboard_normalize_text($link['url'] ?? '', 500);
                if ($label !== '' && $url !== '') {
                    $normalized['links'][] = ['label' => $label, 'url' => $url];
                }
            }
        } elseif ($type === 'todo') {
            $normalized['items'] = [];
            $items = isset($card['items']) && is_array($card['items']) ? $card['items'] : [];
            foreach (array_slice($items, 0, 30) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $text = dashboard_normalize_text($item['text'] ?? '', 250);
                if ($text !== '') {
                    $normalized['items'][] = ['text' => $text, 'done' => !empty($item['done'])];
                }
            }
        } elseif ($type === 'notes') {
            $normalized['text'] = dashboard_normalize_text($card['text'] ?? '', 5000);
        }

        if ($normalized['id'] === '') {
            $normalized['id'] = 'card-' . uniqid();
        }
        $cards[] = $normalized;
    }

    return ['cards' => $cards ?: dashboard_default_cards()];
}

function dashboard_card_default_title($type) {
    $titles = [
        'stat_new_ideas' => 'New content ideas',
        'stat_draft_items' => 'Draft content items',
        'stat_converted_ideas' => 'Converted ideas',
        'stat_scheduled_ideas' => 'Scheduled ideas',
        'links' => 'Useful links',
        'todo' => 'To-do',
        'notes' => 'Notes',
    ];
    return $titles[$type] ?? 'Card';
}

function dashboard_save_config($dbo, $accountId, $key, $config) {
    $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $now = date('YmdHis');
    $existing = $dbo->getRS('SELECT id FROM settings WHERE account_id = ? AND key_code = ? ORDER BY id DESC LIMIT 1', [$accountId, $key]);
    if ($existing) {
        $dbo->execSQL(
            'UPDATE settings SET title = ?, key_value = ?, date_modified = ?, s_type = ? WHERE id = ? AND account_id = ?',
            ['Dashboard layout', $encoded, $now, 1, (int)$existing[0]['id'], $accountId]
        );
        return;
    }

    $dbo->execSQL(
        'INSERT INTO settings (key_code, title, key_value, date_modified, s_type, account_id) VALUES (?, ?, ?, ?, ?, ?)',
        [$key, 'Dashboard layout', $encoded, $now, 1, $accountId]
    );
}

function dashboard_count($dbo, $sql, $params) {
    $rows = $dbo->getRS($sql, $params);
    return $rows ? (int)$rows[0]['total'] : 0;
}

function dashboard_scope_sql(&$params, $accountId, $propertyId, $alias = '') {
    $prefix = $alias !== '' ? $alias . '.' : '';
    $sql = $prefix . 'account_id = ?';
    $params[] = $accountId;
    if ($propertyId > 0) {
        $sql .= ' AND ' . $prefix . 'property_id = ?';
        $params[] = $propertyId;
    }
    return $sql;
}

function dashboard_stat_data($dbo, $accountId, $propertyId) {
    $params = [];
    $scope = dashboard_scope_sql($params, $accountId, $propertyId);
    $newIdeas = dashboard_count($dbo, "SELECT COUNT(*) AS total FROM content_ideas WHERE {$scope} AND status = ?", array_merge($params, ['suggested']));

    $params = [];
    $scope = dashboard_scope_sql($params, $accountId, $propertyId);
    $draftItems = dashboard_count($dbo, "SELECT COUNT(*) AS total FROM content_items WHERE {$scope} AND status = ?", array_merge($params, ['draft']));

    $params = [];
    $scope = dashboard_scope_sql($params, $accountId, $propertyId);
    $convertedIdeas = dashboard_count($dbo, "SELECT COUNT(*) AS total FROM content_ideas WHERE {$scope} AND status = ?", array_merge($params, ['converted']));

    $params = [];
    $scope = dashboard_scope_sql($params, $accountId, $propertyId);
    $scheduledIdeas = dashboard_count($dbo, "SELECT COUNT(*) AS total FROM content_ideas WHERE {$scope} AND status = ? AND content_item_id IS NULL", array_merge($params, ['accepted']));

    return [
        'stat_new_ideas' => [
            'value' => $newIdeas,
            'label' => 'suggested',
            'url' => 'content_ideas.php?search_status=suggested',
        ],
        'stat_draft_items' => [
            'value' => $draftItems,
            'label' => 'drafts',
            'url' => 'content_items.php',
        ],
        'stat_converted_ideas' => [
            'value' => $convertedIdeas,
            'label' => 'converted',
            'url' => 'content_ideas.php?search_status=converted',
        ],
        'stat_scheduled_ideas' => [
            'value' => $scheduledIdeas,
            'label' => 'accepted',
            'url' => 'content_ideas.php?search_status=accepted',
        ],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_dashboard') {
    $raw = (string)($_POST['dashboard_json'] ?? '');
    $decoded = json_decode($raw, true);
    $config = dashboard_normalize_config($decoded);
    dashboard_save_config($dbo, $accountId, $dashboardSettingKey, $config);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['result' => 'success']);
    exit;
}

$dashboardConfig = dashboard_normalize_config(dashboard_load_config($dbo, $accountId, $dashboardSettingKey));
$stats = dashboard_stat_data($dbo, $accountId, $propertyId);
$dashboardJson = json_encode($dashboardConfig, JSON_UNESCAPED_UNICODE);
$statsJson = json_encode($stats, JSON_UNESCAPED_UNICODE);
$propertyContext = $propertyId > 0 ? $current_property_name : 'All properties';

?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($user_language ?: 'gr', ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="utf-8">
    <title><?php echo app::$project_name; ?></title>
    <?php include "_head.php"; ?>
    <style>
        .dashboard-head { display:flex; justify-content:space-between; gap:12px; align-items:flex-end; margin-bottom:18px; }
        .dashboard-title h1 { margin:0 0 4px; font-size:28px; }
        .dashboard-context { color:#52606d; }
        .dashboard-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:flex-end; position:relative; margin-left:auto; }
        .dashboard-grid { column-count:3; column-gap:16px; }
        .home-block { background:#fff; border:1px solid #d9e2ec; border-radius:8px; box-shadow:0 6px 16px rgba(15,23,42,.07); min-height:150px; overflow:hidden; display:inline-block; width:100%; margin:0 0 16px; break-inside:avoid; }
        .home-block.dragging { opacity:.55; }
        .card-head { display:flex; justify-content:space-between; gap:10px; align-items:center; padding:12px 14px; border-bottom:1px solid #edf2f7; background:#fbfdff; }
        .card-head input { border:0; background:transparent; font-weight:700; width:100%; min-width:0; padding:4px 0; }
        .card-head input:focus { outline:0; border-bottom:1px solid #4f8cc9; }
        .card-tools { display:flex; gap:4px; flex-shrink:0; }
        .icon-btn { border:1px solid #d9e2ec; background:#fff; border-radius:6px; width:28px; height:28px; line-height:24px; text-align:center; cursor:pointer; }
        .card-body { padding:14px; }
        .stat-value { font-size:38px; line-height:1; font-weight:800; margin-bottom:6px; }
        .stat-link { display:inline-block; margin-top:10px; }
        .links-list, .todo-list { display:flex; flex-direction:column; gap:8px; }
        .link-row, .todo-row { display:grid; grid-template-columns:1fr 1fr 54px 28px; gap:6px; align-items:center; }
        .todo-row { grid-template-columns:28px 1fr 28px; }
        .mini-input, .notes-area { width:100%; border:1px solid #d9e2ec; border-radius:6px; padding:7px 8px; }
        .notes-area { min-height:120px; resize:vertical; }
        .link-open { display:inline-flex; align-items:center; justify-content:center; min-height:32px; border:1px solid #d9e2ec; border-radius:6px; background:#fbfdff; font-size:12px; }
        .empty-state { color:#52606d; padding:28px; border:1px dashed #bcccdc; border-radius:8px; background:#fbfdff; }
        .add-card-panel { display:none; position:absolute; right:0; top:38px; z-index:20; background:#fff; border:1px solid #d9e2ec; border-radius:8px; box-shadow:0 12px 28px rgba(15,23,42,.16); padding:10px; width:260px; }
        .add-card-panel.open { display:block; }
        .add-card-panel .add-menu { margin-bottom:8px; }
        .add-card-panel-actions { display:flex; justify-content:flex-end; gap:8px; }
        .save-status { color:#52606d; min-width:90px; }
        @media (max-width:1100px) { .dashboard-grid { column-count:2; } }
        @media (max-width:720px) { .dashboard-head { align-items:flex-start; flex-direction:column; } .dashboard-grid { column-count:1; } .link-row { grid-template-columns:1fr; } .add-card-panel { left:0; right:auto; } }
    </style>
</head>
<body class="home">
    <?php include "blocks/header.php"; ?>

    <div class="padding-20">
        <div class="dashboard-head">
            <div class="dashboard-title">
                <h1>Dashboard</h1>
                <div class="dashboard-context">
                    <?php echo htmlspecialchars($current_account_name, ENT_QUOTES, 'UTF-8'); ?> ·
                    <?php echo htmlspecialchars($propertyContext, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
            <div class="dashboard-actions">
                <button type="button" class="btn btn-primary" id="add-card-btn">Add card</button>
                <div class="add-card-panel" id="add-card-panel">
                    <select class="form-control add-menu" id="add-card-type">
                        <option value="stat_new_ideas">New content ideas</option>
                        <option value="stat_draft_items">Draft content items</option>
                        <option value="stat_converted_ideas">Converted ideas</option>
                        <option value="stat_scheduled_ideas">Scheduled ideas</option>
                        <option value="links">Useful links</option>
                        <option value="todo">To-do</option>
                        <option value="notes">Notes</option>
                    </select>
                    <div class="add-card-panel-actions">
                        <button type="button" class="btn btn-default btn-sm" id="cancel-add-card-btn">Cancel</button>
                        <button type="button" class="btn btn-primary btn-sm" id="confirm-add-card-btn">Add</button>
                    </div>
                </div>
                <span class="save-status" id="save-status"></span>
            </div>
        </div>

        <div id="dashboard-grid" class="dashboard-grid"></div>
        <div id="dashboard-empty" class="empty-state" style="display:none;">Your dashboard is empty. Add a card to get started.</div>
    </div>

    <?php include "blocks/footer.php"; ?>

    <script>
        const dashboardStats = <?php echo $statsJson ?: '{}'; ?>;
        let dashboard = <?php echo $dashboardJson ?: '{"cards":[]}'; ?>;
        const grid = document.getElementById('dashboard-grid');
        const empty = document.getElementById('dashboard-empty');
        const saveStatus = document.getElementById('save-status');
        const addCardPanel = document.getElementById('add-card-panel');
        let saveTimer = null;

        const defaultTitles = {
            stat_new_ideas: 'New content ideas',
            stat_draft_items: 'Draft content items',
            stat_converted_ideas: 'Converted ideas',
            stat_scheduled_ideas: 'Scheduled ideas',
            links: 'Useful links',
            todo: 'To-do',
            notes: 'Notes'
        };

        function escapeHtml(value) {
            return (value || '').toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function linkHref(value) {
            const url = (value || '').toString().trim();
            if (!url || /^javascript:/i.test(url)) return '';
            return escapeHtml(url);
        }

        function isExternalLink(value) {
            const url = (value || '').toString().trim();
            return /^(https?:)?\/\//i.test(url);
        }

        function cardTemplate(card, index) {
            const title = escapeHtml(card.title || defaultTitles[card.type] || 'Card');
            return `
                <div class="home-block" draggable="true" data-card-id="${escapeHtml(card.id)}">
                    <div class="card-head">
                        <input value="${title}" aria-label="Card title" title="Card title" placeholder="Card title" data-action="title">
                        <div class="card-tools">
                            <button type="button" class="icon-btn" title="Move left" data-action="up">↑</button>
                            <button type="button" class="icon-btn" title="Move right" data-action="down">↓</button>
                            <button type="button" class="icon-btn" title="Remove" data-action="remove">×</button>
                        </div>
                    </div>
                    <div class="card-body">${cardBody(card, index)}</div>
                </div>
            `;
        }

        function cardBody(card, index) {
            if (card.type.startsWith('stat_')) {
                const stat = dashboardStats[card.type] || { value: 0, label: '', url: '#' };
                return `
                    <div class="stat-value">${escapeHtml(stat.value)}</div>
                    <div>${escapeHtml(stat.label)}</div>
                    <a class="stat-link" href="${escapeHtml(stat.url)}">Open</a>
                `;
            }
            if (card.type === 'links') {
                const links = card.links || [];
                return `
                    <div class="links-list">
                        ${links.map((link, linkIndex) => `
                            <div class="link-row">
                                <input class="mini-input" value="${escapeHtml(link.label)}" placeholder="Label" data-action="link-label" data-index="${linkIndex}">
                                <input class="mini-input" value="${escapeHtml(link.url)}" placeholder="URL" data-action="link-url" data-index="${linkIndex}">
                                ${linkHref(link.url) ? `<a class="link-open" href="${linkHref(link.url)}"${isExternalLink(link.url) ? ' target="_blank" rel="noopener noreferrer"' : ''}>Open</a>` : '<span></span>'}
                                <button type="button" class="icon-btn" data-action="remove-link" data-index="${linkIndex}">×</button>
                            </div>
                        `).join('')}
                    </div>
                    <button type="button" class="btn btn-default btn-sm" style="margin-top:10px;" data-action="add-link">Add link</button>
                `;
            }
            if (card.type === 'todo') {
                const items = card.items || [];
                return `
                    <div class="todo-list">
                        ${items.map((item, itemIndex) => `
                            <div class="todo-row">
                                <input type="checkbox" ${item.done ? 'checked' : ''} data-action="todo-done" data-index="${itemIndex}">
                                <input class="mini-input" value="${escapeHtml(item.text)}" placeholder="Task" data-action="todo-text" data-index="${itemIndex}">
                                <button type="button" class="icon-btn" data-action="remove-todo" data-index="${itemIndex}">×</button>
                            </div>
                        `).join('')}
                    </div>
                    <button type="button" class="btn btn-default btn-sm" style="margin-top:10px;" data-action="add-todo">Add task</button>
                `;
            }
            return `<textarea class="notes-area" data-action="notes" placeholder="Write notes...">${escapeHtml(card.text || '')}</textarea>`;
        }

        function renderDashboard() {
            grid.innerHTML = (dashboard.cards || []).map(cardTemplate).join('');
            empty.style.display = dashboard.cards && dashboard.cards.length ? 'none' : 'block';
        }

        function findCardIndex(cardId) {
            return dashboard.cards.findIndex(card => card.id === cardId);
        }

        function scheduleSave() {
            clearTimeout(saveTimer);
            saveStatus.textContent = 'Saving...';
            saveTimer = setTimeout(saveDashboard, 350);
        }

        function saveDashboard() {
            const form = new FormData();
            form.append('action', 'save_dashboard');
            form.append('dashboard_json', JSON.stringify(dashboard));
            fetch('home.php', { method: 'POST', body: form, credentials: 'same-origin' })
                .then(response => response.json())
                .then(data => {
                    saveStatus.textContent = data.result === 'success' ? 'Saved' : 'Error';
                    setTimeout(() => { if (saveStatus.textContent === 'Saved') saveStatus.textContent = ''; }, 1200);
                })
                .catch(() => { saveStatus.textContent = 'Error'; });
        }

        function addCard(type) {
            const card = { id: 'card-' + Date.now() + '-' + Math.floor(Math.random() * 1000), type, title: defaultTitles[type] || 'Card' };
            if (type === 'links') card.links = [{ label: 'Content ideas', url: 'content_ideas.php' }];
            if (type === 'todo') card.items = [{ text: 'New task', done: false }];
            if (type === 'notes') card.text = '';
            dashboard.cards.push(card);
            renderDashboard();
            scheduleSave();
        }

        document.getElementById('add-card-btn').addEventListener('click', event => {
            event.stopPropagation();
            addCardPanel.classList.toggle('open');
        });

        document.getElementById('cancel-add-card-btn').addEventListener('click', () => {
            addCardPanel.classList.remove('open');
        });

        document.getElementById('confirm-add-card-btn').addEventListener('click', () => {
            addCard(document.getElementById('add-card-type').value);
            addCardPanel.classList.remove('open');
        });

        addCardPanel.addEventListener('click', event => {
            event.stopPropagation();
        });

        document.addEventListener('click', () => {
            addCardPanel.classList.remove('open');
        });

        grid.addEventListener('input', event => {
            const block = event.target.closest('.home-block');
            if (!block) return;
            const card = dashboard.cards[findCardIndex(block.dataset.cardId)];
            if (!card) return;
            const action = event.target.dataset.action;
            const itemIndex = parseInt(event.target.dataset.index || '-1', 10);
            if (action === 'title') card.title = event.target.value;
            if (action === 'notes') card.text = event.target.value;
            if (action === 'link-label' && card.links[itemIndex]) card.links[itemIndex].label = event.target.value;
            if (action === 'link-url' && card.links[itemIndex]) card.links[itemIndex].url = event.target.value;
            if (action === 'todo-text' && card.items[itemIndex]) card.items[itemIndex].text = event.target.value;
            scheduleSave();
        });

        grid.addEventListener('change', event => {
            const block = event.target.closest('.home-block');
            if (!block) return;
            const card = dashboard.cards[findCardIndex(block.dataset.cardId)];
            if (!card) return;
            if (event.target.dataset.action === 'todo-done') {
                const itemIndex = parseInt(event.target.dataset.index || '-1', 10);
                if (card.items[itemIndex]) card.items[itemIndex].done = event.target.checked;
                scheduleSave();
            }
        });

        grid.addEventListener('click', event => {
            const action = event.target.dataset.action;
            if (!action) return;
            const structuralActions = ['remove', 'up', 'down', 'add-link', 'remove-link', 'add-todo', 'remove-todo'];
            if (!structuralActions.includes(action)) return;
            const block = event.target.closest('.home-block');
            if (!block) return;
            const index = findCardIndex(block.dataset.cardId);
            const card = dashboard.cards[index];
            if (!card) return;
            const itemIndex = parseInt(event.target.dataset.index || '-1', 10);
            if (action === 'remove') {
                if (!window.confirm('Delete this card?')) return;
                dashboard.cards.splice(index, 1);
            }
            if (action === 'up' && index > 0) [dashboard.cards[index - 1], dashboard.cards[index]] = [dashboard.cards[index], dashboard.cards[index - 1]];
            if (action === 'down' && index < dashboard.cards.length - 1) [dashboard.cards[index + 1], dashboard.cards[index]] = [dashboard.cards[index], dashboard.cards[index + 1]];
            if (action === 'add-link') card.links = [...(card.links || []), { label: 'New link', url: '' }];
            if (action === 'remove-link' && card.links) card.links.splice(itemIndex, 1);
            if (action === 'add-todo') card.items = [...(card.items || []), { text: 'New task', done: false }];
            if (action === 'remove-todo' && card.items) card.items.splice(itemIndex, 1);
            renderDashboard();
            scheduleSave();
        });

        let dragId = null;
        grid.addEventListener('dragstart', event => {
            if (event.target.closest('input, textarea, select, button, a')) {
                event.preventDefault();
                return;
            }
            const block = event.target.closest('.home-block');
            if (!block) return;
            if (!event.target.closest('.card-head')) {
                event.preventDefault();
                return;
            }
            dragId = block.dataset.cardId;
            block.classList.add('dragging');
        });
        grid.addEventListener('dragend', event => {
            const block = event.target.closest('.home-block');
            if (block) block.classList.remove('dragging');
            dragId = null;
        });
        grid.addEventListener('dragover', event => event.preventDefault());
        grid.addEventListener('drop', event => {
            event.preventDefault();
            const target = event.target.closest('.home-block');
            if (!target || !dragId || target.dataset.cardId === dragId) return;
            const from = findCardIndex(dragId);
            const to = findCardIndex(target.dataset.cardId);
            if (from < 0 || to < 0) return;
            const [card] = dashboard.cards.splice(from, 1);
            dashboard.cards.splice(to, 0, card);
            renderDashboard();
            scheduleSave();
        });

        renderDashboard();
    </script>
</body>
</html>
