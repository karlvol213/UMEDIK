<?php
/**
 * Migration: Create pending_biometric_notes table
 * Purpose: Persist pending biometric records that haven't had clinical notes recorded yet
 * This allows patient cards to survive logout/login cycles
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from command line.\n");
}

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = Database::getInstance();
    
    $sql = "CREATE TABLE IF NOT EXISTS pending_biometric_notes (
        id INT PRIMARY KEY AUTO_INCREMENT,
        biometric_id INT NOT NULL UNIQUE,
        user_id INT NOT NULL,
        recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_by_admin_id INT,
        is_processed BOOLEAN DEFAULT FALSE,
        notes_recorded_at DATETIME,
        FOREIGN KEY (biometric_id) REFERENCES biometrics(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by_admin_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX (user_id),
        INDEX (recorded_at),
        INDEX (is_processed)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    
    echo "✓ Migration successful: pending_biometric_notes table created.\n";
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "✓ Migration skipped: pending_biometric_notes table already exists.\n";
    } else {
        echo "✗ Migration failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>
