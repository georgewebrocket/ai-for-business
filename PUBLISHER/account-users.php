<?php

require_once('php/config.php');
require_once('php/db.php');
require_once('php/dataobjects.php');
require_once('php/utils.php');
require_once('php/start.php');
require_once('php/session.php');
require_once('php/controls.php');
require_once('login/auth-helpers.php');

publisher_require_permission('team');

$errors = [];
$success = '';
$emailStatus = '';
$accountId = (int)$current_account_id;
$currentRole = $current_account_role;
$currentUserId = (int)$userid;
$allowedRoles = ['owner', 'admin', 'editor', 'author', 'viewer'];

function account_users_can_assign_role($actorRole, $targetRole) {
    if ($actorRole === 'owner') {
        return in_array($targetRole, ['owner', 'admin', 'editor', 'author', 'viewer'], true);
    }

    if ($actorRole === 'admin') {
        return in_array($targetRole, ['editor', 'author', 'viewer'], true);
    }

    return false;
}

function account_users_owner_count($dbo, $accountId) {
    $rs = $dbo->getRS(
        "SELECT COUNT(*) AS total
         FROM account_users
         WHERE account_id = ? AND role = 'owner' AND status = 'active'",
        [$accountId]
    );

    return $rs ? (int)$rs[0]['total'] : 0;
}

function account_users_membership($dbo, $accountId, $userId) {
    $rs = $dbo->getRS(
        'SELECT * FROM account_users WHERE account_id = ? AND user_id = ? LIMIT 1',
        [$accountId, $userId]
    );

    return $rs ? $rs[0] : false;
}

function account_users_send_invitation($email, $name, $token) {
    $link = auth_login_url('user-signup-confirm.php?token=' . urlencode($token));
    $subject = 'ROCKET-AI Publisher - Account invitation';
    $message = "Hello " . $name . ",\n\n"
        . "You have been invited to ROCKET-AI Publisher.\n\n"
        . "Open this link to activate your account:\n\n"
        . $link . "\n\n"
        . "This link expires in 24 hours.";

    return auth_send_email($email, $subject, $message);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'invite') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'viewer';

        if ($name === '') {
            $errors[] = 'Συμπληρώστε όνομα.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Συμπληρώστε σωστό email.';
        }
        if (!in_array($role, $allowedRoles, true) || !account_users_can_assign_role($currentRole, $role)) {
            $errors[] = 'Δεν μπορείτε να αναθέσετε αυτόν τον ρόλο.';
        }

        if (!$errors) {
            $now = date('Y-m-d H:i:s');
            $userRows = $dbo->getRS('SELECT id, name, email, password_hash, status FROM users WHERE email = ? LIMIT 1', [$email]);
            $isNewUser = false;

            if ($userRows) {
                $targetUser = $userRows[0];
            } else {
                $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                $targetUserId = $dbo->execSQL(
                    'INSERT INTO users (name, email, password_hash, status, created_at) VALUES (?, ?, ?, ?, ?)',
                    [$name, $email, $passwordHash, 'inactive', $now]
                );

                if (!$targetUserId) {
                    $errors[] = 'Δεν ήταν δυνατή η δημιουργία χρήστη.';
                } else {
                    $targetUser = [
                        'id' => $targetUserId,
                        'name' => $name,
                        'email' => $email,
                        'password_hash' => $passwordHash,
                        'status' => 'inactive'
                    ];
                    $isNewUser = true;
                }
            }

            if (!$errors) {
                $membership = account_users_membership($dbo, $accountId, $targetUser['id']);
                if ($membership) {
                    $dbo->execSQL(
                        'UPDATE account_users SET role = ?, status = ?, updated_at = ? WHERE id = ?',
                        [$role, 'active', $now, $membership['id']]
                    );
                } else {
                    $dbo->execSQL(
                        'INSERT INTO account_users (account_id, user_id, role, status, created_at) VALUES (?, ?, ?, ?, ?)',
                        [$accountId, $targetUser['id'], $role, 'active', $now]
                    );
                }

                if ($targetUser['status'] === 'inactive') {
                    $expires = time() + (3600 * 24);
                    $token = auth_create_token($targetUser, 'signup_confirm', $expires);
                    if (!account_users_send_invitation($targetUser['email'], $targetUser['name'], $token)) {
                        $errors[] = 'Η πρόσβαση δημιουργήθηκε, αλλά δεν στάλθηκε email πρόσκλησης. ' . auth_last_email_error();
                    } else {
                        $success = 'Η πρόσκληση στάλθηκε.';
                        $emailStatus = auth_last_email_status();
                    }
                } else {
                    $success = $isNewUser ? 'Ο χρήστης προστέθηκε.' : 'Η πρόσβαση ενημερώθηκε.';
                }
            }
        }
    }

    if ($action === 'update') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $role = $_POST['role'] ?? '';
        $status = $_POST['status'] ?? '';

        if (!in_array($role, $allowedRoles, true) || !account_users_can_assign_role($currentRole, $role)) {
            $errors[] = 'Δεν μπορείτε να αναθέσετε αυτόν τον ρόλο.';
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors[] = 'Μη έγκυρη κατάσταση πρόσβασης.';
        }

        $membership = $targetUserId > 0 ? account_users_membership($dbo, $accountId, $targetUserId) : false;
        if (!$membership) {
            $errors[] = 'Δεν βρέθηκε η πρόσβαση χρήστη.';
        }

        if (!$errors && $membership['role'] === 'owner' && ($role !== 'owner' || $status !== 'active') && account_users_owner_count($dbo, $accountId) <= 1) {
            $errors[] = 'Δεν επιτρέπεται να μείνει το account χωρίς ενεργό owner.';
        }

        if (!$errors && $currentRole === 'admin' && in_array($membership['role'], ['owner', 'admin'], true)) {
            $errors[] = 'Οι admin δεν μπορούν να αλλάξουν owner/admin χρήστες.';
        }

        if (!$errors) {
            $dbo->execSQL(
                'UPDATE account_users SET role = ?, status = ?, updated_at = ? WHERE id = ?',
                [$role, $status, date('Y-m-d H:i:s'), $membership['id']]
            );
            $success = 'Η πρόσβαση ενημερώθηκε.';

            if ($targetUserId === $currentUserId) {
                publisher_set_current_account($dbo, $currentUserId, $accountId);
                $currentRole = publisher_current_account_role();
            }
        }
    }

    if ($action === 'resend_invitation') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $membership = $targetUserId > 0 ? account_users_membership($dbo, $accountId, $targetUserId) : false;

        if (!$membership) {
            $errors[] = 'Δεν βρέθηκε η πρόσβαση χρήστη.';
        } elseif ($currentRole === 'admin' && in_array($membership['role'], ['owner', 'admin'], true)) {
            $errors[] = 'Οι admin δεν μπορούν να στείλουν πρόσκληση σε owner/admin χρήστες.';
        } else {
            $userRows = $dbo->getRS(
                'SELECT id, name, email, password_hash, status FROM users WHERE id = ? LIMIT 1',
                [$targetUserId]
            );

            if (!$userRows) {
                $errors[] = 'Δεν βρέθηκε ο χρήστης.';
            } elseif ($userRows[0]['status'] !== 'inactive') {
                $errors[] = 'Ο χρήστης είναι ήδη ενεργός.';
            } else {
                $expires = time() + (3600 * 24);
                $token = auth_create_token($userRows[0], 'signup_confirm', $expires);
                if (account_users_send_invitation($userRows[0]['email'], $userRows[0]['name'], $token)) {
                    $success = 'Η πρόσκληση στάλθηκε ξανά.';
                    $emailStatus = auth_last_email_status();
                } else {
                    $errors[] = 'Δεν στάλθηκε η πρόσκληση. ' . auth_last_email_error();
                }
            }
        }
    }
}

$members = $dbo->getRS(
    "SELECT au.id AS membership_id, au.user_id, au.role, au.status AS membership_status,
            u.name, u.email, u.status AS user_status, u.last_login_at
     FROM account_users au
     INNER JOIN users u ON u.id = au.user_id
     WHERE au.account_id = ?
     ORDER BY FIELD(au.role, 'owner', 'admin', 'editor', 'author', 'viewer'), u.name",
    [$accountId]
);

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <title><?php echo app::$project_name; ?> - Account users</title>
    <?php include "_head.php"; ?>
    <style>
        .team-layout { display:grid; grid-template-columns: minmax(260px, 360px) 1fr; gap:24px; align-items:start; }
        .panel-lite { background:#fff; border:1px solid #ddd; border-radius:8px; padding:18px; }
        .team-table th, .team-table td { vertical-align:middle !important; }
        .inline-form { display:flex; gap:8px; align-items:center; margin:0; }
        .inline-form select { min-width:110px; }
        @media (max-width:900px) { .team-layout { grid-template-columns:1fr; } .inline-form { flex-wrap:wrap; } }
    </style>
</head>
<body>
    <?php include "blocks/header.php"; ?>

    <div class="padding-20">
        <h1>Account users</h1>
        <p>
            <strong><?php echo htmlspecialchars($current_account_name, ENT_QUOTES, 'UTF-8'); ?></strong>
            <span style="text-transform:capitalize;">(<?php echo htmlspecialchars($current_account_role, ENT_QUOTES, 'UTF-8'); ?>)</span>
        </p>

        <?php if ($errors) { ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8'); ?></div>
        <?php } ?>

        <?php if ($success) { ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php } ?>

        <?php if ($emailStatus) { ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($emailStatus, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php } ?>

        <div class="team-layout">
            <div class="panel-lite">
                <h3>Invite user</h3>
                <form method="post" action="account-users.php">
                    <input type="hidden" name="action" value="invite">

                    <label for="name">Name</label>
                    <input class="form-control" type="text" id="name" name="name" required>

                    <label for="email">Email</label>
                    <input class="form-control" type="email" id="email" name="email" required>

                    <label for="role">Role</label>
                    <select class="form-control" id="role" name="role">
                        <?php foreach ($allowedRoles as $roleOption) { ?>
                            <?php if (account_users_can_assign_role($currentRole, $roleOption)) { ?>
                                <option value="<?php echo $roleOption; ?>"><?php echo $roleOption; ?></option>
                            <?php } ?>
                        <?php } ?>
                    </select>

                    <button class="btn btn-primary" type="submit" style="margin-top:10px;">Send invitation</button>
                </form>
            </div>

            <div class="panel-lite">
                <h3>Team</h3>
                <table class="table table-striped team-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>User status</th>
                            <th>Access</th>
                            <th>Last login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($members) { ?>
                        <?php foreach ($members as $member) { ?>
                            <?php
                            $canEditMember = $currentRole === 'owner'
                                || ($currentRole === 'admin' && !in_array($member['role'], ['owner', 'admin'], true));
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($member['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($member['user_status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php if ($canEditMember) { ?>
                                        <form class="inline-form" method="post" action="account-users.php">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="user_id" value="<?php echo (int)$member['user_id']; ?>">
                                            <select class="form-control" name="role">
                                                <?php foreach ($allowedRoles as $roleOption) { ?>
                                                    <?php if (account_users_can_assign_role($currentRole, $roleOption)) { ?>
                                                        <option value="<?php echo $roleOption; ?>" <?php echo $member['role'] === $roleOption ? 'selected' : ''; ?>>
                                                            <?php echo $roleOption; ?>
                                                        </option>
                                                    <?php } ?>
                                                <?php } ?>
                                            </select>
                                            <select class="form-control" name="status">
                                                <option value="active" <?php echo $member['membership_status'] === 'active' ? 'selected' : ''; ?>>active</option>
                                                <option value="inactive" <?php echo $member['membership_status'] === 'inactive' ? 'selected' : ''; ?>>inactive</option>
                                            </select>
                                            <button class="btn btn-default" type="submit">Save</button>
                                        </form>
                                    <?php } else { ?>
                                        <?php echo htmlspecialchars($member['role'] . ' / ' . $member['membership_status'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php } ?>
                                </td>
                                <td><?php echo htmlspecialchars((string)$member['last_login_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php if ($member['user_status'] === 'inactive' && $canEditMember) { ?>
                                        <form method="post" action="account-users.php" style="margin:0;">
                                            <input type="hidden" name="action" value="resend_invitation">
                                            <input type="hidden" name="user_id" value="<?php echo (int)$member['user_id']; ?>">
                                            <button class="btn btn-default btn-sm" type="submit">Resend invitation</button>
                                        </form>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include "blocks/footer.php"; ?>
</body>
</html>
