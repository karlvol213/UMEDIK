<?php
require_once 'config/database.php';


$sql = "CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    service_type VARCHAR(255),
    comments TEXT,
    status ENUM('requested', 'approved', 'completed', 'cancelled') DEFAULT 'requested',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";


if ($conn->query($sql) === TRUE) {
    echo "Appointments table created successfully or already exists.";
} else {
    echo "Error creating table: " . $conn->error;
}


$conn->close();
?>