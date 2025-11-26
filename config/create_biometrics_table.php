<?php
require_once 'database.php';

$sql = "CREATE TABLE IF NOT EXISTS biometrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    height DECIMAL(5,2) NOT NULL,  -- in centimeters
    weight DECIMAL(5,2) NOT NULL,  -- in kilograms
    blood_pressure VARCHAR(20) NOT NULL,  -- e.g., '120/80'
    temperature DECIMAL(4,2) NOT NULL,  -- in Celsius
    pulse_rate INT NOT NULL,  -- beats per minute
    record_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if (mysqli_query($conn, $sql)) {
    echo "Biometrics table created successfully!";
} else {
    echo "Error creating biometrics table: " . mysqli_error($conn);
}

mysqli_close($conn);
?>