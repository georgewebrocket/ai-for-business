<?php

function publisher_role_permissions() {
    return [
        'owner' => ['dashboard', 'accounts', 'users', 'team', 'properties', 'content_types', 'content_categories', 'writing_styles', 'image_styles', 'content_templates', 'settings', 'content', 'ai', 'channels', 'help'],
        'admin' => ['dashboard', 'users', 'team', 'properties', 'content_types', 'content_categories', 'writing_styles', 'image_styles', 'content_templates', 'settings', 'content', 'ai', 'channels', 'help'],
        'editor' => ['dashboard', 'content_types', 'content_categories', 'writing_styles', 'image_styles', 'content_templates', 'content', 'ai', 'channels', 'help'],
        'author' => ['dashboard', 'content', 'ai', 'help'],
        'viewer' => ['dashboard', 'content_view', 'help'],
    ];
}

function publisher_role_can($role, $permission) {
    $permissions = publisher_role_permissions();
    return isset($permissions[$role]) && in_array($permission, $permissions[$role], true);
}

function publisher_current_account_id() {
    return $_SESSION[app::$slug . 'CURRENT_ACCOUNT_ID'] ?? 0;
}

function publisher_current_account_role() {
    return $_SESSION[app::$slug . 'CURRENT_ACCOUNT_ROLE'] ?? '';
}

function publisher_current_account_name() {
    return $_SESSION[app::$slug . 'CURRENT_ACCOUNT_NAME'] ?? '';
}

function publisher_current_property_id() {
    return $_SESSION[app::$slug . 'CURRENT_PROPERTY_ID'] ?? 0;
}

function publisher_current_property_name() {
    return $_SESSION[app::$slug . 'CURRENT_PROPERTY_NAME'] ?? '';
}

function publisher_can_access($permission) {
    return publisher_role_can(publisher_current_account_role(), $permission);
}

function publisher_require_permission($permission) {
    if (!publisher_can_access($permission)) {
        http_response_code(403);
        die('Access denied');
    }
}

function publisher_user_accounts($dbo, $userId) {
    return $dbo->getRS(
        "SELECT a.id, a.name, a.company_name, a.status AS account_status,
                au.role, au.status AS membership_status
         FROM account_users au
         INNER JOIN accounts a ON a.id = au.account_id
         WHERE au.user_id = ? AND au.status = 'active' AND a.status = 'active'
         ORDER BY a.name",
        [$userId]
    );
}

function publisher_set_current_account($dbo, $userId, $accountId, $clearProperty = true) {
    $rs = $dbo->getRS(
        "SELECT a.id, a.name, a.company_name, au.role
         FROM account_users au
         INNER JOIN accounts a ON a.id = au.account_id
         WHERE au.user_id = ? AND au.account_id = ?
           AND au.status = 'active' AND a.status = 'active'
         LIMIT 1",
        [$userId, $accountId]
    );

    if (!$rs) {
        return false;
    }

    $_SESSION[app::$slug . 'CURRENT_ACCOUNT_ID'] = $rs[0]['id'];
    $_SESSION[app::$slug . 'CURRENT_ACCOUNT_NAME'] = $rs[0]['name'];
    $_SESSION[app::$slug . 'CURRENT_ACCOUNT_ROLE'] = $rs[0]['role'];
    if ($clearProperty) {
        publisher_clear_current_property();
    }

    $dbo->execSQL(
        'UPDATE users SET last_account_id = ?, updated_at = ? WHERE id = ?',
        [$rs[0]['id'], date('Y-m-d H:i:s'), $userId]
    );

    return $rs[0];
}

function publisher_clear_current_account() {
    unset($_SESSION[app::$slug . 'CURRENT_ACCOUNT_ID']);
    unset($_SESSION[app::$slug . 'CURRENT_ACCOUNT_NAME']);
    unset($_SESSION[app::$slug . 'CURRENT_ACCOUNT_ROLE']);
    publisher_clear_current_property();
}

function publisher_set_current_property($dbo, $accountId, $propertyId) {
    $rs = $dbo->getRS(
        "SELECT id, name
         FROM properties
         WHERE id = ? AND account_id = ? AND status = 'active'
         LIMIT 1",
        [$propertyId, $accountId]
    );

    if (!$rs) {
        return false;
    }

    $_SESSION[app::$slug . 'CURRENT_PROPERTY_ID'] = $rs[0]['id'];
    $_SESSION[app::$slug . 'CURRENT_PROPERTY_NAME'] = $rs[0]['name'];

    return $rs[0];
}

function publisher_clear_current_property() {
    unset($_SESSION[app::$slug . 'CURRENT_PROPERTY_ID']);
    unset($_SESSION[app::$slug . 'CURRENT_PROPERTY_NAME']);
}

function publisher_validate_current_property($dbo, $accountId) {
    $propertyId = publisher_current_property_id();
    if (!$propertyId) {
        return false;
    }

    return publisher_set_current_property($dbo, $accountId, $propertyId);
}

function publisher_require_property() {
    if (!publisher_current_property_id()) {
        header('Location: ' . app::$host . 'properties.php');
        exit;
    }
}

function publisher_select_default_account($dbo, $userId, $lastAccountId = null) {
    if ($lastAccountId) {
        $selected = publisher_set_current_account($dbo, $userId, $lastAccountId);
        if ($selected) {
            return ['status' => 'selected', 'account' => $selected];
        }
    }

    $accounts = publisher_user_accounts($dbo, $userId);
    if (!$accounts) {
        return ['status' => 'none', 'accounts' => []];
    }

    if (count($accounts) === 1) {
        $selected = publisher_set_current_account($dbo, $userId, $accounts[0]['id']);
        return ['status' => 'selected', 'account' => $selected];
    }

    return ['status' => 'choose', 'accounts' => $accounts];
}
