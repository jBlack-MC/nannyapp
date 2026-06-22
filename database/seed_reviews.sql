-- =====================================================================
--  Nanny-App  •  Demo seed: profile images, bookings & reviews
--  Run AFTER schema.sql (and optionally migrate_v2.sql / migrate_v3.sql).
--  Import via phpMyAdmin: choose nanny_app database → Import this file.
-- =====================================================================

USE nanny_app;

-- -----------------------------------------------------------------------
-- 1. Profile images — assign avatar images to seed nanny accounts
-- -----------------------------------------------------------------------
UPDATE users SET profile_image = 'assets/img/avatar-amelia.svg'
WHERE email = 'amelia@nanny.app';

UPDATE users SET profile_image = 'assets/img/avatar-margaret.svg'
WHERE email = 'margaret@nanny.app';

UPDATE users SET profile_image = 'assets/img/avatar-jasmine.svg'
WHERE email = 'jasmine@nanny.app';

-- -----------------------------------------------------------------------
-- 2. Banner images for nanny profiles (requires migrate_v2.sql)
--    Skip this block if you have NOT yet run migrate_v2.sql
-- -----------------------------------------------------------------------
UPDATE nanny_profiles SET banner_image = "assets/img/nanny's/nanny-3--IFXl40m.jpg"
WHERE user_id = (SELECT id FROM users WHERE email = 'amelia@nanny.app');

UPDATE nanny_profiles SET banner_image = "assets/img/nanny's/nanny-2-oKu3e9Fl.jpg"
WHERE user_id = (SELECT id FROM users WHERE email = 'margaret@nanny.app');

UPDATE nanny_profiles SET banner_image = 'assets/img/hero-nanny-SMceVYR7.jpg'
WHERE user_id = (SELECT id FROM users WHERE email = 'jasmine@nanny.app');

-- -----------------------------------------------------------------------
-- 3. Extra nanny profile details (requires migrate_v2.sql)
-- -----------------------------------------------------------------------
UPDATE nanny_profiles
SET languages      = 'English, Zulu, Xhosa',
    qualifications = 'Early Childhood Development (ECD) Certificate,First Aid & CPR Certified,Paediatric Nutrition Course',
    specialisations = 'Newborns & Infants,Educational Play,Special Needs Support'
WHERE user_id = (SELECT id FROM users WHERE email = 'amelia@nanny.app');

UPDATE nanny_profiles
SET languages      = 'English, Afrikaans, Spanish',
    qualifications = 'Bachelor of Education (Early Childhood),Advanced First Aid,Montessori Teaching Certificate',
    specialisations = 'Bilingual Households,Cooking & Nutrition,Structured Routines'
WHERE user_id = (SELECT id FROM users WHERE email = 'margaret@nanny.app');

UPDATE nanny_profiles
SET languages      = 'English, Sotho',
    qualifications = 'Childcare Level 3 Diploma,Creative Arts Facilitator,Swim Safety Trained',
    specialisations = 'Music & Movement,Arts & Crafts,Toddler Development'
WHERE user_id = (SELECT id FROM users WHERE email = 'jasmine@nanny.app');

-- -----------------------------------------------------------------------
-- 4. Ensure parent_profiles exist for seed parents
-- -----------------------------------------------------------------------
INSERT IGNORE INTO parent_profiles (user_id)
SELECT id FROM users WHERE email IN ('parent@nanny.app', 'james@nanny.app');

-- -----------------------------------------------------------------------
-- 5. Remove any previous seed bookings + reviews so this file is re-runnable
-- -----------------------------------------------------------------------
DELETE r FROM reviews r
INNER JOIN users u ON u.id = r.reviewer_id
WHERE u.email IN ('parent@nanny.app', 'james@nanny.app');

DELETE b FROM bookings b
INNER JOIN users u ON u.id = b.parent_id
WHERE u.email IN ('parent@nanny.app', 'james@nanny.app');

-- -----------------------------------------------------------------------
-- 6. Set user ID variables for readability
-- -----------------------------------------------------------------------
SET @parent1 = (SELECT id FROM users WHERE email = 'parent@nanny.app');
SET @parent2 = (SELECT id FROM users WHERE email = 'james@nanny.app');
SET @nanny1  = (SELECT id FROM users WHERE email = 'amelia@nanny.app');
SET @nanny2  = (SELECT id FROM users WHERE email = 'margaret@nanny.app');
SET @nanny3  = (SELECT id FROM users WHERE email = 'jasmine@nanny.app');

-- -----------------------------------------------------------------------
-- 7. Completed bookings (basis for reviews)
-- -----------------------------------------------------------------------
INSERT INTO bookings (parent_id, nanny_id, date_time, duration, location, notes, status) VALUES
(@parent1, @nanny1, DATE_SUB(NOW(), INTERVAL 45 DAY), 4.0,  '12 Oak Avenue, Sandton, Johannesburg',   'First time, loved it!',              'completed'),
(@parent1, @nanny2, DATE_SUB(NOW(), INTERVAL 32 DAY), 3.0,  '45 Nelson Mandela Ave, Midrand',          'Kids loved her cooking',             'completed'),
(@parent1, @nanny1, DATE_SUB(NOW(), INTERVAL 18 DAY), 5.0,  '12 Oak Avenue, Sandton, Johannesburg',   'Second booking, even better!',        'completed'),
(@parent2, @nanny2, DATE_SUB(NOW(), INTERVAL 28 DAY), 6.0,  '8 Garden Road, Rosebank',                 'Margaret was a total lifesaver',     'completed'),
(@parent2, @nanny3, DATE_SUB(NOW(), INTERVAL 14 DAY), 3.0,  '22 Buitenkant Street, Cape Town',         'Great energy with the kids',         'completed'),
(@parent2, @nanny1, DATE_SUB(NOW(), INTERVAL 7  DAY), 4.5,  '8 Garden Road, Rosebank',                 NULL,                                 'completed');

-- Capture the six booking IDs in order
SET @b1 = (SELECT id FROM bookings WHERE parent_id=@parent1 AND nanny_id=@nanny1 ORDER BY date_time ASC  LIMIT 1);
SET @b2 = (SELECT id FROM bookings WHERE parent_id=@parent1 AND nanny_id=@nanny2 ORDER BY date_time ASC  LIMIT 1);
SET @b3 = (SELECT id FROM bookings WHERE parent_id=@parent1 AND nanny_id=@nanny1 ORDER BY date_time DESC LIMIT 1);
SET @b4 = (SELECT id FROM bookings WHERE parent_id=@parent2 AND nanny_id=@nanny2 ORDER BY date_time ASC  LIMIT 1);
SET @b5 = (SELECT id FROM bookings WHERE parent_id=@parent2 AND nanny_id=@nanny3 ORDER BY date_time ASC  LIMIT 1);
SET @b6 = (SELECT id FROM bookings WHERE parent_id=@parent2 AND nanny_id=@nanny1 ORDER BY date_time ASC  LIMIT 1);

-- -----------------------------------------------------------------------
-- 8. Reviews
-- -----------------------------------------------------------------------
INSERT INTO reviews (booking_id, reviewer_id, nanny_id, rating, comment) VALUES
(@b1, @parent1, @nanny1, 5, 'Amelia was absolutely wonderful with my little ones. She kept them engaged with crafts and outdoor play all afternoon — they were happily exhausted by the time I got home. She communicated brilliantly throughout the day. 100% recommend!'),
(@b2, @parent1, @nanny2, 5, 'Margaret is a total gem. Her experience really shows — she had my kids in a calm, happy routine within the first hour. They even asked when she is coming back! Her cooking was a bonus we did not expect.'),
(@b3, @parent1, @nanny1, 5, 'Second booking with Amelia and she just keeps getting better. She remembered everything about my kids from the first visit and came prepared with new activities. She is now our go-to nanny.'),
(@b4, @parent2, @nanny2, 5, 'Margaret stepped in on short notice and handled everything like a pro. The children adored her stories and she followed the bedtime routine perfectly. I came home to a tidy house and two sleeping kids — absolute magic.'),
(@b5, @parent2, @nanny3, 4, 'Jasmine brought so much energy and creativity. She set up a mini music and art session that had the kids talking about it for days. Very punctual and professional too. Would definitely book again!'),
(@b6, @parent2, @nanny1, 5, 'Amelia went above and beyond. She noticed my youngest was a bit under the weather and adjusted the activities accordingly — very caring and intuitive. Exactly what you want in a nanny.');

-- -----------------------------------------------------------------------
-- 9. Recompute average ratings on nanny_profiles
-- -----------------------------------------------------------------------
UPDATE nanny_profiles np
SET average_rating = (
    SELECT IFNULL(ROUND(AVG(r.rating), 2), 0)
    FROM reviews r WHERE r.nanny_id = np.user_id
)
WHERE np.user_id IN (@nanny1, @nanny2, @nanny3);
