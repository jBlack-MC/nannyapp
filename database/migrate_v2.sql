-- =====================================================================
--  Nanny-App  •  v2 Migration
--  Run AFTER the original schema.sql.
--  Safe to run via phpMyAdmin or: mysql -u root nanny_app < database/migrate_v2.sql
-- =====================================================================

USE nanny_app;

-- -----------------------------------------------------------------------
-- Expand users table
-- -----------------------------------------------------------------------
ALTER TABLE users
    ADD COLUMN date_of_birth DATE         DEFAULT NULL AFTER phone,
    ADD COLUMN address       VARCHAR(255) DEFAULT NULL AFTER date_of_birth,
    ADD COLUMN gender        ENUM('male','female','non-binary','prefer_not_to_say') DEFAULT NULL AFTER address;

-- -----------------------------------------------------------------------
-- Expand parent_profiles
-- -----------------------------------------------------------------------
ALTER TABLE parent_profiles
    ADD COLUMN emergency_contact_name         VARCHAR(100) DEFAULT NULL AFTER emergency_contact,
    ADD COLUMN emergency_contact_relationship VARCHAR(50)  DEFAULT NULL AFTER emergency_contact_name;

-- -----------------------------------------------------------------------
-- Expand nanny_profiles
-- -----------------------------------------------------------------------
ALTER TABLE nanny_profiles
    ADD COLUMN gender           ENUM('male','female','non-binary','prefer_not_to_say') DEFAULT NULL AFTER bio,
    ADD COLUMN date_of_birth    DATE          DEFAULT NULL AFTER gender,
    ADD COLUMN banner_image     VARCHAR(255)  DEFAULT NULL AFTER photo_url,
    ADD COLUMN languages        VARCHAR(255)  DEFAULT 'English' AFTER skills,
    ADD COLUMN qualifications   TEXT          DEFAULT NULL AFTER languages,
    ADD COLUMN specialisations  VARCHAR(255)  DEFAULT NULL AFTER qualifications,
    ADD COLUMN profile_views    INT UNSIGNED  DEFAULT 0 AFTER specialisations;

-- Fix missing UNIQUE constraint (use ALTER IGNORE to skip if duplicates exist)
ALTER IGNORE TABLE nanny_profiles ADD UNIQUE KEY uq_nanny_user (user_id);

-- Fix payment / review uniqueness
ALTER IGNORE TABLE payments ADD UNIQUE KEY uq_pay_booking   (booking_id);
ALTER IGNORE TABLE reviews  ADD UNIQUE KEY uq_rev_booking   (booking_id, reviewer_id);

-- -----------------------------------------------------------------------
-- Performance indexes
-- -----------------------------------------------------------------------
ALTER TABLE bookings
    ADD INDEX idx_b_parent   (parent_id),
    ADD INDEX idx_b_nanny    (nanny_id),
    ADD INDEX idx_b_status   (status),
    ADD INDEX idx_b_datetime (date_time);

ALTER TABLE chat_messages ADD INDEX idx_cm_recv_read (receiver_id, is_read);
ALTER TABLE notifications ADD INDEX idx_n_user_read  (user_id, is_read);
ALTER TABLE reviews        ADD INDEX idx_r_nanny      (nanny_id);

ALTER TABLE nanny_profiles
    ADD INDEX idx_np_verif  (verification_status),
    ADD INDEX idx_np_rating (average_rating);

-- -----------------------------------------------------------------------
-- New: children
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS children (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    parent_id            INT              NOT NULL,
    name                 VARCHAR(100)     NOT NULL,
    age                  TINYINT UNSIGNED DEFAULT NULL,
    gender               ENUM('male','female','other') DEFAULT NULL,
    allergies            VARCHAR(500)     DEFAULT NULL,
    medical_conditions   VARCHAR(500)     DEFAULT NULL,
    special_needs        VARCHAR(500)     DEFAULT NULL,
    favourite_activities VARCHAR(500)     DEFAULT NULL,
    notes_for_nannies    TEXT             DEFAULT NULL,
    created_at           TIMESTAMP        DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_child_parent FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------
-- New: saved_nannies  (parent favourites)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS saved_nannies (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    parent_id   INT NOT NULL,
    nanny_id    INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY  uq_save (parent_id, nanny_id),
    CONSTRAINT fk_save_parent FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_save_nanny  FOREIGN KEY (nanny_id)  REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------
-- New: nanny_availability  (weekly schedule)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS nanny_availability (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    nanny_id     INT     NOT NULL,
    day_of_week  TINYINT NOT NULL COMMENT '0=Sun 1=Mon 2=Tue 3=Wed 4=Thu 5=Fri 6=Sat',
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    time_start   TIME    DEFAULT '08:00:00',
    time_end     TIME    DEFAULT '18:00:00',
    UNIQUE KEY   uq_avail_day (nanny_id, day_of_week),
    CONSTRAINT fk_avail_nanny FOREIGN KEY (nanny_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------
-- New: nanny_portfolio  (documents, certs, gallery)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS nanny_portfolio (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    nanny_id       INT             NOT NULL,
    type           ENUM('certificate','id','photo','reference','other') NOT NULL DEFAULT 'certificate',
    title          VARCHAR(150)    NOT NULL,
    file_path      VARCHAR(255)    NOT NULL,
    admin_verified TINYINT(1)      NOT NULL DEFAULT 0,
    created_at     TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_port_nanny FOREIGN KEY (nanny_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------
-- New: page_content  (CMS for admin-editable static pages)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS page_content (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    page_key   VARCHAR(50)  NOT NULL UNIQUE,
    title      VARCHAR(200) DEFAULT NULL,
    body       MEDIUMTEXT   DEFAULT NULL,
    updated_by INT          DEFAULT NULL,
    updated_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pc_admin FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO page_content (page_key, title, body) VALUES
('faq',     'Frequently Asked Questions', '<p>FAQ content — edit in Admin &rarr; Content.</p>'),
('terms',   'Terms of Service',           '<p>Terms of Service — edit in Admin &rarr; Content.</p>'),
('privacy', 'Privacy Policy',             '<p>Privacy Policy — edit in Admin &rarr; Content.</p>');
