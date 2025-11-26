<?php 
session_start();
require_once '../config/database.php';
require_once '../config/history_log_functions.php';
require_once '../config/admin_access.php';
require_once '../config/functions.php';

// Show errors during debugging so we can see why the page is blank in the browser.
// Remove or disable these lines in production.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

error_log("Session data: " . print_r($_SESSION, true));


// Require login: if user is not logged in, set an error and redirect to login
if (!isset($_SESSION["loggedin"])) {
    $_SESSION['error'] = "Please log in first.";
    header('Location: ../index.php');
    exit;
}

// Prevent doctors and nurses from accessing this page
if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['doctor', 'nurse'])) {
    $_SESSION['error_message'] = "You do not have access to this page.";
    if ($_SESSION['role'] === 'doctor') {
        header("Location: patient_history.php");
    } else {
        header("Location: admin.php");
    }
    exit();
}

// Logged-in: load logs and render page
$logs = get_all_logs($conn);
// Debug: expose log count so we can confirm the query ran and returned rows
error_log('history_log: fetched ' . count($logs) . ' rows');
// Also print a small visible debug banner so a completely blank page reveals the issue
echo '<div style="position:fixed;left:8px;top:72px;z-index:9999;background:#fffbcc;border:1px solid #ffd24d;padding:6px;border-radius:4px;font-size:13px;color:#333">Debug: fetched ' . count($logs) . ' log(s)</div>';
?>
<!DOCTYPE html>
    <html lang="en">
    <head>
        <?php require_once '../includes/header.php'; outputHeader('History Logs'); ?>
        <style>
            /* Page-specific adjustments that complement shared styles in common.css */
            .dashboard { 
                margin: 16px auto 30px; 
                max-width: 1000px; 
                background: white; 
                border-radius: 8px; 
                box-shadow: 0 3px 8px rgba(0,0,0,0.12); 
                padding: 12px; 
            }
            .dashboard h1 { 
                text-align: center; 
                margin-bottom: 14px; 
                color: #003366; 
                font-size: 20px;
            }
            table { width: 100%; border-collapse: collapse; font-size: 13px; }
            th, td { padding: 8px; border: 1px solid #e0e6ef; text-align: left; }
            th { background: #003366; color: white; }
            tr:nth-child(even) { background: #f9fbff; }
            .controls { display:flex; justify-content:space-between; margin-bottom:12px; flex-wrap:wrap; gap:8px; align-items:center }
            .reset-button { background-color:#dc3545; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; font-size:13px }
            .reset-button:hover{ background-color:#c82333 }
            .info-message { background-color:#e6f3ff; border-left:4px solid #003366; padding:8px; margin-bottom:12px; color:#003366; font-size:13px }
            .search-box{ padding:6px 10px; border:1px solid #ccc; border-radius:4px; width:200px; font-size:13px }
            .filter-select{ padding:6px 10px; border:1px solid #ccc; border-radius:4px; font-size:13px }
            .pagination{ display:flex; justify-content:center; margin-top:12px; gap:6px }
            .pagination a{ padding:6px 10px; border:1px solid #003366; border-radius:4px; text-decoration:none; color:#003366; font-size:13px }
            .pagination a.active{ background-color:#003366;color:white }

            .user-name { display:flex; align-items:center; gap:8px; font-size:13px }
            .user-type-badge { font-size:0.75em; padding:2px 4px; border-radius:3px; color:white }
            .user-type-badge.administrator { background:#dc3545 }
            .user-type-badge.user { background:#28a745 }
        </style>
    </head>
    <body>
        <?php include '../includes/tailwind_nav.php'; ?>

        <div class="dashboard">
            <h1>History Logs</h1>
        
            <div class="info-message">
                Note: History logs are automatically cleaned up every 5 days to maintain system performance.
            </div>
        
            <form method="POST" style="margin-bottom: 20px;" onsubmit="return confirm('Are you sure you want to reset all history logs? This action cannot be undone.');">
                <button type="submit" name="reset_logs" class="reset-button">
                    Reset All Logs
                </button>
            </form>

            <div class="controls">
                <input type="text" id="searchInput" placeholder="Search by name or department..." class="search-box">
                <select id="dateFilter" class="filter-select">
                    <option value="">All Dates</option>
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                </select>
            </div>

            <table id="logsTable">
                <thead>
                    <tr>
                        <th>User Name</th>
                        <th>Role & Department</th>
                        <th>Email</th>
                        <th>Login Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <div class="user-name">
                                        <?= htmlspecialchars($log['full_name'] ?? 'Unknown User'); ?>
                                        <span class="user-type-badge <?= strtolower($log['user_type'] ?? 'user') ?>">
                                            <?= htmlspecialchars($log['user_type'] ?? 'User'); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <strong><?= ucfirst(htmlspecialchars($log['role'] ?? 'N/A')); ?></strong>
                                    <?php if (!empty($log['department_college_institute'])): ?>
                                        <br><small><?= htmlspecialchars($log['department_college_institute']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($log['email'] ?? 'N/A'); ?></td>
                                <td><?= date('M j, Y g:i A', strtotime($log['login_time'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4">No login records available.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="pagination">
                <a href="#" class="active">1</a>
                <a href="#">2</a>
                <a href="#">3</a>
                <a href="#">Next »</a>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const dateFilter = document.getElementById('dateFilter');
            const table = document.getElementById('logsTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
        
            function filterLogs() {
                const searchValue = (searchInput.value || '').toLowerCase();
                const dateValue = (dateFilter.value || '');
            
                for (let i = 0; i < rows.length; i++) {
                    const nameCell = rows[i].getElementsByTagName('td')[0];
                    const roleCell = rows[i].getElementsByTagName('td')[1];
                    const emailCell = rows[i].getElementsByTagName('td')[2];
                    const dateCell = rows[i].getElementsByTagName('td')[3];
                
                    if (nameCell && roleCell && dateCell) {
                        const allText = (
                            nameCell.textContent.toLowerCase() + ' ' +
                            roleCell.textContent.toLowerCase() + ' ' +
                            emailCell.textContent.toLowerCase()
                        );

                        const loginDate = new Date(dateCell.textContent);
                        const today = new Date();
                        const yesterday = new Date(today);
                        yesterday.setDate(yesterday.getDate() - 1);

                        let dateMatch = true;
                        if (dateValue === 'today') {
                            dateMatch = loginDate.toDateString() === today.toDateString();
                        } else if (dateValue === 'yesterday') {
                            dateMatch = loginDate.toDateString() === yesterday.toDateString();
                        } else if (dateValue === 'week') {
                            const weekAgo = new Date(today);
                            weekAgo.setDate(weekAgo.getDate() - 7);
                            dateMatch = loginDate >= weekAgo;
                        } else if (dateValue === 'month') {
                            const monthAgo = new Date(today);
                            monthAgo.setMonth(monthAgo.getMonth() - 1);
                            dateMatch = loginDate >= monthAgo;
                        }

                        const searchMatch = searchValue === '' || allText.includes(searchValue);

                        rows[i].style.display = (dateMatch && searchMatch) ? '' : 'none';
                    }
                }
            }
        
            searchInput.addEventListener('keyup', filterLogs);
            dateFilter.addEventListener('change', filterLogs);
        });
        </script>
    </body>
</html>
    