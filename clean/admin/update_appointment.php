<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Ensure only admin can access
if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["isAdmin"]) || $_SESSION["isAdmin"] !== true) {
    header("Location: ../index.php");
    exit();
}

// Two possible POST actions: status update, or time update (new_time)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'])) {
    $appointment_id = intval($_POST['appointment_id']);

    // If new_time provided -> attempt to update time (server-side validation)
    if (isset($_POST['new_time'])) {
        $new_time = trim($_POST['new_time']);
        $ok = update_appointment_time($appointment_id, $new_time);
        if ($ok) {
            $_SESSION['message'] = "Appointment time updated successfully.";
        } else {
            $_SESSION['error'] = "Failed to update appointment time. Ensure the appointment is still 'requested' and time is between 08:00 and 17:00.";
        }
        header("Location: admin.php");
        exit();
    }

    // Otherwise handle status update
    if (isset($_POST['status'])) {
        $status = $_POST['status'];
        if (update_appointment_status($appointment_id, $status)) {
            // Log the action
            $user = get_user_by_email($_SESSION["email"]);
            if ($user) {
                log_action($user['id'], "Updated appointment status", 
                          "Appointment ID: $appointment_id, New Status: $status");
            }
            $_SESSION['message'] = "Appointment status updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update appointment status.";
        }
    }
}

header("Location: admin.php");
exit();
?>