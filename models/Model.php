<?php
// Base model providing PDO instance access
require_once __DIR__ . '/../config/database.php';

abstract class Model
{
    protected static $pdo = null;

    protected static function pdo()
    {
        if (self::$pdo === null) {
            self::$pdo = Database::getInstance();
        }
        return self::$pdo;
    }
}

?>
