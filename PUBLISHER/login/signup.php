<?php
require_once __DIR__ . '/../php/config.php';
require_once __DIR__ . '/../php/db.php';
require_once __DIR__ . '/auth-helpers.php';

auth_start_session();

$errors = [];
$success = '';
$emailStatus = '';
$name = '';
$companyName = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $companyName = trim($_POST['company_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

    if ($name === '') {
        $errors[] = 'Συμπληρώστε ονοματεπώνυμο.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Συμπληρώστε σωστό email.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Ο κωδικός πρέπει να έχει τουλάχιστον 8 χαρακτήρες.';
    }

    if ($password !== $passwordConfirm) {
        $errors[] = 'Οι κωδικοί δεν ταιριάζουν.';
    }

    if (!$errors) {
        $dbo = auth_db($errors);
    }

    if (!$errors && $dbo) {
        $existing = $dbo->getRS('SELECT id, name, email, password_hash, status FROM users WHERE email = ? LIMIT 1', [$email]);

        if ($existing) {
            if ($existing[0]['status'] === 'inactive') {
                $expires = time() + (3600 * 24);
                $token = auth_create_token($existing[0], 'signup_confirm', $expires);
                $confirmationLink = auth_login_url('user-signup-confirm.php?token=' . urlencode($token));
                $subject = 'ROCKET-AI Publisher - Account confirmation';
                $message = "Hello " . $existing[0]['name'] . ",\n\n"
                    . "Open this link to activate your ROCKET-AI Publisher account:\n\n"
                    . $confirmationLink . "\n\n"
                    . "This link expires in 24 hours.";

                if (auth_send_email($existing[0]['email'], $subject, $message)) {
                    $success = 'Στάλθηκε νέο email επιβεβαίωσης.';
                    $emailStatus = auth_last_email_status();
                } else {
                    $errors[] = 'Δεν στάλθηκε email επιβεβαίωσης. ' . auth_last_email_error();
                }
            } else {
                $errors[] = 'Υπάρχει ήδη λογαριασμός με αυτό το email.';
            }
        } else {
            $now = date('Y-m-d H:i:s');
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $userId = $dbo->execSQL(
                'INSERT INTO users (name, email, password_hash, status, created_at) VALUES (?, ?, ?, ?, ?)',
                [$name, $email, $passwordHash, 'inactive', $now]
            );

            if ($userId) {
                $accountName = $companyName !== '' ? $companyName : $name;
                $accountId = $dbo->execSQL(
                    'INSERT INTO accounts (name, company_name, status, created_at) VALUES (?, ?, ?, ?)',
                    [$accountName, $companyName !== '' ? $companyName : null, 'active', $now]
                );

                if ($accountId) {
                    $dbo->execSQL(
                        'INSERT INTO account_users (account_id, user_id, role, status, created_at) VALUES (?, ?, ?, ?, ?)',
                        [$accountId, $userId, 'owner', 'inactive', $now]
                    );
                }

                $user = [
                    'id' => $userId,
                    'email' => $email,
                    'password_hash' => $passwordHash
                ];
                $expires = time() + (3600 * 24);
                $token = auth_create_token($user, 'signup_confirm', $expires);
                $confirmationLink = auth_login_url('user-signup-confirm.php?token=' . urlencode($token));

                $subject = 'ROCKET-AI Publisher - Account confirmation';
                $message = "Hello " . $name . ",\n\n"
                    . "Open this link to activate your ROCKET-AI Publisher account:\n\n"
                    . $confirmationLink . "\n\n"
                    . "This link expires in 24 hours.";

                if (auth_send_email($email, $subject, $message)) {
                    $success = 'Ο λογαριασμός δημιουργήθηκε. Ελέγξτε το email σας για επιβεβαίωση.';
                    $emailStatus = auth_last_email_status();
                    $name = '';
                    $companyName = '';
                    $email = '';
                } else {
                    $errors[] = 'Ο λογαριασμός δημιουργήθηκε, αλλά δεν στάλθηκε email επιβεβαίωσης. ' . auth_last_email_error();
                }
            } else {
                $errors[] = 'Δεν ήταν δυνατή η δημιουργία λογαριασμού.';
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
    <title><?php echo auth_h(app::$project_name); ?> - Signup</title>
    <link rel="icon" type="image/png" href="../img/favicon.png">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">
    <style>
        body { background:#f5f7fb; color:#1f2933; font-family:Arial, sans-serif; }
        .auth-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
        .auth-box { width:100%; max-width:500px; background:#fff; border:1px solid #d9e2ec; border-radius:8px; padding:28px; box-shadow:0 12px 30px rgba(15, 23, 42, .08); }
        h1 { font-size:24px; margin:0 0 20px; font-weight:700; }
        .form-control { height:42px; margin-bottom:12px; }
        .btn-primary { width:100%; height:42px; background:#185adb; border-color:#185adb; }
        .links { margin-top:16px; font-size:14px; }
    </style>
</head>
<body>
<main class="auth-wrap">
    <section class="auth-box">
        <h1>Νέος λογαριασμός Publisher</h1>

        <?php if ($errors) { ?>
            <div class="alert alert-danger"><?php echo auth_h(implode(' ', $errors)); ?></div>
        <?php } ?>

        <?php if ($success) { ?>
            <div class="alert alert-success"><?php echo auth_h($success); ?></div>
        <?php } ?>

        <?php if ($emailStatus) { ?>
            <div class="alert alert-info"><?php echo auth_h($emailStatus); ?></div>
        <?php } ?>

        <form method="post" action="signup.php" autocomplete="on">
            <label for="name">Ονοματεπώνυμο</label>
            <input class="form-control" type="text" id="name" name="name" value="<?php echo auth_h($name); ?>" required>

            <label for="company_name">Εταιρεία</label>
            <input class="form-control" type="text" id="company_name" name="company_name" value="<?php echo auth_h($companyName); ?>">

            <label for="email">Email</label>
            <input class="form-control" type="email" id="email" name="email" value="<?php echo auth_h($email); ?>" required>

            <label for="password">Κωδικός</label>
            <input class="form-control" type="password" id="password" name="password" minlength="8" required>

            <label for="password_confirm">Επιβεβαίωση κωδικού</label>
            <input class="form-control" type="password" id="password_confirm" name="password_confirm" minlength="8" required>

            <button class="btn btn-primary" type="submit">Δημιουργία λογαριασμού</button>
        </form>

        <div class="links">
            <a href="login.php">Έχω ήδη λογαριασμό</a>
        </div>
    </section>
</main>
</body>
</html>
