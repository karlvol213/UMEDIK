<?php
/**
 * Database Connection Test
 * Tests both PDO and MySQLi connections
 */

require_once 'config/database.php';

echo "=== Database Connection Test ===\n\n";

// Test PDO Connection
echo "1. PDO Connection Test:\n";
try {
    $pdo = Database::getInstance();
    $result = $pdo->query('SELECT 1');
    $result->fetch();
    echo "   ✅ PDO Connection: OK\n";
} catch (Exception $e) {
    echo "   ❌ PDO Connection: FAILED\n";
    echo "   Error: " . $e->getMessage() . "\n";
}

// Test MySQLi Connection
echo "\n2. MySQLi Connection Test:\n";
if (isset($conn)) {
    if ($conn->connect_error) {
        echo "   ❌ MySQLi Connection: FAILED\n";
        echo "   Error: " . $conn->connect_error . "\n";
    } else {
        $result = $conn->query('SELECT 1');
        if ($result) {
            echo "   ✅ MySQLi Connection: OK\n";
        } else {
            echo "   ❌ MySQLi Connection: FAILED\n";
            echo "   Error: " . $conn->error . "\n";
        }
    }
} else {
    echo "   ⚠️  MySQLi connection not initialized\n";
}

// Test Database Accessibility
echo "\n3. Database Accessibility Test:\n";
try {
    $pdo = Database::getInstance();
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM users');
    $row = $stmt->fetch();
    echo "   ✅ Users table accessible\n";
    echo "   Total users: " . $row['count'] . "\n";
} catch (Exception $e) {
    echo "   ❌ Users table not accessible\n";
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
?>
