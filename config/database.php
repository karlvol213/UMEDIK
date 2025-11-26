<?php
/**
 * PDO-based Database singleton
 *
 * Provides a single PDO connection across the application. Uses environment
 * variables when available, with sensible defaults for local development.
 *
 * Backwards compatibility: a global $pdo variable is exported so existing
 * files that expect a $conn or $pdo can be updated gradually. Prefer using
 * Database::getInstance() directly in new code.
 */

// Load .env file if it exists
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Remove quotes if present
            $value = trim($value, '"\'');
            // Set in $_ENV and $_SERVER
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            // Also set as actual environment variable
            putenv("$key=$value");
        }
    }
}

// Gather DB config from environment or server globals with sensible defaults
$DB_HOST = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? '127.0.0.1';
$DB_NAME = $_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? 'medical_appointment_db';
$DB_PORT = $_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? 3306;
$DB_USER = $_ENV['DB_USERNAME'] ?? $_SERVER['DB_USERNAME'] ?? 'root';
$DB_PASS = $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? '';

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        // Use environment variables if set, otherwise fallback to local defaults
    // Read from the same env/server fallbacks used above so values are consistent
    $host = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? '127.0.0.1';
    $db   = $_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? 'medical_appointment_db';
    $port = $_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? 3306;
    $user = $_ENV['DB_USERNAME'] ?? $_SERVER['DB_USERNAME'] ?? 'root';
    $pass = $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? '';

        $dsn = "mysql:host=$host;dbname=$db;port=$port;charset=utf8mb4";

        try {
            $this->connection = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            // Provide a clearer, actionable message when PDO MySQL driver is missing.
            $msg = $e->getMessage();
            if (stripos($msg, 'could not find driver') !== false || stripos($msg, 'driver not found') !== false) {
                // Friendly instructions for common XAMPP/Windows setups
                $help = "PDO MySQL driver not found. Please enable the pdo_mysql extension in your PHP configuration and restart Apache/XAMPP.\n" .
                        "On Windows with XAMPP: edit your php.ini (e.g. C:\\xampp\\php\\php.ini), uncomment or add the line:\n" .
                        "    extension=pdo_mysql\n" .
                        "Then restart Apache from the XAMPP control panel. After restarting, refresh this page.\n" .
                        "If you're running PHP-FPM or another setup, ensure the pdo_mysql extension is enabled for the PHP SAPI used by your web server.\n";
                // Print a readable HTML message when accessed in a browser.
                if (php_sapi_name() !== 'cli') {
                    echo '<h2>Database connection failed: PDO MySQL driver not found</h2>';
                    echo '<pre>' . htmlspecialchars($help) . '</pre>';
                    exit;
                }
                // For CLI, show plain text
                die("Database connection failed: PDO MySQL driver not found.\n" . $help);
            }
            // Other PDO errors: rethrow or show the message
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->connection;
    }
}

// Export a global $pdo for compatibility with newer code. Use Database::getInstance()
// directly in new code. Also create a mysqli $conn for backwards compatibility
// with existing files that call mysqli_* or expect $conn->... methods.
if (!isset($pdo)) {
    $pdo = Database::getInstance();
}

// Create mysqli connection for legacy code expecting $conn
if (!isset($conn)) {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, (int)$DB_PORT);

    if ($conn->connect_error) {
        // If mysqli fails but PDO succeeded, prefer not to die; log and continue.
        // However, keep the original behavior of stopping on fatal DB errors.
        die("MySQLi connection failed: " . $conn->connect_error);
    }

    // set charset to utf8mb4 for proper encoding support
    $conn->set_charset('utf8mb4');
}

// Export the selected database name for other scripts that query information_schema
if (!isset($dbname)) {
    $dbname = $DB_NAME;
}

?>