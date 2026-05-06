<?php
require_once __DIR__ . '/../php/config.php';
require_once __DIR__ . '/../php/db.php';
require_once __DIR__ . '/auth-helpers.php';
require_once __DIR__ . '/../php/access.php';

auth_start_session();

$errors = [];
$email = '';

function redirect_to_home() {
    header('Location: ' . app::$host);
    exit;
}

if (isset($_SESSION[app::$slug . 'AUTHORIZED']) && $_SESSION[app::$slug . 'AUTHORIZED'] == 1) {
    redirect_to_home();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Συμπληρώστε σωστό email.';
    }

    if ($password === '') {
        $errors[] = 'Συμπληρώστε τον κωδικό πρόσβασης.';
    }

    if (!$errors) {
        $dbo = auth_db($errors);
    }

    if (!$errors && $dbo) {
        $rs = $dbo->getRS(
            'SELECT id, name, email, password_hash, status, last_account_id FROM users WHERE email = ? LIMIT 1',
            [$email]
        );

        if (!$rs || !password_verify($password, $rs[0]['password_hash'])) {
            $errors[] = 'Λάθος email ή κωδικός πρόσβασης.';
        } elseif ($rs[0]['status'] !== 'active') {
            $errors[] = 'Ο λογαριασμός δεν έχει ενεργοποιηθεί ακόμα.';
        } else {
            session_regenerate_id(true);
            $_SESSION[app::$slug . 'AUTHORIZED'] = 1;
            $_SESSION[app::$slug . 'USER-ID'] = $rs[0]['id'];
            $_SESSION[app::$slug . 'USER-FULLNAME'] = $rs[0]['name'];
            $_SESSION[app::$slug . 'USER-EMAIL'] = $rs[0]['email'];
            $_SESSION[app::$slug . 'EXPIRE'] = time() + (3600 * 24);
            publisher_clear_current_account();

            if (!empty($_POST['remember'])) {
                setcookie(app::$slug . 'ID', $rs[0]['id'], time() + (3600 * 24 * 30), '/');
                setcookie(app::$slug . 'HASH', $rs[0]['password_hash'], time() + (3600 * 24 * 30), '/');
                setcookie(app::$slug . 'USER-FULLNAME', $rs[0]['name'], time() + (3600 * 24 * 30), '/');
            }

            $dbo->execSQL('UPDATE users SET last_login_at = ?, updated_at = ? WHERE id = ?', [
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s'),
                $rs[0]['id']
            ]);

            $accountSelection = publisher_select_default_account($dbo, $rs[0]['id'], $rs[0]['last_account_id']);
            if ($accountSelection['status'] === 'none') {
                $errors[] = 'Δεν έχετε πρόσβαση σε ενεργό account.';
                publisher_clear_current_account();
                unset($_SESSION[app::$slug . 'AUTHORIZED']);
                unset($_SESSION[app::$slug . 'USER-ID']);
                unset($_SESSION[app::$slug . 'USER-FULLNAME']);
                unset($_SESSION[app::$slug . 'USER-EMAIL']);
            } elseif ($accountSelection['status'] === 'choose') {
                header('Location: select-account.php');
                exit;
            } else {
                redirect_to_home();
            }
        }
    }
}
?>
<!doctype html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo auth_h(app::$project_name); ?> - Login</title>
    <link rel="icon" type="image/png" href="../img/favicon.png">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">
    <style>
        body { background:#f5f7fb; color:#1f2933; font-family:Arial, sans-serif; }
        .auth-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
        .auth-box { width:100%; max-width:430px; background:#fff; border:1px solid #d9e2ec; border-radius:8px; padding:28px; box-shadow:0 12px 30px rgba(15, 23, 42, .08); }
        .brand { display:flex; align-items:center; justify-content:center; gap:12px; margin-bottom:24px; }
        .brand img { width:200px; /*height:42px;*/ object-fit:contain; }
        .brand h1 { font-size:24px; margin:0; font-weight:700; }
        .form-control { height:42px; margin-bottom:12px; }
        .btn-primary { width:100%; height:42px; background:#185adb; border-color:#185adb; }
        .links { display:flex; justify-content:space-between; gap:16px; margin-top:16px; font-size:14px; }
        .alert { margin-bottom:16px; }
    </style>
</head>
<body>
<main class="auth-wrap">
    <section class="auth-box">
        <div class="brand">
            <img src="../img/logo.png" alt="ROCKET-AI">
            <!-- <h1>ROCKET-AI Publisher</h1> -->
        </div>

        <?php if ($errors) { ?>
            <div class="alert alert-danger"><?php echo auth_h(implode(' ', $errors)); ?></div>
        <?php } ?>

        <?php if (isset($_GET['confirmed'])) { ?>
            <div class="alert alert-success">Ο λογαριασμός ενεργοποιήθηκε. Μπορείτε να συνδεθείτε.</div>
        <?php } ?>

        <?php if (isset($_GET['reset'])) { ?>
            <div class="alert alert-success">Ο κωδικός ενημερώθηκε. Μπορείτε να συνδεθείτε.</div>
        <?php } ?>

        <form method="post" action="login.php" autocomplete="on">
            <label for="email">Email</label>
            <input class="form-control" type="email" id="email" name="email" value="<?php echo auth_h($email); ?>" required>

            <label for="password">Κωδικός</label>
            <input class="form-control" type="password" id="password" name="password" required>

            <label style="font-weight:400; margin:6px 0 16px;">
                <input type="checkbox" name="remember" value="1"> Να με θυμάσαι
            </label>

            <button class="btn btn-primary" type="submit">Σύνδεση</button>
        </form>

        <div class="links">
            <a href="signup.php">Νέος λογαριασμός</a>
            <a href="reset-password.php">Ξέχασα τον κωδικό</a>
        </div>
    </section>
</main>
</body>
</html>
