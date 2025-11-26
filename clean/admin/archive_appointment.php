<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../models/Appointment.php';
require_once __DIR__ . '/../models/Service.php';

$pdo = Database::getInstance();

// Check if user is logged in
if (!isset($_SESSION["loggedin"])) {
    header("Location: ../index.php");
    exit();
}

// Get appointment ID from either POST or GET
$appointment_id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'])) {
    $appointment_id = intval($_POST['appointment_id']);
} elseif (isset($_GET['id'])) {
    $appointment_id = intval($_GET['id']);
}

if ($appointment_id > 0) {
    // Verify the appointment belongs to the current user (or user is admin)
    $sql = "SELECT a.*, u.full_name, GROUP_CONCAT(s.name SEPARATOR ', ') as service_names
        FROM appointments a
        LEFT JOIN appointment_services aps ON a.id = aps.appointment_id
        LEFT JOIN services s ON aps.service_id = s.id
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.id = :id " . 
        (!isset($_SESSION["isAdmin"]) || $_SESSION["isAdmin"] !== true ? "AND a.user_id = :user_id " : "") .
        "GROUP BY a.id";
    
    $stmt = $pdo->prepare($sql);
    $params = [':id' => $appointment_id];
    if (!isset($_SESSION["isAdmin"]) || $_SESSION["isAdmin"] !== true) {
        $params[':user_id'] = $_SESSION['user_id'];
    }
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        try {
            $pdo->beginTransaction();

            // Insert into archive with service_category
            $ins = $pdo->prepare('INSERT INTO appointments_archive (
                original_id, user_id, appointment_date, appointment_time, 
                status, comment, services, service_category, archived_by, 
                archive_reason
            ) VALUES (
                :orig, :uid, :ad, :at,
                :st, :cm, :sv, :cat, :arch,
                :reason
            )');
            
            // Determine archive reason and ensure it matches the ENUM values
            $archive_reason = 'other';
            if ($row['status'] === 'completed') {
                $archive_reason = 'completed';
            } elseif ($row['status'] === 'cancelled' || $row['status'] === 'requested') {
                $archive_reason = 'cancelled';
            }

            $ins->execute([
                ':orig' => (int)$row['id'],
                ':uid' => isset($row['user_id']) ? (int)$row['user_id'] : null,
                ':ad' => $row['appointment_date'] ?? null,
                ':at' => $row['appointment_time'] ?? null,
                ':st' => $row['status'] ?? null,
                ':cm' => $row['comment'] ?? null,
                ':sv' => $row['service_names'] ?? null,
                ':cat' => $row['service_category'] ?? null,
                ':arch' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
                ':reason' => $archive_reason
            ]);

            // Delete the original appointment
            $del = $pdo->prepare('DELETE FROM appointments WHERE id = :id');
            $del->execute([':id' => $appointment_id]);

            $pdo->commit();

            // Log the action
            if (function_exists('log_action') && isset($_SESSION['user_id'])) {
                $action = $row['status'] === 'completed' ? 'ARCHIVE_COMPLETED' : 'CANCEL_APPOINTMENT';
                log_action($_SESSION['user_id'], $action, 'Appointment #' . $appointment_id);
            }

            $_SESSION['success_message'] = $row['status'] === 'completed' ? 
                'Appointment archived successfully' : 
                'Appointment cancelled successfully';

        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = 'Failed to process appointment: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = 'Appointment not found or access denied';
    }
}

// Redirect back to appointments page
if (isset($_SESSION["isAdmin"]) && $_SESSION["isAdmin"] === true) {
    header('Location: admin.php');
} else {
    header('Location: ../appointments.php');
}
exit();



