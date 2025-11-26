<?php
require_once __DIR__ . '/database.php';


$sql = "CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql)) {
    echo "Services table created successfully!\n";
} else {
    echo "Error creating services table: " . $conn->error . "\n";
    exit;
}

// Insert default services
$services = [
    ['Dental', 'Teeth Cleaning'],
    ['Dental', 'Toothache'],
    ['Dental', 'Tooth Extraction'],
    ['General Health', 'Period Cramps'],
    ['General Health', 'Headache'],
    ['General Health', 'Dizziness'],
    ['General Health', 'Stomach Ache'],
    ['General Health', 'High Fever'],
    ['Respiratory', 'Asthma'],
    ['Respiratory', 'Allergy']
];

// Prepare insert statement
$stmt = $conn->prepare("INSERT INTO services (category, name) VALUES (?, ?)");

foreach ($services as $service) {
    $stmt->bind_param("ss", $service[0], $service[1]);
    if ($stmt->execute()) {
        echo "Added service: {$service[1]} in category {$service[0]}\n";
    } else {
        echo "Error adding service {$service[1]}: " . $stmt->error . "\n";
    }
}

// Create appointment_services table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS appointment_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    service_id INT NOT NULL,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT
)";

if ($conn->query($sql)) {
    echo "Appointment services table created successfully!\n";
} else {
    echo "Error creating appointment_services table: " . $conn->error . "\n";
}

$conn->close();
echo "Setup complete!\n";
?>