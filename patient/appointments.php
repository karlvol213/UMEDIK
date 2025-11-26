<?php
ob_start();
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';


if (!isset($_SESSION['user_id'])) {
    // Not logged in - redirect to parent login page
    header("Location: ../index.php", true, 302);
    exit;
}


function deleteAppointment($conn, $appointment_id, $user_id) {
    try {
       
        mysqli_begin_transaction($conn);
        
      
        $verify_sql = "SELECT id, appointment_date, appointment_time, status, comment FROM appointments WHERE id = ? AND user_id = ?";
        $verify_stmt = $conn->prepare($verify_sql);
        $verify_stmt->bind_param("ii", $appointment_id, $user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows === 0) {
            throw new Exception("Appointment not found or unauthorized");
        }
        
        $appointment = $verify_result->fetch_assoc();
        
        // Get services
        $services_sql = "SELECT GROUP_CONCAT(s.name SEPARATOR ', ') as service_names FROM appointment_services as_link LEFT JOIN services s ON as_link.service_id = s.id WHERE as_link.appointment_id = ?";
        $services_stmt = $conn->prepare($services_sql);
        $services_stmt->bind_param("i", $appointment_id);
        $services_stmt->execute();
        $services_result = $services_stmt->get_result();
        $services_row = $services_result->fetch_assoc();
        $service_names = $services_row['service_names'] ?? 'No services';
        
        // Create archive table if it doesn't exist
        $create_archive_sql = "CREATE TABLE IF NOT EXISTS appointments_archive (
            id INT AUTO_INCREMENT PRIMARY KEY,
            original_id INT NOT NULL,
            user_id INT NOT NULL,
            appointment_date DATE,
            appointment_time TIME,
            status VARCHAR(50),
            comment TEXT,
            services TEXT,
            service_category VARCHAR(100),
            archive_reason VARCHAR(50),
            archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            archived_by INT
        )";
        mysqli_query($conn, $create_archive_sql);
        
        // Archive the appointment (WITHOUT deleting from appointments table)
        $archive_reason = $appointment['status'] === 'completed' ? 'completed' : 'cancelled';
        $archive_sql = "INSERT INTO appointments_archive (original_id, user_id, appointment_date, appointment_time, status, comment, services, archive_reason, archived_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $archive_stmt = $conn->prepare($archive_sql);
        $archive_stmt->bind_param("iissssssi", $appointment_id, $user_id, $appointment['appointment_date'], 
                                  $appointment['appointment_time'], $appointment['status'], $appointment['comment'], 
                                  $service_names, $archive_reason, $user_id);
        
        if (!$archive_stmt->execute()) {
            throw new Exception("Failed to archive appointment");
        }
        
        // Log the action
        log_action($user_id, "Appointment Archived", "Appointment #$appointment_id was archived as $archive_reason");
        
        // Update appointment status to cancelled (so it doesn't show in active lists)
        $update_sql = "UPDATE appointments SET status = 'cancelled' WHERE id = ? AND user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ii", $appointment_id, $user_id);
        $update_stmt->execute();
        
        // Commit transaction
        mysqli_commit($conn);
        
        return true;
        
    } catch (Exception $e) {
      
        mysqli_rollback($conn);
        error_log("Error in appointment archiving: " . $e->getMessage());
        throw $e;
    }
}


if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $appointment_id = $_GET['id'];
        $user_id = $_SESSION['user_id'];
        
        deleteAppointment($conn, $appointment_id, $user_id);
        $_SESSION['success_message'] = "Appointment cancelled successfully!";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error cancelling appointment: " . $e->getMessage();
    }
    
    header("Location: appointments.php");
    exit();
}


// Get user appointments
try {
    $user_appointments = getUserAppointments($conn, $_SESSION['user_id']);
} catch (Exception $e) {
    error_log("Error in appointments.php: " . $e->getMessage());
    $_SESSION['error_message'] = "Error retrieving appointments: " . $e->getMessage();
    $user_appointments = [];
}

// Filter appointments by status
$requested_appointments = array_filter($user_appointments, function($app) {
    return $app['status'] === 'requested';
});

$approved_appointments = array_filter($user_appointments, function($app) {
    return $app['status'] === 'approved';
});

$completed_appointments = array_filter($user_appointments, function($app) {
    return $app['status'] === 'completed';
});

$cancelled_appointments = array_filter($user_appointments, function($app) {
    return $app['status'] === 'cancelled';
});



// Function to get user appointments with error handling
function getUserAppointments($conn, $user_id) {
    try {
        $sql = "SELECT 
                    a.*, 
                    GROUP_CONCAT(s.name SEPARATOR ', ') as services_list,
                    GROUP_CONCAT(s.category SEPARATOR ', ') as service_categories,
                    s.category as service_category
                FROM appointments a 
                LEFT JOIN appointment_services as_link ON a.id = as_link.appointment_id 
                LEFT JOIN services s ON as_link.service_id = s.id 
                WHERE a.user_id = ? 
                GROUP BY a.id 
                ORDER BY a.appointment_date DESC, a.appointment_time DESC";
                
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("Error in appointments.php: " . $e->getMessage());
        throw $e;
    }
}

// Get user appointments
try {
    $user_appointments = getUserAppointments($conn, $_SESSION['user_id']);
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error retrieving appointments: " . $e->getMessage();
    $user_appointments = [];
}

// Filter appointments by status
$requested_appointments = array_filter($user_appointments, function($app) {
    return $app['status'] === 'requested';
});

$approved_appointments = array_filter($user_appointments, function($app) {
    return $app['status'] === 'approved';
});

$completed_appointments = array_filter($user_appointments, function($app) {
    return $app['status'] === 'completed';
});

$cancelled_appointments = array_filter($user_appointments, function($app) {
    return $app['status'] === 'cancelled';
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - UMak Medical Clinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/common.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        .card-hover:hover {
            transform: translateY(-5px);
            transition: all 0.3s ease;
        }
        
        .btn-hover:hover {
            transform: translateY(-2px);
            transition: all 0.3s ease;
        }
    
        .appointment-card {
            border: 1px solid #e6e9ef;
            padding: 20px;
            border-radius: 10px;
            background: #ffffff;
            box-shadow: 0 6px 18px rgba(18, 38, 63, 0.04);
            margin-bottom: 20px;
        }
        .appointment-card-header {
            display: block;
            margin-bottom: 15px;
        }
        .card-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-bottom: 10px;
        }
        .status-pending { background:#fff7e6; color:#8a5d00; border:1px solid #ffecb8 }
        .status-approved { background:#e8f8ef; color:#116033; border:1px solid #c7efd6 }
        .status-completed { background:#e8f0ff; color:#0b4b8a; border:1px solid #d6e6ff }
        .card-datetime {
            text-align: right;
            margin-top: 8px;
        }
        .card-date {
            font-weight: 700;
            color: #3b4b5a;
            display: block;
        }
        .card-time {
            color: #2563eb;
            padding: 2px 6px;
            background-color: #eff6ff;
            border-radius: 4px;
            display: inline-block;
            font-size: 12px;
            margin-top: 4px;
        }
        .appointment-content {
            color: #374151;
            margin: 15px 0;
        }
        .appointment-actions {
            text-align: right;
            margin-top: 15px;
        }
        .appointment-actions a {
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: 600;
            display: inline-block;
        }
        .action-cancel { background:#fff1f0; color:#b91c1c; border:1px solid #f8d7da }
        .action-delete { background:#fff5f7; color:#9f1239; border:1px solid #ffd6e0 }
        .action-primary { background:#eef2ff; color:#3730a3; border:1px solid #dbe4ff }
    </style>
</head>
<body class="min-h-screen" style="background: linear-gradient(to bottom right, #ffffff, #cce0ff); background-attachment: fixed; background-repeat: no-repeat;">
    <?php include '../includes/tailwind_nav.php'; ?>
    <div class="min-h-screen" style="padding-top: 0;">
        <div class="pt-16">
            <div class="container mx-auto px-4 py-8 max-w-6xl">
                <div class="flex justify-end mb-6">
                <a href="./schedule.php" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition-colors duration-200 flex items-center gap-2">
                    <i class="fas fa-calendar-plus"></i>
                    Schedule Now
                </a>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                    <p class="font-medium">
                        <?php 
                            $sm = $_SESSION['success_message'];
                            if ($sm === 'Appointment scheduled successfully!') {
                                $sm = 'Appointment successfully! Please wait for the Gmail to notify you when your approved.';
                            }
                            echo $sm;
                            unset($_SESSION['success_message']);
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                    <p class="font-medium">
                        <?php 
                            echo $_SESSION['error_message']; 
                            unset($_SESSION['error_message']);
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="grid gap-6">
                
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-clock text-yellow-500 mr-2"></i>Requested Appointments
                    </h2>
                    <?php if (empty($requested_appointments)): ?>
                        <p class="text-gray-500 italic">No pending requests</p>
                    <?php else: ?>
                        <?php foreach ($requested_appointments as $appt): ?>
                            <div class="appointment-card">
                                <div class="appointment-card-header">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <div class="card-badge status-pending">Pending</div>
                                            <div class="mt-3">
                                                <?php
                                                    $services_display = trim($appt['services_list'] ?? '');
                                                    $service_category = trim($appt['service_category'] ?? '');
                                                    
                                                    $category_icons = [
                                                        'Dental Consultation' => '<i class="fas fa-tooth mr-2 text-blue-500"></i>',
                                                        'Medical Consultation' => '<i class="fas fa-stethoscope mr-2 text-green-500"></i>',
                                                        'Check-up Consultation' => '<i class="fas fa-notes-medical mr-2 text-purple-500"></i>'
                                                    ];
                                                    
                                                    if ($services_display === '') {
                                                        $services_display = trim($appt['comment'] ?? '');
                                                        if ($services_display === '') {
                                                            $services_display = 'No services listed';
                                                        }
                                                        echo '<p class="text-sm"><i class="fas fa-info-circle mr-2 text-gray-400"></i>' . htmlspecialchars($services_display) . '</p>';
                                                    } else {
                                                        $is_other = (strtolower(trim($services_display)) === 'other' || strtolower(trim($services_display)) === 'others/more');
                                                        $icon = $category_icons[$service_category] ?? '<i class="fas fa-stethoscope mr-2"></i>';
                                                        echo '<p class="font-medium text-sm mb-1">' . $icon . htmlspecialchars($service_category) . '</p>';
                                                        
                                                        if ($is_other && !empty($appt['comment'])) {
                                                            echo '<p class="ml-6 font-medium text-sm">Others/More:</p>';
                                                            echo '<p class="ml-6 text-sm mt-1">' . htmlspecialchars($appt['comment']) . '</p>';
                                                        } else {
                                                            echo '<p class="ml-6 text-sm">' . htmlspecialchars($services_display) . '</p>';
                                                        }
                                                    }
                                                ?>
                                            </div>
                                        </div>
                                        <div class="card-datetime">
                                            <span class="card-date"><i class="far fa-calendar-alt mr-2"></i><?= date('F j, Y', strtotime($appt['appointment_date'])) ?></span>
                                            <span class="card-time"><i class="far fa-clock mr-2"></i><?= date('h:i A', strtotime($appt['appointment_time'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($appt['comment'])): ?>
                                    <p class="mt-2 text-gray-500 text-sm break-words" style="word-break: break-word; overflow-wrap: break-word; max-width: 100%;">
                                        <i class="far fa-comment-alt mr-2"></i>
                                        <?php 
                                            $comment = htmlspecialchars($appt['comment']);
                                            // Split by whitespace to count words
                                            $words = preg_split('/\s+/', trim($comment), -1, PREG_SPLIT_NO_EMPTY);
                                            $wordCount = count($words);
                                            
                                            // Truncate to 40 words or 100 characters
                                            if ($wordCount > 40) {
                                                $truncated = implode(' ', array_slice($words, 0, 40));
                                                echo $truncated . '... <a href="#" class="text-blue-600 font-semibold" onclick="alert(\'' . addslashes($comment) . '\'); return false;">Read more</a>';
                                            } else if (strlen($comment) > 100) {
                                                // If no word break but too long, limit by character to 100
                                                $truncated = substr($comment, 0, 100);
                                                echo $truncated . '... <a href="#" class="text-blue-600 font-semibold" onclick="alert(\'' . addslashes($comment) . '\'); return false;">Read more</a>';
                                            } else {
                                                echo $comment;
                                            }
                                        ?>
                                    </p>
                                <?php endif; ?>
                                <div class="appointment-actions">
                                    <a href="?action=delete&id=<?= $appt['id'] ?>" 
                                       class="action-cancel"
                                       onclick="return confirm('Are you sure you want to cancel this appointment?');">
                                        <i class="fas fa-times-circle mr-1"></i>
                                        Cancel
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Approved Appointments -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-check-circle text-green-500 mr-2"></i>Approved Appointments
                    </h2>
                    <?php if (empty($approved_appointments)): ?>
                        <p class="text-gray-500 italic">No approved appointments</p>
                    <?php else: ?>
                        <div class="space-y-4">
                        <?php foreach ($approved_appointments as $appt): ?>
                            <div class="appointment-card">
                                <div class="appointment-card-header">
                                    <span class="card-badge badge-approved">Approved</span>
                                    <div class="card-datetime">
                                        <div class="card-date">
                                            <i class="far fa-calendar-alt mr-2"></i><?= date('F j, Y', strtotime($appt['appointment_date'])) ?>
                                        </div>
                                        <div class="card-time">
                                            <i class="far fa-clock mr-2"></i><?= date('h:i A', strtotime($appt['appointment_time'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="appointment-content">
                                    <p class="text-gray-600">
                                        <i class="fas fa-stethoscope mr-2"></i>
                                        <?php
                                            $services_display = trim($appt['services_list'] ?? '');
                                            if ($services_display === '') {
                                                $services_display = trim($appt['comment'] ?? '');
                                                if ($services_display === '') {
                                                    $services_display = 'No services listed';
                                                }
                                            }
                                            echo htmlspecialchars($services_display);
                                        ?>
                                    </p>
                                    <?php if (!empty($appt['comment'])): ?>
                                        <p class="mt-2 text-gray-500 text-sm break-words" style="word-break: break-word; overflow-wrap: break-word; max-width: 100%;">
                                            <i class="far fa-comment-alt mr-2"></i>
                                            <?php 
                                                $comment = htmlspecialchars($appt['comment']);
                                                // Split by whitespace to count words
                                                $words = preg_split('/\s+/', trim($comment), -1, PREG_SPLIT_NO_EMPTY);
                                                $wordCount = count($words);
                                                
                                                // Truncate to 40 words or 100 characters
                                                if ($wordCount > 40) {
                                                    $truncated = implode(' ', array_slice($words, 0, 40));
                                                    echo $truncated . '... <a href="#" class="text-blue-600 font-semibold" onclick="alert(\'' . addslashes($comment) . '\'); return false;">Read more</a>';
                                                } else if (strlen($comment) > 100) {
                                                    // If no word break but too long, limit by character to 100
                                                    $truncated = substr($comment, 0, 100);
                                                    echo $truncated . '... <a href="#" class="text-blue-600 font-semibold" onclick="alert(\'' . addslashes($comment) . '\'); return false;">Read more</a>';
                                                } else {
                                                    echo $comment;
                                                }
                                            ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Completed Appointments -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-check-double text-blue-500 mr-2"></i>Completed Appointments
                    </h2>
                    <?php if (empty($completed_appointments)): ?>
                        <p class="text-gray-500 italic">No completed appointments</p>
                    <?php else: ?>
                        <div class="space-y-4">
                        <?php foreach ($completed_appointments as $appt): ?>
                            <div class="appointment-card">
                                <div class="appointment-card-header">
                                    <span class="card-badge badge-completed">Completed</span>
                                    <div class="card-datetime">
                                        <div class="card-date"><i class="far fa-calendar-alt mr-2"></i><?= date('F j, Y', strtotime($appt['appointment_date'])) ?></div>
                                        <div class="card-time">
                                            <i class="far fa-clock mr-2"></i><?= date('h:i A', strtotime($appt['appointment_time'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="appointment-content">
                                    <?php
                                        $services_display = trim($appt['services_list'] ?? '');
                                        $service_category = trim($appt['service_category'] ?? '');
                                        
                                        $category_icons = [
                                            'Dental Consultation' => '<i class="fas fa-tooth mr-2 text-blue-500"></i>',
                                            'Medical Consultation' => '<i class="fas fa-stethoscope mr-2 text-green-500"></i>',
                                            'Check-up Consultation' => '<i class="fas fa-notes-medical mr-2 text-purple-500"></i>'
                                        ];
                                        
                                        if ($services_display === '') {
                                            $services_display = trim($appt['comment'] ?? '');
                                            if ($services_display === '') {
                                                $services_display = 'No services listed';
                                            }
                                            echo '<p><i class="fas fa-info-circle mr-2 text-gray-400"></i>' . htmlspecialchars($services_display) . '</p>';
                                        } else {
                                            $icon = $category_icons[$service_category] ?? '<i class="fas fa-stethoscope mr-2"></i>';
                                            echo '<p class="font-medium mb-1">' . $icon . 'Selected Service:</p>';
                                            echo '<p class="ml-6">' . htmlspecialchars($services_display) . '</p>';
                                        }
                                    ?>
                                    <?php if (!empty($appt['comment'])): ?>
                                        <p class="mt-2 text-gray-500 text-sm break-words" style="word-break: break-word; overflow-wrap: break-word; max-width: 100%;">
                                            <i class="far fa-comment-alt mr-2"></i>
                                            <?php 
                                                $comment = htmlspecialchars($appt['comment']);
                                                // Split by whitespace to count words
                                                $words = preg_split('/\s+/', trim($comment), -1, PREG_SPLIT_NO_EMPTY);
                                                $wordCount = count($words);
                                                
                                                // Truncate to 40 words or 100 characters
                                                if ($wordCount > 40) {
                                                    $truncated = implode(' ', array_slice($words, 0, 40));
                                                    echo $truncated . '... <a href="#" class="text-blue-600 font-semibold" onclick="alert(\'' . addslashes($comment) . '\'); return false;">Read more</a>';
                                                } else if (strlen($comment) > 100) {
                                                    // If no word break but too long, limit by character to 100
                                                    $truncated = substr($comment, 0, 100);
                                                    echo $truncated . '... <a href="#" class="text-blue-600 font-semibold" onclick="alert(\'' . addslashes($comment) . '\'); return false;">Read more</a>';
                                                } else {
                                                    echo $comment;
                                                }
                                            ?>
                                        </p>
                                    <?php endif; ?>
                                    <div class="appointment-actions">
                                        <a href="?action=delete&id=<?= $appt['id'] ?>" 
                                           class="action-cancel"
                                           onclick="return confirm('Are you sure you want to remove this completed appointment?');">
                                            <i class="fas fa-archive mr-1"></i>
                                            Remove
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>

       
        <button id="backToTop" 
                class="fixed bottom-4 right-4 bg-purple-600 text-white p-2 rounded-full shadow-lg hidden hover:bg-purple-700 transition-all">
            <i class="fas fa-arrow-up"></i>
        </button>
    </div>

    <script>
      
        const backToTopButton = document.getElementById('backToTop');
        
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTopButton.classList.remove('hidden');
            } else {
                backToTopButton.classList.add('hidden');
            }
        });
        
        backToTopButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
