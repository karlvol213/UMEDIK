<?php
// Note: session_start() should be called by the including file

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to output header with dynamic title
function outputHeader($pageTitle) {
    $title = htmlspecialchars($pageTitle) . ' - Healthcare Management System';
    // Ensure the HTTP Content-Language header is set to English to help browsers
    // avoid incorrect automatic translations.
    if (!headers_sent()) {
        header('Content-Language: en');
    }
    ?>
    <meta http-equiv="Content-Language" content="en">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet">
    
    <!-- Tailwind CSS (used by the site's navbar component) -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../' : ''); ?>assets/css/style.css">
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../' : ''); ?>assets/css/common.css">
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../' : ''); ?>assets/css/responsive.css">
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../' : ''); ?>assets/css/admin.css">
    
    <!-- jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <?php
}

// Navigation is now handled by includes/nav.php

?>