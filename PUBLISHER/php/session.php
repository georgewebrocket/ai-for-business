<?php

require_once __DIR__ . '/access.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionPath = session_save_path();
    if ($sessionPath === '' || !publisher_session_path_is_writable($sessionPath)) {
        $fallbackPath = __DIR__ . '/../tmp/sessions';
        if (!is_dir($fallbackPath)) {
            @mkdir($fallbackPath, 0775, true);
        }
        if (is_dir($fallbackPath) && publisher_session_path_is_writable($fallbackPath)) {
            session_save_path($fallbackPath);
        } elseif (publisher_session_path_is_writable('/tmp')) {
            session_save_path('/tmp');
        }
    }

    session_start();
}

function publisher_session_path_is_writable($path) {
    if ($path === '') {
        return false;
    }

    $openBasedir = ini_get('open_basedir');
    if ($openBasedir !== '') {
        $realPath = @realpath($path);
        $checkPath = $realPath !== false ? $realPath : $path;
        $allowed = false;

        foreach (explode(PATH_SEPARATOR, $openBasedir) as $allowedPath) {
            $allowedPath = rtrim($allowedPath, DIRECTORY_SEPARATOR);
            if ($allowedPath === '') {
                continue;
            }

            $realAllowedPath = @realpath($allowedPath);
            $checkAllowedPath = $realAllowedPath !== false ? $realAllowedPath : $allowedPath;

            if (strpos($checkPath, $checkAllowedPath) === 0) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            return false;
        }
    }

    return @is_writable($path);
}

if (!isset($_SESSION[app::$slug . 'AUTHORIZED']) || $_SESSION[app::$slug . 'AUTHORIZED']==0) {
    if (isset($_COOKIE[app::$slug . 'ID']) and isset($_COOKIE[app::$slug . 'HASH'])){       
        $sql = "SELECT * FROM users WHERE id=? LIMIT 1";
        $userdata = $db1->getRS($sql,array(intval($_COOKIE[app::$slug . 'ID'])));

        $storedHash = $userdata && isset($userdata[0]['password_hash']) ? $userdata[0]['password_hash'] : '';

        if (!$userdata || $storedHash !== $_COOKIE[app::$slug . 'HASH'] || $userdata[0]['id'] != $_COOKIE[app::$slug . 'ID'] || $userdata[0]['status'] !== 'active') {

            setcookie(app::$slug . "ID", "", time() - 3600*24, "/");
            setcookie(app::$slug . "HASH", "", time() - 3600*24, "/");
            setcookie(app::$slug . "USER-FULLNAME", "", time() - 3600*24, "/");
            setcookie(app::$slug . "USER-LANGUAGE", "", time() - 3600*24, "/");
            header("Location: ".app::$host."login/login.php");
            exit;
        }
        else {
            $_SESSION[app::$slug . 'AUTHORIZED'] = 1;
            $_SESSION[app::$slug . 'USER-ID'] = $userdata[0]['id'];
            $_SESSION[app::$slug . 'USER-FULLNAME'] = $userdata[0]['name'];
            $_SESSION[app::$slug . 'USER-EMAIL'] = $userdata[0]['email'];
        }
    }
    else{
        header("Location: ".app::$host."login/login.php");
        exit;
    }
}

if (isset($_SESSION[app::$slug . 'AUTHORIZED']) && $_SESSION[app::$slug . 'AUTHORIZED']<>1) {
    header("Location: ".app::$host."login/login.php");
    exit;
}

$_SESSION[app::$slug . 'START'] = time(); // taking now logged in time

if(!isset($_SESSION[app::$slug . 'EXPIRE'])){
    $_SESSION[app::$slug . 'EXPIRE'] = $_SESSION[app::$slug . 'START'] + (3600*24) ; // ending a session in 8 hours
}
$now = time(); // checking the time now when home page starts

if(isset($_SESSION[app::$slug . 'EXPIRE'])){
    if($now > $_SESSION[app::$slug . 'EXPIRE'])
    {
        session_destroy();
        header("Location: ".app::$host."login/login.php");
    exit;
    }
}


$userid = 0;
if(isset($_COOKIE[app::$slug . 'ID'])){$userid = $_COOKIE[app::$slug . 'ID'];}
if(isset($_SESSION[app::$slug . 'USER-ID'])){$userid = $_SESSION[app::$slug . 'USER-ID'];}

if ($userid > 0 && !isset($_SESSION[app::$slug . 'CURRENT_ACCOUNT_ID'])) {
    $lastAccountId = null;
    $userAccountRows = $db1->getRS('SELECT last_account_id FROM users WHERE id = ? LIMIT 1', [$userid]);
    if ($userAccountRows) {
        $lastAccountId = $userAccountRows[0]['last_account_id'];
    }

    $accountSelection = publisher_select_default_account($db1, $userid, $lastAccountId);
    if ($accountSelection['status'] !== 'selected') {
        header("Location: ".app::$host."login/select-account.php");
        exit;
    }
}
elseif ($userid > 0 && isset($_SESSION[app::$slug . 'CURRENT_ACCOUNT_ID'])) {
    $activeAccount = publisher_set_current_account($db1, $userid, $_SESSION[app::$slug . 'CURRENT_ACCOUNT_ID'], false);
    if (!$activeAccount) {
        publisher_clear_current_account();
        header("Location: ".app::$host."login/select-account.php");
        exit;
    }
}


$user_access="";
if(isset($_SESSION['USER_ACCESS'])){$user_access = $_SESSION['USER_ACCESS'];}
if(isset($_COOKIE['USER_ACCESS'])){$user_access = $_COOKIE['USER_ACCESS'];}

$current_account_id = publisher_current_account_id();
$current_account_name = publisher_current_account_name();
$current_account_role = publisher_current_account_role();

if ($current_account_id && publisher_current_property_id()) {
    publisher_validate_current_property($db1, $current_account_id);
}

$current_property_id = publisher_current_property_id();
$current_property_name = publisher_current_property_name();

switch ($current_account_role) {
    case 'owner':
    case 'admin':
        $user_access = '[1][2][3][4][5]';
        break;
    case 'editor':
        $user_access = '[2][3][4]';
        break;
    case 'author':
        $user_access = '[3][4]';
        break;
    case 'viewer':
        $user_access = '[4]';
        break;
}

$user_fullname="";
if(isset($_SESSION[app::$slug . 'USER-FULLNAME'])){$user_fullname = $_SESSION[app::$slug . 'USER-FULLNAME'];}
if(isset($_COOKIE[app::$slug . 'USER-FULLNAME'])){$user_fullname = $_COOKIE[app::$slug . 'USER-FULLNAME'];}

$user_lang_id="";
if(isset($_SESSION[app::$slug . 'USER-LANGUAGE'])){$user_lang_id = $_SESSION[app::$slug . 'USER-LANGUAGE'];}
if(isset($_COOKIE[app::$slug . 'USER-LANGUAGE'])){$user_lang_id = $_COOKIE[app::$slug . 'USER-LANGUAGE'];}
switch ($user_lang_id) {
    case 1:
        $user_language = 'gr';
        break;

    case 2:
        $user_language = 'en';
        break;
    
    default:
        # code...
        break;
}



$bgColor = "";
$textColor = "";
$bgColorHeader = "";
$textColorHeader = "";
$homeBgImg = "";
$userCSS = "";

if ($userid > 0 && !class_exists('users') && is_file(__DIR__ . '/dataobjects.php')) {
    require_once __DIR__ . '/dataobjects.php';
}

if ($userid>0 && class_exists('users')) {
    $user = new users($dbo, $userid);
    $bgColor = $user->bodybgcolor();
    $textColor = $user->bodytextcolor();
    $bgColorHeader = $user->headerbgcolor();
    $textColorHeader = $user->headertextcolor();
    $homeBgImg = $user->homebgimage();
    $userCSS = $user->css();
    
}
