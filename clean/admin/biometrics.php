<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Biometric.php';
require_once __DIR__ . '/components/ui.php';

// Require admin login
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['isAdmin']) || !$_SESSION['isAdmin']) {
    header('Location: ../index.php');
    exit;
}

// Initialize models
$biometricModel = new Biometric();

// Get data
$users = $biometricModel->getLatestVitals();
$recent_records = $biometricModel->getRecentRecords(5);
$recent_count = $biometricModel->getRecentCount(24);
$avg_bp = $biometricModel->getAverageBloodPressure(24);

// Set page variables for layout
$title = 'Biometrics Management';
$header_title = 'Patient Biometrics';
$header_description = 'Manage and monitor patient biometric data';
$header_actions = '
    <button onclick="location.href=\'biometrics_enroll.php\'" 
            class="btn-primary">
        <i class="fas fa-plus-circle mr-2"></i>New Record
    </button>
    <button onclick="location.href=\'biometrics_export.php\'" 
            class="btn-secondary">
        <i class="fas fa-download mr-2"></i>Export All
    </button>
';

ob_start();
?>

<!-- New Dashboard Layout (Tailwind) -->
<header class="bg-white shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-4">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-primary-600 rounded-md flex items-center justify-center text-white font-bold">H</div>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Biometrics</h1>
                    <p class="text-sm text-gray-500">Health monitoring and patient vitals</p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <?php echo $header_actions; ?>
            </div>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Summary Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4 flex items-center">
            <div class="p-3 rounded-full bg-blue-50 mr-4">
                <i class="fas fa-users text-blue-600 text-xl"></i>
            </div>
            <div>
                <div class="text-sm text-gray-500">Total Patients</div>
                <div class="text-2xl font-bold text-gray-900"><?php echo count($users); ?></div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 flex items-center">
            <div class="p-3 rounded-full bg-green-50 mr-4">
                <i class="fas fa-heartbeat text-green-600 text-xl"></i>
            </div>
            <div>
                <div class="text-sm text-gray-500">Recent Records</div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $recent_count; ?></div>
                <div class="text-xs text-gray-500">Last 24 hours</div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 flex items-center">
            <div class="p-3 rounded-full bg-purple-50 mr-4">
                <i class="fas fa-chart-line text-purple-600 text-xl"></i>
            </div>
            <div>
                <div class="text-sm text-gray-500">Average BP</div>
                <div class="text-2xl font-bold text-gray-900"><?php echo round($avg_bp['systolic']) . '/' . round($avg_bp['diastolic']); ?></div>
                <div class="text-xs text-gray-500">Last 24 hours</div>
            </div>
        </div>
    </div>

    <!-- Search + Content -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-lg font-medium text-gray-900">Patient Overview</h2>
                <p class="text-sm text-gray-500">Search and filter patient cards</p>
            </div>
            <div class="w-full sm:w-1/3">
                <input id="searchBox" type="search" placeholder="Search by name or department..." class="w-full border border-gray-200 rounded-md px-3 py-2" onkeyup="filterCards()">
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Patient Cards (left) -->
        <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-6">
            <?php foreach ($users as $user): 
                $search_data = implode(' ', [
                        strtolower(trim($user['full_name'] ?? '')),
                        strtolower(trim($user['email'] ?? '')),
                        strtolower(trim($user['phone'] ?? '')),
                        strtolower(trim($user['department_college_institute'] ?? ''))
                ]);
            ?>
                <div class="bg-white rounded-lg shadow p-4" data-search="<?= htmlspecialchars($search_data) ?>">
                    <div class="flex items-start space-x-4">
                        <div class="w-12 h-12 bg-primary-600 rounded-md flex items-center justify-center text-white font-bold text-lg"><?= strtoupper(substr($user['full_name'] ?? '', 0, 1)); ?></div>
                        <div class="flex-1">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="text-md font-semibold text-gray-900"><?= htmlspecialchars($user['full_name'] ?? ''); ?></div>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($user['department_college_institute'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="text-sm text-primary-600 font-medium"><?= htmlspecialchars(ucfirst($user['role'] ?? 'Patient')); ?></div>
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-3 text-sm text-gray-700">
                                <div class="bg-gray-50 p-2 rounded">
                                    <div class="text-xs text-gray-500">BP</div>
                                    <div class="font-medium"><?= htmlspecialchars($user['blood_pressure'] ?? '—'); ?></div>
                                </div>
                                <div class="bg-gray-50 p-2 rounded">
                                    <div class="text-xs text-gray-500">Temp</div>
                                    <div class="font-medium"><?= htmlspecialchars($user['temperature'] ? $user['temperature'] . '°C' : '—'); ?></div>
                                </div>
                                <div class="bg-gray-50 p-2 rounded">
                                    <div class="text-xs text-gray-500">Pulse</div>
                                    <div class="font-medium"><?= htmlspecialchars($user['pulse_rate'] ?? '—'); ?></div>
                                </div>
                                <div class="bg-gray-50 p-2 rounded">
                                    <div class="text-xs text-gray-500">Last Visit</div>
                                    <div class="font-medium"><?= !empty($user['record_date']) ? date('M j, Y', strtotime($user['record_date'])) : 'No records'; ?></div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <a href="biometric/view/<?= $user['user_id'] ?>" class="inline-block w-full text-center bg-primary-600 text-white rounded-md px-3 py-2 text-sm">
                                    <i class="fas fa-history mr-2"></i>View History
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Recent records (right) -->
        <aside class="bg-white rounded-lg shadow p-4">
            <h3 class="text-sm font-medium text-gray-900 mb-3">Recent Biometric Records</h3>
            <div class="table-wrapper">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th class="text-left text-xs text-gray-500 uppercase">Patient</th>
                            <th class="text-left text-xs text-gray-500 uppercase">BP</th>
                            <th class="text-left text-xs text-gray-500 uppercase">Temp</th>
                            <th class="text-left text-xs text-gray-500 uppercase">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_records as $r): ?>
                        <tr class="border-t">
                            <td class="py-2 text-sm"><?= htmlspecialchars($r['full_name']); ?></td>
                            <td class="py-2 text-sm"><?= htmlspecialchars($r['blood_pressure']); ?></td>
                            <td class="py-2 text-sm"><?= htmlspecialchars($r['temperature']) . '°C'; ?></td>
                            <td class="py-2 text-sm"><?= date('M j, Y g:i A', strtotime($r['record_date'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </aside>
    </div>
</main>

<?php
$content = ob_get_clean();

?>
<!DOCTYPE html>
<html lang="en">
<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> - Healthcare Management System</title>

        <!-- Tailwind config must be declared before the CDN script when customizing -->
        <script>
            window.tailwind = window.tailwind || {};
            window.tailwind.config = {
                theme: {
                    extend: {
                        colors: {
                            primary: {
                                50:  '#f0f9ff',
                                100: '#e0f2fe',
                                200: '#bae6fd',
                                300: '#7dd3fc',
                                400: '#38bdf8',
                                500: '#0ea5e9',
                                600: '#0284c7',
                                700: '#0369a1',
                                800: '#075985',
                                900: '#0c4a6e',
                            }
                        }
                    }
                }
            }
        </script>

        <!-- Tailwind CSS (CDN for development) -->
        <script src="https://cdn.tailwindcss.com"></script>

        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

        <style>
            /* Minimal fallbacks for components in case Tailwind utilities miss */
            .table-wrapper { overflow-x: auto; }
        </style>
</head>
<body class="min-h-screen bg-gray-50">
        <!-- Navigation -->
        <?php include __DIR__ . '/../includes/tailwind_nav.php'; ?>

        <!-- Main layout output -->
        <?php echo $content; ?>

</body>
</html>

<script>
function filterCards() {
        const query = document.getElementById('searchBox').value.toLowerCase();
        const cards = document.querySelectorAll('[data-search]');
    
        cards.forEach(card => {
                const searchData = card.getAttribute('data-search').toLowerCase();
                const isVisible = searchData.includes(query);
                if (isVisible) {
                        card.classList.remove('hidden', 'opacity-0');
                } else {
                        card.classList.add('hidden');
                }
        });
}

// Add subtle transitions after DOM ready
document.addEventListener('DOMContentLoaded', () => {
        const cards = document.querySelectorAll('[data-search]');
        cards.forEach(card => {
                card.classList.add('transition-opacity', 'duration-200');
        });
});
</script>