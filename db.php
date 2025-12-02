<?php
$host = 'localhost';      // e.g., containers-us-west-123.railway.app
$db   = 'medical_appointment_db';      // e.g., railway
$user = 'root';      // e.g., root
$pass = '';  // copy from Railway
$port = 3306;                         // usually 3306

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Database connected successfully!";
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}