<?php
/**
 * Email Verification Handler
 * Processes email verification when user clicks link in email
 * Usage: verify.php?code=xxxxx
 */

session_start();
require_once 'config/database.php';
require_once 'config/verification.php';

$message = '';
$success = false;

if (isset($_GET['code'])) {
    $code = trim($_GET['code']);
    $result = verify_email($code);
    
    if ($result['success']) {
        $success = true;
        $message = "✅ " . $result['message'] . " Please <a href='index.php'>login</a> to continue.";
    } else {
        $message = "❌ " . $result['message'];
    }
} else {
    $message = "❌ No verification code provided.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Medical Appointment System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8">
        <h1 class="text-2xl font-bold text-center mb-6">Email Verification</h1>
        
        <div class="<?= $success ? 'bg-green-50 border-l-4 border-green-500' : 'bg-red-50 border-l-4 border-red-500' ?> p-4 rounded mb-6">
            <p class="<?= $success ? 'text-green-700' : 'text-red-700' ?>">
                <?= $message ?>
            </p>
        </div>
        
        <div class="text-center">
            <a href="index.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded">
                Go to Login
            </a>
        </div>
    </div>
</body>
</html>
