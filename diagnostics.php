<?php
/**
 * Diagnostics page for Railway deployment
 * Shows environment and database status
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Diagnostics</h1>";
echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>";

// Check PHP version
echo "PHP Version: " . phpversion() . "\n";

// Check environment variables
echo "\n=== Database Environment Variables ===\n";
echo "DB_HOST: " . (getenv('DB_HOST') ?: 'NOT SET (will use 127.0.0.1)') . "\n";
echo "DB_PORT: " . (getenv('DB_PORT') ?: 'NOT SET (will use 3306)') . "\n";
echo "DB_DATABASE: " . (getenv('DB_DATABASE') ?: 'NOT SET (will use medical_appointment_db)') . "\n";
echo "DB_USERNAME: " . (getenv('DB_USERNAME') ?: 'NOT SET (will use root)') . "\n";
echo "DB_PASSWORD: " . (getenv('DB_PASSWORD') ? '***SET***' : 'NOT SET (will use empty)') . "\n";

$platformUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: getenv('MYSQL_DATABASE_URL');
echo "DATABASE_URL: " . ($platformUrl ? '***SET***' : 'NOT SET') . "\n";

// Try to connect to database
echo "\n=== Database Connection Test ===\n";
try {
    require_once 'config/database.php';
    
    $pdo = Database::getInstance();
    $result = $pdo->query("SELECT 1");
    
    if ($result) {
        echo "✅ Database connection SUCCESSFUL\n";
        
        // Check tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll();
        echo "Tables found: " . count($tables) . "\n";
        foreach ($tables as $table) {
            echo "  - " . $table[0] . "\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Database connection FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
}

// Check file permissions
echo "\n=== File Permissions ===\n";
echo "config/: " . (is_writable('config/') ? '✅ writable' : '❌ not writable') . "\n";
echo "logs/: " . (is_writable('logs/') ? '✅ writable' : '❌ not writable') . "\n";

// Check required files
echo "\n=== Required Files ===\n";
$required_files = [
    'config/database.php',
    'config/functions.php',
    'config/admin_access.php',
];
foreach ($required_files as $file) {
    echo "$file: " . (file_exists($file) ? '✅ exists' : '❌ NOT FOUND') . "\n";
}

echo "</pre>";
?>
