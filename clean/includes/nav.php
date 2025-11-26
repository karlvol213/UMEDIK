<?php
/**
 * DEPRECATED: Use navbar.php instead
 * 
 * This file is kept for backwards compatibility only.
 * New code should include /includes/navbar.php instead.
 */

// Include the new navbar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/navbar.php';
?>