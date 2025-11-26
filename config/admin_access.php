<?php
/**
 * Admin Access Control and Role Management
 * 
 * This file handles role-based access control for admin users including doctors and nurses.
 * - Doctors can only access: patient history and patient notes
 * - Nurses can only access: appointment requests, biometrics, and patient history
 */

/**
 * Check if user is logged in as admin
 */
function is_admin_logged_in() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true;
}

/**
 * Check if user is a doctor
 */
function is_doctor() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['role']) && $_SESSION['role'] === 'doctor';
}

/**
 * Check if user is a nurse
 */
function is_nurse() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['role']) && $_SESSION['role'] === 'nurse';
}

/**
 * Get doctor emails allowed in the system
 */
function get_allowed_doctors() {
    return [
        'doctor1@umak.edu.ph' => 'Dr. Maria Santos',
        'doctor2@umak.edu.ph' => 'Dr. Juan Dela Cruz'
    ];
}

/**
 * Get nurse emails allowed in the system
 */
function get_allowed_nurses() {
    return [
        'nurse1@umak.edu.ph' => 'Nurse Anna Garcia',
        'nurse2@umak.edu.ph' => 'Nurse Rosa Fernandez',
        'nurse3@umak.edu.ph' => 'Nurse Elena Lopez'
    ];
}

/**
 * Check if email belongs to an allowed doctor
 */
function is_allowed_doctor($email) {
    $allowed_doctors = get_allowed_doctors();
    return isset($allowed_doctors[$email]);
}

/**
 * Check if email belongs to an allowed nurse
 */
function is_allowed_nurse($email) {
    $allowed_nurses = get_allowed_nurses();
    return isset($allowed_nurses[$email]);
}

/**
 * Verify admin access - redirects if not admin
 */
function require_admin_access() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true) {
        header("Location: ../index.php");
        exit();
    }
}

/**
 * Verify doctor-specific access
 * Doctors can only access patient history and patient notes pages
 */
function require_doctor_allowed_page() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is a doctor
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
        return true; // Not a doctor, allow normal access
    }
    
    // Get the current page name
    $current_page = basename($_SERVER['PHP_SELF']);
    
    // Define allowed pages for doctors
    $allowed_pages = ['patient_history.php', 'patient_notes.php'];
    
    // If doctor is trying to access unauthorized page, redirect to home
    if (!in_array($current_page, $allowed_pages)) {
        $_SESSION['error_message'] = "You do not have access to this page. Doctors can only access Patient History and Patient Notes.";
        header("Location: patient_history.php");
        exit();
    }
    
    return true;
}

/**
 * Verify nurse-specific access
 * Nurses can only access appointment requests, biometrics, and patient history pages
 */
function require_nurse_allowed_page() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is a nurse
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'nurse') {
        return true; // Not a nurse, allow normal access
    }
    
    // Get the current page name
    $current_page = basename($_SERVER['PHP_SELF']);
    
    // Define allowed pages for nurses
    $allowed_pages = ['admin.php', 'info_admin.php', 'patient_history.php'];
    
    // If nurse is trying to access unauthorized page, redirect to home
    if (!in_array($current_page, $allowed_pages)) {
        $_SESSION['error_message'] = "You do not have access to this page. Nurses can only access Appointment Requests, Biometrics, and Patient History.";
        header("Location: admin.php");
        exit();
    }
    
    return true;
}

/**
 * Get list of accessible pages for current user role
 */
function get_accessible_pages() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $role = $_SESSION['role'] ?? null;
    
    // Doctor-only accessible pages
    if ($role === 'doctor') {
        return [
            'patient_history.php' => 'Patient History',
            'patient_notes.php' => 'Patient Notes'
        ];
    }
    
    // Nurse-only accessible pages
    if ($role === 'nurse') {
        return [
            'admin.php' => 'Appointment Requests',
            'info_admin.php' => 'Biometrics',
            'patient_history.php' => 'Patient History'
        ];
    }
    
    // Admin-accessible pages (full admin)
    if ($_SESSION['isAdmin'] ?? false) {
        return [
            'admin.php' => 'Dashboard',
            'registered_users.php' => 'Registered Users',
            'patient_history.php' => 'Patient History',
            'patient_history_details.php' => 'Patient Details',
            'patient_notes.php' => 'Patient Notes',
            'update_appointment.php' => 'Update Appointment',
            'biometrics.php' => 'Biometrics',
            'history_log.php' => 'History Logs',
            'reset_user_password.php' => 'Reset Password',
            'admin_unlock_user.php' => 'Unlock User'
        ];
    }
    
    return [];
}

/**
 * Display navigation based on user role
 */
function get_role_based_nav() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $role = $_SESSION['role'] ?? null;
    $nav_items = [];
    
    if ($role === 'doctor') {
        $nav_items = [
            ['url' => 'patient_history.php', 'label' => 'Patient History', 'icon' => '📋'],
            ['url' => 'patient_notes.php', 'label' => 'Patient Notes', 'icon' => '📝']
        ];
    } elseif ($role === 'nurse') {
        $nav_items = [
            ['url' => 'admin.php', 'label' => 'Appointment Requests', 'icon' => '📅'],
            ['url' => 'info_admin.php', 'label' => 'Biometrics', 'icon' => '❤️'],
            ['url' => 'patient_history.php', 'label' => 'Patient History', 'icon' => '📋']
        ];
    }
    
    return $nav_items;
}

?>
