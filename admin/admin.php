<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/functions.php';
require_once '../config/admin_access.php';

// Check if user is a doctor - redirect to patient history
if (isset($_SESSION['role']) && $_SESSION['role'] === 'doctor') {
    header("Location: patient_history.php");
    exit();
}

// Check if user is a nurse - allow access to admin (appointment requests)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'nurse') {
    require_nurse_allowed_page();
}

// Check admin access
if (!isset($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true) {
    header('Location: ../index.php');
    exit();
}

$appointments = [];
if (function_exists('get_all_appointments')) {
    try {
        $appointments = get_all_appointments();
        if (!is_array($appointments)) {
            $appointments = [];
        }
    } catch (Throwable $e) {
       
        $appointments = [];
    }
}

// Auto-cancel overdue approved appointments
// Check for approved appointments that have passed their scheduled time
require_once '../config/database.php';
try {
    $pdo = Database::getInstance();
    $now = new DateTime();
    $now_string = $now->format('Y-m-d H:i:s');
    
    // Find approved appointments where the scheduled time has passed
    $overdue_sql = "SELECT a.id, a.appointment_date, a.appointment_time, a.user_id
                    FROM appointments a
                    WHERE a.status = 'approved' 
                    AND CONCAT(a.appointment_date, ' ', a.appointment_time) < ?
                    LIMIT 100";
    $overdue_stmt = $pdo->prepare($overdue_sql);
    $overdue_stmt->execute([$now_string]);
    
    while ($overdue = $overdue_stmt->fetch(PDO::FETCH_ASSOC)) {
        // Auto-cancel this appointment
        $cancel_stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
        $cancel_stmt->execute([$overdue['id']]);
        
        // Log the action
        if (function_exists('log_action')) {
            log_action($overdue['user_id'], 'APPOINTMENT_AUTO_CANCELLED', 'Appointment automatically cancelled - patient did not show up at scheduled time');
        }
    }
} catch (Exception $e) {
    error_log("Error auto-cancelling overdue appointments: " . $e->getMessage());
}

// Sort appointments: requested first, then by date
usort($appointments, function($a, $b) {
    // Requested appointments always come first
    if ($a['status'] === 'requested' && $b['status'] !== 'requested') {
        return -1;
    }
    if ($a['status'] !== 'requested' && $b['status'] === 'requested') {
        return 1;
    }
    // Then sort by appointment date
    return strtotime($a['appointment_date']) - strtotime($b['appointment_date']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require_once '../includes/header.php'; outputHeader('Admin'); ?>
    <style>
   
    .dashboard {
        margin: 80px auto;
        max-width: 1400px;
        background: #f8fafc;
        border-radius: 16px;
        box-shadow: 0 8px 16px rgba(0,0,0,0.05);
        padding: 32px;
    }

    .dashboard h1 {
        text-align: left;
        margin-bottom: 24px;
        color: #1a365d;
        font-size: 28px;
        font-weight: 600;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 16px;
    }

   
    table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-top: 24px;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }

    th, td {
        padding: 16px;
        text-align: left;
        border-bottom: 1px solid #e2e8f0;
        font-size: 14px;
    }

    th {
        background: #1a365d;
        color: white;
        font-weight: 500;
        text-transform: uppercase;
        font-size: 13px;
        letter-spacing: 0.5px;
    }

    tr:hover {
        background-color: #f8fafc;
        transition: all 0.2s ease;
    }

 
    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: inline-block;
    }

    .status-requested {
        background-color: #fef3c7;
        color: #92400e;
        border: 1px solid #fbbf24;
    }

    .status-approved {
        background-color: #dcfce7;
        color: #166534;
        border: 1px solid #4ade80;
    }

    .status-completed {
        background-color: #dbeafe;
        color: #1e40af;
        border: 1px solid #60a5fa;
    }

    .status-cancelled {
        background-color: #fee2e2;
        color: #991b1b;
        border: 1px solid #f87171;
    }

    .status-warning {
        background-color: #fef08a;
        color: #92400e;
        border: 1px solid #facc15;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.7;
        }
    }

    .action-btn {
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        margin-right: 8px;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .btn-approve {
        background-color: #059669;
        color: white;
    }

    .btn-approve:hover {
        background-color: #047857;
    }

    .btn-complete {
        background-color: #2563eb;
        color: white;
    }

    .btn-complete:hover {
        background-color: #1d4ed8;
    }

    .btn-cancel {
        background-color: #dc2626;
        color: white;
    }

    .btn-cancel:hover {
        background-color: #b91c1c;
    }

   
    .filter-section {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        margin-bottom: 24px;
        display: flex;
        gap: 16px;
        align-items: center;
        flex-wrap: wrap;
    }

    .filter-section select,
    .filter-section input {
        padding: 10px 16px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 14px;
        color: #1a365d;
        transition: all 0.2s ease;
        min-width: 200px;
    }

    .filter-section select:focus,
    .filter-section input:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
    }



  
    @media (max-width: 1024px) {
        .dashboard {
            margin: 60px 20px;
            padding: 24px;
        }
        
        .filter-section {
            flex-direction: column;
            align-items: stretch;
        }
        
        .filter-section select,
        .filter-section input {
            width: 100%;
        }
    }
    </style>
</head>
<body>
<?php include '../includes/tailwind_nav.php'; ?>
<div class="dashboard">
    <h1>Appointment Requests</h1>
    <div class="filter-section">
        <div style="flex: 1;">
            <label for="search-input" style="display: block; margin-bottom: 8px; color: #4a5568; font-weight: 500;">
                <i class="fas fa-search"></i> Search Patient
            </label>
            <input type="text" id="search-input" placeholder="Enter patient name..." style="width: 100%;">
        </div>
        <div style="flex: 1;">
            <label for="status-filter" style="display: block; margin-bottom: 8px; color: #4a5568; font-weight: 500;">
                <i class="fas fa-filter"></i> Filter by Status
            </label>
            <select id="status-filter" style="width: 100%;">
                <option value="">All Status</option>
                <option value="requested">Requested</option>
                <option value="approved">Approved</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
    </div>

    <div style="background: white; border-radius: 8px; padding: 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="color: #1a365d; font-size: 18px; font-weight: 600;">
                 Current Appointments
            </h2>
            <div style="color: #4a5568; font-size: 14px;">
                Total: <?= count($appointments) ?> appointments
            </div>
        </div>
        
        <table id="appointments-table">
            <thead>
                <tr>
                    <th>Patient Name</th>
                    <th>Services</th>
                    <th>Appointment Date</th>
                    <th>Appointment Time</th>
                    <th>Status</th>
                    <th>Comments</th>
                    <th>Actions</th>
                </tr>
            </thead>
        <tbody>
            <?php if (count($appointments) > 0): ?>
                <?php foreach ($appointments as $appt): ?>
                    <?php
                        // Check if appointment is within 2 hours of cancellation time
                        $warning_class = '';
                        $warning_text = '';
                        if ($appt['status'] === 'approved') {
                            try {
                                $appt_datetime = new DateTime($appt['appointment_date'] . ' ' . ($appt['appointment_time'] ?? '09:00:00'));
                                $now = new DateTime();
                                $diff_minutes = ($appt_datetime->getTimestamp() - $now->getTimestamp()) / 60;
                                
                                // If within 2 hours (120 minutes) and appointment time hasn't passed yet
                                if ($diff_minutes <= 120 && $diff_minutes > 0) {
                                    $warning_class = 'status-warning';
                                    $warning_text = ' ⏰ ' . round($diff_minutes) . ' min to cancel';
                                }
                            } catch (Exception $e) {
                                // Continue if datetime parsing fails
                            }
                        }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($appt['full_name'] ?? ''); ?></td>
                        <td>
                            <?php
                                $svc = trim($appt['service_names'] ?? '');
                             
                                $svc = preg_replace('/\bOther\b/i', 'Others/More', $svc);
                                if ($svc === '') {
                                    $svc = trim($appt['comment'] ?? '');
                                    if ($svc === '') $svc = 'No services listed';
                                }
                                echo htmlspecialchars($svc);
                            ?>
                        </td>
                        <td><?= htmlspecialchars($appt['appointment_date'] ?? ''); ?></td>
                        <td>
                                <?php
                                    $has_time = !empty($appt['appointment_time']);
                                    if ($has_time) {
                                        $display_time = htmlspecialchars(date('h:i A', strtotime($appt['appointment_time'])));
                                    } else {
                                        $display_time = '';
                                    }
                                ?>
                                <span><?= $display_time ?></span>
                        </td>
                        <td>
                            <span class="status-badge status-<?= htmlspecialchars($appt['status'] ?? ''); ?> <?= $warning_class ?>">
                                <?= htmlspecialchars(ucfirst($appt['status'] ?? '')); ?><?= $warning_text ?>
                            </span>
                        </td>
                        <td>
                            <?php
                                $raw_comment = trim($appt['comment'] ?? '');
                                $other_desc = '';
                                if ($raw_comment !== '' && preg_match('/Other service requested:\s*(.+)$/mi', $raw_comment, $m2)) {
                                    $other_desc = trim($m2[1]);
                                    $raw_comment = preg_replace('/(\r?\n)?\s*Other service requested:\s*.+$/mi', '', $raw_comment);
                                    $raw_comment = trim($raw_comment);
                                }
                                
                                // Truncate to 40 words or 100 characters
                                $words = preg_split('/\s+/', trim($raw_comment), -1, PREG_SPLIT_NO_EMPTY);
                                $wordCount = count($words);
                                
                                if ($wordCount > 40) {
                                    $truncated = implode(' ', array_slice($words, 0, 40));
                                    echo '<div style="word-break: break-word; overflow-wrap: break-word;">' . htmlspecialchars($truncated) . '... <a href="#" class="text-blue-600 font-semibold" onclick="alert(\'' . addslashes(htmlspecialchars($raw_comment)) . '\'); return false;">Read more</a></div>';
                                } else if (strlen($raw_comment) > 100) {
                                    $truncated = substr($raw_comment, 0, 100);
                                    echo '<div style="word-break: break-word; overflow-wrap: break-word;">' . htmlspecialchars($truncated) . '... <a href="#" class="text-blue-600 font-semibold" onclick="alert(\'' . addslashes(htmlspecialchars($raw_comment)) . '\'); return false;">Read more</a></div>';
                                } else {
                                    echo '<div style="word-break: break-word; overflow-wrap: break-word;">' . htmlspecialchars($raw_comment) . '</div>';
                                }
                            ?>
                        </td>
                        <td>
                            <?php if ($appt['status'] == 'requested'): ?>
                                <button class="action-btn btn-approve" onclick="updateStatus(<?= $appt['id'] ?>, 'approved')">Approve</button>
                                <button class="action-btn btn-cancel" onclick="updateStatus(<?= $appt['id'] ?>, 'cancelled')">Cancel</button>
                            <?php elseif ($appt['status'] == 'approved'): ?>
                                <button class="action-btn btn-complete" onclick="updateStatus(<?= $appt['id'] ?>, 'completed')">Complete</button>
                                <button class="action-btn btn-cancel" onclick="updateStatus(<?= $appt['id'] ?>, 'cancelled')">Cancel</button>
                            <?php elseif ($appt['status'] == 'completed' || $appt['status'] == 'cancelled'): ?>
                             
                                <button class="action-btn" onclick="archiveAppointment(<?= $appt['id'] ?>)">Archive</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7">No appointment requests.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function updateStatus(appointmentId, status) {
    if (confirm('Are you sure you want to update this appointment status?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'update_appointment.php';
        
        const inputId = document.createElement('input');
        inputId.type = 'hidden';
        inputId.name = 'appointment_id';
        inputId.value = appointmentId;
        
        const inputStatus = document.createElement('input');
        inputStatus.type = 'hidden';
        inputStatus.name = 'status';
        inputStatus.value = status;
        
        form.appendChild(inputId);
        form.appendChild(inputStatus);
        document.body.appendChild(form);
        
        if (status === 'approved') {
            form.addEventListener('submit', function(e) {
                setTimeout(function() {
                    window.location.href = 'info_admin.php';
                }, 500);
            });
        }
        
        form.submit();
    }
}

function archiveAppointment(appointmentId) {
    if (confirm('Archive this completed appointment? This will move it to the archive and remove it from the list.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'archive_appointment.php';
        const inputId = document.createElement('input');
        inputId.type = 'hidden';
        inputId.name = 'appointment_id';
        inputId.value = appointmentId;
        form.appendChild(inputId);
        document.body.appendChild(form);
        form.submit();
    }
}



document.addEventListener('DOMContentLoaded', function() {
    const statusFilter = document.getElementById('status-filter');
    const searchInput = document.getElementById('search-input');
    const table = document.getElementById('appointments-table');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    function filterTable() {
        const statusValue = statusFilter.value.toLowerCase();
        const searchValue = searchInput.value.toLowerCase();
        
        for (let i = 0; i < rows.length; i++) {
            const statusCell = rows[i].getElementsByTagName('td')[4];
            const nameCell = rows[i].getElementsByTagName('td')[0];
            
            if (statusCell && nameCell) {
                const rowStatus = statusCell.textContent.toLowerCase() || statusCell.innerText.toLowerCase();
                const rowName = nameCell.textContent.toLowerCase() || nameCell.innerText.toLowerCase();
                
                const statusMatch = statusValue === '' || rowStatus.includes(statusValue);
                const nameMatch = searchValue === '' || rowName.includes(searchValue);
                
                if (statusMatch && nameMatch) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }
    }
    
    statusFilter.addEventListener('change', filterTable);
    searchInput.addEventListener('keyup', filterTable);
});
</script>

</body>
</html>