-- Add nurse_recommendation column to patient_history_records table
-- This column stores nurse recommendations and notes from biometric recordings

ALTER TABLE patient_history_records 
ADD COLUMN nurse_recommendation TEXT DEFAULT NULL AFTER respiratory_rate;

-- Verify the column was added
-- DESCRIBE patient_history_records;
