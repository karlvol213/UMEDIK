<?php

function ensure_history_logs_table_exists($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS history_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action_type VARCHAR(50),
        action_text TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )";
    
    if (mysqli_query($conn, $sql)) {
        return true;
    } else {
        error_log("Error creating history_logs table: " . mysqli_error($conn));
        return false;
    }
}


function add_log_entry($conn, $user_id, $action_type, $action_text) {
    $query = "INSERT INTO history_logs (user_id, action_type, action_text) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "iss", $user_id, $action_type, $action_text);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}


function get_all_logs($conn) {
    $query = "SELECT 
        hl.created_at as login_time,
        hl.user_id,
        hl.action_type,
        hl.action_text,
        u.full_name,
        u.email,
        u.role,
        u.department_college_institute,
        CASE 
            WHEN hl.action_type = 'ADMIN_LOGIN' THEN 'Administrator'
            WHEN hl.action_type = 'USER_LOGIN' THEN 
                CASE 
                    WHEN u.role = 'student' THEN 'Student'
                    WHEN u.role = 'employee' THEN 'Employee'
                    ELSE 'User'
                END
            ELSE 'Unknown'
        END as user_type
        FROM history_logs hl
        LEFT JOIN users u ON hl.user_id = u.id
        WHERE hl.action_type IN ('USER_LOGIN', 'ADMIN_LOGIN')
        ORDER BY hl.created_at DESC";
    
    $result = mysqli_query($conn, $query);
    if (!$result) {
        error_log("Query error in get_all_logs: " . mysqli_error($conn));
        return [];
    }
    
    $logs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $logs[] = $row;
    }
    
    
    if (empty($logs)) {
        error_log("No logs found in get_all_logs");
    }
    
    return $logs;
}


function cleanup_old_logs($conn) {
    $days = 5;
    $query = "DELETE FROM history_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $days);
    
    if (mysqli_stmt_execute($stmt)) {
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        if ($affected_rows > 0) {
            add_log_entry(
                $conn,
                null,
                "SYSTEM_CLEANUP",
                "Automatically deleted " . $affected_rows . " log(s) older than " . $days . " days"
            );
        }
    }
    
    mysqli_stmt_close($stmt);
}
?>