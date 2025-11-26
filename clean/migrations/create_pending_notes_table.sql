-- Create table to persist pending biometrics waiting for clinical notes
-- This ensures patient cards survive logout/login cycles
CREATE TABLE IF NOT EXISTS pending_biometric_notes (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
