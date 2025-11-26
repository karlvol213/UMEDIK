<?php
require_once 'database.php';


$create_table_sql = "CREATE TABLE IF NOT EXISTS history_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action_type VARCHAR(50) NOT NULL,
    action_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)";

if (mysqli_query($conn, $create_table_sql)) {
    echo "History logs table created or already exists successfully!";
} else {
    echo "Error creating history logs table: " . mysqli_error($conn);
}
?>