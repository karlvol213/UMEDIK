<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

// Admin-only
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true) {
    header('Location: ../index.php'); exit;
}

$message = '';
$error = '';

// Fetch users for dropdown
$users = [];
$r = $conn->query("SELECT id, email, full_name FROM users ORDER BY email ASC");
if ($r) {
    while ($row = $r->fetch_assoc()) $users[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $new_password = trim($_POST['new_password'] ?? '');

    if ($user_id <= 0) {
        $error = 'Select a user to reset.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('si', $hashed, $user_id);
        if ($stmt->execute()) {
            $stmt->close();
            // Log action
            if (function_exists('log_action') && !empty($_SESSION['user_id'])) {
                log_action($_SESSION['user_id'], 'ADMIN_RESET_PASSWORD', "Reset password for user_id={$user_id}");
            }

            // Try to email the user with the temporary password
            $s = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
            $s->bind_param('i', $user_id); $s->execute();
            $res = $s->get_result(); $u = $res->fetch_assoc(); $s->close();

            $sent = false;
            if ($u && filter_var($u['email'], FILTER_VALIDATE_EMAIL)) {
                $to = $u['email'];
                $subject = 'Your temporary password';
                $body = "A system administrator has set a temporary password for your account.\n\n" .
                        "Temporary password: {$new_password}\n\n" .
                        "Please log in and change your password immediately.\n";
                $headers = 'From: noreply@umak.edu.ph' . "\r\n" . 'X-Mailer: PHP/' . phpversion();
                try { $sent = @mail($to, $subject, $body, $headers); } catch (Exception $e) { $sent = false; }
            }

            if ($sent) {
                $message = 'Password reset successfully and emailed to the user.';
            } else {
                $message = 'Password reset successfully. Email not sent — mail() may be unconfigured. The temporary password is shown below.';
            }
            // Keep showing the temp password for admin copy (avoid storing it anywhere persistent)
            $temp_shown = htmlspecialchars($new_password);
        } else {
            $error = 'Failed to update password.';
        }
    }
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin - Reset User Password</title>
    <link href="/assets/css/../style/common.css" rel="stylesheet">
    <style>.container{max-width:760px;margin:36px auto;padding:18px;background:#fff;border-radius:8px}</style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="container">
    <h2>Reset User Password</h2>
    <p>Use this page to set a temporary password for a user. For security, the original password is not recoverable.</p>

    <?php if (!empty($message)): ?><div style="background:#ecfdf5;padding:10px;border:1px solid #bbf7d0;margin-bottom:10px"><?= $message ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div style="background:#fee2e2;padding:10px;border:1px solid #fca5a5;margin-bottom:10px"><?= $error ?></div><?php endif; ?>

    <form method="post">
        <label>Select user</label>
        <select name="user_id" required>
            <option value="">-- choose user --</option>
            <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['email']) ?> - <?= htmlspecialchars($u['full_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <div style="margin-top:10px">
            <label>New temporary password</label>
            <input type="text" name="new_password" required placeholder="Enter temporary password" style="width:100%;padding:8px;margin-top:4px" />
            <small>Make sure to tell the user to change their password after login.</small>
        </div>

        <div style="margin-top:12px">
            <button type="submit" name="reset_password">Reset Password</button>
            <a href="admin.php" style="margin-left:8px">Back</a>
        </div>
    </form>

    <?php if (!empty($temp_shown)): ?>
        <div style="margin-top:12px;padding:10px;background:#fff7ed;border:1px solid #ffedd5">
            <strong>Temporary password (copy to share with user):</strong>
            <div style="word-break:break-all;padding-top:6px;"><?= $temp_shown ?></div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
