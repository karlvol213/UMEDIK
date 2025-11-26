-- Create appointment archives table
CREATE TABLE IF NOT EXISTS appointments_archive (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_appointment_id INT NOT NULL,
    user_id INT NOT NULL,
    appointment_date DATE,
    appointment_time TIME,
    status VARCHAR(50),
    comment TEXT,
    services TEXT,
    category VARCHAR(100),
    archive_reason ENUM('cancelled', 'completed', 'other') NOT NULL,
    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    archived_by INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (archived_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create archive services relation table
CREATE TABLE IF NOT EXISTS appointment_archive_services (
    archive_id INT NOT NULL,
    service_id INT NOT NULL,
    FOREIGN KEY (archive_id) REFERENCES appointments_archive(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    PRIMARY KEY (archive_id, service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add indexes for better query performance
CREATE INDEX idx_appointment_archives_user ON appointments_archive(user_id);
CREATE INDEX idx_appointment_archives_date ON appointments_archive(appointment_date);
CREATE INDEX idx_appointment_archives_status ON appointments_archive(status);