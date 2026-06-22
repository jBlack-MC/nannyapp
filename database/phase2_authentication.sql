-- =====================================================================
--  Phase 2: Authentication Database Schema
--  Purpose: Email verification and password reset functionality
--  Usage  : mysql -u root nanny_app < database/phase2_authentication.sql
-- =====================================================================

-- =====================================================================
--  Email Verification Columns (on users table)
-- =====================================================================

-- Add email verification fields to users table
ALTER TABLE users
ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
ADD COLUMN IF NOT EXISTS verification_token VARCHAR(100) DEFAULT NULL AFTER email_verified;

-- Add index for email verification lookups
ALTER TABLE users
ADD INDEX IF NOT EXISTS idx_users_email_verified (email_verified);

-- =====================================================================
--  Password Reset Table
-- =====================================================================

-- Create password_resets table for forgot password functionality
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_reset_token (token),
    INDEX idx_reset_user (user_id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =====================================================================
--  Status: Run this migration when ready for Phase 2
-- =====================================================================
