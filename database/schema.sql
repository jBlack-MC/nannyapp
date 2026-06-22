-- =====================================================================
--  Nanny-App  •  Database schema + seed data
--  Engine : MySQL / MariaDB (XAMPP)
--  Usage  : mysql -u root < database/schema.sql
--           or import this file via phpMyAdmin.
--  All seed accounts use the password:  Password123!
-- =====================================================================

DROP DATABASE IF EXISTS nanny_app;
CREATE DATABASE nanny_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nanny_app;

-- ---------------------------------------------------------------------
--  users  (one row per account, role drives the "live role")
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(100)    NOT NULL,
    email           VARCHAR(150)    NOT NULL UNIQUE,
    phone           VARCHAR(20)     DEFAULT NULL,
    password_hash   VARCHAR(255)    NOT NULL,
    role            ENUM('parent','nanny','admin') NOT NULL DEFAULT 'parent',
    status          ENUM('active','suspended') NOT NULL DEFAULT 'active',
    email_verified  TINYINT(1)      NOT NULL DEFAULT 0,
    verification_token VARCHAR(100) DEFAULT NULL,
    verification_sent_at DATETIME   DEFAULT NULL,
    remember_token  VARCHAR(64)     DEFAULT NULL,
    profile_image   VARCHAR(255)    DEFAULT NULL,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  parent_profiles  (1:1 with a user whose role = 'parent')
-- ---------------------------------------------------------------------
CREATE TABLE parent_profiles (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    user_id            INT          NOT NULL UNIQUE,
    emergency_contact  VARCHAR(20)  DEFAULT NULL,
    number_of_children INT          DEFAULT 0,
    CONSTRAINT fk_parent_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  nanny_profiles  (1:1 with a user whose role = 'nanny')
-- ---------------------------------------------------------------------
CREATE TABLE nanny_profiles (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT             NOT NULL,
    bio                 TEXT,
    experience_years    INT             DEFAULT 0,
    hourly_rate         DECIMAL(8,2)    DEFAULT 0.00,
    location            VARCHAR(120)    DEFAULT NULL,
    skills              VARCHAR(255)    DEFAULT NULL,   -- comma separated
    availability        VARCHAR(255)    DEFAULT 'Weekdays',
    verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    photo_url           VARCHAR(255)    DEFAULT NULL,
    average_rating      DECIMAL(3,2)    DEFAULT 0.00,
    CONSTRAINT fk_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  bookings  (parent books a nanny)
-- ---------------------------------------------------------------------
CREATE TABLE bookings (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    parent_id   INT             NOT NULL,
    nanny_id    INT             NOT NULL,
    date_time   DATETIME        NOT NULL,
    duration    DECIMAL(4,1)    NOT NULL DEFAULT 1.0,   -- hours
    location    VARCHAR(200)    DEFAULT NULL,
    notes       VARCHAR(500)    DEFAULT NULL,
    status      ENUM('pending','confirmed','rejected','completed','cancelled') NOT NULL DEFAULT 'pending',
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_booking_parent FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_nanny  FOREIGN KEY (nanny_id)  REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  payments  (1:1 with a booking)
-- ---------------------------------------------------------------------
CREATE TABLE payments (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    booking_id      INT             NOT NULL,
    amount          DECIMAL(10,2)   NOT NULL,
    method          VARCHAR(40)     DEFAULT 'card',
    transaction_id  VARCHAR(100)    DEFAULT NULL,
    status          ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  chat_messages  (M:M between users)
-- ---------------------------------------------------------------------
CREATE TABLE chat_messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    sender_id   INT             NOT NULL,
    receiver_id INT             NOT NULL,
    content     VARCHAR(1000)   NOT NULL,
    is_read     TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_msg_sender   FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_msg_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  reviews  (1:1 with a completed booking)
-- ---------------------------------------------------------------------
CREATE TABLE reviews (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    booking_id  INT             NOT NULL,
    reviewer_id INT             NOT NULL,
    nanny_id    INT             NOT NULL,
    rating      TINYINT         NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment     VARCHAR(1000)   DEFAULT NULL,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_review_booking  FOREIGN KEY (booking_id)  REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_review_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_review_nanny    FOREIGN KEY (nanny_id)    REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  notifications  (in-app notifications per user)
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT             NOT NULL,
    title       VARCHAR(150)    NOT NULL,
    message     VARCHAR(500)    NOT NULL,
    url         VARCHAR(255)    DEFAULT NULL,
    is_read     TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  contact_messages  (public "Contact us" form submissions)
-- ---------------------------------------------------------------------
CREATE TABLE contact_messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)    NOT NULL,
    email       VARCHAR(150)    NOT NULL,
    subject     VARCHAR(150)    DEFAULT NULL,
    message     VARCHAR(2000)   NOT NULL,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================================
--  SEED DATA   (password for every account below = Password123!)
-- =====================================================================
SET @pw = '$2y$10$tsFm/578qCWodJOPBwCJ0OYdTjA9FjOKQT/vZwgt.xdFiPQKRWu66';

-- Admin
INSERT INTO users (full_name, email, phone, password_hash, role) VALUES
('Site Administrator', 'admin@nanny.app', '0670000000', @pw, 'admin');

-- Parents
INSERT INTO users (full_name, email, phone, password_hash, role) VALUES
('Thandi Nkosi',  'parent@nanny.app', '0671111111', @pw, 'parent'),
('James Carter',  'james@nanny.app',  '0672222222', @pw, 'parent');

-- Nannies (users)
INSERT INTO users (full_name, email, phone, password_hash, role) VALUES
('Amelia Carter',   'amelia@nanny.app',   '0673333333', @pw, 'nanny'),
('Margaret Lopez',  'margaret@nanny.app', '0674444444', @pw, 'nanny'),
('Jasmine Williams','jasmine@nanny.app',  '0675555555', @pw, 'nanny');

-- Nanny profiles (link to the nanny users created above)
INSERT INTO nanny_profiles (user_id, bio, experience_years, hourly_rate, location, skills, availability, verification_status, average_rating)
SELECT id, 'Certified early-childhood educator who loves crafts, story-time and outdoor play.', 8, 22.00, 'Brooklyn, NY', 'Newborn care,Tutoring', 'Weekdays', 'verified', 0.00
FROM users WHERE email='amelia@nanny.app';

INSERT INTO nanny_profiles (user_id, bio, experience_years, hourly_rate, location, skills, availability, verification_status, average_rating)
SELECT id, 'Grandmother of four with 20+ years caring for little ones in cozy, structured days.', 22, 28.00, 'Queens, NY', 'Cooking,Bilingual', 'Weekends', 'verified', 5.00
FROM users WHERE email='margaret@nanny.app';

INSERT INTO nanny_profiles (user_id, bio, experience_years, hourly_rate, location, skills, availability, verification_status, average_rating)
SELECT id, 'Energetic and playful - perfect for active toddlers who love music and movement.', 4, 18.00, 'Manhattan, NY', 'Music,Arts & crafts', 'Flexible', 'pending', 4.00
FROM users WHERE email='jasmine@nanny.app';
