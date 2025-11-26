-- Migration script to add archive_reason column
ALTER TABLE appointments_archive
ADD COLUMN archive_reason ENUM('cancelled', 'completed', 'other') NOT NULL DEFAULT 'other' AFTER services;

-- Update existing records based on their status
UPDATE appointments_archive 
SET archive_reason = 
    CASE 
        WHEN status = 'completed' THEN 'completed'
        WHEN status = 'cancelled' THEN 'cancelled'
        ELSE 'other'
    END;

-- Add indexes for better performance
CREATE INDEX idx_appointments_archive_reason ON appointments_archive(archive_reason);

-- Optional: Add service_category if you want to track it
ALTER TABLE appointments_archive
ADD COLUMN service_category VARCHAR(100) AFTER services;