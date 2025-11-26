<?php

function cleanup_old_logs($conn) {
    
    require_once 'create_history_logs_table.php';
    
    $days = 5;
    $query = "DELETE FROM history_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $days);
    
    if (mysqli_stmt_execute($stmt)) {
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        
        if ($affected_rows > 0) {
            
            $cleanup_log_query = "INSERT INTO history_logs (user_id, action_type, action_text) VALUES (?, ?, ?)";
            $cleanup_stmt = mysqli_prepare($conn, $cleanup_log_query);
            $user_id = NULL; 
            $action_type = "SYSTEM_CLEANUP";
            $action_text = "Automatically deleted " . $affected_rows . " log(s) older than " . $days . " days";
            mysqli_stmt_bind_param($cleanup_stmt, "iss", $user_id, $action_type, $action_text);
            mysqli_stmt_execute($cleanup_stmt);
            mysqli_stmt_close($cleanup_stmt);
        }
    }
    
    mysqli_stmt_close($stmt);
}