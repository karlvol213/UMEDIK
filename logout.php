<?php
session_start();
require_once 'config/functions.php';

// Get user ID from either session variable
$user_id = $_SESSION["user_id"] ?? $_SESSION["id"] ?? null;

if ($user_id) {
    $is_admin = isset($_SESSION["isAdmin"]) && $_SESSION["isAdmin"] === true;
    $user_name = $_SESSION["full_name"] ?? "Unknown User";
    
    if ($is_admin) {
        log_action($user_id, "ADMIN_LOGOUT", "Administrator logged out of the system");
    } else {
        log_action(
            $user_id, 
            "USER_LOGOUT",
            sprintf("User logged out - Name: %s", $user_name)
        );
    }
}

// Destroy session completely
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Redirect to login page
header("Location: index.php");
exit();
?>
