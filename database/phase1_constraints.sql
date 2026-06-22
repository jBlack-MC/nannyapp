-- language: mysql
-- =====================================================================
--  Phase 1: Database Optimization & Constraints
--  Purpose: Add UNIQUE constraints and performance indexes
--  Usage  : mysql -u root nanny_app < database/phase1_constraints.sql
-- =====================================================================

-- =====================================================================
--  UNIQUE CONSTRAINTS - Prevent duplicate data
-- =====================================================================

-- Nanny profiles: One profile per nanny user
ALTER TABLE nanny_profiles
ADD CONSTRAINT uk_nanny_user_id UNIQUE (user_id);

-- Payments: One payment record per booking (1:1 relationship)
ALTER TABLE payments
ADD CONSTRAINT uk_payment_booking_id UNIQUE (booking_id);

-- Reviews: One review per booking per reviewer combination
ALTER TABLE reviews
ADD CONSTRAINT uk_review_booking_reviewer UNIQUE (booking_id, reviewer_id);

-- =====================================================================
--  PERFORMANCE INDEXES - Faster queries
-- =====================================================================

-- Bookings indexes
ALTER TABLE bookings
ADD INDEX idx_bookings_parent (parent_id),
ADD INDEX idx_bookings_nanny (nanny_id),
ADD INDEX idx_bookings_status (status);

-- Notifications indexes
ALTER TABLE notifications
ADD INDEX idx_notifications_user_read (user_id, is_read);

-- Chat messages indexes
ALTER TABLE chat_messages
ADD INDEX idx_messages_receiver_read (receiver_id, is_read);

-- Parent profiles index (if needed for lookups)
ALTER TABLE parent_profiles
ADD INDEX idx_parent_user_id (user_id);

-- Nanny profiles indexes
ALTER TABLE nanny_profiles
ADD INDEX idx_nanny_verification (verification_status),
ADD INDEX idx_nanny_rating (average_rating);

-- =====================================================================
--  OPTIONAL: Email verification columns (for Phase 2)
-- =====================================================================
-- Uncomment when ready for Phase 2: Email Verification

-- ALTER TABLE users
-- ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
-- ADD COLUMN verification_token VARCHAR(100) DEFAULT NULL AFTER email_verified;

-- =====================================================================
--  OPTIONAL: Password reset table (for Phase 2)
-- =====================================================================
-- Uncomment when ready for Phase 2: Forgot Password

-- CREATE TABLE password_resets (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     user_id INT NOT NULL,
--     token VARCHAR(100) NOT NULL UNIQUE,
--     expires_at DATETIME NOT NULL,
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
--     INDEX idx_reset_token (token)
-- ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =====================================================================
--  Status: Run this migration in phpMyAdmin or via command line
-- =====================================================================
