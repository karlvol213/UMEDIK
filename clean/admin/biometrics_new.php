<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Require admin login
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['isAdmin']) || !$_SESSION['isAdmin']) {
    header('Location: ../index.php');
    exit;
}

// Get all users with their latest vitals
$vitals_sql = "SELECT u.*, 
    (SELECT b.height FROM biometrics b WHERE b.user_id = u.id ORDER BY b.record_date DESC LIMIT 1) AS height,
    (SELECT b.weight FROM biometrics b WHERE b.user_id = u.id ORDER BY b.record_date DESC LIMIT 1) AS weight,
    (SELECT b.blood_pressure FROM biometrics b WHERE b.user_id = u.id ORDER BY b.record_date DESC LIMIT 1) AS blood_pressure,
    (SELECT b.temperature FROM biometrics b WHERE b.user_id = u.id ORDER BY b.record_date DESC LIMIT 1) AS temperature,
    (SELECT b.pulse_rate FROM biometrics b WHERE b.user_id = u.id ORDER BY b.record_date DESC LIMIT 1) AS pulse_rate,
    (SELECT b.record_date FROM biometrics b WHERE b.user_id = u.id ORDER BY b.record_date DESC LIMIT 1) AS record_date
FROM users u
WHERE u.is_deleted = 0 AND (u.role IS NULL OR u.role <> 'admin')
ORDER BY u.full_name";

$vitals_result = mysqli_query($conn, $vitals_sql);
$users = $vitals_result ? mysqli_fetch_all($vitals_result, MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biometrics Management - Healthcare Management System</title>

    <!-- Load Bootstrap first -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="/assets/css/style.css" rel="stylesheet">
    
    <!-- Load Tailwind Last -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        important: true,
        corePlugins: {
            preflight: false,
        }
    }
    </script>
</head>
<body class="bg-gray-100">
    <?php include __DIR__ . '/../includes/tailwind_nav.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Dashboard Section -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Total Patients Card -->
            <div class="dashboard-card">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 mr-4">
                        <i class="fas fa-users text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-700">Total Patients</h3>
                        <p class="text-3xl font-bold text-gray-900"><?php echo count($users); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Recent Records Card -->
            <div class="dashboard-card">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 mr-4">
                        <i class="fas fa-heartbeat text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-700">Recent Records</h3>
                        <p class="text-3xl font-bold text-gray-900">
                            <?php
                            $recent_count = mysqli_query($conn, "SELECT COUNT(*) as count FROM biometrics WHERE record_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                            $count = mysqli_fetch_assoc($recent_count);
                            echo $count['count'];
                            ?>
                        </p>
                        <p class="text-sm text-gray-600">Last 24 hours</p>
                    </div>
                </div>
            </div>

            <!-- Average Stats Card -->
            <div class="dashboard-card">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 mr-4">
                        <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-700">Average Blood Pressure</h3>
                        <p class="text-3xl font-bold text-gray-900">
                            <?php
                            $avg_bp = mysqli_query($conn, "SELECT AVG(SUBSTRING_INDEX(blood_pressure, '/', 1)) as systolic, AVG(SUBSTRING_INDEX(blood_pressure, '/', -1)) as diastolic FROM biometrics WHERE record_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                            $avg = mysqli_fetch_assoc($avg_bp);
                            echo round($avg['systolic']) . "/" . round($avg['diastolic']);
                            ?>
                        </p>
                        <p class="text-sm text-gray-600">Last 24 hours</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Patient Biometrics</h1>
                    <p class="text-gray-600 mt-2">Individual patient cards with recent biometric readings</p>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="biometrics_enroll.php" 
                       class="action-btn bg-blue-600 text-white">
                        <i class="fas fa-plus mr-2"></i>New Record
                    </a>
                    <a href="biometrics_export.php" 
                       class="action-btn bg-gray-600 text-white">
                        <i class="fas fa-download mr-2"></i>Export All
                    </a>
                </div>
            </div>
        </div>

        <!-- Search Section -->
        <div class="search-container">
            <input type="search" 
                   id="searchBox" 
                   class="search-input" 
                   placeholder="Search patients..."
                   onkeyup="filterCards()">
        </div>

        <!-- Patient Cards Grid -->
        <div class="patient-grid">
            <?php foreach ($users as $user): ?>
                <?php
                    $uid = (int)($user['user_id'] ?? 0);
                    $initial = !empty($user['full_name']) ? strtoupper(substr($user['full_name'], 0, 1)) : 'U';
                    $name = htmlspecialchars($user['full_name'] ?? 'Unnamed');
                    $status = htmlspecialchars($user['role'] ?? 'Patient');
                    $email = htmlspecialchars($user['email'] ?? '—');
                    $phone = htmlspecialchars($user['phone'] ?? '—');
                    $last_visit = !empty($user['record_date']) ? date('M j, Y g:i A', strtotime($user['record_date'])) : 'No records';
                    
                    $search_text = strtolower("$name $status $email $phone");
                ?>
                <div class="patient-card hover-lift" data-search="<?= htmlspecialchars($search_text) ?>">
                    <div class="patient-info">
                        <div class="gradient-avatar flex-shrink-0">
                            <?= $initial ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-lg font-bold text-gray-900 truncate"><?= $name ?></h3>
                            <p class="text-sm text-emerald-600 font-semibold mb-3"><?= $status ?></p>
                            
                            <?php if ($user['blood_pressure'] || $user['temperature'] || $user['pulse_rate']): ?>
                            <div class="grid grid-cols-2 gap-2 mb-4">
                                <?php if ($user['blood_pressure']): ?>
                                <div class="bg-gray-50 p-2 rounded">
                                    <span class="text-xs text-gray-500 block">BP</span>
                                    <span class="font-semibold"><?= htmlspecialchars($user['blood_pressure']) ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($user['temperature']): ?>
                                <div class="bg-gray-50 p-2 rounded">
                                    <span class="text-xs text-gray-500 block">Temp</span>
                                    <span class="font-semibold"><?= htmlspecialchars($user['temperature']) ?>°C</span>
                                </div>
                                <?php endif; ?>
                                <?php if ($user['pulse_rate']): ?>
                                <div class="bg-gray-50 p-2 rounded">
                                    <span class="text-xs text-gray-500 block">Pulse</span>
                                    <span class="font-semibold"><?= htmlspecialchars($user['pulse_rate']) ?> bpm</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="space-y-2 mb-4">
                                <p class="text-sm text-gray-600 truncate">
                                    <i class="fas fa-envelope mr-2 text-gray-400"></i><?= $email ?>
                                </p>
                                <p class="text-sm text-gray-600">
                                    <i class="fas fa-phone mr-2 text-gray-400"></i><?= $phone ?>
                                </p>
                                <p class="text-sm text-gray-600">
                                    <i class="fas fa-calendar mr-2 text-gray-400"></i><?= $last_visit ?>
                                </p>
                            </div>

                            <div class="flex space-x-2">
                                <a href="biometric_view.php?id=<?= $uid ?>" 
                                   class="action-btn flex-1 bg-blue-600 text-white">
                                    View History
                                </a>
                                <a href="biometric_record.php?id=<?= $uid ?>" 
                                   class="action-btn flex-1 gradient-btn">
                                    Record New
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    function filterCards() {
        const searchText = document.getElementById('searchBox').value.toLowerCase();
        document.querySelectorAll('[data-search]').forEach(card => {
            const cardText = card.dataset.search.toLowerCase();
            card.style.display = cardText.includes(searchText) ? 'block' : 'none';
        });
    }
    </script>
</body>
</html>