-- Migration: add special_status to users
-- Values: 'none', 'pwd', 'senior'
ALTER TABLE `users`
  ADD COLUMN `special_status` VARCHAR(20) NOT NULL DEFAULT 'none';
