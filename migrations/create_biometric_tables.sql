-- Create biometric identity templates table
CREATE TABLE IF NOT EXISTS `biometric_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `biometric_type` ENUM('fingerprint','face','iris','other') NOT NULL,
  `template` MEDIUMBLOB NOT NULL,
  `template_hash` CHAR(64) NOT NULL,
  `device_id` VARCHAR(100) DEFAULT NULL,
  `enrollment_source` VARCHAR(50) DEFAULT NULL,
  `enrolled_by` INT DEFAULT NULL,
  `enrolled_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `is_active` TINYINT(1) DEFAULT 1,
  `notes` TEXT DEFAULT NULL,
  UNIQUE KEY (`template_hash`),
  KEY (`user_id`),
  CONSTRAINT fk_bt_user FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create biometric events table (enrollment, verification, vitals capture events)
CREATE TABLE IF NOT EXISTS `biometric_events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `template_id` INT DEFAULT NULL,
  `event_type` ENUM('ENROLL','VERIFY','VERIFY_FAIL','DELETE','VITALS_CAPTURE') NOT NULL,
  `event_data` JSON DEFAULT NULL,
  `actor_id` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY (`created_at`),
  KEY (`user_id`),
  CONSTRAINT fk_be_template FOREIGN KEY (`template_id`) REFERENCES `biometric_templates`(`id`) ON DELETE SET NULL,
  CONSTRAINT fk_be_user FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create detailed audit logs table for template access
CREATE TABLE IF NOT EXISTS `biometric_audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `template_id` INT DEFAULT NULL,
  `user_id` INT DEFAULT NULL,
  `actor_id` INT DEFAULT NULL,
  `action` ENUM('VIEW','ENROLL','VERIFY','DELETE','EXPORT','IMPORT','CLEANUP') NOT NULL,
  `outcome` VARCHAR(50) DEFAULT NULL,
  `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `details` TEXT DEFAULT NULL,
  KEY (`template_id`),
  KEY (`user_id`),
  CONSTRAINT fk_bal_template FOREIGN KEY (`template_id`) REFERENCES `biometric_templates`(`id`) ON DELETE SET NULL,
  CONSTRAINT fk_bal_user FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;