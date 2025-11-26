<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../config/admin_access.php';
require_once '../models/Patient.php';
require_once '../models/Biometric.php';
require_once '../models/PatientHistoryRecord.php';
require_once '../models/Appointment.php';
require_once '../models/HistoryLog.php';

// Check admin access
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true) {
    header('Location: ../index.php');
    exit();
}

// Allow nurses to access this page, prevent others from accessing if they're restricted
if (isset($_SESSION['role']) && $_SESSION['role'] === 'nurse') {
    require_nurse_allowed_page();
} else {
    // For non-nurse restricted roles (doctors, etc), use doctor page check
    require_doctor_allowed_page();
}

// Only output HTML for non-AJAX requests
if (!isset($_POST['update_biometrics'])) {
    require_once '../includes/header.php';
    outputHeader('Admin - Biometrics');
}

$create_table_sql = "CREATE TABLE IF NOT EXISTS biometrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    height DECIMAL(5,2) NOT NULL,
    weight DECIMAL(5,2) NOT NULL,
    blood_pressure VARCHAR(20) NOT NULL,
    temperature DECIMAL(4,2) NOT NULL,
    pulse_rate INT NOT NULL,
    respiratory_rate INT DEFAULT NULL,
    record_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
try {
    $pdo = Database::getInstance();
    $pdo->exec($create_table_sql);
} catch (Exception $e) {
    die("Error creating biometrics table: " . $e->getMessage());
}

if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["isAdmin"]) || $_SESSION["isAdmin"] !== true) {
    header("Location: index.php");
    exit();
}

if (function_exists('log_action')) {
    log_action($_SESSION['user_id'], "ADMIN_VISIT", "Administrator accessed the biometrics page");
}

// Handle AJAX requests first
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_biometrics'])) {
    // Prevent any HTML output
    ob_clean();
    header('Content-Type: application/json');
    $response = ['success'=>false,'message'=>''];
    
    // Ensure we're starting with a clean error state
    error_clear_last();
    
    try {
        if (!isset($pdo)) throw new Exception("Database connection failed");

        if (isset($_POST['appointment_id']) && !isset($_POST['user_id'])) {
            $appointment_id = filter_var($_POST['appointment_id'], FILTER_VALIDATE_INT);
            if ($appointment_id === false) throw new Exception("Invalid appointment ID");
            $appt = Appointment::findById($appointment_id);
            if (!$appt) throw new Exception('Appointment not found');
            if (!$appt->updateStatus('completed')) throw new Exception('Failed to update appointment status');
            $response['success'] = true;
            $response['message'] = "Appointment marked as completed successfully!";
            echo json_encode($response); exit;
        }

        if (isset($_POST['user_id'])) {
            $user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
            if ($user_id === false || $user_id <= 0) throw new Exception('Invalid user id');

            $appointment_id_optional = null;
            if (isset($_POST['appointment_id'])) {
                $appointment_id_optional = filter_var($_POST['appointment_id'], FILTER_VALIDATE_INT);
                if ($appointment_id_optional === false) $appointment_id_optional = null;
            }

            $height = isset($_POST['height']) ? floatval($_POST['height']) : null;
            $weight = isset($_POST['weight']) ? floatval($_POST['weight']) : null;
            $blood_pressure = isset($_POST['blood_pressure']) ? trim($_POST['blood_pressure']) : null;
            $temperature = isset($_POST['temperature']) ? floatval($_POST['temperature']) : null;
            $pulse_rate = isset($_POST['pulse_rate']) ? intval($_POST['pulse_rate']) : null;
            $respiratory_rate = isset($_POST['respiratory_rate']) ? intval($_POST['respiratory_rate']) : null;
            $nurse_recommendation = isset($_POST['nurse_recommendation']) ? trim($_POST['nurse_recommendation']) : null;
            $record_date = date('Y-m-d');
            // Removed diagnosis/interview/recommendations from biometric save flow.
            // These will be recorded later from the Patient Notes UI.

            if ($height !== null && ($height < 30 || $height > 300)) throw new Exception('Height out of range');
            if ($weight !== null && ($weight < 2 || $weight > 500)) throw new Exception('Weight out of range');
            if ($temperature !== null && ($temperature < 30 || $temperature > 45)) throw new Exception('Temperature out of range');
            if ($pulse_rate !== null && ($pulse_rate < 20 || $pulse_rate > 250)) throw new Exception('Pulse out of range');
            if ($respiratory_rate !== null && ($respiratory_rate < 5 || $respiratory_rate > 80)) throw new Exception('Respiratory rate out of range');

            $bio = new Biometric([
                'user_id' => $user_id,
                'height' => $height,
                'weight' => $weight,
                'blood_pressure' => $blood_pressure,
                'temperature' => $temperature,
                'pulse_rate' => $pulse_rate,
                'respiratory_rate' => $respiratory_rate,
                'nurse_recommendation' => $nurse_recommendation,
                'record_date' => $record_date
            ]);

            if (!$bio->create()) {
                throw new Exception('Failed to save biometric data');
            }

            // get the inserted biometric id so we can link to it for clinician notes
            $bio_id = $pdo->lastInsertId();

            // Log the successful save
            if (function_exists('log_action')) {
                log_action($_SESSION['user_id'], 'BIOMETRIC_SAVE', "Successfully saved biometric data for user_id={$user_id}");
            }

            $response['success'] = true;
            $response['message'] = 'Biometric data saved successfully.';
            
            // Do NOT create a patient_history_records entry here. Clinical notes
            // (diagnosis/interview/recommendation) should be recorded later from
            // the Patient Notes UI by the clinician. We only save biometrics now.
            $response['bio_id'] = intval($bio_id ?? 0);

            $response['appointment_completed'] = false;
            if (!empty($appointment_id_optional)) {
                $appt = Appointment::findById($appointment_id_optional);
                if ($appt && $appt->updateStatus('completed')) {
                    $response['appointment_completed'] = true;
                    if (function_exists('log_action') && !empty($_SESSION['user_id'])) {
                        log_action($_SESSION['user_id'], 'COMPLETE_APPOINTMENT', "Auto-completed appointment id={$appointment_id_optional} after biometric record for user_id={$user_id}");
                    }
                }
            }

            $response['removed_user_id'] = $user_id;
            $response['user_id'] = $user_id;
            $response['bio_id'] = intval($bio_id ?? 0);
            $response['success'] = true;
            $response['message'] = 'Biometric data saved successfully.';
            
            // Store in session so patient_notes.php can display it (still needed for immediate display)
            if (!isset($_SESSION['recorded_biometrics'])) {
                $_SESSION['recorded_biometrics'] = [];
            }
            $_SESSION['recorded_biometrics'][$user_id] = [
                'bio_id' => intval($bio_id ?? 0),
                'user_id' => $user_id,
                'timestamp' => time()
            ];
            
            // Also save to database so it persists after logout/login
            try {
                $pdo = Database::getInstance();
                // Check if this biometric is already marked as pending
                $checkStmt = $pdo->prepare('SELECT id FROM pending_biometric_notes WHERE biometric_id = ? LIMIT 1');
                $checkStmt->execute([$bio_id]);
                $exists = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$exists) {
                    $insertStmt = $pdo->prepare('INSERT INTO pending_biometric_notes (biometric_id, user_id, created_by_admin_id, is_processed) VALUES (?, ?, ?, 0)');
                    $insertStmt->execute([$bio_id, $user_id, $_SESSION['user_id'] ?? null]);
                }
            } catch (Exception $dbE) {
                error_log("Warning: Could not save pending biometric note to database: " . $dbE->getMessage());
                // Continue anyway - session storage still works
            }
            
            echo json_encode($response); exit;
        }
    } catch (Exception $e) {
        // Get any PHP errors that occurred
        $error = error_get_last();
        $errorMsg = $error ? " (PHP Error: {$error['message']})" : '';
        
        // Log the error with full context
        $trace = $e->getTraceAsString();
        $logMsg = date('c') . " - Biometric save error: " . $e->getMessage() . $errorMsg . 
                 "\nTrace: " . $trace . "\nPOST: " . json_encode($_POST) . "\n\n";
        $logFile = __DIR__ . '/logs/biometric_errors.log';
        error_log($logMsg, 3, $logFile);
        
        // Set the response
        $response['success'] = false;
        $response['message'] = 'Could not save biometric data. Please try again.';
    }
    // Ensure clean JSON output
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require_once '../includes/header.php'; outputHeader('Admin - Biometrics'); ?>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-blue-50 pt-20">
<?php require '../includes/tailwind_nav.php'; ?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');
    
    body {
        font-family: "Inter", system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans";
    }
    
    /* Search input with magnifying glass icon */
    #searchInput {
        background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="%2364748b" viewBox="0 0 24 24"><path d="M21 21l-4.3-4.3m1.8-4.2a7.5 7.5 0 11-15 0 7.5 7.5 0 0115 0z" stroke="%2364748b" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>');
        background-repeat: no-repeat;
        background-position: 12px 50%;
    }
    
    #searchInput:focus {
        border-color: #111c4e;
        box-shadow: 0 0 0 4px rgba(17, 28, 78, 0.14);
    }
</style>

<div class="max-w-4xl mx-auto px-4 pb-12">
    <div class="flex items-center gap-4 p-5 rounded-xl bg-gradient-to-r from-white to-blue-50 border border-gray-200 shadow-sm mb-4">
        <div class="emblem" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-8 h-8 text-blue-900">
                <path d="M12 3v18M3 12h18" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </div>
        <div>
            <h1 class="text-xl md:text-2xl font-bold text-gray-900">Vital signs</h1>
            <div class="text-sm text-gray-600 mt-1">Vital signs recording </div>
        </div>
    </div>

    <div class="flex gap-3 items-center flex-wrap mb-4">
        <input type="text" id="searchInput" class="max-w-md w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600" placeholder="Search patients by name, email, ID, phone, service, or date…">
    </div>

    <?php if (!empty($alertMessage)): ?>
        <!-- success/error accent uses brand, not green -->
        <div class="<?php echo strpos($alertMessage, 'Error') !== false ? 'bg-red-50 border-l-4 border-red-900 text-red-900' : 'bg-blue-50 border-l-4 border-blue-900 text-blue-900'; ?> p-4 rounded-lg mb-4">
            <div><?php echo $alertMessage; ?></div>
        </div>
    <?php endif; ?>

    <?php
    $colStmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = :db AND table_name = 'appointments' AND COLUMN_NAME = 'service_type'");
    $colStmt->execute([':db' => $GLOBALS['dbname']]);
    $hasServiceType = (bool)$colStmt->fetchColumn();
    if (!$hasServiceType) { $pdo->exec("ALTER TABLE appointments ADD COLUMN service_type VARCHAR(255) AFTER appointment_date"); }

    $users_sql = "SELECT 
        u.id, u.first_name, u.last_name, u.email, u.phone, u.birthday, u.sex, u.department, u.student_number,
        MAX(b.created_at) as last_record_date, a.id as appointment_id, COALESCE(a.service_type, 'General Checkup') as service_type, a.appointment_date, a.status
        FROM users u
        INNER JOIN appointments a ON u.id = a.user_id
        LEFT JOIN biometrics b ON u.id = b.user_id
        WHERE u.role = 'patient' AND a.status = 'approved'
        GROUP BY u.id, u.first_name, u.last_name, u.email, u.phone, u.birthday, u.sex, u.department, u.student_number, a.id, a.appointment_date, a.status, a.service_type
        ORDER BY a.appointment_date ASC, u.last_name, u.first_name";
    $users_result = $pdo->query($users_sql);
    if ($users_result === false) { die('Error fetching users'); }
    ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-12 gap-3" id="patientsGrid">
        <?php while ($user = $users_result->fetch(PDO::FETCH_ASSOC)) { 
            $full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        ?>
            <div class="sm:col-span-1 md:col-span-2 lg:col-span-3 bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all" data-user-id="<?= (int)$user['id'] ?>" data-name="<?= htmlspecialchars($full_name) ?>" data-email="<?= htmlspecialchars($user['email'] ?? '') ?>" data-phone="<?= htmlspecialchars($user['phone'] ?? '') ?>" data-student="<?= htmlspecialchars($user['student_number'] ?? '') ?>" data-service="<?= htmlspecialchars($user['service_type'] ?? '') ?>" data-appointment="<?= htmlspecialchars($user['appointment_date'] ?? '') ?>">
                <div class="flex items-center gap-3 p-3 border-b border-gray-100">
                    <div class="w-10 h-10 rounded-full bg-blue-100 border border-blue-300 flex items-center justify-center font-bold text-blue-900"><?= strtoupper(substr(($user['first_name'] ?? '') . ($user['last_name'] ?? ''), 0, 1)); ?></div>
                    <div>
                        <div class="font-bold text-gray-900"><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))); ?></div>
                        <span class="inline-block mt-1 text-xs px-2 py-1 bg-blue-100 text-blue-900 rounded-full border border-blue-300"><?= $user['last_record_date'] ? 'Recorded' : 'Not Recorded' ?></span>
                    </div>
                </div>

                <div class="p-3 space-y-2">
                    <div class="text-xs text-gray-600"><?= htmlspecialchars($user['student_number'] ??  'No ID'); ?></div>
                    <div class="text-xs text-gray-600"><?= htmlspecialchars($user['email']); ?></div>
                    <div class="text-xs text-gray-600"><?= htmlspecialchars($user['phone'] ?? 'No phone'); ?></div>
                    <div class="text-xs text-gray-600"><?= htmlspecialchars($user['appointment_date']); ?></div>
                    <div class="text-xs text-gray-600"><?= htmlspecialchars($user['service_type']); ?></div>

                    <div class="flex gap-2 justify-end pt-2">
                        <button type="button" class="px-3 py-1 rounded-lg font-bold text-white bg-blue-900 hover:bg-blue-950 shadow-sm text-sm"
                                onclick='showRecordForm(<?= $user['id']; ?>, <?= json_encode($full_name); ?>, <?= (int)$user['appointment_id']; ?>, this)'>
                            Record Biometrics
                        </button>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5 mb-6">
        <h2 class="text-xl md:text-2xl font-bold text-gray-900 mb-4">Recent Biometric Records</h2>
        <?php
        $check_table_query = "SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = 'biometrics' LIMIT 1";
        $stmt = mysqli_prepare($conn, $check_table_query);
        mysqli_stmt_bind_param($stmt, "s", $dbname);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) == 0) {
            $create_table_sql = "CREATE TABLE IF NOT EXISTS biometrics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                height DECIMAL(5,2),
                weight DECIMAL(5,2),
                blood_pressure VARCHAR(20),
                temperature DECIMAL(4,1),
                pulse_rate INT,
                respiratory_rate INT,
                record_date DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )";
            mysqli_query($conn, $create_table_sql);
        }

        $records_sql = "SELECT b.*, u.first_name, u.last_name 
              FROM biometrics b 
              JOIN users u ON b.user_id = u.id 
              ORDER BY b.record_date DESC, b.created_at DESC 
              LIMIT 10";
        $records_result = $pdo->query($records_sql);

        if ($records_result && $records_result->rowCount() > 0) {
            $wantedCols = ['diagnosis','interview','assessment_notes','notes','doctors_recommendations'];
            $col_in = "'" . implode("','", $wantedCols) . "'";
            $col_query = "SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = :db AND table_name = 'patient_history_records' AND COLUMN_NAME IN ($col_in)";
            $col_stmt = $pdo->prepare($col_query);
            $col_stmt->execute([':db' => $GLOBALS['dbname']]);
            $availableCols = $col_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

            $vd_stmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = :db AND table_name = 'patient_history_records' AND COLUMN_NAME = 'visit_date'");
            $vd_stmt->execute([':db' => $GLOBALS['dbname']]);
            $visit_date_exists = (bool)$vd_stmt->fetchColumn();

            if (!empty($availableCols)) {
                $selectCols = implode(', ', array_map(function($c){ return $c; }, $availableCols));
                if ($visit_date_exists) {
                    $hist_sql = "SELECT $selectCols FROM patient_history_records WHERE user_id = ? AND (visit_date = ? OR DATE(created_at) = ?) ORDER BY created_at DESC LIMIT 1";
                } else {
                    $hist_sql = "SELECT $selectCols FROM patient_history_records WHERE user_id = ? AND DATE(created_at) = ? ORDER BY created_at DESC LIMIT 1";
                }
                $hist_stmt = $pdo->prepare($hist_sql);
            } else { $hist_stmt = null; }
        ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-12 gap-3">
                <?php while ($record = $records_result->fetch(PDO::FETCH_ASSOC)) {
                    $diag = ''; $inter = ''; $rec = '';
                    if ($hist_stmt) {
                        if ($visit_date_exists) {
                            $hist_stmt->execute([$record['user_id'],$record['record_date'],$record['record_date']]);
                        } else {
                            $hist_stmt->execute([$record['user_id'],$record['record_date']]);
                        }
                        $hrow = $hist_stmt->fetch(PDO::FETCH_ASSOC);
                        if ($hrow) {
                            $diag = trim($hrow['diagnosis'] ?? '');
                            $inter = trim($hrow['interview'] ?? '');
                            $rec  = trim($hrow['doctors_recommendations'] ?? '');
                            // If the interview column is empty but assessment_notes exists, try to parse combined notes
                            $combined = trim($hrow['assessment_notes'] ?? $hrow['notes'] ?? '');
                            if ($combined !== '' && ($diag === '' || $inter === '')) {
                                // Normalize newlines
                                $c = str_replace(["\r\n", "\r"], "\n", $combined);
                                // Try to extract Diagnosis and Interview labeled sections
                                if (preg_match('/Diagnosis\s*[:\-]*\s*\n\s*(.*?)\n\s*\n\s*Interview\s*[:\-]*\s*\n\s*(.*)/is', $c, $m)) {
                                    if ($diag === '') $diag = trim($m[1]);
                                    if ($inter === '') $inter = trim($m[2]);
                                } else {
                                    // Fallback: split around the 'Interview' label if present
                                    if (preg_match('/(.*?)\n\s*Interview\s*[:\-]*\s*\n\s*(.*)/is', $c, $m2)) {
                                        if ($diag === '') $diag = trim($m2[1]);
                                        if ($inter === '') $inter = trim($m2[2]);
                                    } else {
                                        // If no labels, preserve whole combined text in interview as fallback
                                        if ($inter === '') $inter = trim($c);
                                    }
                                }
                            }
                        }
                    }
                ?>
                    <div class="sm:col-span-1 md:col-span-2 lg:col-span-3 bg-white border border-gray-200 rounded-lg shadow-sm">
                        <div class="flex items-center gap-3 p-3 border-b border-gray-100">
                            <div class="w-10 h-10 rounded-full bg-blue-100 border border-blue-300 flex items-center justify-center font-bold text-blue-900"><?= strtoupper(substr($record['first_name'] . ' ' . $record['last_name'], 0, 1)); ?></div>
                            <div>
                                <div class="font-bold text-gray-900"><?= htmlspecialchars($record['last_name'] . ', ' . $record['first_name']); ?></div>
                                <div class="text-xs text-gray-600"><?= htmlspecialchars($record['record_date']); ?></div>
                            </div>
                        </div>
                        <div class="p-3 space-y-2 text-sm">
                            <div><strong class="text-gray-900">Height:</strong> <span class="text-gray-600"><?= htmlspecialchars($record['height']); ?> cm</span></div>
                            <div><strong class="text-gray-900">Weight:</strong> <span class="text-gray-600"><?= htmlspecialchars($record['weight']); ?> kg</span></div>
                            <div><strong class="text-gray-900">BP:</strong> <span class="text-gray-600"><?= htmlspecialchars($record['blood_pressure']); ?></span></div>
                            <div><strong class="text-gray-900">Temp:</strong> <span class="text-gray-600"><?= htmlspecialchars($record['temperature']); ?> °C</span></div>
                            <div><strong class="text-gray-900">Pulse:</strong> <span class="text-gray-600"><?= htmlspecialchars($record['pulse_rate']); ?> bpm</span></div>
                            <div><strong class="text-gray-900">Respiratory Rate:</strong> <span class="text-gray-600"><?= htmlspecialchars($record['respiratory_rate']); ?> cpm</span></div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="text-center py-8 text-gray-500">
                No biometric records found. Add your first record using the form above.
            </div>
        <?php } ?>
    </div>
</div>

<?php
$col_check = mysqli_query($conn, "SHOW COLUMNS FROM biometrics LIKE 'respiratory_rate'");
if ($col_check && mysqli_num_rows($col_check) == 0) {
    mysqli_query($conn, "ALTER TABLE biometrics ADD COLUMN respiratory_rate INT DEFAULT NULL");
}
?>

<!-- Modal -->
<div id="recordModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-lg max-w-md w-full max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-900">Record Vital signs</h2>
            <button type="button" class="text-2xl text-gray-500 hover:text-gray-700" onclick="closeModal()">&times;</button>
        </div>
        <form class="p-4 space-y-4" method="POST">
            <input type="hidden" name="user_id" id="form_user_id">
            <input type="hidden" name="appointment_id" id="form_appointment_id">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Recording for: <span id="selected_user" class="font-bold text-blue-900"></span></label>
            </div>

            <div>
                <label for="height" class="block text-sm font-medium text-gray-700 mb-1">Height (cm)</label>
                <input type="number" name="height" required min="50" max="250" step="0.1" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600">
                <span class="text-xs text-red-600 hidden">Height should be between 50 and 250 cm</span>
            </div>

            <div>
                <label for="weight" class="block text-sm font-medium text-gray-700 mb-1">Weight (kg)</label>
                <input type="number" name="weight" required min="20" max="300" step="0.1" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600">
                <span class="text-xs text-red-600 hidden">Weight should be between 20 and 300 kg</span>
            </div>

            <div>
                <label for="blood_pressure" class="block text-sm font-medium text-gray-700 mb-1">Blood Pressure</label>
                <input type="text" name="blood_pressure" placeholder="e.g., 120/80" required pattern="\d{2,3}\/\d{2,3}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600">
                <span class="text-xs text-red-600 hidden">Please enter blood pressure in format: 120/80</span>
            </div>

            <div>
                <label for="temperature" class="block text-sm font-medium text-gray-700 mb-1">Temperature (°C)</label>
                <input type="number" name="temperature" step="0.1" required min="35" max="42" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600">
                <span class="text-xs text-red-600 hidden">Temperature should be between 35°C and 42°C</span>
            </div>

            <div>
                <label for="pulse_rate" class="block text-sm font-medium text-gray-700 mb-1">Pulse Rate (bpm)</label>
                <input type="number" name="pulse_rate" required min="40" max="200" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600">
                <span class="text-xs text-red-600 hidden">Pulse rate should be between 40 and 200 bpm</span>
            </div>

            <div>
                <label for="respiratory_rate" class="block text-sm font-medium text-gray-700 mb-1">Respiratory Rate (cpm)</label>
                <input type="number" name="respiratory_rate" required min="5" max="80" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600">
                <span class="text-xs text-red-600 hidden">Respiratory rate should be between 5 and 80 cpm</span>
            </div>

            <div>
                <label for="nurse_recommendation" class="block text-sm font-medium text-gray-700 mb-1">Nurse consultation</label>
                <textarea name="nurse_recommendation" placeholder="Enter any recommendations or notes from the nurse..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600 min-h-20 resize-none"></textarea>
            </div>

            <!-- Clinical notes are recorded from Patient Notes UI. Removed from biometric entry modal. -->

            <div class="flex gap-3 pt-4 border-t border-gray-200">
                <button type="button" class="flex-1 px-4 py-2 rounded-lg font-medium text-gray-700 bg-gray-100 hover:bg-gray-200" onclick="closeModal()">Cancel</button>
                <button type="submit" name="update_biometrics" class="flex-1 px-4 py-2 rounded-lg font-bold text-white bg-blue-900 hover:bg-blue-950">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
    // When clicked, keep brand color but slightly darker to indicate active
    function showRecordForm(userId, userName, appointmentId, btnEl) {
        const modal = document.getElementById('recordModal');
        document.getElementById('form_user_id').value = userId;
        document.getElementById('form_appointment_id').value = appointmentId ? appointmentId : '';
        document.getElementById('selected_user').textContent = userName;

        // remove active state from any previously clicked buttons
        document.querySelectorAll('.action-buttons .btn').forEach(b => b.classList.remove('bg-blue-950'));
        // make the clicked one darker (brand)
        if (btnEl) btnEl.classList.add('bg-blue-950');

        document.querySelectorAll('form input, form textarea').forEach(el => el.classList.remove('border-red-500'));

        modal.classList.remove('hidden');
        const first = modal.querySelector('[name="height"]'); if (first) first.focus();
    }

    function closeModal() {
        document.getElementById('recordModal').classList.add('hidden');
        document.querySelector('form').reset();
        document.querySelectorAll('form input, form textarea').forEach(el => el.classList.remove('border-red-500'));
        // we keep the darker state to indicate which card was opened
    }

    window.onclick = function(event) {
        const modal = document.getElementById('recordModal');
        if (event.target == modal) { closeModal(); }
    }

    function displayNotesForm(userId, bioId) {
        // Simply close the modal - no card display on biometrics page
        closeModal();
    }

    document.querySelector('form').addEventListener('submit', function(e) {
        e.preventDefault();
        let isValid = true;

        const height = this.querySelector('[name="height"]');
        if (height.value < 50 || height.value > 250) { height.classList.add('border-red-500'); isValid = false; } else { height.classList.remove('border-red-500'); }

        const weight = this.querySelector('[name="weight"]');
        if (weight.value < 20 || weight.value > 300) { weight.classList.add('border-red-500'); isValid = false; } else { weight.classList.remove('border-red-500'); }

        const bp = this.querySelector('[name="blood_pressure"]');
        if (!/^\d{2,3}\/\d{2,3}$/.test(bp.value)) { bp.classList.add('border-red-500'); isValid = false; } else { bp.classList.remove('border-red-500'); }

        const temp = this.querySelector('[name="temperature"]');
        if (temp.value < 35 || temp.value > 42) { temp.classList.add('border-red-500'); isValid = false; } else { temp.classList.remove('border-red-500'); }

        const pulse = this.querySelector('[name="pulse_rate"]');
        if (pulse.value < 40 || pulse.value > 200) { pulse.classList.add('border-red-500'); isValid = false; } else { pulse.classList.remove('border-red-500'); }

        if (isValid) {
            const formData = new FormData(this); formData.append('update_biometrics','1');
            fetch('info_admin.php', { 
                method: 'POST', 
                body: formData 
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
            if (data.success) {
                    closeModal();
                    if (data.removed_user_id) {
                        const card = document.querySelector('.user-card[data-user-id="' + data.removed_user_id + '"]');
                        if (card && card.parentNode) {
                            card.parentNode.removeChild(card);
                        }
                    }
                    // Show success message and display notes form for diagnosis/patient interview/recommendations
                    alert('Biometric data saved successfully!');
                    if (data.appointment_completed) {
                        alert('Appointment automatically marked as completed.');
                    }
                    
                    // Display the notes card and form on the same page
                    if (data.user_id && data.bio_id) {
                        displayNotesForm(data.user_id, data.bio_id);
                    }
                } else {
                    throw new Error(data.message || 'Failed to save data');
                }
            })
            .catch(error => {
                console.error('Save error:', error);
                alert(error.message || 'An error occurred while saving the data. Please try again.');
            });
        } else {
            const firstErr = document.querySelector('.biometric-form .error');
            if (firstErr) firstErr.scrollIntoView({behavior:'smooth', block:'center'});
            alert('Please correct the highlighted fields');
        }
    });

    (function(){
        const input = document.getElementById('searchInput');
        const grid = document.getElementById('patientsGrid');
        if (!input || !grid) return;
        const normalize = (s)=> (s||'').toString().toLowerCase();
        function filterCards(term){
            const q = normalize(term);
            const cards = grid.querySelectorAll('.user-card');
            cards.forEach(card=>{
                const match = [
                    normalize(card.dataset.name),
                    normalize(card.dataset.email),
                    normalize(card.dataset.phone),
                    normalize(card.dataset.student),
                    normalize(card.dataset.service),
                    normalize(card.dataset.appointment)
                ].some(v=>v.includes(q));
                card.style.display = (q==='' || match) ? '' : 'none';
            });
        }
        let t=null;
        input.addEventListener('input', function(){
            clearTimeout(t); t=setTimeout(()=>filterCards(this.value),180);
        });
        input.addEventListener('keydown', function(e){ if(e.key==='Escape'){ this.value=''; filterCards(''); } });
    })();
</script>
</body>
</html>
