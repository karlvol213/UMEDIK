<?php
/**
 * Migration: Create Archive Tables
 * Sets up tables for archiving old appointments, biometrics, and patient history
 */

require_once __DIR__ . '/../config/database.php';

// Read the SQL migration file
$sql_file = __DIR__ . '/create_appointment_archives.sql';

if (!file_exists($sql_file)) {
    die("Error: create_appointment_archives.sql not found at $sql_file");
}

$sql = file_get_contents($sql_file);

// Split into individual statements (by semicolon)
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    function($stmt) { 
        return !empty($stmt) && strpos($stmt, '--') !== 0; 
    }
);

// Execute each statement
$success = true;
$errors = [];

foreach ($statements as $statement) {
    if (!empty(trim($statement))) {
        if (!mysqli_query($conn, $statement)) {
            $success = false;
            $errors[] = mysqli_error($conn);
            error_log("Migration error: " . mysqli_error($conn));
        }
    }
}

if ($success) {
    echo "✅ Successfully created archive tables!\n";
    echo "The following tables were created:\n";
    echo "  - appointments_archive\n";
    echo "  - appointment_archive_services\n";
    echo "  - biometrics_archive\n";
    echo "  - patient_history_archive\n";
} else {
    echo "❌ Errors occurred during migration:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

mysqli_close($conn);

?>
