<?php
/**
 * Database Initialization Handler
 * Automatically creates tables on first deployment
 * Called before each request
 */

// Check if initialization is needed
$init_file = '/var/www/html/.initialized';

// Only run once per container
if (file_exists($init_file)) {
    return;
}

try {
    require_once __DIR__ . '/config/database.php';
    
    $pdo = Database::getInstance();
    
    // Check if users table exists
    $result = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($result->rowCount() === 0) {
        // Read and execute database setup
        $setup_sql = file_get_contents(__DIR__ . '/database_setup.sql');
        
        // Split by semicolon and execute each statement
        $statements = array_filter(
            array_map('trim', explode(';', $setup_sql)),
            function($stmt) { return !empty($stmt); }
        );
        
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
        
        error_log("✅ Database tables initialized successfully");
    }
    
    // Mark initialization as complete
    touch($init_file);
    
} catch (Exception $e) {
    error_log("⚠️  Database initialization warning: " . $e->getMessage());
    // Don't fail the app if initialization has an issue
}
