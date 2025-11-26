-- Migration: add login security columns to users table
-- Run this SQL once (for example via phpMyAdmin or CLI) to add the required columns

ALTER TABLE `users`
  ADD COLUMN `failed_login_count` INT NOT NULL DEFAULT 0,
  ADD COLUMN `last_failed_login` DATETIME NULL DEFAULT NULL,
  ADD COLUMN `locked_until` DATETIME NULL DEFAULT NULL,
  ADD COLUMN `is_locked` TINYINT(1) NOT NULL DEFAULT 0;

-- Optional: create an index on locked_until for quick checks
CREATE INDEX idx_users_locked_until ON `users` (`locked_until`);
