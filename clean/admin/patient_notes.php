<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../config/admin_access.php';
require_once '../models/Patient.php';
require_once '../models/Appointment.php';

// Check admin access
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true) {
    header('Location: ../index.php');
    exit();
}

// Check if this is a doctor trying to access authorized page
require_doctor_allowed_page();

// Handle removing a recorded biometric from session
if (isset($_GET['remove_biometric'])) {
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    if ($user_id > 0 && isset($_SESSION['recorded_biometrics'][$user_id])) {
        unset($_SESSION['recorded_biometrics'][$user_id]);
    }
    exit;
}

// Handle saving notes: update existing history record or create a new medical history record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    $bio_id = isset($_POST['bio_id']) ? intval($_POST['bio_id']) : 0;
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $interview = trim($_POST['interview'] ?? '');
    $doctors_recommendations = trim($_POST['doctors_recommendations'] ?? '');
    $history_id = isset($_POST['history_id']) ? intval($_POST['history_id']) : 0;

    try {
        require_once __DIR__ . '/../models/PatientHistoryRecord.php';
        $pdo = Database::getInstance();
        
        if ($history_id > 0) {
            // Update existing history row with correct column names
            // Diagnosis goes to assessment_notes, Interview goes to notes separately
            $diag_block = trim($diagnosis) !== '' ? $diagnosis : '';
            $inter_block = trim($interview) !== '' ? $interview : '';

            // If a biometric row was provided, copy vitals into the history record so
            // the timeline shows the recorded vitals even if biometrics table rows
            // are archived or filtered.
            $height = $weight = $blood_pressure = $temperature = $pulse_rate = $respiratory_rate = $nurse_recommendation = null;
            if ($bio_id) {
                $bst = $pdo->prepare('SELECT height, weight, blood_pressure, temperature, pulse_rate, respiratory_rate, nurse_recommendation FROM biometrics WHERE id = ? LIMIT 1');
                $bst->execute([$bio_id]);
                $bro = $bst->fetch(PDO::FETCH_ASSOC);
                if ($bro) {
                    $height = $bro['height'] ?: null;
                    $weight = $bro['weight'] ?: null;
                    $blood_pressure = $bro['blood_pressure'] ?: null;
                    $temperature = $bro['temperature'] ?: null;
                    $pulse_rate = $bro['pulse_rate'] ?: null;
                    $respiratory_rate = $bro['respiratory_rate'] ?? null;
                    $nurse_recommendation = $bro['nurse_recommendation'] ?? null;
                }
            }

            $sql = "UPDATE patient_history_records SET assessment_notes = :diag, notes = :notes, doctors_recommendations = :treat, record_type = :rtype, height = :height, weight = :weight, blood_pressure = :bp, temperature = :temp, pulse_rate = :pr, respiratory_rate = :rr, nurse_recommendation = :nr WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $ok = $stmt->execute([
                ':diag' => $diag_block,
                ':notes' => $inter_block,
                ':treat' => $doctors_recommendations,
                ':rtype' => 'medical',
                ':height' => $height,
                ':weight' => $weight,
                ':bp' => $blood_pressure,
                ':temp' => $temperature,
                ':pr' => $pulse_rate,
                ':rr' => $respiratory_rate,
                ':nr' => $nurse_recommendation,
                ':id' => $history_id
            ]);
            if (!$ok) {
                $err = $stmt->errorInfo();
                error_log("Failed to update clinical note: " . print_r($err, true));
                throw new Exception("Database error: " . ($err[2] ?? 'Unknown error'));
            }
        } else {
            // Create new history record with explicit transaction for atomicity
            $pdo = Database::getInstance();
            $pdo->beginTransaction();

            // Use biometric record date for visit_date and timestamp matching
            $visit_date = date('Y-m-d');
            $created_at = null;
            $biometric_date = null;

            if ($bio_id) {
                $bstmt = $pdo->prepare('SELECT record_date, created_at, height, weight, blood_pressure, temperature, pulse_rate, respiratory_rate, nurse_recommendation FROM biometrics WHERE id = ? LIMIT 1');
                $bstmt->execute([$bio_id]);
                $bro = $bstmt->fetch(PDO::FETCH_ASSOC);
                if ($bro) {
                    $biometric_date = $bro['created_at'];
                    $visit_date = $bro['record_date'] ?: date('Y-m-d', strtotime($bro['created_at']));
                    $created_at = $bro['created_at'];
                    // copy vitals for insertion into history
                    $height = $bro['height'] ?: null;
                    $weight = $bro['weight'] ?: null;
                    $blood_pressure = $bro['blood_pressure'] ?: null;
                    $temperature = $bro['temperature'] ?: null;
                    $pulse_rate = $bro['pulse_rate'] ?: null;
                    $respiratory_rate = $bro['respiratory_rate'] ?? null;
                    $nurse_recommendation = $bro['nurse_recommendation'] ?? null;
                }
            } else {
                $height = $weight = $blood_pressure = $temperature = $pulse_rate = $respiratory_rate = $nurse_recommendation = null;
            }
            
            // Create a history record matching the biometric timestamp with the correct column names
            // Diagnosis goes to assessment_notes, Interview goes to notes separately
            $diag_block = trim($diagnosis) !== '' ? $diagnosis : '';
            $inter_block = trim($interview) !== '' ? $interview : '';

            $sql = 'INSERT INTO patient_history_records (user_id, visit_date, record_type, assessment_notes, notes, doctors_recommendations, height, weight, blood_pressure, temperature, pulse_rate, respiratory_rate, nurse_recommendation, created_by, created_at) VALUES (:uid, :vd, :rt, :diag, :notes, :treat, :height, :weight, :bp, :temp, :pr, :rr, :nr, :cb, :cat)';
            $stmt = $pdo->prepare($sql);
            $ok = $stmt->execute([
                ':uid' => $user_id,
                ':vd' => $visit_date,
                ':rt' => 'medical',
                ':diag' => $diag_block,
                ':notes' => $inter_block,
                ':treat' => $doctors_recommendations,
                ':height' => $height,
                ':weight' => $weight,
                ':bp' => $blood_pressure,
                ':temp' => $temperature,
                ':pr' => $pulse_rate,
                ':rr' => $respiratory_rate,
                ':nr' => $nurse_recommendation,
                ':cb' => $_SESSION['user_id'] ?? null,
                ':cat' => $created_at ?: date('Y-m-d H:i:s')
            ]);
            
            if (!$ok) {
                $err = $stmt->errorInfo();
                error_log("Failed to save clinical note: " . print_r($err, true));
                $pdo->rollBack();
                throw new Exception("Database error: " . ($err[2] ?? 'Unknown error'));
            }
            
            $pdo->commit();
            $_SESSION['message'] = 'Clinical note saved successfully.';
        }
        $_SESSION['message'] = 'Patient note saved.';
        
        // Mark biometric as processed in database and remove from session after successfully saving clinical notes
        if ($bio_id > 0) {
            try {
                $updateStmt = $pdo->prepare('UPDATE pending_biometric_notes SET is_processed = 1, notes_recorded_at = NOW() WHERE biometric_id = ? LIMIT 1');
                $updateStmt->execute([$bio_id]);
            } catch (Exception $e) {
                error_log("Warning: Could not mark biometric as processed: " . $e->getMessage());
            }
        }
        
        if ($user_id > 0 && isset($_SESSION['recorded_biometrics'][$user_id])) {
            unset($_SESSION['recorded_biometrics'][$user_id]);
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Failed to save note: ' . $e->getMessage();
    }
    // Redirect to admin's patient history details so clinician can see the saved note in the timeline
    header('Location: patient_history_details.php?user_id=' . intval($user_id));
    exit();
}

if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["isAdmin"]) || $_SESSION["isAdmin"] !== true) {
    header("Location: index.php");
    exit();
}

require_once '../includes/header.php';
outputHeader('Admin - Patient Notes');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require_once '../includes/header.php'; outputHeader('Admin - Patient Notes'); ?>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-blue-50 pt-20">
<?php require '../includes/tailwind_nav.php'; ?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');
    
    body {
        font-family: "Inter", system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans";
    }
</style>
<div class="max-w-4xl mx-auto px-4 pb-12">
    <div class="flex items-center gap-4 p-5 rounded-xl bg-gradient-to-r from-white to-blue-50 border border-gray-200 shadow-sm mb-4">
        <div>
            <h1 class="text-xl md:text-2xl font-bold text-gray-900">Patient Notes & Recommendations</h1>
            <div class="text-sm text-gray-600 mt-1">Edit diagnosis, interview/notes, and doctor's recommendations for each patient</div>
        </div>
    </div>

    <!-- Display recently recorded biometrics from session and database -->
    <?php
    $pdo = Database::getInstance();
    $recentBiometrics = [];
    
    // First, get biometrics from session (if any)
    if (isset($_SESSION['recorded_biometrics']) && is_array($_SESSION['recorded_biometrics'])) {
        foreach ($_SESSION['recorded_biometrics'] as $rec_user_id => $bio_data) {
            $recentBiometrics[$bio_data['bio_id']] = $rec_user_id;
        }
    }
    
    // Then, get pending biometrics from database that haven't been processed yet
    try {
        $dbStmt = $pdo->prepare("
            SELECT pbn.biometric_id as bio_id, pbn.user_id 
            FROM pending_biometric_notes pbn
            WHERE pbn.is_processed = 0
            ORDER BY pbn.recorded_at DESC
            LIMIT 50
        ");
        $dbStmt->execute();
        while ($dbRow = $dbStmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($recentBiometrics[$dbRow['bio_id']])) {
                $recentBiometrics[$dbRow['bio_id']] = $dbRow['user_id'];
            }
        }
    } catch (Exception $e) {
        error_log("Warning: Could not fetch pending biometric notes: " . $e->getMessage());
    }
    
    // Then, query database for recent biometrics from last 7 days that don't have clinical notes yet
    $stmt = $pdo->prepare("
        SELECT b.id as bio_id, u.id as user_id, u.first_name, u.last_name, u.email,
               b.record_date, b.created_at, b.height, b.weight, b.blood_pressure, 
               b.temperature, b.pulse_rate, b.respiratory_rate, b.nurse_recommendation,
               CASE WHEN phr.id IS NOT NULL THEN 1 ELSE 0 END as has_clinical_notes
        FROM biometrics b
        INNER JOIN users u ON b.user_id = u.id
        LEFT JOIN patient_history_records phr ON u.id = phr.user_id 
               AND phr.record_type = 'medical' 
               AND DATE(phr.created_at) = DATE(b.created_at)
        WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY b.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    
    while ($bio_row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Only show biometrics that don't have complete clinical notes
        if (!$bio_row['has_clinical_notes']) {
            $recentBiometrics[$bio_row['bio_id']] = $bio_row['user_id'];
        }
    }
    
    if (count($recentBiometrics) > 0):
    ?>
        <div id="recentBiometricsSection" class="mb-8">
            <h2 class="text-lg md:text-xl font-bold text-gray-900 mb-4">Recently Recorded Biometrics</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-12 gap-3">
                <?php
                foreach ($recentBiometrics as $rec_bio_id => $rec_user_id):
                    // Get user details
                    $userStmt = $pdo->prepare('SELECT id, first_name, last_name, email FROM users WHERE id = ? LIMIT 1');
                    $userStmt->execute([$rec_user_id]);
                    $rec_user = $userStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Get biometric data
                    $bioStmt = $pdo->prepare('SELECT * FROM biometrics WHERE id = ? LIMIT 1');
                    $bioStmt->execute([$rec_bio_id]);
                    $rec_bio = $bioStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($rec_user && $rec_bio):
                    ?>
                    <div class="sm:col-span-1 md:col-span-2 lg:col-span-6 xl:col-span-12 bg-white border-l-4 border-green-600 border border-gray-200 rounded-lg shadow-sm">
                        <div class="flex flex-wrap items-center gap-4 p-4">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-full bg-green-100 border border-green-300 flex items-center justify-center font-bold text-green-900"><?= strtoupper(substr(($rec_user['first_name'] ?? '') . ($rec_user['last_name'] ?? ''), 0, 1)); ?></div>
                                <div>
                                    <div class="font-bold text-gray-900"><?= htmlspecialchars(trim(($rec_user['first_name'] ?? '') . ' ' . ($rec_user['last_name'] ?? ''))); ?></div>
                                    <span class="inline-block mt-1 text-xs px-2 py-1 bg-green-100 text-green-900 rounded-full border border-green-300">Biometric Ready</span>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-6 flex-1">
                                <div class="text-sm"><strong class="text-gray-900">Record Date:</strong> <span class="text-gray-600"><?= htmlspecialchars($rec_bio['record_date'] ?? ($rec_bio['created_at'] ?? '—')) ?></span></div>
                                <div class="text-sm"><strong class="text-gray-900">Time:</strong> <span class="text-gray-600"><?= htmlspecialchars(isset($rec_bio['created_at']) ? date('H:i', strtotime($rec_bio['created_at'])) : '—') ?></span></div>
                                <div class="text-sm"><strong class="text-gray-900">Height:</strong> <span class="text-gray-600"><?= htmlspecialchars($rec_bio['height'] ?? '—') ?> cm</span></div>
                                <div class="text-sm"><strong class="text-gray-900">Weight:</strong> <span class="text-gray-600"><?= htmlspecialchars($rec_bio['weight'] ?? '—') ?> kg</span></div>
                                <div class="text-sm"><strong class="text-gray-900">BP:</strong> <span class="text-gray-600"><?= htmlspecialchars($rec_bio['blood_pressure'] ?? '—') ?></span></div>
                                <div class="text-sm"><strong class="text-gray-900">Temp:</strong> <span class="text-gray-600"><?= htmlspecialchars($rec_bio['temperature'] ?? '—') ?> °C</span></div>
                                <div class="text-sm"><strong class="text-gray-900">Pulse:</strong> <span class="text-gray-600"><?= htmlspecialchars($rec_bio['pulse_rate'] ?? '—') ?> bpm</span></div>
                                <div class="text-sm"><strong class="text-gray-900">Respiratory:</strong> <span class="text-gray-600"><?= htmlspecialchars($rec_bio['respiratory_rate'] ?? '—') ?> cpm</span></div>
                            </div>

                            <div class="flex flex-col gap-2 w-full">
                                <div class="text-sm"><strong class="text-gray-900">Nurse consultation:</strong> <span class="text-gray-600"><?= htmlspecialchars($rec_bio['nurse_recommendation'] ?? 'No recommendations') ?></span></div>
                                <button type="button" class="px-4 py-2 rounded-lg font-bold text-white bg-blue-900 hover:bg-blue-950 shadow-sm text-sm self-start" onclick="openClinicalNotesModal(<?= (int)$rec_user['id'] ?>, <?= (int)$rec_bio_id ?>, '<?= htmlspecialchars(trim(($rec_user['first_name'] ?? '') . ' ' . ($rec_user['last_name'] ?? '')), ENT_QUOTES) ?>')">Record Notes</button>
                            </div>
                        </div>
                    </div>
                    <?php
                    endif;
                endforeach;
                ?>
            </div>
        </div>
        <?php
    endif;
    ?>

    <?php
    // If a specific user was selected (from biometric save), show their biometrics card first
    $highlight_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    // bio_id refers to the biometrics record id (a nurse-recorded visit)
    $bio_id = isset($_GET['bio_id']) ? intval($_GET['bio_id']) : 0;
    
    // Variables we'll need for the form
    $bio = null;
    $history_row = null;
    $u = null;
    // Toggle: show or hide the patients grid below. Set to false to hide all cards.
    $show_patient_grid = false;
    
    if ($highlight_user_id) {
        $pdo = Database::getInstance();
        
        // First, get the user details
        $userStmt = $pdo->prepare('SELECT id, first_name, last_name, email, student_number FROM users WHERE id = ? LIMIT 1');
        $userStmt->execute([$highlight_user_id]);
        $u = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        // If a specific biometric row (bio_id) was selected, prefer that one
        if ($bio_id) {
            $bstmt = $pdo->prepare('SELECT * FROM biometrics WHERE id = ? LIMIT 1');
            $bstmt->execute([$bio_id]);
            $brow = $bstmt->fetch(PDO::FETCH_ASSOC);
            if ($brow) {
                $bio = [
                    'height' => $brow['height'],
                    'weight' => $brow['weight'],
                    'blood_pressure' => $brow['blood_pressure'],
                    'temperature' => $brow['temperature'],
                    'pulse_rate' => $brow['pulse_rate'],
                    'respiratory_rate' => $brow['respiratory_rate'],
                    'nurse_recommendation' => $brow['nurse_recommendation'],
                    'record_date' => $brow['record_date'],
                    'created_at' => $brow['created_at']
                ];
                // try to find a matching history record for that same date
                $hstmt = $pdo->prepare('SELECT * FROM patient_history_records WHERE patient_id = ? AND record_type = "medical" AND DATE(created_at) = DATE(?) LIMIT 1');
                $hstmt->execute([$highlight_user_id, $brow['created_at']]);
                $history_row = $hstmt->fetch(PDO::FETCH_ASSOC);
            }
        } else {
            // otherwise, use the latest biometrics for this user
            if ($u) {
                $stmt = $pdo->prepare('SELECT * FROM biometrics WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
                $stmt->execute([$highlight_user_id]);
                $brow = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($brow) {
                    $bio = [
                        'height' => $brow['height'],
                        'weight' => $brow['weight'],
                        'blood_pressure' => $brow['blood_pressure'],
                        'temperature' => $brow['temperature'],
                        'pulse_rate' => $brow['pulse_rate'],
                        'respiratory_rate' => $brow['respiratory_rate'],
                        'nurse_recommendation' => $brow['nurse_recommendation'],
                        'record_date' => $brow['record_date'],
                        'created_at' => $brow['created_at']
                    ];
                    $hstmt = $pdo->prepare('SELECT * FROM patient_history_records WHERE patient_id = ? AND record_type = "medical" AND DATE(created_at) = DATE(?) ORDER BY created_at DESC LIMIT 1');
                    $hstmt->execute([$highlight_user_id, $brow['created_at']]);
                    $history_row = $hstmt->fetch(PDO::FETCH_ASSOC);
                }
            }
        }
    }
    

    // Display the highlighted card and form if we have both user and bio data
    if ($u && $bio): 
    ?>
    <div class="bg-white border-l-4 border-blue-900 border border-gray-200 rounded-lg shadow-sm mb-6">
        <div class="flex items-center gap-3 p-3 border-b border-gray-100">
            <div class="w-10 h-10 rounded-full bg-blue-100 border border-blue-300 flex items-center justify-center font-bold text-blue-900"><?= strtoupper(substr(($u['first_name'] ?? '') . ($u['last_name'] ?? ''), 0, 1)); ?></div>
            <div>
                <div class="font-bold text-gray-900"><?= htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))); ?></div>
                <span class="inline-block mt-1 text-xs px-2 py-1 bg-blue-100 text-blue-900 rounded-full border border-blue-300">Patient (Selected)</span>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-4">
            <div class="space-y-2">
                <div class="text-sm"><strong class="text-gray-900">Record Date:</strong> <span class="text-gray-600"><?= htmlspecialchars($bio['record_date'] ?? ($bio['created_at'] ?? '—')) ?></span></div>
                <div class="text-sm"><strong class="text-gray-900">Time:</strong> <span class="text-gray-600"><?= htmlspecialchars(isset($bio['created_at']) ? date('H:i', strtotime($bio['created_at'])) : '—') ?></span></div>
                <div class="text-sm"><strong class="text-gray-900">Height:</strong> <span class="text-gray-600"><?= htmlspecialchars($bio['height'] ?? '—') ?> cm</span></div>
                <div class="text-sm"><strong class="text-gray-900">Weight:</strong> <span class="text-gray-600"><?= htmlspecialchars($bio['weight'] ?? '—') ?> kg</span></div>
                <div class="text-sm"><strong class="text-gray-900">Blood Pressure:</strong> <span class="text-gray-600"><?= htmlspecialchars($bio['blood_pressure'] ?? '—') ?></span></div>
                <div class="text-sm"><strong class="text-gray-900">Temperature:</strong> <span class="text-gray-600"><?= htmlspecialchars($bio['temperature'] ?? '—') ?> °C</span></div>
                <div class="text-sm"><strong class="text-gray-900">Pulse Rate:</strong> <span class="text-gray-600"><?= htmlspecialchars($bio['pulse_rate'] ?? '—') ?> bpm</span></div>
                <div class="text-sm"><strong class="text-gray-900">Respiratory Rate:</strong> <span class="text-gray-600"><?= htmlspecialchars($bio['respiratory_rate'] ?? '—') ?> cpm</span></div>
            </div>
            <div class="border-l border-gray-200 pl-4">
                <div class="font-semibold text-gray-900 mb-2">Nurse nurse consultation</div>
                <div class="text-sm text-gray-600 line-clamp-4"><?= htmlspecialchars($bio['nurse_recommendation'] ?? 'No recommendations') ?></div>
            </div>
        </div>
    </div>
    <!-- Notes form (Diagnosis / Interview / Recommendations) -->
    <form method="POST" action="" class="notes-form mb-8">
        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
        <input type="hidden" name="history_id" value="<?= (int)($history_row['id'] ?? 0) ?>">
        <input type="hidden" name="bio_id" value="<?= (int)($bio_id ?? 0) ?>">
        
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Clinical Notes</h3>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-900 mb-2">Diagnosis</label>
                <textarea name="diagnosis" rows="3" placeholder="Enter diagnosis..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600" ><?= isset($history_row['assessment_notes']) ? htmlspecialchars($history_row['assessment_notes']) : '' ?></textarea>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-900 mb-2">Patient Interview</label>
                <textarea name="interview" rows="4" placeholder="Enter patient interview notes..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600" ><?= isset($history_row['notes']) ? htmlspecialchars($history_row['notes']) : '' ?></textarea>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-900 mb-2">Doctors treatment</label>
                <textarea name="doctors_recommendations" rows="3" placeholder="Enter recommendations..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600" ><?= isset($history_row['doctors_recommendations']) ? htmlspecialchars($history_row['doctors_recommendations']) : '' ?></textarea>
            </div>
            
            <div class="flex justify-end gap-3">
                <button type="submit" class="px-4 py-2 rounded-lg font-bold text-white bg-blue-900 hover:bg-blue-950 shadow-sm">Save Clinical Notes</button>
            </div>
        </div>
    </form>
    <?php endif; ?>

    <?php if ($show_patient_grid): ?>
    <div class="mb-4">
        <input type="text" id="searchPatients" placeholder="Search patients by name, email, or student number..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600" />
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-12 gap-3" id="patientsGrid">
        <?php
        // Show patients who have biometric records 
        $pdo = Database::getInstance();
        // Select users that have a biometric record with at least one measured value
                $sql = "SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.student_number,
                             b.created_at as biometric_date, b.id as biometric_id,
                             -- only consider a patient as 'Diagnosed' if assessment_notes, notes and doctors_recommendations are present
                             CASE WHEN phr.id IS NOT NULL
                                     AND TRIM(COALESCE(phr.assessment_notes, '')) <> ''
                                     AND TRIM(COALESCE(phr.notes, '')) <> ''
                                     AND TRIM(COALESCE(phr.doctors_recommendations, '')) <> ''
                                 THEN 'yes' ELSE '' END as has_diagnosis
                                FROM users u
                                INNER JOIN biometrics b ON u.id = b.user_id
                                -- consider patient_history_records keyed by either patient_id or user_id (legacy column mismatch)
                                LEFT JOIN patient_history_records phr ON (u.id = phr.patient_id OR u.id = phr.user_id)
                                        AND phr.record_type = 'medical'
                                        AND DATE(phr.created_at) = DATE(b.created_at)
                                WHERE u.role = 'patient' 
                                    AND (
                                            b.height IS NOT NULL OR b.weight IS NOT NULL OR b.blood_pressure IS NOT NULL OR b.temperature IS NOT NULL
                                    )
                                ORDER BY b.created_at DESC";

        $stmt = $pdo->query($sql);
        while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // If the patient already has all three clinical fields recorded, hide their card from the 'needs diagnosis' grid
            if (isset($user['has_diagnosis']) && $user['has_diagnosis'] === 'yes') continue;
            $full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            // fetch biometrics by the biometric_id we selected
            $bstmt = $pdo->prepare('SELECT * FROM biometrics WHERE id = ? LIMIT 1');
            $bstmt->execute([$user['biometric_id']]);
            $b = $bstmt->fetch(PDO::FETCH_ASSOC);
            if ($b):
            ?>
            <div class="sm:col-span-1 md:col-span-2 lg:col-span-3 bg-white border border-gray-200 rounded-lg shadow-sm">
                <div class="flex items-center gap-3 p-3 border-b border-gray-100">
                    <div class="w-10 h-10 rounded-full bg-blue-100 border border-blue-300 flex items-center justify-center font-bold text-blue-900"><?= strtoupper(substr(($user['first_name'] ?? '') . ($user['last_name'] ?? ''), 0, 1)); ?></div>
                    <div>
                        <div class="font-bold text-gray-900"><?= htmlspecialchars($full_name); ?></div>
                        <div class="flex gap-2 items-center mt-1">
                            <span class="inline-block text-xs px-2 py-1 bg-blue-100 text-blue-900 rounded-full border border-blue-300">Patient</span>
                            <?php if ($user['has_diagnosis'] === ''): ?>
                                <span class="inline-block text-xs px-2 py-1 bg-red-100 text-red-900 rounded-full border border-red-300">Needs Diagnosis</span>
                            <?php else: ?>
                                <span class="inline-block text-xs px-2 py-1 bg-green-100 text-green-900 rounded-full border border-green-300">Diagnosed</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="p-3 space-y-2 text-sm">
                    <div class="text-xs text-gray-600"><?= htmlspecialchars($user['student_number'] ??  'No ID'); ?></div>
                    <div class="text-xs text-gray-600"><?= htmlspecialchars($user['email']); ?></div>
                    <div class="text-xs text-gray-600"><strong>Biometrics Date:</strong> <?= htmlspecialchars(date('Y-m-d', strtotime($user['biometric_date']))); ?></div>
                    <div class="text-xs text-gray-600"><strong>Record Date:</strong> <?= htmlspecialchars($b['record_date'] ?? '—'); ?></div>
                    <div class="text-xs text-gray-600"><strong>Height:</strong> <?= htmlspecialchars($b['height'] ?? '—'); ?> cm</div>
                    <div class="text-xs text-gray-600"><strong>Weight:</strong> <?= htmlspecialchars($b['weight'] ?? '—'); ?> kg</div>
                    <div class="text-xs text-gray-600"><strong>BP:</strong> <?= htmlspecialchars($b['blood_pressure'] ?? '—'); ?></div>
                    <div class="text-xs text-gray-600"><strong>Temp:</strong> <?= htmlspecialchars($b['temperature'] ?? '—'); ?> °C</div>
                    
                    <div class="flex gap-2 pt-2">
                        <button type="button" class="flex-1 px-3 py-1.5 rounded-lg font-bold text-white bg-blue-900 hover:bg-blue-950 shadow-sm text-sm" onclick='location.href="patient_notes.php?user_id=<?= (int)$user['id'] ?>&bio_id=<?= (int)$user['biometric_id'] ?>"'>
                            Record
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php } ?>
    </div>
    <?php endif; ?>
</div>

<!-- Clinical Notes Modal -->
<div id="clinicalNotesModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-900">Record Clinical Notes</h2>
            <button type="button" class="text-2xl text-gray-500 hover:text-gray-700" onclick="closeClinicalNotesModal()">&times;</button>
        </div>
        <form class="clinical-notes-form p-4 space-y-4" method="POST">
            <input type="hidden" name="user_id" id="modal_user_id">
            <input type="hidden" name="bio_id" id="modal_bio_id">
            <input type="hidden" name="history_id" id="modal_history_id">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Recording for: <span id="modal_patient_name" class="font-bold text-blue-900"></span></label>
            </div>

            <div>
                <label for="modal_diagnosis" class="block text-sm font-medium text-gray-700 mb-2">Diagnosis</label>
                <textarea name="diagnosis" id="modal_diagnosis" rows="3" placeholder="Enter diagnosis..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600"></textarea>
            </div>

            <div>
                <label for="modal_interview" class="block text-sm font-medium text-gray-700 mb-2">Patient history</label>
                <textarea name="interview" id="modal_interview" rows="4" placeholder="Enter patient interview notes..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600"></textarea>
            </div>

            <div>
                <label for="modal_recommendations" class="block text-sm font-medium text-gray-700 mb-2">Doctors treatment</label>
                <textarea name="doctors_recommendations" id="modal_recommendations" rows="3" placeholder="Enter recommendations..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-600"></textarea>
            </div>

            <div class="flex gap-3 pt-4 border-t border-gray-200">
                <button type="button" onclick="closeClinicalNotesModal()" class="flex-1 px-4 py-2 rounded-lg font-medium text-gray-700 bg-gray-100 hover:bg-gray-200">Cancel</button>
                <button type="submit" class="flex-1 px-4 py-2 rounded-lg font-bold text-white bg-blue-900 hover:bg-blue-950">Save Clinical Notes</button>
            </div>
        </form>
    </div>
</div>

<script>
// localStorage keys for form persistence
const FORM_STORAGE_KEY = 'clinicalNotesFormData';

// Save form data to localStorage
function saveFormDataToStorage() {
    const formData = {
        userId: document.getElementById('modal_user_id').value,
        bioId: document.getElementById('modal_bio_id').value,
        historyId: document.getElementById('modal_history_id').value,
        patientName: document.getElementById('modal_patient_name').textContent,
        diagnosis: document.getElementById('modal_diagnosis').value,
        interview: document.getElementById('modal_interview').value,
        recommendations: document.getElementById('modal_recommendations').value,
        savedAt: new Date().toISOString()
    };
    try {
        localStorage.setItem(FORM_STORAGE_KEY, JSON.stringify(formData));
    } catch (e) {
        console.warn('localStorage not available:', e);
    }
}

// Load form data from localStorage
function loadFormDataFromStorage() {
    try {
        const stored = localStorage.getItem(FORM_STORAGE_KEY);
        if (stored) {
            const formData = JSON.parse(stored);
            return formData;
        }
    } catch (e) {
        console.warn('Error reading localStorage:', e);
    }
    return null;
}

// Clear form data from localStorage
function clearFormDataFromStorage() {
    try {
        localStorage.removeItem(FORM_STORAGE_KEY);
    } catch (e) {
        console.warn('Error clearing localStorage:', e);
    }
}

// Open clinical notes modal
function openClinicalNotesModal(userId, bioId, patientName, historyId = 0, diagnosis = '', interview = '', recommendations = '') {
    document.getElementById('modal_user_id').value = userId;
    document.getElementById('modal_bio_id').value = bioId;
    document.getElementById('modal_history_id').value = historyId || 0;
    document.getElementById('modal_patient_name').textContent = patientName;
    document.getElementById('modal_diagnosis').value = diagnosis;
    document.getElementById('modal_interview').value = interview;
    document.getElementById('modal_recommendations').value = recommendations;
    
    // Check if there's saved form data for this patient and restore it
    const savedData = loadFormDataFromStorage();
    if (savedData && savedData.userId === String(userId) && savedData.bioId === String(bioId)) {
        document.getElementById('modal_diagnosis').value = savedData.diagnosis;
        document.getElementById('modal_interview').value = savedData.interview;
        document.getElementById('modal_recommendations').value = savedData.recommendations;
    }
    
    document.getElementById('clinicalNotesModal').classList.remove('hidden');
    document.getElementById('modal_diagnosis').focus();
}

// Close clinical notes modal
function closeClinicalNotesModal() {
    // Save form data before closing (in case user accidentally closes)
    saveFormDataToStorage();
    document.getElementById('clinicalNotesModal').classList.add('hidden');
    document.querySelector('.clinical-notes-form').reset();
}

window.onclick = function(event) {
    const modal = document.getElementById('clinicalNotesModal');
    if (event.target == modal) {
        closeClinicalNotesModal();
    }
}

// Scroll to the patient form and highlight it
function scrollToPatientForm(userId, bioId) {
    // Load the patient data if needed
    const url = 'patient_notes.php?user_id=' + userId + '&bio_id=' + bioId;
    window.location.href = url;
}

// Remove a recorded biometric from the session display
function removeRecordedBiometric(userId) {
    fetch('patient_notes.php?remove_biometric=1&user_id=' + userId, {
        method: 'GET'
    })
    .then(() => {
        // Remove the card from the UI
        const cards = document.querySelectorAll('.user-card');
        cards.forEach(card => {
            if (card.querySelector('.user-name')) {
                const cardWrapper = card.parentElement;
                if (cardWrapper && cardWrapper.parentElement.id === 'recentBiometricsSection' || cardWrapper.parentElement.classList.contains('users-grid')) {
                    card.parentElement.remove();
                }
            }
        });
    })
    .catch(error => console.error('Error:', error));
}

// Handle form submission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.clinical-notes-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            // Clear saved data on successful submission
            clearFormDataFromStorage();
            this.submit();
        });
        
        // Auto-save form data as user types
        const diagnosisField = document.getElementById('modal_diagnosis');
        const interviewField = document.getElementById('modal_interview');
        const recommendationsField = document.getElementById('modal_recommendations');
        
        if (diagnosisField) {
            diagnosisField.addEventListener('input', saveFormDataToStorage);
        }
        if (interviewField) {
            interviewField.addEventListener('input', saveFormDataToStorage);
        }
        if (recommendationsField) {
            recommendationsField.addEventListener('input', saveFormDataToStorage);
        }
    }
    
    // Search patients functionality
    const searchInput = document.getElementById('searchPatients');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const patientCards = document.querySelectorAll('#patientsGrid .user-card');
            
            patientCards.forEach(card => {
                const userName = card.querySelector('.user-name') ? card.querySelector('.user-name').textContent.toLowerCase() : '';
                const userEmail = card.querySelector('.user-email') ? card.querySelector('.user-email').textContent.toLowerCase() : '';
                const userNumber = card.textContent.toLowerCase();
                
                if (userName.includes(searchTerm) || userEmail.includes(searchTerm) || userNumber.includes(searchTerm)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }
});

// Optionally add JS for AJAX save or UI feedback
</script>
