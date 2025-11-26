<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../models/Biometric.php';
require_once '../models/PatientHistoryRecord.php';
require_once '../models/Appointment.php';

$pdo = Database::getInstance();

// Only admins can archive/delete records
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(response_code: 405);
    echo "Method not allowed";
    exit();
}

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0; // for redirect
$type = $_POST['type'] ?? '';
$archived_any = false;
$admin_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

// Helper to ensure archive table exists (create LIKE)
function ensure_archive_table($pdo, $sourceTable, $archiveTable) {
    $stmt = $pdo->prepare("SHOW TABLES LIKE :t");
    $stmt->execute([':t' => $archiveTable]);
    if ($stmt->fetch()) return true;
    $sql = "CREATE TABLE `$archiveTable` LIKE `$sourceTable`";
    $pdo->exec($sql);
    return true;
}

// Helper: get column list for a table
function get_table_columns($pdo, $table, $schema) {
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = :db AND table_name = :tbl ORDER BY ORDINAL_POSITION");
    $stmt->execute([':db' => $schema, ':tbl' => $table]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}

// Helper: return columns common to source and archive (fallback to source columns)
function get_common_columns($pdo, $sourceTable, $archiveTable, $schema) {
    $src = get_table_columns($pdo, $sourceTable, $schema);
    $arch = get_table_columns($pdo, $archiveTable, $schema);
    $common = array_values(array_intersect($src, $arch));
    if (empty($common)) $common = $src;
    return $common;
}

// helper to perform an archive of one row using common columns
function archive_row($pdo, $sourceTable, $archiveTable, $idCol, $idVal, $schema, $dryRun = false) {
    $cols = get_common_columns($pdo, $sourceTable, $archiveTable, $schema);
    if (empty($cols)) return false;
    $colList = implode(',', array_map(function($c){ return "`$c`"; }, $cols));
    $selList = implode(',', array_map(function($c){ return "`$c`"; }, $cols));
    $sql = "INSERT INTO `$archiveTable` ($colList) SELECT $selList FROM `$sourceTable` WHERE `$idCol` = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $idVal]);
    if ($dryRun) {
        // rollback what we did
        return true;
    }
    // delete original row
    $del = $pdo->prepare("DELETE FROM `$sourceTable` WHERE `$idCol` = :id");
    return $del->execute([':id' => $idVal]);
}
$dry = !empty($_POST['dry_run']) || (!empty($_GET['dry']) && $_GET['dry'] == '1');

if ($type === 'biometric') {
    $bid = isset($_POST['biometric_id']) ? (int)$_POST['biometric_id'] : 0;
    if ($bid > 0) {
        ensure_archive_table($pdo, 'biometrics', 'biometrics_archive');
        if ($dry) {
            // dry-run: report existence
            $exists = $pdo->prepare('SELECT COUNT(*) FROM biometrics WHERE id = :id');
            $exists->execute([':id'=>$bid]);
            $count = (int)$exists->fetchColumn();
            if ($count > 0) $archived_any = true;
        } else {
            $pdo->beginTransaction();
            $ok = archive_row($pdo, 'biometrics', 'biometrics_archive', 'id', $bid, $GLOBALS['dbname'], false);
            if ($ok) {
                $pdo->commit();
                log_action($admin_id, 'ARCHIVE_BIOMETRIC', "Archived biometric id {$bid} for user_id {$user_id}");
                $archived_any = true;
            } else {
                $pdo->rollBack();
            }
        }
    }
} elseif ($type === 'history') {
    $hid = isset($_POST['history_id']) ? (int)$_POST['history_id'] : 0;
    if ($hid > 0) {
        ensure_archive_table($pdo, 'patient_history_records', 'patient_history_archive');
        if ($dry) {
            $exists = $pdo->prepare('SELECT COUNT(*) FROM patient_history_records WHERE id = :id');
            $exists->execute([':id'=>$hid]);
            if ((int)$exists->fetchColumn() > 0) $archived_any = true;
        } else {
            $pdo->beginTransaction();
            $ok = archive_row($pdo, 'patient_history_records', 'patient_history_archive', 'id', $hid, $GLOBALS['dbname'], false);
            if ($ok) {
                $pdo->commit();
                log_action($admin_id, 'ARCHIVE_HISTORY', "Archived history id {$hid} for user_id {$user_id}");
                $archived_any = true;
            } else {
                $pdo->rollBack();
            }
        }
    }
} elseif ($type === 'combined') {
    $bid = isset($_POST['biometric_id']) ? (int)$_POST['biometric_id'] : 0;
    $hid = isset($_POST['history_id']) ? (int)$_POST['history_id'] : 0;

    if ($bid > 0) {
        ensure_archive_table($pdo, 'biometrics', 'biometrics_archive');
        if ($dry) {
            $exists = $pdo->prepare('SELECT COUNT(*) FROM biometrics WHERE id = :id');
            $exists->execute([':id'=>$bid]);
            if ((int)$exists->fetchColumn() > 0) $archived_any = true;
        } else {
            $pdo->beginTransaction();
            $ok = archive_row($pdo, 'biometrics', 'biometrics_archive', 'id', $bid, $GLOBALS['dbname'], false);
            if ($ok) {
                log_action($admin_id, 'ARCHIVE_BIOMETRIC', "Archived biometric id {$bid} for user_id {$user_id}");
                $archived_any = true;
            } else {
                $pdo->rollBack();
            }
        }
    }

    if ($hid > 0) {
        ensure_archive_table($pdo, 'patient_history_records', 'patient_history_archive');
        if ($dry) {
            $exists = $pdo->prepare('SELECT COUNT(*) FROM patient_history_records WHERE id = :id');
            $exists->execute([':id'=>$hid]);
            if ((int)$exists->fetchColumn() > 0) $archived_any = true;
        } else {
            $pdo->beginTransaction();
            $ok = archive_row($pdo, 'patient_history_records', 'patient_history_archive', 'id', $hid, $GLOBALS['dbname'], false);
            if ($ok) {
                log_action($admin_id, 'ARCHIVE_HISTORY', "Archived history id {$hid} for user_id {$user_id}");
                $archived_any = true;
            } else {
                $pdo->rollBack();
            }
        }
    }
}

$redirect = 'patient_history_details.php?user_id=' . $user_id;
if ($archived_any) {
    header('Location: ' . $redirect . '&msg=archived');
    exit();
} else {
    header('Location: ' . $redirect . '&err=failed');
    exit();
}

?>
