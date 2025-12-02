<?php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

try {
    require_once 'config/database.php';
    require_once 'config/functions.php';
    require_once 'config/admin_access.php';
} catch (Exception $e) {
    die("❌ Failed to load required files: " . $e->getMessage());
}

$message = "";


if (isset($_SESSION['error'])) {
    $message = $_SESSION['error'];
    unset($_SESSION['error']);
}

// basta login
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["login"])) {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    try {
        // Checking admin
        if ($email === "admin@gmail.com" && $password === "123") {
            $_SESSION["loggedin"] = true;
            $_SESSION["isAdmin"] = true;
            $_SESSION["email"] = "admin@gmail.com";
            $_SESSION["id"] = 1; // Admin ID
            $_SESSION["user_id"] = 1; // Add this for nav.php compatibility
            $_SESSION["full_name"] = "Administrator";
            $_SESSION["role"] = "admin";

            // for admin
            log_action(
                1, 
                "ADMIN_LOGIN",
                "Administrator logged into the system"
            );

            header("Location: admin/admin.php");
            exit();
        }

        // Check if email belongs to allowed doctors
        if (is_allowed_doctor($email)) {
            // Doctor hardcoded login - in production, you'd want this in database
            // For now, we'll accept the doctor emails with a specific password or allow them to login
            if ($password === "123") { // You can change this to use hashed passwords from DB
                $_SESSION["loggedin"] = true;
                $_SESSION["isAdmin"] = true; // Doctors are admin-level but restricted
                $_SESSION["email"] = $email;
                $_SESSION["full_name"] = get_allowed_doctors()[$email];
                $_SESSION["role"] = "doctor";
                $_SESSION["id"] = null; // Doctor may not have user_id in system
                $_SESSION["user_id"] = null;

                log_action(
                    null,
                    "DOCTOR_LOGIN",
                    "Doctor {$_SESSION['full_name']} logged in successfully"
                );

                header("Location: admin/patient_history.php");
                exit();
            } else {
                $message = "Invalid email or password.";
            }
        }

        // Check if email belongs to allowed nurses
        if (is_allowed_nurse($email)) {
            // Nurse hardcoded login - in production, you'd want this in database
            if ($password === "123") { // You can change this to use hashed passwords from DB
                $_SESSION["loggedin"] = true;
                $_SESSION["isAdmin"] = true; // Nurses are admin-level but restricted
                $_SESSION["email"] = $email;
                $_SESSION["full_name"] = get_allowed_nurses()[$email];
                $_SESSION["role"] = "nurse";
                $_SESSION["id"] = null; // Nurse may not have user_id in system
                $_SESSION["user_id"] = null;

                log_action(
                    null,
                    "NURSE_LOGIN",
                    "Nurse {$_SESSION['full_name']} logged in successfully"
                );

                header("Location: admin/admin.php");
                exit();
            } else {
                $message = "Invalid email or password.";
            }
        }

        // Normal users (from database)
        $user = get_user_by_email($email);
        if ($user) {
            // Check if account is locked
            if (is_user_locked($user)) {
                $message = "Account is locked. Please try again later or contact an administrator.";
            } else if (password_verify($password, $user['password'])) {
                // Successful login reset counters
                reset_failed_login($user['id']);
                // Set session variables consistently
                $_SESSION["loggedin"] = true;
                $_SESSION["id"] = $user['id'];
                $_SESSION["user_id"] = $user['id']; 
                $_SESSION["email"] = $user['email'];
                $_SESSION["full_name"] = $user['full_name'];
                $_SESSION["role"] = $user['role'] ?? 'user';

                // Check if user is admin
                if ($user['role'] === 'admin') {
                    $_SESSION["isAdmin"] = true;
                    log_action($user['id'], "ADMIN_LOGIN", "Admin user logged in successfully");
                    header("Location: admin/admin.php");
                    exit();
                } else {
                    $_SESSION["isAdmin"] = false;
                    log_action($user['id'], "USER_LOGIN", "User logged in successfully");
                    header("Location: home.php");
                    exit();
                }
            } else {
                // Invalid password: record failed attempt
                record_failed_login($user['id']);
                // Determine remaining attempts message
                $remaining = max(0, 5 - (int)$user['failed_login_count'] - 1);
                if ($remaining <= 0) {
                    $message = "Account locked due to multiple failed login attempts. Please contact an administrator to unlock.";
                } else if ($remaining <= 2) {
                    $message = "Invalid email or password. You have {$remaining} attempts remaining before account lock.";
                } else {
                    $message = "Invalid email or password.";
                }
            }
        } else {
            $message = "Invalid email or password.";
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Medical Appointment System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(to bottom right, #ffffff, #cce0ff);
            background-attachment: fixed;
            background-repeat: no-repeat;
            min-height: 100vh;
        }
        
        .form-container {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            width: 100%;
            max-width: 28rem;
            margin: 100px auto 30px;
        }
        .brand-logo { width:72px; height:auto; display:block; margin:0 auto }

        .nav-logo { height: 48px; width: auto; display: inline-block; }
        .brand-text { color: #ffffff; font-weight: 700; font-size: 1.05rem; margin-left: 8px; }

        .top-nav a { padding: 10px 16px; }

        .input-field {
            width: 100%;
            padding: 0.75rem;
            margin: 0.5rem 0;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }

        .input-field:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5);
        }

        .submit-button {
            width: 100%;
            padding: 0.75rem 1.5rem;
            margin-top: 1.5rem;
            background-color: #003366;
            color: white;
            border: none;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .submit-button:hover {
            background-color: #002244;
        }

        .error-message {
            background-color: #fee2e2;
            border: 1px solid #ef4444;
            color: #b91c1c;
            padding: 0.75rem;
            border-radius: 0.375rem;
            margin: 1rem 0;
        }
    </style>
    <!-- Removed nav.css reference since using Tailwind -->
</head>
<body>
    <?php include 'includes/tailwind_nav.php'; ?>

    <div class="form-container">
        <div style="text-align: center; margin-bottom: 2rem;">
            <img class="brand-logo" src="./assets/images/umak3.ico" alt="Logo">
            <h2 style="margin-top: 1.5rem; font-size: 1.875rem; font-weight: 800; color: #111827;">
                Welcome back
            </h2>
            <p style="margin-top: 0.5rem; font-size: 0.875rem; color: #6b7280;">
                Sign in to your account
            </p>
        </div>

        <?php if (!empty($message)) : ?>
            <div class="error-message">
                <?= $message; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div>
                <label for="email" style="display: none;">Email address</label>
                <input id="email" name="email" type="email" required 
                       class="input-field"
                       placeholder="Email address">
            </div>
            <div>
                <label for="password" style="display: none;">Password</label>
                <div style="position: relative;">
                    <input id="password" name="password" type="password" required 
                           class="input-field"
                           placeholder="Password"
                           style="padding-right: 2.5rem;">
                    <button type="button" onclick="togglePassword('password', 'loginToggleIcon')"
                            style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #6b7280;">
                        <i id="loginToggleIcon" class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" name="login" class="submit-button primary-cta">
                Sign in
            </button>

            <div style="text-align: center; margin-top: 1rem;">
                <a href="register.php" style="color: #003366; font-size: 0.875rem; text-decoration: none;">
                    Don't have an account? Register here
                </a>
            </div>
        </form>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword(fieldId, iconId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(iconId);
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>