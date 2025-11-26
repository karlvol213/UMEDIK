<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

// Admin-only
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true) {
    header('Location: ../index.php'); exit;
}

$message = '';
$error = '';
$temp_shown = '';

// Fetch users for dropdown
$users = [];
$r = $conn->query("SELECT id, email, full_name FROM users ORDER BY email ASC");
if ($r) {
    while ($row = $r->fetch_assoc()) $users[] = $row;
}

// Pre-select user if provided in query string
$preselect_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

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

require_once __DIR__ . '/../includes/header.php';
outputHeader('Admin - Reset Password');
?>
<style>
    /* minimal adjustments to match admin info style */
    .page-container{max-width:1000px;margin:0 auto;padding:24px}
    .card{background:#fff;border:1px solid #e6eef2;border-radius:12px;padding:18px;box-shadow:0 6px 20px rgba(2,6,23,.04)}
</style>

<?php require __DIR__ . '/../includes/tailwind_nav.php'; ?>
<div class="page-container">
    <div class="card">
        <h1 style="margin:0 0 8px;font-size:20px;font-weight:700">Reset User Password</h1>
        <p style="color:#475569;margin:0 0 12px">Set a temporary password for a user. Original passwords are irretrievable.</p>

        <?php if (!empty($message)): ?>
            <div style="margin-bottom:12px;padding:10px;background:#ecfdf5;border:1px solid #bbf7d0;color:#065f46;border-radius:6px"><?= $message ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div style="margin-bottom:12px;padding:10px;background:#fee2e2;border:1px solid #fca5a5;color:#7f1d1d;border-radius:6px"><?= $error ?></div>
        <?php endif; ?>

        <form method="post" id="resetForm">
            <input type="hidden" name="reset_password" value="1" id="reset_flag">
            <div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap">
                <div style="flex:1;min-width:260px">
                    <label style="display:block;font-weight:600">Select user</label>
                    <select name="user_id" id="userSelect" required style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;margin-top:6px">
                        <option value="">-- choose user --</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($preselect_user_id && $preselect_user_id == $u['id']) ? 'selected' : '' ?>><?= htmlspecialchars($u['email']) ?> - <?= htmlspecialchars($u['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="flex:1;min-width:260px">
                    <label style="display:block;font-weight:600">New temporary password</label>
                    <input type="text" name="new_password" required placeholder="Enter temporary password" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;margin-top:6px" />
                    <small style="color:#64748b;display:block;margin-top:6px">Make sure to tell the user to change their password after login.</small>
                </div>
            </div>

            <div style="margin-top:14px">
                <button type="button" id="resetBtn" class="btn btn-primary">Reset Password</button>
                <a href="admin.php" style="margin-left:12px;color:#111c4e">Back</a>
            </div>
        </form>

        <!-- Confirmation modal -->
        <div id="confirmModal" style="display:none;position:fixed;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.45);z-index:2000;">
            <div style="max-width:520px;margin:80px auto;background:#fff;padding:18px;border-radius:10px;">
                <h3 style="margin:0 0 8px">Confirm Password Reset</h3>
                <p id="confirmText" style="color:#374151">Are you sure you want to reset the password for this user? This will overwrite their existing password.</p>
                <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
                    <button id="confirmCancel" style="padding:8px 12px;border-radius:8px;border:1px solid #d1d5db;background:#fff">Cancel</button>
                    <button id="confirmOk" style="padding:8px 12px;border-radius:8px;background:linear-gradient(90deg,#0aa3a3,#0077cc);color:#fff;border:0">Confirm Reset</button>
                </div>
            </div>
        </div>

        <?php if (!empty($temp_shown)): ?>
            <div style="margin-top:14px;padding:12px;background:#fff7ed;border:1px solid #ffedd5;border-radius:8px;color:#92400e">
                <strong>Temporary password (copy to share with user):</strong>
                <div style="word-break:break-all;padding-top:6px;"><?= $temp_shown ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>

    <script>
        (function(){
            const resetBtn = document.getElementById('resetBtn');
            const modal = document.getElementById('confirmModal');
            const cancel = document.getElementById('confirmCancel');
            const ok = document.getElementById('confirmOk');
            const userSelect = document.getElementById('userSelect');
            const confirmText = document.getElementById('confirmText');
            const form = document.getElementById('resetForm');

            function openModal(){
                const opt = userSelect.options[userSelect.selectedIndex];
                if (!opt || !opt.value) {
                    alert('Please select a user to reset.');
                    return;
                }
                confirmText.textContent = 'Are you sure you want to reset the password for: ' + opt.text + " ? This will overwrite their existing password.";
                modal.style.display = 'block';
            }

            resetBtn && resetBtn.addEventListener('click', openModal);
            cancel && cancel.addEventListener('click', function(){ modal.style.display = 'none'; });
            ok && ok.addEventListener('click', function(){
                // submit the form
                document.getElementById('reset_flag').value = '1';
                form.submit();
            });

            // auto-open modal if preselected user and new_password field already filled? no — leave as manual
            // focus/select if preselected
            <?php if ($preselect_user_id): ?>
            try{ userSelect.focus(); }catch(e){}
            <?php endif; ?>
        })();
    </script>

    </body>
    </html>
