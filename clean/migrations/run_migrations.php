<?php
// Simple migration runner for local dev. Safe and idempotent.
require_once __DIR__ . '/../config/database.php';

function ensure_column_exists($conn, $dbname, $table, $column, $definition) {
    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $dbname, $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res->num_rows > 0;
    $stmt->close();

    if ($exists) {
        echo "Column {$column} already exists on {$table}.\n";
        return true;
    }

    $alter = "ALTER TABLE `{$table}` ADD COLUMN {$definition}";
    if ($conn->query($alter) === TRUE) {
        echo "Added column {$column} to {$table}.\n";
        return true;
    } else {
        echo "Failed to add column {$column} to {$table}: " . $conn->error . "\n";
        return false;
    }
}

$db = $dbname ?? 'medical_appointment_db';
$ok = true;

// Ensure special_status
$ok = $ok && ensure_column_exists($conn, $db, 'users', 'special_status', "`special_status` VARCHAR(20) NOT NULL DEFAULT 'none'");

// Ensure patient_id on patient_history_records (some installs used user_id instead)
$ok = $ok && ensure_column_exists($conn, $db, 'patient_history_records', 'patient_id', "`patient_id` INT DEFAULT NULL");

// Ensure doctors_recommendations on patient_history_records
$ok = $ok && ensure_column_exists($conn, $db, 'patient_history_records', 'doctors_recommendations', "`doctors_recommendations` TEXT NULL");

// Print summary
if ($ok) {
    echo "All migrations applied or already present.\n";
    exit(0);
} else {
    echo "One or more migrations failed. See messages above.\n";
    exit(1);
}

?>
