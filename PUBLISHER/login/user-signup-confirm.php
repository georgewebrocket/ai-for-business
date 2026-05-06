<?php
require_once __DIR__ . '/../php/config.php';
require_once __DIR__ . '/../php/db.php';
require_once __DIR__ . '/auth-helpers.php';

$message = 'Ο σύνδεσμος επιβεβαίωσης δεν είναι έγκυρος.';
$success = false;

$dbo = auth_db();
$user = $dbo ? auth_verify_token($_GET['token'] ?? '', 'signup_confirm', $dbo) : false;

if (!$dbo) {
    $message = 'Δεν είναι δυνατή η σύνδεση με τη βάση δεδομένων αυτή τη στιγμή.';
}

if ($user) {
    $now = date('Y-m-d H:i:s');
    $dbo->execSQL('UPDATE users SET status = ?, updated_at = ? WHERE id = ?', ['active', $now, $user['id']]);
    $dbo->execSQL('UPDATE account_users SET status = ?, updated_at = ? WHERE user_id = ?', ['active', $now, $user['id']]);
    $message = 'Ο λογαριασμός σας ενεργοποιήθηκε.';
    $success = true;
}
?>
<!doctype html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo auth_h(app::$project_name); ?> - Confirmation</title>
    <link rel="icon" type="image/png" href="../img/favicon.png">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">
    <style>
        body { background:#f5f7fb; color:#1f2933; font-family:Arial, sans-serif; }
        .auth-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
        .auth-box { width:100%; max-width:460px; background:#fff; border:1px solid #d9e2ec; border-radius:8px; padding:28px; box-shadow:0 12px 30px rgba(15, 23, 42, .08); text-align:center; }
        h1 { font-size:24px; margin:0 0 16px; font-weight:700; }
        .btn-primary { margin-top:12px; background:#185adb; border-color:#185adb; }
    </style>
</head>
<body>
<main class="auth-wrap">
    <section class="auth-box">
        <h1><?php echo $success ? 'Επιβεβαίωση ολοκληρώθηκε' : 'Δεν έγινε επιβεβαίωση'; ?></h1>
        <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?>"><?php echo auth_h($message); ?></div>
        <a class="btn btn-primary" href="login.php">Σύνδεση</a>
    </section>
</main>
</body>
</html>
