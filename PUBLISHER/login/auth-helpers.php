<?php

// header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Athens');

function auth_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function auth_start_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $sessionPath = session_save_path();
    if ($sessionPath === '' || !auth_path_is_writable($sessionPath)) {
        $fallbackPath = __DIR__ . '/../tmp/sessions';
        if (!is_dir($fallbackPath)) {
            @mkdir($fallbackPath, 0775, true);
        }

        if (is_dir($fallbackPath) && auth_path_is_writable($fallbackPath)) {
            session_save_path($fallbackPath);
        } elseif (auth_path_is_writable('/tmp')) {
            session_save_path('/tmp');
        }
    }

    session_start();
}

function auth_path_is_writable($path) {
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

function auth_db(&$errors = null) {
    static $dbo = null;

    if ($dbo instanceof DB) {
        return $dbo;
    }

    try {
        $dbo = new DB(conn1::$connstr, conn1::$username, conn1::$password);
        return $dbo;
    } catch (Throwable $ex) {
        if (is_array($errors)) {
            $errors[] = 'Δεν είναι δυνατή η σύνδεση με τη βάση δεδομένων αυτή τη στιγμή.';
        }

        return false;
    }
}

function auth_login_url($path) {
    if (!empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . $_SERVER['HTTP_HOST'] . '/login/' . ltrim($path, '/');
    }

    return rtrim(app::$host, '/') . '/login/' . ltrim($path, '/');
}

function auth_send_email($to, $subject, $body) {
    $GLOBALS['AUTH_LAST_EMAIL_ERROR'] = '';
    $GLOBALS['AUTH_LAST_EMAIL_STATUS'] = '';
    $host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : app::$app_domain;
    // $from = 'no-reply@' . preg_replace('/^www\./', '', $host);
    $from = app::$SMTP_FROM;
    $fromName = app::$project_name;

    $phpMailerPath = __DIR__ . '/../PHP-Mailer/src/PHPMailer.php';
    $smtpPath = __DIR__ . '/../PHP-Mailer/src/SMTP.php';
    $exceptionPath = __DIR__ . '/../PHP-Mailer/src/Exception.php';

    if (!is_file($phpMailerPath) || !is_file($smtpPath) || !is_file($exceptionPath)) {
        $GLOBALS['AUTH_LAST_EMAIL_ERROR'] = 'PHPMailer files not found under ' . __DIR__ . '/../PHP-Mailer/src';
        error_log($GLOBALS['AUTH_LAST_EMAIL_ERROR']);
        return false;
    }

    require_once $exceptionPath;
    require_once $phpMailerPath;
    require_once $smtpPath;

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host       = app::$SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = app::$SMTP_USERNAME;
        $mail->Password   = app::$SMTP_PASSWORD;
        $mail->SMTPSecure = app::$SMTP_SECURE;
        $mail->Port       = app::$SMTP_PORT ?? 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(app::$SMTP_FROM, app::$project_name);
        $mail->Sender = $from;
        $mail->addAddress($to);
        $mail->addReplyTo($from, $fromName);
        $mail->Subject = $subject;
        $mail->Body = $body;

        if ($mail->send()) {
            $GLOBALS['AUTH_LAST_EMAIL_STATUS'] = 'SMTP accepted email. From: ' . app::$SMTP_FROM . ' To: ' . $to;
            return true;
        }
        else {
            $GLOBALS['AUTH_LAST_EMAIL_ERROR'] = 'PHPMailer error: ' . $mail->ErrorInfo;
            error_log($GLOBALS['AUTH_LAST_EMAIL_ERROR']);
        }
    } catch (Throwable $ex) {
        // show error
        $GLOBALS['AUTH_LAST_EMAIL_ERROR'] = 'PHPMailer exception: ' . $ex->getMessage();
        error_log($GLOBALS['AUTH_LAST_EMAIL_ERROR']);
    }
    

    return false;
}

function auth_last_email_error() {
    return $GLOBALS['AUTH_LAST_EMAIL_ERROR'] ?? '';
}

function auth_last_email_status() {
    return $GLOBALS['AUTH_LAST_EMAIL_STATUS'] ?? '';
}

function auth_create_token($user, $purpose, $expires) {
    $payload = $user['id'] . '|' . $purpose . '|' . $expires;
    $secret = conn1::$password . '|' . app::$slug . '|' . $user['email'] . '|' . $user['password_hash'];
    $signature = hash_hmac('sha256', $payload, $secret);

    return rtrim(strtr(base64_encode($payload . '|' . $signature), '+/', '-_'), '=');
}

function auth_decode_token($token) {
    $token = strtr((string)$token, '-_', '+/');
    $padding = strlen($token) % 4;

    if ($padding > 0) {
        $token .= str_repeat('=', 4 - $padding);
    }

    return base64_decode($token, true);
}

function auth_verify_token($token, $purpose, $dbo) {
    $decoded = auth_decode_token($token);
    if (!$decoded) {
        return false;
    }

    $parts = explode('|', $decoded);
    if (count($parts) !== 4) {
        return false;
    }

    [$userId, $tokenPurpose, $expires, $signature] = $parts;
    if ($tokenPurpose !== $purpose || !ctype_digit($userId) || !ctype_digit($expires) || (int)$expires < time()) {
        return false;
    }

    $rs = $dbo->getRS('SELECT id, email, password_hash, status FROM users WHERE id = ? LIMIT 1', [(int)$userId]);
    if (!$rs) {
        return false;
    }

    $payload = $userId . '|' . $tokenPurpose . '|' . $expires;
    $secret = conn1::$password . '|' . app::$slug . '|' . $rs[0]['email'] . '|' . $rs[0]['password_hash'];
    $expected = hash_hmac('sha256', $payload, $secret);

    return hash_equals($expected, $signature) ? $rs[0] : false;
}
