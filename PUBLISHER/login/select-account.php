<?php
require_once __DIR__ . '/../php/config.php';
require_once __DIR__ . '/../php/db.php';
require_once __DIR__ . '/auth-helpers.php';
require_once __DIR__ . '/../php/access.php';

auth_start_session();

$errors = [];
$dbo = auth_db($errors);

if (!isset($_SESSION[app::$slug . 'AUTHORIZED']) || $_SESSION[app::$slug . 'AUTHORIZED'] != 1) {
    header('Location: login.php');
    exit;
}

$userId = (int)($_SESSION[app::$slug . 'USER-ID'] ?? 0);
if ($userId <= 0) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dbo) {
    $accountId = (int)($_POST['account_id'] ?? 0);
    if ($accountId <= 0 || !publisher_set_current_account($dbo, $userId, $accountId)) {
        $errors[] = 'Δεν είναι δυνατή η επιλογή αυτού του account.';
    } else {
        header('Location: ' . app::$host);
        exit;
    }
}

$accounts = $dbo ? publisher_user_accounts($dbo, $userId) : false;
?>
<!doctype html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo auth_h(app::$project_name); ?> - Account</title>
    <link rel="icon" type="image/png" href="../img/favicon.png">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">
    <style>
        body { background:#f5f7fb; color:#1f2933; font-family:Arial, sans-serif; }
        .auth-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
        .auth-box { width:100%; max-width:560px; background:#fff; border:1px solid #d9e2ec; border-radius:8px; padding:28px; box-shadow:0 12px 30px rgba(15, 23, 42, .08); }
        h1 { font-size:24px; margin:0 0 20px; font-weight:700; }
        .account-list { display:grid; gap:10px; }
        .account-button { width:100%; text-align:left; padding:14px 16px; border:1px solid #d9e2ec; background:#fff; border-radius:8px; }
        .account-button:hover { border-color:#185adb; background:#f8fbff; }
        .account-name { display:block; font-weight:700; }
        .account-role { display:block; color:#52606d; font-size:13px; margin-top:4px; text-transform:capitalize; }
        .logout { display:inline-block; margin-top:16px; }
    </style>
</head>
<body>
<main class="auth-wrap">
    <section class="auth-box">
        <h1>Επιλογή account</h1>

        <?php if ($errors) { ?>
            <div class="alert alert-danger"><?php echo auth_h(implode(' ', $errors)); ?></div>
        <?php } ?>

        <?php if (!$accounts) { ?>
            <div class="alert alert-warning">Δεν βρέθηκε ενεργό account για τον χρήστη σας.</div>
        <?php } else { ?>
            <form method="post" action="select-account.php" class="account-list">
                <?php foreach ($accounts as $account) { ?>
                    <button class="account-button" type="submit" name="account_id" value="<?php echo auth_h($account['id']); ?>">
                        <span class="account-name"><?php echo auth_h($account['name']); ?></span>
                        <span class="account-role"><?php echo auth_h($account['role']); ?></span>
                    </button>
                <?php } ?>
            </form>
        <?php } ?>

        <a class="logout" href="logout.php">Αποσύνδεση</a>
    </section>
</main>
</body>
</html>
