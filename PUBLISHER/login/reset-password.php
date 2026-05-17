<?php
require_once __DIR__ . '/../php/config.php';
require_once __DIR__ . '/../php/db.php';
require_once __DIR__ . '/auth-helpers.php';

$errors = [];
$success = '';
$emailStatus = '';
$resetLink = '';
$email = '';
$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$isSetup = isset($_GET['setup']) || isset($_POST['setup']);
$tokenUser = false;

if ($token !== '') {
    $dbo = auth_db($errors);
    $tokenUser = $dbo ? auth_verify_token($token, 'password_reset', $dbo) : false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email.';
    } else {
        $dbo = auth_db($errors);
    }

    if (!$errors && $dbo) {
        $rs = $dbo->getRS(
            'SELECT id, email, password_hash, status FROM users WHERE email = ? LIMIT 1',
            [$email]
        );

        if ($rs) {
            $expires = time() + 3600;
            $resetToken = auth_create_token($rs[0], 'password_reset', $expires);
            $resetLink = auth_login_url('reset-password.php?token=' . urlencode($resetToken));

            $subject = 'ROCKET-AI Publisher - Password reset';
            $message = "Open this link to reset your password:\n\n" . $resetLink;
            if (!auth_send_email($rs[0]['email'], $subject, $message)) {
                $errors[] = 'Δεν στάλθηκε email αλλαγής κωδικού. ' . auth_last_email_error();
            } else {
                $emailStatus = auth_last_email_status();
            }
        }

        if (!$errors) {
            $success = 'If an account exists for this email, a password reset link has been sent.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset') {
    $dbo = auth_db($errors);
    $tokenUser = $dbo ? auth_verify_token($token, 'password_reset', $dbo) : false;
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

    if (!$tokenUser) {
        $errors[] = 'The password reset link is invalid or has expired.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif ($password !== $passwordConfirm) {
        $errors[] = 'Passwords do not match.';
    } else {
        $dbo->execSQL('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?', [
            password_hash($password, PASSWORD_DEFAULT),
            date('Y-m-d H:i:s'),
            $tokenUser['id']
        ]);

        header('Location: login.php?reset=1');
        exit;
    }
}
?>
<!doctype html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo auth_h(app::$project_name); ?> - Password reset</title>
    <link rel="icon" type="image/png" href="../img/favicon.png">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">
    <style>
        body { background:#f5f7fb; color:#1f2933; font-family:Arial, sans-serif; }
        .auth-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
        .auth-box { width:100%; max-width:460px; background:#fff; border:1px solid #d9e2ec; border-radius:8px; padding:28px; box-shadow:0 12px 30px rgba(15, 23, 42, .08); }
        h1 { font-size:24px; margin:0 0 20px; font-weight:700; }
        .form-control { height:42px; margin-bottom:12px; }
        .btn-primary { width:100%; height:42px; background:#185adb; border-color:#185adb; }
        .links { margin-top:16px; font-size:14px; }
        .dev-link { word-break:break-all; font-size:13px; margin-top:12px; }
        .password-wrap { position:relative; }
        .password-wrap .form-control { padding-right:72px; }
        .toggle-password { position:absolute; right:8px; top:5px; height:32px; min-width:54px; border:0; background:#eef2f7; color:#243b53; border-radius:4px; font-size:12px; }
    </style>
</head>
<body>
<main class="auth-wrap">
    <section class="auth-box">
        <h1><?php echo $isSetup ? 'Set password' : 'Password reset'; ?></h1>

        <?php if ($errors) { ?>
            <div class="alert alert-danger"><?php echo auth_h(implode(' ', $errors)); ?></div>
        <?php } ?>

        <?php if ($success) { ?>
            <div class="alert alert-success">
                <?php echo auth_h($success); ?>
                <?php if ($resetLink) { ?>
                    <!-- <div class="dev-link"><a href="<?php echo auth_h($resetLink); ?>"><?php echo auth_h($resetLink); ?></a></div> -->
                <?php } ?>
            </div>
        <?php } ?>

        <?php if ($emailStatus) { ?>
            <!-- <div class="alert alert-info"><?php echo auth_h($emailStatus); ?></div> -->
        <?php } ?>

        <?php if ($token !== '' && $tokenUser) { ?>
            <form method="post" action="reset-password.php" autocomplete="off">
                <input type="hidden" name="action" value="reset">
                <input type="hidden" name="token" value="<?php echo auth_h($token); ?>">
                <?php if ($isSetup) { ?>
                    <input type="hidden" name="setup" value="1">
                <?php } ?>

                <label for="password"><?php echo $isSetup ? 'Password' : 'New password'; ?></label>
                <div class="password-wrap">
                    <input class="form-control" type="password" id="password" name="password" minlength="8" required>
                    <button class="toggle-password" type="button" data-password-toggle="password" aria-label="Show password">Show</button>
                </div>

                <label for="password_confirm">Confirm password</label>
                <div class="password-wrap">
                    <input class="form-control" type="password" id="password_confirm" name="password_confirm" minlength="8" required>
                    <button class="toggle-password" type="button" data-password-toggle="password_confirm" aria-label="Show password confirmation">Show</button>
                </div>

                <button class="btn btn-primary" type="submit"><?php echo $isSetup ? 'Set password' : 'Save password'; ?></button>
            </form>
        <?php } else { ?>
            <form method="post" action="reset-password.php" autocomplete="on">
                <input type="hidden" name="action" value="request">

                <label for="email">Account email</label>
                <input class="form-control" type="email" id="email" name="email" value="<?php echo auth_h($email); ?>" required>

                <button class="btn btn-primary" type="submit">Send reset link</button>
            </form>
        <?php } ?>

        <div class="links">
            <a href="login.php">Back to login</a>
        </div>
    </section>
</main>
<script>
    document.querySelectorAll('[data-password-toggle]').forEach(function(button) {
        button.addEventListener('click', function() {
            var input = document.getElementById(button.getAttribute('data-password-toggle'));
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Hide' : 'Show';
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });
</script>
</body>
</html>
