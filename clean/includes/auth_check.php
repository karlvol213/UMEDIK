<?php
/**
 * Authentication Check Include
 * 
 * This file ensures that only authenticated admin users can access admin pages.
 * Include this at the top of every admin page:
 * 
 *   require_once __DIR__ . '/../includes/auth_check.php';
 * 
 * It will:
 * - Start the session if not already started
 * - Check if user is logged in
 * - Check if user has admin privileges
 * - Redirect to login page if unauthorized
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /index.php');
    exit('Unauthorized. Redirecting to login...');
}

// Check if user is admin
if (empty($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true) {
    header('Location: /index.php');
    exit('Unauthorized. Admin access required.');
}

// Session is valid - script continues
?>
