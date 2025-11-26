<?php
/**
 * Migration: Create biometric identity tables
 * - biometric_templates: store encrypted templates (fingerprint, face, iris)
 * - biometric_events: track enrollment, verification, and vitals capture events
 * - biometric_audit_logs: detailed audit of template access and operations
 */

require_once __DIR__ . '/../config/database.php';

// Read the SQL file
$sql = file_get_contents(__DIR__ . '/create_biometric_tables.sql');

// Split into individual statements (on semicolon followed by newline)
$statements = array_filter(
    array_map('trim', explode(";\n", $sql)),
    function($stmt) { return !empty($stmt) && strpos($stmt, '--') !== 0; }
);

// Execute each statement
$success = true;
foreach ($statements as $statement) {
    if (!mysqli_query($conn, $statement)) {
        $success = false;
        error_log("Error executing statement:\n" . $statement . "\n\nError: " . mysqli_error($conn));
        echo "Error: " . mysqli_error($conn) . "\n";
        // Option: break here to stop on first error, or continue to try remaining statements
        break;
    }
}

if ($success) {
    echo "Successfully created biometric tables.\n";
    
    // Add an initial event to mark the tables' creation
    $event_data = json_encode([
        'migration' => 'create_biometric_tables',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    mysqli_query($conn, "
        INSERT INTO biometric_events 
        (event_type, event_data, actor_id) 
        VALUES ('ENROLL', '$event_data', NULL)
    ");
    
    // Log the migration in audit_logs too
    mysqli_query($conn, "
        INSERT INTO biometric_audit_logs 
        (action, outcome, details) 
        VALUES ('IMPORT', 'SUCCESS', 'Initial biometric tables creation')
    ");
} else {
    echo "Error creating biometric tables. Check the error log for details.\n";
}

?>