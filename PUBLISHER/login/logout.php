<?php
require_once __DIR__ . '/../php/config.php';
require_once __DIR__ . '/auth-helpers.php';

auth_start_session();

$cookieNames = [
    app::$slug . 'ID',
    app::$slug . 'HASH',
    app::$slug . 'USER-FULLNAME',
    app::$slug . 'USER-EMAIL',
    app::$slug . 'USER-LANGUAGE',
    'USER_ACCESS'
];

foreach ($cookieNames as $cookieName) {
    setcookie($cookieName, '', time() - 3600, '/');
    unset($_COOKIE[$cookieName]);
}

foreach (array_keys($_SESSION) as $sessionKey) {
    if (strpos($sessionKey, app::$slug) === 0 || $sessionKey === 'USER_ACCESS') {
        unset($_SESSION[$sessionKey]);
    }
}

session_regenerate_id(true);
session_destroy();

header('Location: login.php');
exit;
