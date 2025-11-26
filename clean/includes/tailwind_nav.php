<?php
/**
 * Responsive Navigation with Sidebar Menu
 * Matches UMak TBL Hub design pattern
 * Desktop: Horizontal navbar with centered links
 * Mobile/Tablet: Hamburger menu converts to left sidebar
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn  = !empty($_SESSION['loggedin']);
$isAdmin     = !empty($_SESSION['isAdmin']);
$isDoctor    = !empty($_SESSION['role']) && $_SESSION['role'] === 'doctor';
$isNurse     = !empty($_SESSION['role']) && $_SESSION['role'] === 'nurse';

// Determine base path for links
$isPatientDir = strpos($_SERVER['PHP_SELF'], '/patient/') !== false;
$basePrefix = $isPatientDir ? '../' : './';

// Admin links - full access
$adminLinks = [
  'registered_users.php' => 'Registered Users',
  'history_log.php'      => 'History Logs',
  'admin.php'            => 'Appointment Requests',
  'info_admin.php'       => 'Biometrics',
  'patient_history.php'  => 'Patient History',
  'patient_notes.php'    => 'Patient Notes'
];

// Doctor links - restricted access
$doctorLinks = [
  'patient_history.php'  => 'Patient History',
  'patient_notes.php'    => 'Patient Notes'
];

// Nurse links - restricted access
$nurseLinks = [
  'admin.php'            => 'Appointment Requests',
  'info_admin.php'       => 'Biometrics',
  'patient_history.php'  => 'Patient History'
];

function nav_anchor($href, $label, $currentPage, $mobile = false) {
    $isActive = ($currentPage === basename($href));
    if ($mobile) {
        $baseClasses  = "block w-full text-left px-4 py-3 text-white no-underline font-semibold text-lg rounded-lg transition-all";
        $activeClasses= $isActive ? "bg-white/20" : "hover:bg-white/10";
    } else {
        $baseClasses  = "nav-link text-white no-underline px-4 py-2 rounded-lg inline-flex items-center whitespace-nowrap font-semibold text-base transition-all hover:bg-white/10";
        $activeClasses= $isActive ? "bg-white/10 font-bold" : "";
    }
    $safeLabel    = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    echo '<a href="'.$href.'" class="'.$baseClasses.' '.$activeClasses.'">'.$safeLabel.'</a>';
}

/* ========== ADMIN/DOCTOR/NURSE NAVBAR ========== */
if ($isLoggedIn && $isAdmin): 
  // Determine which links to show based on role
  if ($isDoctor) {
      $links = $doctorLinks;
      $portal_name = 'Doctor Portal';
      $homeLink = 'patient_history.php';
  } elseif ($isNurse) {
      $links = $nurseLinks;
      $portal_name = 'Nurse Portal';
      $homeLink = 'admin.php';
  } else {
      $links = $adminLinks;
      $portal_name = 'University of Makati';
      $homeLink = 'admin.php';
  }
  
  $isAdminDir = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
  $prefix = $isAdminDir ? '../' : '';
?>
  <!-- Top Navbar -->
  <nav class="fixed top-0 left-0 right-0 z-50 bg-[#003366] h-16 shadow-lg flex items-center justify-between px-4 md:px-6">
    <!-- Left: Logo and Brand (hidden on mobile, visible from md) -->
    <div class="hidden md:flex items-center gap-4 min-w-[240px]">
      <a href="<?php echo $isAdminDir ? $homeLink : $prefix . $homeLink; ?>" class="inline-flex items-center gap-2.5 no-underline">
        <img src="<?php echo $isAdminDir ? '../' : ''; ?>assets/images/umak3.ico" alt="UMAK" class="h-11 w-auto rounded-full border-2 border-white">
        <span class="brand-text text-white font-extrabold text-[1.05rem] leading-none hidden lg:inline">
          <?php echo $portal_name; ?>
        </span>
      </a>
    </div>

    <!-- Mobile Hamburger Button -->
    <button id="sidebar-toggle" class="md:hidden text-white p-2 hover:bg-white/10 rounded-lg transition-colors">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
    </button>

    <!-- Center: Navigation Links (hidden on mobile, visible from md) -->
    <div class="hidden md:flex flex-1 items-center px-4 overflow-x-auto no-scrollbar">
      <div class="flex gap-2 items-center whitespace-nowrap">
        <?php foreach ($links as $file => $label) {
          $filePath = $isAdminDir ? $file : "admin/$file";
          nav_anchor($filePath, $label, $currentPage, false);
        } ?>
      </div>
    </div>

    <!-- Right: User Info and Logout -->
    <div class="flex items-center gap-3 md:gap-4">
      <?php if (!empty($_SESSION['full_name'])): ?>
        <span class="text-white text-xs md:text-sm font-semibold hidden sm:inline truncate">
          <?php echo htmlspecialchars($_SESSION['full_name']); ?>
        </span>
      <?php endif; ?>
      <a href="<?php echo $prefix; ?>logout.php" class="bg-[#cc0000] text-white px-2 md:px-3 py-2 rounded-lg no-underline inline-flex items-center font-bold hover:bg-[#b20000] text-xs md:text-sm transition-colors">
        Logout
      </a>
    </div>
  </nav>

  <!-- Mobile Sidebar -->
  <div id="sidebar" class="fixed left-0 top-0 bottom-0 w-64 bg-[#003366] shadow-xl z-40 transform -translate-x-full transition-transform duration-300 md:hidden overflow-y-auto">
    <!-- Sidebar Header -->
    <div class="flex items-center gap-3 p-4 border-b border-white/10">
      <img src="<?php echo $isAdminDir ? '../' : ''; ?>assets/images/umak3.ico" alt="UMAK" class="h-10 w-auto rounded-full border border-white">
      <span class="text-white font-bold text-sm"><?php echo $portal_name; ?></span>
    </div>

    <!-- Sidebar Links -->
    <div class="flex flex-col p-4 gap-2">
      <?php foreach ($links as $file => $label) {
        $filePath = $isAdminDir ? $file : "admin/$file";
        nav_anchor($filePath, $label, $currentPage, true);
      } ?>
    </div>

    <!-- Sidebar Footer -->
    <div class="absolute bottom-0 left-0 right-0 border-t border-white/10 p-4 space-y-2">
      <?php if (!empty($_SESSION['full_name'])): ?>
        <div class="text-white text-xs font-semibold px-4 py-2 truncate">
          <?php echo htmlspecialchars($_SESSION['full_name']); ?>
        </div>
      <?php endif; ?>
      <a href="<?php echo $prefix; ?>logout.php" class="block w-full bg-[#cc0000] text-white px-4 py-2 rounded-lg no-underline text-center font-bold hover:bg-[#b20000] transition-colors">
        Logout
      </a>
    </div>
  </div>

  <!-- Sidebar Overlay (clicks to close) -->
  <div id="sidebar-overlay" class="hidden fixed inset-0 z-30 bg-black/50 md:hidden"></div>

<?php /* ========== GUEST NAVBAR ========== */ elseif (!$isLoggedIn): ?>
  <!-- Top Navbar for Guests -->
  <nav class="fixed top-0 left-0 right-0 z-50 bg-[#003366] h-16 shadow-md flex items-center justify-between px-4 md:px-6">
    <!-- Left: Logo -->
    <div class="flex items-center gap-3">
      <a href="home.php" class="inline-flex items-center gap-2.5 no-underline">
        <img src="./assets/images/umak3.ico" alt="UMAK" class="h-11 w-auto rounded-full border-2 border-white">
        <span class="brand-text text-white font-extrabold text-[1.05rem] leading-none hidden sm:inline">
          UMak Medical Clinic
        </span>
      </a>
    </div>

    <!-- Right: Login/Register Buttons -->
    <div class="flex items-center gap-2 md:gap-3">
      <?php
        $isRegister   = ($currentPage === 'register.php');
        $isLogin      = ($currentPage === 'index.php');
        $baseClasses  = "btn-cta bg-white text-[#003366] font-bold px-2 md:px-3 py-2 rounded-lg no-underline inline-flex items-center gap-2 border border-black/5 hover:brightness-[0.98] transition-all text-xs md:text-sm";
      ?>
      <a href="register.php" class="<?php echo $baseClasses . ($isRegister ? ' brightness-[0.98]' : ''); ?>">Register</a>
      <a href="index.php" class="<?php echo $baseClasses . ($isLogin ? ' brightness-[0.98]' : ''); ?>">Login</a>
    </div>
  </nav>

<?php /* ========== REGULAR USER NAVBAR ========== */ else:
  $userLinks = [
      $basePrefix . 'home.php'                        => 'Home',
      $basePrefix . 'patient/appointments.php'        => 'Appointments'
  ];
?>
  <!-- Top Navbar -->
  <nav class="fixed top-0 left-0 right-0 z-50 bg-[#003366] h-16 shadow-md flex items-center justify-between px-4 md:px-6">
    <!-- Left: Logo -->
    <div class="flex items-center gap-3">
      <a href="<?php echo $basePrefix; ?>home.php" class="inline-flex items-center gap-2.5 no-underline">
        <img src="<?php echo $basePrefix; ?>assets/images/umak3.ico" alt="UMAK" class="h-11 w-auto rounded-full border-2 border-white">
        <span class="brand-text text-white font-extrabold text-[1.05rem] leading-none hidden sm:inline">
          University of Makati
        </span>
      </a>
    </div>

    <!-- Mobile Hamburger -->
    <button id="user-sidebar-toggle" class="md:hidden text-white p-2 hover:bg-white/10 rounded-lg transition-colors">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
    </button>

    <!-- Center: Navigation (hidden on mobile) -->
    <div class="hidden md:flex flex-1 items-center justify-center px-4 gap-4">
      <?php foreach ($userLinks as $file => $label) { nav_anchor($file, $label, $currentPage, false); } ?>
    </div>

    <!-- Right: User Menu -->
    <div class="flex items-center gap-2 md:gap-3">
      <?php if (!empty($_SESSION['full_name'])): ?>
        <a href="<?php echo $basePrefix; ?>user_profile.php" class="text-white no-underline px-2 md:px-3 py-2 rounded-lg inline-flex items-center gap-2 font-semibold text-xs md:text-sm transition-all hover:bg-white/10">
          <?php echo htmlspecialchars($_SESSION['full_name']); ?>
        </a>
      <?php endif; ?>
      <a href="<?php echo $basePrefix; ?>logout.php" class="bg-[#cc0000] text-white px-2 md:px-3 py-2 rounded-lg no-underline inline-flex items-center font-bold hover:bg-[#b20000] text-xs md:text-sm transition-colors">
        Logout
      </a>
    </div>
  </nav>

  <!-- Mobile Sidebar for User -->
  <div id="user-sidebar" class="fixed left-0 top-0 bottom-0 w-64 bg-[#003366] shadow-xl z-40 transform -translate-x-full transition-transform duration-300 md:hidden overflow-y-auto">
    <!-- Sidebar Header -->
    <div class="flex items-center gap-3 p-4 border-b border-white/10">
      <img src="<?php echo $basePrefix; ?>assets/images/umak3.ico" alt="UMAK" class="h-10 w-auto rounded-full border border-white">
      <span class="text-white font-bold text-sm">Navigation</span>
    </div>

    <!-- Sidebar Links -->
    <div class="flex flex-col p-4 gap-2">
      <?php foreach ($userLinks as $file => $label) { nav_anchor($file, $label, $currentPage, true); } ?>
      <?php if (!empty($_SESSION['full_name'])): ?>
        <a href="<?php echo $basePrefix; ?>user_profile.php" class="block w-full text-left px-4 py-3 text-white no-underline font-semibold text-lg rounded-lg hover:bg-white/10 transition-all">
          <?php echo htmlspecialchars($_SESSION['full_name']); ?>
        </a>
      <?php endif; ?>
    </div>

    <!-- Sidebar Footer -->
    <div class="absolute bottom-0 left-0 right-0 border-t border-white/10 p-4">
      <a href="<?php echo $basePrefix; ?>logout.php" class="block w-full bg-[#cc0000] text-white px-4 py-2 rounded-lg no-underline text-center font-bold hover:bg-[#b20000] transition-colors">
        Logout
      </a>
    </div>
  </div>

  <!-- Sidebar Overlay -->
  <div id="user-sidebar-overlay" class="hidden fixed inset-0 z-30 bg-black/50 md:hidden"></div>
<?php endif; ?>

<script>
// Admin/Doctor/Nurse Sidebar Toggle
const sidebarToggle = document.getElementById('sidebar-toggle');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebar-overlay');

if (sidebarToggle && sidebar) {
  sidebarToggle.addEventListener('click', () => {
    sidebar.classList.toggle('-translate-x-full');
    sidebarOverlay.classList.toggle('hidden');
  });

  if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', () => {
      sidebar.classList.add('-translate-x-full');
      sidebarOverlay.classList.add('hidden');
    });
  }

  // Close sidebar when a link is clicked
  const sidebarLinks = sidebar.querySelectorAll('a:not(:last-child)');
  sidebarLinks.forEach(link => {
    link.addEventListener('click', () => {
      sidebar.classList.add('-translate-x-full');
      sidebarOverlay.classList.add('hidden');
    });
  });
}

// User Sidebar Toggle
const userSidebarToggle = document.getElementById('user-sidebar-toggle');
const userSidebar = document.getElementById('user-sidebar');
const userSidebarOverlay = document.getElementById('user-sidebar-overlay');

if (userSidebarToggle && userSidebar) {
  userSidebarToggle.addEventListener('click', () => {
    userSidebar.classList.toggle('-translate-x-full');
    userSidebarOverlay.classList.toggle('hidden');
  });

  if (userSidebarOverlay) {
    userSidebarOverlay.addEventListener('click', () => {
      userSidebar.classList.add('-translate-x-full');
      userSidebarOverlay.classList.add('hidden');
    });
  }

  // Close sidebar when a link is clicked
  const userSidebarLinks = userSidebar.querySelectorAll('a:not(:last-child)');
  userSidebarLinks.forEach(link => {
    link.addEventListener('click', () => {
      userSidebar.classList.add('-translate-x-full');
      userSidebarOverlay.classList.add('hidden');
    });
  });
}
</script>

<style>
/* Base Navbar Styles */
nav.fixed.top-0.left-0.right-0 {
  background: #003366 !important;
  height: 64px !important;
  box-shadow: 0 2px 8px rgba(0,0,0,.12) !important;
}

/* Navigation Links */
nav.fixed.top-0.left-0.right-0 a.nav-link {
  color: #fff !important;
}

/* CTA Buttons */
.btn-cta {
  color: #003366 !important;
}

/* Page Content Padding */
body {
  padding-top: 64px;
}

/* Remove scrollbar styling */
.no-scrollbar::-webkit-scrollbar {
  display: none;
}

.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

/* Link Transitions */
nav a {
  transition: all .2s ease-in-out;
}

/* Mobile Responsive */
@media (max-width: 768px) {
  nav.fixed.top-0.left-0.right-0 {
    height: 56px !important;
    padding: 0.5rem !important;
  }

  body {
    padding-top: 56px;
  }

  nav a {
    padding-left: 0.5rem !important;
    padding-right: 0.5rem !important;
  }

  nav .brand-text {
    font-size: 0.85rem !important;
  }
}

/* Sidebar Animation */
#sidebar,
#user-sidebar {
  transition: transform 0.3s ease-in-out;
}

#sidebar.show,
#user-sidebar.show {
  transform: translateX(0);
}

/* Hide scrollbar but keep scrolling functionality */
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

.no-scrollbar::-webkit-scrollbar {
  display: none;
}

/* Smooth horizontal scrolling for navbar */
nav .no-scrollbar {
  scroll-behavior: smooth;
}

/* Smooth scrolling in sidebar */
#sidebar,
#user-sidebar {
  scrollbar-width: thin;
  scrollbar-color: rgba(255,255,255,0.3) transparent;
}

#sidebar::-webkit-scrollbar,
#user-sidebar::-webkit-scrollbar {
  width: 6px;
}

#sidebar::-webkit-scrollbar-track,
#user-sidebar::-webkit-scrollbar-track {
  background: transparent;
}

#sidebar::-webkit-scrollbar-thumb,
#user-sidebar::-webkit-scrollbar-thumb {
  background: rgba(255,255,255,0.3);
  border-radius: 3px;
}

#sidebar::-webkit-scrollbar-thumb:hover,
#user-sidebar::-webkit-scrollbar-thumb:hover {
  background: rgba(255,255,255,0.5);
}
</style>
