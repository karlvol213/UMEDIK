<?php
/**
 * Web Migration Runner: Create pending_biometric_notes table
 * Access: http://localhost/project_HCI/clean/migrations/run_pending_notes_migration_web.php
 * 
 * This table persists pending biometric records that haven't had clinical notes recorded yet,
 * allowing patient cards to survive logout/login cycles.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/admin_access.php';

// Simple auth check - only allow admin access
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true) {
    http_response_code(403);
    die("Access denied. Admin authentication required.");
}

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
    
    $message = "✓ Migration successful: pending_biometric_notes table created.";
    $success = true;
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        $message = "✓ Migration skipped: pending_biometric_notes table already exists.";
        $success = true;
    } else {
        $message = "✗ Migration failed: " . $e->getMessage();
        $success = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white rounded-lg shadow-lg p-8 max-w-md w-full">
        <h1 class="text-2xl font-bold text-gray-900 mb-4">Database Migration</h1>
        <div class="<?php echo $success ? 'bg-green-50 border-l-4 border-green-600' : 'bg-red-50 border-l-4 border-red-600'; ?> p-4 rounded">
            <p class="<?php echo $success ? 'text-green-900' : 'text-red-900'; ?> font-medium"><?php echo $message; ?></p>
        </div>
        <div class="mt-6 text-sm text-gray-600">
            <p><strong>What changed:</strong></p>
            <ul class="list-disc list-inside mt-2 space-y-1">
                <li>Created <code>pending_biometric_notes</code> table</li>
                <li>Biometric records now persist across logout/login</li>
                <li>Patient cards will remain visible until clinical notes are recorded</li>
            </ul>
        </div>
        <div class="mt-6">
            <a href="../admin/info_admin.php" class="inline-block px-4 py-2 bg-blue-900 text-white rounded-lg hover:bg-blue-950 font-medium">Return to Biometrics</a>
        </div>
    </div>
</body>
</html>
