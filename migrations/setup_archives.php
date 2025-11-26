<?php
// Run this script to set up the appointment archives database tables
require_once __DIR__ . '/../config/database.php';

try {
    // Read and execute the SQL file
    $sql = file_get_contents(__DIR__ . '/create_appointment_archives.sql');
    
    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $conn->query($statement);
        }
    }
    
    echo "✅ Successfully created appointment archives tables!\n";
} catch (Exception $e) {
    echo "❌ Error creating tables: " . $e->getMessage() . "\n";
}