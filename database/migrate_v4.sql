-- =====================================================================
--  Nanny-App v4.0 — incremental migration
--  Run via: /migrate_v4.php  (admin-authenticated)
--  Safe to run on an existing v3 database — uses ALTER IGNORE / IF NOT EXISTS patterns.
--
--  Adds:
--   1. admin_profiles — a real profile record for admin accounts (access level,
--      department, notes) so admins show up in the schema the same way
--      parents and nannies do.
--   2. A check-in / check-out verification handshake on bookings, so a nanny
--      can't just click "accept" and have a job silently count as worked.
--      A one-time PIN is shown only to the parent; the nanny must be handed
--      it in person to check in, which is what proves someone is actually there.
--   3. An escrow-style payout_status on payments, so money is only released
--      to the nanny after the parent confirms the job actually happened.
-- =====================================================================

USE nanny_app;

-- ---------------------------------------------------------------------
--  admin_profiles  (1:1 with a user whose role = 'admin')
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_profiles (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT          NOT NULL UNIQUE,
    access_level ENUM('super_admin','support','moderator') NOT NULL DEFAULT 'support',
    department   VARCHAR(100) DEFAULT NULL,
    phone_ext    VARCHAR(20)  DEFAULT NULL,
    notes        TEXT         DEFAULT NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Give the seed admin account a profile so it isn't empty on first login.
INSERT IGNORE INTO admin_profiles (user_id, access_level, department)
SELECT id, 'super_admin', 'Platform Operations' FROM users WHERE email = 'admin@nanny.app';

-- ---------------------------------------------------------------------
--  bookings — presence verification + dispute handling
-- ---------------------------------------------------------------------
ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS check_in_code      VARCHAR(6)   DEFAULT NULL AFTER status,
    ADD COLUMN IF NOT EXISTS check_in_attempts  TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER check_in_code,
    ADD COLUMN IF NOT EXISTS checked_in_at      DATETIME     DEFAULT NULL AFTER check_in_attempts,
    ADD COLUMN IF NOT EXISTS checked_out_at     DATETIME     DEFAULT NULL AFTER checked_in_at,
    ADD COLUMN IF NOT EXISTS parent_confirmed_at DATETIME    DEFAULT NULL AFTER checked_out_at,
    ADD COLUMN IF NOT EXISTS dispute_reason     VARCHAR(500) DEFAULT NULL AFTER parent_confirmed_at,
    ADD COLUMN IF NOT EXISTS disputed_at        DATETIME     DEFAULT NULL AFTER dispute_reason;

-- 'in_progress'  = nanny has checked in with the parent's PIN (verified on-site)
-- 'disputed'     = parent reported the nanny never arrived / a problem occurred
ALTER TABLE bookings
    MODIFY COLUMN status ENUM('pending','confirmed','in_progress','completed','rejected','cancelled','disputed')
    NOT NULL DEFAULT 'pending';

-- ---------------------------------------------------------------------
--  payments — escrow payout tracking
--  'held'     = charged to the parent, held safely, NOT yet payable to the nanny
--  'released' = parent confirmed the job happened; nanny can be paid out
--  'refunded' = returned to the parent (cancellation / upheld dispute)
-- ---------------------------------------------------------------------
ALTER TABLE payments
    ADD COLUMN IF NOT EXISTS payout_status ENUM('held','released','refunded') DEFAULT NULL AFTER status,
    ADD COLUMN IF NOT EXISTS released_at   DATETIME DEFAULT NULL AFTER payout_status;

-- Backfill: anything already marked paid under the old model is treated as held
-- until an admin/parent explicitly releases it under the new flow.
UPDATE payments SET payout_status = 'held' WHERE status = 'paid' AND payout_status IS NULL;
