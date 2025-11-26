<?php

function create_initial_patient_record($user_id) {
    global $conn;
    
    
    $check_query = "SELECT id FROM patient_records WHERE user_id = ? AND record_type = 'medical'";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) == 0) {
        
        $insert_query = "INSERT INTO patient_records (user_id, record_type) VALUES (?, 'medical')";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
    }

    
    $check_query = "SELECT id FROM patient_records WHERE user_id = ? AND record_type = 'dental'";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) == 0) {
        
        $insert_query = "INSERT INTO patient_records (user_id, record_type) VALUES (?, 'dental')";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
    }
}


/**
 * Determine the foreign key column name used by patient_history_records table.
 * Returns either 'patient_id' or 'user_id' (whichever exists), defaults to 'patient_id'.
 *
 * @return string
 */
function get_history_fk_column() {
    global $conn, $dbname;

    $query = "SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = ? AND table_name = 'patient_history_records' AND COLUMN_NAME IN ('patient_id','user_id') LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $dbname);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && $row = mysqli_fetch_assoc($res)) {
            mysqli_stmt_close($stmt);
            return $row['COLUMN_NAME'];
        }
        mysqli_stmt_close($stmt);
    }

    // If neither patient_id nor user_id exists, log actual columns for debugging
    $col_dump_query = "SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = ? AND table_name = 'patient_history_records'";
    $stmt2 = mysqli_prepare($conn, $col_dump_query);
    if ($stmt2) {
        mysqli_stmt_bind_param($stmt2, 's', $dbname);
        mysqli_stmt_execute($stmt2);
        $res2 = mysqli_stmt_get_result($stmt2);
        $cols = [];
        while ($row2 = mysqli_fetch_assoc($res2)) {
            $cols[] = $row2['COLUMN_NAME'];
        }
        mysqli_stmt_close($stmt2);
        error_log('patient_history_records columns: ' . implode(', ', $cols));
    } else {
        error_log('Failed to query information_schema.columns for patient_history_records');
    }

    return 'user_id';
}


/**
 * Add a diagnosis entry to patient_history_records as performed by an admin.
 *
 * @param int $patient_id
 * @param string $diagnosis
 * @param int|null $admin_user_id  ID of admin performing the entry (for logging)
 * @param string|null $visit_date  Date of visit (Y-m-d). Defaults to today.
 * @return bool
 */
function add_admin_diagnosis($patient_id, $diagnosis, $admin_user_id = null, $visit_date = null) {
    global $conn;
    global $dbname;
    // Place diagnosis text into assessment_notes and set created_by = admin_user_id (if provided)
    // Other numeric fields are left NULL where applicable.
    $assessment = "Diagnosis:\n" . $diagnosis;

    $insert_sql = "INSERT INTO patient_history_records (user_id, record_type, height, weight, blood_pressure, pulse_rate, respiratory_rate, temperature, assessment_notes, created_by) VALUES (?, 'medical', NULL, NULL, NULL, NULL, NULL, NULL, ?, ?)";
    $stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param($stmt, "isi", $patient_id, $assessment, $admin_user_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Log action if log_action exists
    if (function_exists('log_action') && $admin_user_id) {
        log_action($admin_user_id, 'ADMIN_DIAGNOSIS', "Added diagnosis for patient_id={$patient_id}");
    }

    return $ok;
}


/**
 * Add a patient interview note to patient_history_records as recorded by an admin.
 *
 * @param int $patient_id
 * @param string $interview_text
 * @param int|null $admin_user_id
 * @param string|null $visit_date
 * @return bool
 */
function add_admin_interview($patient_id, $interview_text, $admin_user_id = null, $visit_date = null) {
    global $conn;
    global $dbname;
    // Place interview text into assessment_notes and set created_by = admin_user_id
    $assessment = "Interview/Notes:\n" . $interview_text;

    $insert_sql = "INSERT INTO patient_history_records (user_id, record_type, height, weight, blood_pressure, pulse_rate, respiratory_rate, temperature, assessment_notes, created_by) VALUES (?, 'medical', NULL, NULL, NULL, NULL, NULL, NULL, ?, ?)";
    $stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param($stmt, "isi", $patient_id, $assessment, $admin_user_id);
    // Note: bind types: i (user_id), s (assessment), i (created_by)
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (function_exists('log_action') && $admin_user_id) {
        log_action($admin_user_id, 'ADMIN_INTERVIEW', "Added interview note for patient_id={$patient_id}");
    }

    return $ok;
}