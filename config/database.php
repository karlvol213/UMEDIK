<?php
/**
 * PDO-based Database singleton — ready for Railway
 * Automatically uses Railway environment variables or falls back to local defaults.
 */

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        // Environment-aware DB config
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: 3306;
        $db   = getenv('DB_DATABASE') ?: 'medical_appointment_db';
        $user = getenv('DB_USERNAME') ?: 'root';
        $pass = getenv('DB_PASSWORD') ?: '';

        // Support for DATABASE_URL / MYSQL_URL (Railway style)
        $platformUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: getenv('MYSQL_DATABASE_URL');
        if ($platformUrl) {
            $parts = parse_url($platformUrl);
            if ($parts !== false) {
                $host = $parts['host'] ?? $host;
                $port = $parts['port'] ?? $port;
                $user = $parts['user'] ?? $user;
                $pass = $parts['pass'] ?? $pass;
                if (!empty($parts['path'])) {
                    $db = ltrim($parts['path'], '/');
                }
            }
        }

        // PDO connection string
        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

        // Retry loop — sometimes the DB service isn't ready yet (platform race condition)
        $attempts = 0;
        $maxAttempts = 10;
        $lastException = null;

        while ($attempts < $maxAttempts) {
            try {
                $this->connection = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT => false,
                ]);
                break; // success
            } catch (PDOException $e) {
                $lastException = $e;
                $attempts++;
                error_log(sprintf(
                    "Database connection attempt %d/%d failed to %s (user=%s) — %s",
                    $attempts,
                    $maxAttempts,
                    "$host:$port/$db",
                    $user,
                    $e->getMessage()
                ));
                // If we've exhausted retries, break and handle failure
                if ($attempts >= $maxAttempts) {
                    break;
                }
                // Wait a bit before retrying
                sleep(2);
            }
        }

        if (!$this->connection) {
            $errMsg = $lastException ? $lastException->getMessage() : 'unknown error';
            $message = "❌ Database connection failed after $maxAttempts attempts to $host:$port/$db: $errMsg";
            error_log($message);
            die($message);
        }
    }

    // Singleton getter
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->connection;
    }
}

// Optional global variable for legacy code
$pdo = Database::getInstance();