<?php
/**
 * Revised Navbar - All Paths Fixed
 * 
 * Uses absolute paths starting with /clean/ for consistency
 * Works from any subdirectory in the /clean/ folder
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = !empty($_SESSION['loggedin']);
$isAdmin = !empty($_SESSION['isAdmin']);
$userName = $_SESSION['full_name'] ?? $_SESSION['email'] ?? 'User';

// Get the base URL path up to /clean/
$currentUri = $_SERVER['REQUEST_URI'];
$cleanPos = strpos($currentUri, '/clean/');
$baseUrl = '/clean/';

?>
<!-- Navigation Bar - Bootstrap Based -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top" style="margin-bottom: 0;">
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
            <!-- Center Navigation - Available to all users -->
            <ul class="navbar-nav mx-auto">
                <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>home.php">Home</a></li>
                <?php if ($isLoggedIn): ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>patient/appointments.php">Appointments</a></li>
                <?php endif; ?>
            </ul>

            <!-- Right Side Navigation -->
            <ul class="navbar-nav ms-auto">
                <?php if ($isLoggedIn): ?>
                    <!-- Logged in user - show profile and logout -->
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>user_profile.php"><?php echo htmlspecialchars($userName); ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>logout.php">Logout</a></li>
                <?php else: ?>
                    <!-- Guest - show login and register -->
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>index.php">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<style>
    .navbar {
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        background-color: #212529 !important;
        padding: 1rem 0;
    }

    .navbar-brand {
        font-weight: 600;
        font-size: 1.1rem;
        color: white !important;
    }

    .nav-link {
        margin: 0 12px;
        transition: color 0.3s ease;
        color: rgba(255, 255, 255, 0.9) !important;
        font-weight: 500;
        display: inline-block;
        padding: 0.5rem 0 !important;
    }

    .nav-link:hover {
        color: #0d6efd !important;
        text-decoration: none;
    }

    .nav-link.active {
        color: #0d6efd !important;
        font-weight: 600;
    }

    .navbar-dark .navbar-nav .nav-link {
        color: rgba(255, 255, 255, 0.9) !important;
    }

    .navbar-dark .navbar-nav .nav-link:hover {
        color: #0d6efd !important;
    }

    .navbar-dark .navbar-nav .nav-link.active {
        color: #0d6efd !important;
    }

    .navbar-toggler {
        border-color: rgba(255, 255, 255, 0.5) !important;
    }

    .navbar-toggler-icon {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.9%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
    }

    /* Mobile responsiveness */
    @media (max-width: 768px) {
        .nav-link {
            margin: 12px 0;
        }
    }
</style>
