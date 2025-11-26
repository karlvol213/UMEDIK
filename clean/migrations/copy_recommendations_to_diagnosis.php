<?php
// copy_recommendations_to_diagnosis.php
// Safe migration: copy non-empty doctors_recommendations into diagnosis where diagnosis is empty.
// Usage (web):  migrations/copy_recommendations_to_diagnosis.php?confirm=yes
// Usage (CLI): php migrations/copy_recommendations_to_diagnosis.php confirm=yes

require_once __DIR__ . '/../config/database.php';

// Confirm param required to run to avoid accidental execution
$confirm = null;
if (php_sapi_name() === 'cli') {
    // parse CLI args
    foreach ($argv as $a) {
        if (strpos($a, 'confirm=') === 0) { $confirm = substr($a, 8); }
    }
} else {
    $confirm = isset($_GET['confirm']) ? $_GET['confirm'] : null;
}

if ($confirm !== 'yes') {
    echo "This script will permanently copy `doctors_recommendations` into `diagnosis` for records where diagnosis is empty\n";
    echo "To actually run it, call with confirm=yes (web or CLI):\n";
    echo "  php " . __FILE__ . " confirm=yes\n";
    echo "  OR via browser: http://your-host/migrations/copy_recommendations_to_diagnosis.php?confirm=yes\n";
    // show how many rows would be affected
    $check_sql = "SELECT COUNT(*) AS c FROM patient_history_records WHERE (diagnosis IS NULL OR TRIM(diagnosis) = '') AND TRIM(COALESCE(doctors_recommendations, '')) <> ''";
    $res = mysqli_query($conn, $check_sql);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    $count = $row ? (int)$row['c'] : 0;
    echo "\nRows that would be updated: {$count}\n";
    exit;
}

// Start migration
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn->begin_transaction();

    // create backup table if not exists
    $create_backup_sql = "CREATE TABLE IF NOT EXISTS patient_history_records_backup_before_diag_copy LIKE patient_history_records";
    mysqli_query($conn, $create_backup_sql);

    // insert affected rows into backup table (only new records to avoid duplicates)
    $insert_backup_sql = "INSERT INTO patient_history_records_backup_before_diag_copy
        SELECT * FROM patient_history_records
        WHERE (diagnosis IS NULL OR TRIM(diagnosis) = '') AND TRIM(COALESCE(doctors_recommendations, '')) <> ''";
    $insert_res = mysqli_query($conn, $insert_backup_sql);
    $backup_count = $insert_res ? mysqli_affected_rows($conn) : 0;

    // perform the update
    $update_sql = "UPDATE patient_history_records
        SET diagnosis = doctors_recommendations
        WHERE (diagnosis IS NULL OR TRIM(diagnosis) = '') AND TRIM(COALESCE(doctors_recommendations, '')) <> ''";
    mysqli_query($conn, $update_sql);
    $updated = mysqli_affected_rows($conn);

    $conn->commit();

    echo "Backup rows inserted: {$backup_count}\n";
    echo "Rows updated: {$updated}\n";
    echo "Migration complete. A backup table `patient_history_records_backup_before_diag_copy` was created.\n";

} catch (Exception $e) {
    $conn->rollback();
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

?>