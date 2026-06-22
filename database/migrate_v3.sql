-- =====================================================================
--  Nanny-App v3.0 — incremental migration
--  Run via: /migrate_v3.php  (admin-authenticated)
--  Safe to run on an existing v2 database — uses ALTER IGNORE / IF NOT EXISTS patterns.
-- =====================================================================

USE nanny_app;

-- ---------------------------------------------------------------------
--  users — add email verification + remember me
-- ---------------------------------------------------------------------
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS email_verified  TINYINT(1)   NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN IF NOT EXISTS verification_token VARCHAR(100) DEFAULT NULL AFTER email_verified,
    ADD COLUMN IF NOT EXISTS verification_sent_at DATETIME DEFAULT NULL AFTER verification_token,
    ADD COLUMN IF NOT EXISTS remember_token  VARCHAR(64)  DEFAULT NULL AFTER verification_sent_at;

-- ---------------------------------------------------------------------
--  bookings — extra fields for wizard step 3
-- ---------------------------------------------------------------------
ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS children_details TEXT         DEFAULT NULL AFTER notes,
    ADD COLUMN IF NOT EXISTS booking_address  VARCHAR(255) DEFAULT NULL AFTER children_details,
    ADD COLUMN IF NOT EXISTS booking_ref      VARCHAR(20)  DEFAULT NULL AFTER booking_address;

-- Generate booking refs for existing rows that have none
UPDATE bookings SET booking_ref = CONCAT('BK', LPAD(id, 6, '0')) WHERE booking_ref IS NULL;

-- ---------------------------------------------------------------------
--  support_tickets
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS support_tickets (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT             DEFAULT NULL,
    name        VARCHAR(100)    NOT NULL,
    email       VARCHAR(150)    NOT NULL,
    category    ENUM('booking','payment','technical','safety','general') NOT NULL DEFAULT 'general',
    subject     VARCHAR(200)    NOT NULL,
    message     TEXT            NOT NULL,
    status      ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
    admin_notes TEXT            DEFAULT NULL,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ticket_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX IF NOT EXISTS idx_tickets_status   ON support_tickets (status);
CREATE INDEX IF NOT EXISTS idx_tickets_user     ON support_tickets (user_id);
CREATE INDEX IF NOT EXISTS idx_tickets_category ON support_tickets (category);

-- ---------------------------------------------------------------------
--  password_resets
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    token       VARCHAR(64)  NOT NULL UNIQUE,
    expires_at  DATETIME     NOT NULL,
    used        TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX IF NOT EXISTS idx_reset_token ON password_resets (token);

-- ---------------------------------------------------------------------
--  email_verifications
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_verifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    token       VARCHAR(64)  NOT NULL UNIQUE,
    verified    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_verify_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Mark existing seed/demo users as verified so they can still log in
UPDATE users SET email_verified = 1 WHERE email IN (
    'admin@nanny.app','parent@nanny.app','james@nanny.app',
    'amelia@nanny.app','margaret@nanny.app','jasmine@nanny.app'
);

-- ---------------------------------------------------------------------
--  availability_slots  (replaces free-text; nanny_availability still kept)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS availability_slots (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nanny_id    INT          NOT NULL,
    day_of_week TINYINT      NOT NULL COMMENT '0=Sun,1=Mon,...,6=Sat',
    slot        ENUM('morning','afternoon','evening') NOT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_slot (nanny_id, day_of_week, slot),
    CONSTRAINT fk_slot_nanny FOREIGN KEY (nanny_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  Seed FAQ entries into page_content (safe to skip if already present)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO page_content (page_key, title, body) VALUES
('faq-booking',  'How do I book a nanny?',
 'After creating an account, browse verified nannies, choose the one that fits your needs, then click "Book Now". Follow the 5-step wizard to choose your date, time, address, children details and complete payment.'),
('faq-payment',  'How do payments work?',
 'All payments are processed securely via Paystack. Your card is charged only after the nanny confirms the booking. Money is held safely until after the session is completed.'),
('faq-nanny',    'How do I become a nanny?',
 'Register and choose the "Nanny" role. Complete your profile including bio, experience, qualifications and upload your ID. Our team will review and verify your application within 1–3 business days.'),
('faq-cancel',   'Can I cancel or reschedule?',
 'Yes. You can cancel or reschedule from your Bookings page. Cancellations made more than 24 hours before the booking are fully refunded. Late cancellations may incur a small fee.'),
('faq-safety',   'How are nannies verified?',
 'Every nanny completes ID verification, a criminal background check, reference checks and qualifications review before receiving a Verified badge. Admins review all documentation manually.'),
('faq-contact',  'How do I contact support?',
 'Use the Support page to submit a ticket or email us directly. Our team responds within 24 hours on business days. For urgent safety concerns, use the Emergency button in your dashboard.');
