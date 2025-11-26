<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/patient_functions.php';


function get_user_by_email($email) {
    global $conn;
    
    // include login security related fields
    $stmt = $conn->prepare("SELECT *, failed_login_count, last_failed_login, locked_until, is_locked FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    $stmt->close();
    return $user;
}


// Update failed login metadata for a user
function record_failed_login($user_id) {
    global $conn;
    $now = date('Y-m-d H:i:s');

    // Increment failed_login_count and update last_failed_login
    $stmt = $conn->prepare("UPDATE users SET failed_login_count = failed_login_count + 1, last_failed_login = ? WHERE id = ?");
    $stmt->bind_param('si', $now, $user_id);
    $stmt->execute();
    $stmt->close();

    // Fetch the updated count
    $s = $conn->prepare("SELECT failed_login_count FROM users WHERE id = ?");
    $s->bind_param('i', $user_id);
    $s->execute();
    $res = $s->get_result();
    $row = $res->fetch_assoc();
    $s->close();

    $count = isset($row['failed_login_count']) ? (int)$row['failed_login_count'] : 0;

    // If reached 3, set a temporary lock for 3 minutes
    if ($count >= 3 && $count < 5) {
        $locked_until = date('Y-m-d H:i:s', strtotime('+3 minutes'));
        $u = $conn->prepare("UPDATE users SET locked_until = ? WHERE id = ?");
        $u->bind_param('si', $locked_until, $user_id);
        $u->execute();
        $u->close();
    }

    // If reached 5 or more, mark account locked until admin unlocks
    if ($count >= 5) {
        $u = $conn->prepare("UPDATE users SET is_locked = 1, locked_until = NULL WHERE id = ?");
        $u->bind_param('i', $user_id);
        $u->execute();
        $u->close();
    }
}

function reset_failed_login($user_id) {
    global $conn;
    $stmt = $conn->prepare("UPDATE users SET failed_login_count = 0, last_failed_login = NULL, locked_until = NULL, is_locked = 0 WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
}

function is_user_locked($user) {
    // Accept either array (user row) or id
    global $conn;
    if (is_array($user)) {
        $row = $user;
        // if explicitly marked locked
        if (!empty($row['is_locked'])) return true;
        if (!empty($row['locked_until'])) {
            return strtotime($row['locked_until']) > time();
        }
        return false;
    } else {
        $id = (int)$user;
        $s = $conn->prepare("SELECT is_locked, locked_until FROM users WHERE id = ?");
        $s->bind_param('i', $id);
        $s->execute();
        $res = $s->get_result();
        $r = $res->fetch_assoc();
        $s->close();
        if (!$r) return false;
        if (!empty($r['is_locked'])) return true;
        if (!empty($r['locked_until'])) {
            return strtotime($r['locked_until']) > time();
        }
        return false;
    }
}

function create_user($email, $password, $first_name, $last_name, $middle_name, $phone, $department, $birthday, $age, $sex, $address = '', $special_status = 'none') {
    global $conn;
    
    $existing_user = get_user_by_email($email);
    if ($existing_user) {
        return false;
    }
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $role = 'patient';
    
    $stmt = $conn->prepare("INSERT INTO users (
        email, password, first_name, last_name, middle_name, 
        phone, role, department, birthday, 
        age, sex, address, full_name, special_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $full_name = $first_name . ' ' . ($middle_name ? $middle_name . ' ' : '') . $last_name;
    
    $stmt->bind_param("ssssssssssssss", 
        $email, $hashed_password, $first_name, $last_name, $middle_name,
        $phone, $role, $department, $birthday,
        $age, $sex, $address, $full_name, $special_status
    );
    
    if ($stmt->execute()) {
        $stmt->close();
        return true;
    } else {
        $stmt->close();
        return false;
    }
}


function create_appointment($user_id, $date, $time, $services, $comment) {
    global $conn;
    
    
    $conn->begin_transaction();
    
    try {
        
        $status = 'requested';
        $stmt = $conn->prepare("INSERT INTO appointments (user_id, appointment_date, appointment_time, status, comment) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $date, $time, $status, $comment);
        $stmt->execute();
        
        $appointment_id = $conn->insert_id;
        $stmt->close();
        
        
        $stmt = $conn->prepare("INSERT INTO appointment_services (appointment_id, service_id) VALUES (?, ?)");
        foreach ($services as $service_id) {
            $stmt->bind_param("ii", $appointment_id, $service_id);
            $stmt->execute();
        }
        $stmt->close();
        
        
        $conn->commit();
        
        
        log_action($user_id, "Created new appointment", "Appointment ID: " . $appointment_id);
        
        return $appointment_id;
    } catch (Exception $e) {
        
        $conn->rollback();
        return false;
    }
}

function get_user_appointments($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT a.*, u.full_name, GROUP_CONCAT(s.name SEPARATOR ', ') as service_names
        FROM appointments a 
        JOIN users u ON a.user_id = u.id 
        LEFT JOIN appointment_services aps ON a.id = aps.appointment_id
        LEFT JOIN services s ON aps.service_id = s.id
        WHERE a.user_id = ?
        GROUP BY a.id
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $appointments = [];
    
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    
    $stmt->close();
    return $appointments;
}

function update_patient_history_from_biometrics($user_id, $height, $weight, $blood_pressure, $temperature, $pulse_rate, $record_date) {
    global $conn;
    global $dbname;
    
    // Create patient_history_records table if it doesn't exist
    $check_table_query = "SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = 'patient_history_records' LIMIT 1";
    $stmt = mysqli_prepare($conn, $check_table_query);
    mysqli_stmt_bind_param($stmt, "s", $dbname);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) == 0) {
        $create_table_sql = "CREATE TABLE IF NOT EXISTS patient_history_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT,
            visit_date DATE NOT NULL,
            symptoms TEXT,
            diagnosis TEXT,
            treatment TEXT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE SET NULL
        )";
        mysqli_query($conn, $create_table_sql);
    }

    // Format biometric data for notes
    $biometric_notes = sprintf(
        "Biometric Record:\nHeight: %.2f cm\nWeight: %.2f kg\nBlood Pressure: %s\nTemperature: %.1f°C\nPulse Rate: %d bpm",
        $height, $weight, $blood_pressure, $temperature, $pulse_rate
    );

    // Insert new history record using the actual columns in patient_history_records
    // Columns present: user_id, record_type, height, weight, blood_pressure, pulse_rate, respiratory_rate, temperature, assessment_notes, created_by
    $insert_sql = "INSERT INTO patient_history_records (user_id, record_type, height, weight, blood_pressure, pulse_rate, temperature, assessment_notes, created_by) VALUES (?, 'medical', ?, ?, ?, ?, ?, ?, NULL)";
    $stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param($stmt, "iddsids", $user_id, $height, $weight, $blood_pressure, $pulse_rate, $temperature, $biometric_notes);
    return mysqli_stmt_execute($stmt);
}

function get_all_appointments() {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT a.*, u.full_name, GROUP_CONCAT(s.name SEPARATOR ', ') as service_names
        FROM appointments a 
        JOIN users u ON a.user_id = u.id 
        LEFT JOIN appointment_services aps ON a.id = aps.appointment_id
        LEFT JOIN services s ON aps.service_id = s.id
        GROUP BY a.id
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute();
    
    $result = $stmt->get_result();
    $appointments = [];
    
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    
    $stmt->close();
    return $appointments;
}

function update_appointment_status($appointment_id, $status) {
    global $conn;
    
    
    $conn->begin_transaction();
    
    try {
        
        $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $appointment_id);
        $stmt->execute();

        // If appointment completed, archive it and remove from live table
        if ($status === 'completed') {
            // create archive table if it doesn't exist
            $create_sql = "CREATE TABLE IF NOT EXISTS appointments_archive (
                id INT AUTO_INCREMENT PRIMARY KEY,
                original_id INT NOT NULL,
                user_id INT,
                appointment_date DATE,
                appointment_time TIME,
                status VARCHAR(50),
                comment TEXT,
                services TEXT,
                archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                archived_by INT DEFAULT NULL
            )";
            mysqli_query($conn, $create_sql);

            // fetch appointment row and services
            $s = $conn->prepare("SELECT a.*, u.full_name, GROUP_CONCAT(s.name SEPARATOR ', ') as service_names
                FROM appointments a
                LEFT JOIN appointment_services aps ON a.id = aps.appointment_id
                LEFT JOIN services s ON aps.service_id = s.id
                LEFT JOIN users u ON a.user_id = u.id
                WHERE a.id = ?
                GROUP BY a.id");
            $s->bind_param('i', $appointment_id);
            $s->execute();
            $res = $s->get_result();
            $row = $res->fetch_assoc();
            $s->close();

            if ($row) {
                $ins = $conn->prepare("INSERT INTO appointments_archive (original_id, user_id, appointment_date, appointment_time, status, comment, services, archived_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $arch_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
                $orig_id_v = (int)$row['id'];
                $user_id_v = isset($row['user_id']) ? (int)$row['user_id'] : null;
                $appt_date_v = isset($row['appointment_date']) ? $row['appointment_date'] : null;
                $appt_time_v = isset($row['appointment_time']) ? $row['appointment_time'] : null;
                $status_v = isset($row['status']) ? $row['status'] : null;
                $comment_v = isset($row['comment']) ? $row['comment'] : null;
                $services_v = isset($row['service_names']) ? $row['service_names'] : null;
                if ($ins) {
                    $ins->bind_param('iisssssi', $orig_id_v, $user_id_v, $appt_date_v, $appt_time_v, $status_v, $comment_v, $services_v, $arch_by);
                    $ins->execute();
                    $ins->close();
                } else {
                    // fallback to manual escaped insert
                    $orig_id = (int)$row['id'];
                    $uid = isset($row['user_id']) ? (int)$row['user_id'] : 'NULL';
                    $ad = isset($row['appointment_date']) ? "'" . $conn->real_escape_string($row['appointment_date']) . "'" : 'NULL';
                    $at = isset($row['appointment_time']) ? "'" . $conn->real_escape_string($row['appointment_time']) . "'" : 'NULL';
                    $st = isset($row['status']) ? "'" . $conn->real_escape_string($row['status']) . "'" : 'NULL';
                    $cm = isset($row['comment']) ? "'" . $conn->real_escape_string($row['comment']) . "'" : 'NULL';
                    $sv = isset($row['service_names']) ? "'" . $conn->real_escape_string($row['service_names']) . "'" : 'NULL';
                    $archer = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 'NULL';
                    $ins_sql = "INSERT INTO appointments_archive (original_id, user_id, appointment_date, appointment_time, status, comment, services, archived_by) VALUES ($orig_id, $uid, $ad, $at, $st, $cm, $sv, $archer)";
                    mysqli_query($conn, $ins_sql);
                }

                // delete original appointment
                $del = $conn->prepare("DELETE FROM appointments WHERE id = ?");
                $del->bind_param('i', $appointment_id);
                $del->execute();
                $del->close();
            }
        }

        if ($status === 'approved') {
            
            $stmt = $conn->prepare("SELECT user_id FROM appointments WHERE id = ?");
            $stmt->bind_param("i", $appointment_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $appointment = $result->fetch_assoc();
            
            if ($appointment) {
                
                create_initial_patient_record($appointment['user_id']);
            }
        }
        
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        
        $conn->rollback();
        return false;
    }
}


/**
 * Update appointment time (server-side).
 * Only allow changing time when appointment status is 'requested'.
 * Enforces time between 08:00 and 17:00 (inclusive).
 */
function update_appointment_time($appointment_id, $new_time) {
    global $conn;

    // Basic format check HH:MM
    if (!preg_match('/^\d{2}:\d{2}$/', $new_time)) {
        return false;
    }

    list($hh, $mm) = explode(':', $new_time);
    $hh = (int)$hh;
    $mm = (int)$mm;

    // enforce allowed window 09:00 - 17:00, with 30-min increments
    if ($hh < 9 || $hh > 17) {
        return false;
    }
    
    // Only allow times on the hour or at 30 minutes
    if ($mm !== 0 && $mm !== 30) {
        return false;
    }
    
    // If 17:00, allow it; if 17:30, disallow (outside 5 PM)
    if ($hh === 17 && $mm === 30) {
        return false;
    }

    // ensure appointment exists
    $s = $conn->prepare("SELECT status FROM appointments WHERE id = ?");
    $s->bind_param('i', $appointment_id);
    $s->execute();
    $res = $s->get_result();
    $row = $res->fetch_assoc();
    $s->close();

    if (!$row) return false;
    
    // Allow editing for all statuses (requested, approved, completed)
    // Only disallow for cancelled
    if ($row['status'] === 'cancelled') {
        return false;
    }

    $stmt = $conn->prepare("UPDATE appointments SET appointment_time = ? WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param('si', $new_time, $appointment_id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        // log action if session user available
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        if ($user_id) {
            log_action($user_id, 'Updated appointment time', "Appointment ID: $appointment_id, New Time: $new_time");
        }
    }

    return (bool)$ok;
}


function get_all_services() {
    global $conn;
    
    $result = $conn->query("SELECT * FROM services ORDER BY category, name");
    $services = [];
    
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
    
    return $services;
}


function log_action($user_id, $action, $details = '') {
    global $conn;
    
   
    require_once 'history_log_functions.php';
    ensure_history_logs_table_exists($conn);
    
    
    $stmt = $conn->prepare("INSERT INTO history_logs (user_id, action_type, action_text) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $action, $details);
    $stmt->execute();
    $stmt->close();
}

function get_logs() {
    global $conn;
    
    require_once 'history_log_functions.php';
    return get_all_logs($conn);
}

// Unicode-aware left-trim: remove standard and Unicode separator characters
function ltrim_unicode($s) {
    // remove BOM, NBSP, zero-width and other unicode separator/space/control characters from the start
    // includes: Z (separator), 00A0 (NBSP), FEFF (BOM), 200B/200C/200D (zero-width), 2060 (word-joiner), 00AD (soft hyphen), and C (other, control)
    return preg_replace('/^[\p{Z}\x{00A0}\x{FEFF}\x{200B}\x{200C}\x{200D}\x{2060}\x{00AD}\p{C}\s]+/u', '', (string)$s);
}

// Render clinical text safely for HTML output: trim unicode-leading whitespace, escape, convert newlines to <br>,
// and remove any leading <br> tags that might have resulted from odd stored content.
function render_clinical_html($text) {
    $s = (string)$text;
    // Normalize newlines
    $s = str_replace(["\r\n", "\r"], "\n", $s);

    // Split into lines and remove leading unicode whitespace from each line
    $lines = explode("\n", $s);
    foreach ($lines as &$line) {
        $line = ltrim_unicode($line);
        // also trim trailing spaces to avoid wide gaps from trailing pads
        $line = rtrim($line);
    }
    unset($line);

    // Remove leading/trailing empty lines
    while (count($lines) && trim($lines[0]) === '') array_shift($lines);
    while (count($lines) && trim(end($lines)) === '') array_pop($lines);

    // Collapse multiple blank lines into a single blank line
    $normalized = [];
    $prevEmpty = false;
    foreach ($lines as $ln) {
        $isEmpty = trim($ln) === '';
        if ($isEmpty) {
            if (!$prevEmpty) { $normalized[] = ''; }
            $prevEmpty = true;
        } else {
            $normalized[] = $ln;
            $prevEmpty = false;
        }
    }
    $s = implode("\n", $normalized);

    if ($s === '') return '<em>—</em>';

    // escape then convert newlines
    $escaped = htmlspecialchars($s);
    $br = nl2br($escaped);
    // Remove leading <br> tags and any surrounding whitespace/newlines more aggressively
    $br = preg_replace('/^\s*(?:<br\s*\/?>\s*)*\n*/i', '', $br);
    // Also remove trailing <br> tags
    $br = preg_replace('/(?:\s*<br\s*\/?>\s*)*\n*$/i', '', $br);
    return $br;
}
?>