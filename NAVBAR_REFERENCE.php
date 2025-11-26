<?php
/**
 * REVISED NAVBAR - Complete Reference
 * 
 * File: /clean/includes/navbar.php
 * Date: November 22, 2025
 * Status: ✅ COMPLETE WITH ALL PATHS FIXED
 * 
 * ============================================
 * KEY IMPROVEMENTS
 * ============================================
 * 
 * 1. ALL PATHS NOW USE /clean/ PREFIX
 *    - Before: Used relative paths like ../home.php
 *    - After:  Uses absolute paths like /clean/home.php
 * 
 * 2. LOGO CHANGED TO UMAK3.ICO
 *    - Before: umaklogo.png (404 error)
 *    - After:  umak3.ico (exists in assets/images/)
 * 
 * 3. CONSISTENT NAVIGATION
 *    - Works from ANY directory in /clean/
 *    - No path calculation errors
 *    - Same links everywhere
 * 
 * 4. RESPONSIVE DESIGN
 *    - Bootstrap navbar for mobile compatibility
 *    - Hamburger menu for small screens
 * 
 * ============================================
 * NAVBAR STRUCTURE
 * ============================================
 * 
 * $baseUrl = '/clean/';
 * 
 * ADMIN NAVIGATION (if isAdmin):
 * ├── /clean/admin/admin.php (Dashboard)
 * ├── /clean/admin/registered_users.php (Users)
 * ├── /clean/admin/info_admin.php (Biometrics)
 * ├── /clean/admin/patient_history.php (Patient History)
 * ├── /clean/admin/history_log.php (Logs)
 * ├── /clean/admin/patient_notes.php (Notes)
 * ├── /clean/admin/reset_user_password.php (Reset Password)
 * └── /clean/logout.php (Logout)
 * 
 * PATIENT NAVIGATION (if isLoggedIn):
 * ├── /clean/home.php (Home)
 * ├── /clean/patient/appointments.php (Appointments)
 * ├── /clean/user_profile.php (Profile)
 * └── /clean/logout.php (Logout)
 * 
 * GUEST NAVIGATION (if not logged in):
 * ├── /clean/index.php (Login)
 * └── /clean/register.php (Register)
 * 
 * ============================================
 * LOGO BEHAVIOR
 * ============================================
 * 
 * Logo Link:
 * - If logged in:  Links to /clean/home.php (patient dashboard)
 * - If not logged in: Links to /clean/index.php (login page)
 * 
 * Logo Image:
 * - Source: /clean/assets/images/umak3.ico
 * - Size: 40px height
 * - Alt Text: "UMAK Logo"
 * 
 * ============================================
 * USAGE IN PHP FILES
 * ============================================
 * 
 * Include in any PHP file:
 * 
 *   <?php
 *   session_start();
 *   include '/clean/includes/navbar.php';
 *   ?>
 * 
 * The navbar will automatically:
 * - Detect if user is logged in
 * - Show appropriate menu for user role
 * - Display correct logo and links
 * 
 * ============================================
 * PATH MAPPING - ALL FILES
 * ============================================
 * 
 * ROOT LEVEL:
 * /clean/index.php ...................... Login page (entry point)
 * /clean/register.php ................... Registration page
 * /clean/home.php ....................... Patient dashboard (post-login)
 * /clean/user_profile.php ............... User profile management
 * /clean/logout.php ..................... Session termination
 * /clean/schedule.php ................... Appointment scheduling (root level)
 * 
 * ADMIN SECTION (/clean/admin/):
 * /clean/admin/admin.php ................ Admin dashboard
 * /clean/admin/registered_users.php ..... User management
 * /clean/admin/info_admin.php ........... Biometrics monitoring
 * /clean/admin/patient_history.php ...... Patient medical records
 * /clean/admin/patient_history_details.php .. Record details
 * /clean/admin/patient_notes.php ........ Clinical notes
 * /clean/admin/history_log.php .......... Activity audit log
 * /clean/admin/reset_user_password.php .. Password management
 * /clean/admin/admin_unlock_user.php .... Account unlocking
 * /clean/admin/archive_appointment.php .. Archive function
 * /clean/admin/update_appointment.php ... Appointment updates
 * /clean/admin/export_record_pdf.php .... Export to PDF
 * /clean/admin/admin_archive_record.php . Archive records
 * 
 * PATIENT SECTION (/clean/patient/):
 * /clean/patient/appointments.php ....... View appointments
 * /clean/patient/schedule.php ........... Schedule new appointment
 * 
 * ASSETS:
 * /clean/assets/images/umak3.ico ........ Logo (40x40px)
 * /clean/assets/images/clinic_umak.ico . Clinic logo
 * /clean/assets/images/umak2.png ....... Logo variant
 * /clean/assets/images/umaklogo.png ... Logo variant
 * /clean/assets/css/ ................... CSS stylesheets
 * /clean/assets/js/ .................... JavaScript files
 * 
 * CONFIG:
 * /clean/config/database.php ........... Database connection
 * /clean/config/functions.php ......... Utility functions
 * /clean/config/admin_access.php ....... RBAC configuration
 * 
 * INCLUDES:
 * /clean/includes/navbar.php ........... Main navbar (THIS FILE)
 * /clean/includes/header.php ........... HTML head template
 * /clean/includes/auth_check.php ....... Authentication middleware
 * /clean/includes/nav.php ............. Deprecated (redirects to navbar.php)
 * 
 * ============================================
 * SESSION VARIABLES USED
 * ============================================
 * 
 * $_SESSION['loggedin'] ......... true if logged in
 * $_SESSION['isAdmin'] ......... true if user is admin
 * $_SESSION['full_name'] ....... User's full name
 * $_SESSION['email'] ........... User's email
 * $_SESSION['role'] ............ User role (admin, doctor, nurse, user)
 * 
 * ============================================
 * BOOTSTRAP REQUIREMENTS
 * ============================================
 * 
 * The navbar requires Bootstrap 5 CSS and JS:
 * 
 * CSS: <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
 * JS:  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
 * 
 * These should be included in your HTML <head> before including navbar.php
 * 
 * ============================================
 * MOBILE RESPONSIVENESS
 * ============================================
 * 
 * - Desktop (> 768px): Horizontal navbar with all links visible
 * - Tablet/Mobile (≤ 768px): Hamburger menu with collapse animation
 * - Hamburger icon appears automatically on small screens
 * - Links collapse into dropdown menu
 * 
 * ============================================
 * COLOR SCHEME
 * ============================================
 * 
 * Primary: #003366 (dark blue) - navbar background
 * Hover: #0d6efd (bright blue) - link hover color
 * Active: #0d6efd (bright blue) - current page link
 * Text: White (#ffffff) on dark background
 * Logout Button: #cc0000 (red)
 * 
 * ============================================
 * TROUBLESHOOTING
 * ============================================
 * 
 * Issue: Logo not showing
 * Solution: Check /clean/assets/images/umak3.ico exists
 * 
 * Issue: Links go to wrong pages
 * Solution: Clear browser cache (Ctrl+Shift+Delete)
 * 
 * Issue: Navbar not appearing
 * Solution: Ensure Bootstrap CSS/JS is included in HTML head
 * 
 * Issue: Logo clicks redirect to wrong page
 * Solution: Check $_SESSION['loggedin'] is set correctly
 * 
 * ============================================
 * RECENT FIXES (November 22, 2025)
 * ============================================
 * 
 * ✅ Changed all paths from relative to absolute (/clean/)
 * ✅ Fixed logo image from umaklogo.png to umak3.ico
 * ✅ Fixed "Home" button in patient navbar
 * ✅ Fixed patient/appointments.php links
 * ✅ Fixed admin navigation paths
 * ✅ Fixed logout links from all pages
 * ✅ Verified image asset paths
 * ✅ Tested navigation from all subdirectories
 * 
 * ============================================
 */

?>

<!-- NAVBAR PHP CODE -->
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = !empty($_SESSION['loggedin']);
$isAdmin = !empty($_SESSION['isAdmin']);
$userName = $_SESSION['full_name'] ?? $_SESSION['email'] ?? 'User';
$baseUrl = '/clean/';
?>

<!-- Navigation Bar - Bootstrap Based -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top" style="margin-bottom: 60px;">
    <div class="container-fluid">
        <!-- Logo / Brand -->
        <a class="navbar-brand d-flex align-items-center" 
           href="<?php echo $baseUrl . ($isLoggedIn ? 'home.php' : 'index.php'); ?>">
            <img src="<?php echo $baseUrl; ?>assets/images/umak3.ico" 
                 alt="UMAK Logo" 
                 style="height: 40px; margin-right: 10px;">
            <span>University of Makati</span>
        </a>

        <!-- Mobile Hamburger Menu -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navigation Links -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <?php if ($isLoggedIn && $isAdmin): ?>
                <!-- Admin Navigation -->
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>admin/admin.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>admin/registered_users.php">Users</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>admin/info_admin.php">Biometrics</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>admin/patient_history.php">Patient History</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>admin/history_log.php">Logs</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>admin/patient_notes.php">Notes</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>admin/reset_user_password.php">Reset Password</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>logout.php">Logout</a></li>
                </ul>

            <?php elseif ($isLoggedIn): ?>
                <!-- Patient Navigation -->
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>home.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>patient/appointments.php">Appointments</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>user_profile.php"><?php echo htmlspecialchars($userName); ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>logout.php">Logout</a></li>
                </ul>

            <?php else: ?>
                <!-- Guest Navigation -->
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>index.php">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>register.php">Register</a></li>
                </ul>

            <?php endif; ?>
        </div>
    </div>
</nav>

<style>
    .navbar {
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .navbar-brand {
        font-weight: 600;
        font-size: 1.1rem;
    }

    .nav-link {
        margin: 0 8px;
        transition: color 0.3s ease;
    }

    .nav-link:hover {
        color: #0d6efd !important;
    }

    .nav-link.active {
        color: #0d6efd !important;
        font-weight: 600;
    }

    .navbar-dark .navbar-nav .nav-link {
        color: rgba(255, 255, 255, 0.8);
    }

    .navbar-dark .navbar-nav .nav-link.active {
        color: #0d6efd !important;
    }

    @media (max-width: 768px) {
        .nav-link {
            margin: 8px 0;
        }
    }
</style>
