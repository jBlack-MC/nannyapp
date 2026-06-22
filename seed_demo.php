<?php
/**
 * Demo data seeder — South African platform data.
 * Admin-only. Run once. Safe to re-run (duplicate rows skipped via INSERT IGNORE / try-catch).
 */
require_once __DIR__ . '/config/config.php';
require_role('admin');

set_time_limit(120);

$log  = [];
$errs = [];

function seed_log(string $msg): void { global $log;  $log[]  = $msg; }
function seed_err(string $msg): void { global $errs; $errs[] = $msg; }

/* ════════════════════════════════════════════════════════════
   CONSTANTS
   ════════════════════════════════════════════════════════════ */
$PASS = password_hash('Password123!', PASSWORD_DEFAULT);

$SA_CITIES = [
    'Johannesburg', 'Sandton', 'Pretoria', 'Midrand', 'Centurion',
    'Randburg', 'Roodepoort', 'Cape Town', 'Durban', 'Soweto',
    'Tembisa', 'Boksburg', 'Benoni', 'Germiston', 'East London',
];
$STREETS = ['Elm Street','Rose Avenue','Oak Road','Pine Close','Maple Drive','Cedar Lane','Jacaranda Way','Protea Street'];

/* ════════════════════════════════════════════════════════════
   ENSURE UPLOAD DIRECTORIES EXIST
   ════════════════════════════════════════════════════════════ */
foreach (['parents','nannies','children'] as $sub) {
    $dir = __DIR__ . '/assets/uploads/' . $sub;
    if (!is_dir($dir)) { mkdir($dir, 0755, true); seed_log("Created: $dir"); }
}

/* ════════════════════════════════════════════════════════════
   1. PARENTS (20)
   ════════════════════════════════════════════════════════════ */
$parents_data = [
    ['Sarah Dlamini',        'sarah.dlamini@gmail.com',    'Johannesburg', '+27 82 111 2001'],
    ['Michael Mokoena',      'michael.mokoena@gmail.com',  'Sandton',      '+27 82 111 2002'],
    ['Lebo Khumalo',         'lebo.khumalo@gmail.com',     'Midrand',      '+27 82 111 2003'],
    ['Nomsa Ncube',          'nomsa.ncube@gmail.com',      'Pretoria',     '+27 82 111 2004'],
    ['Amanda Daniels',       'amanda.daniels@gmail.com',   'Cape Town',    '+27 82 111 2005'],
    ['Thabo Sithole',        'thabo.sithole@gmail.com',    'Centurion',    '+27 82 111 2006'],
    ['Zanele Mthembu',       'zanele.mthembu@gmail.com',   'Durban',       '+27 82 111 2007'],
    ['Pieter van der Berg',  'pieter.vdb@gmail.com',       'Randburg',     '+27 82 111 2008'],
    ['Fatima Essop',         'fatima.essop@gmail.com',     'Cape Town',    '+27 82 111 2009'],
    ['Sipho Ndlovu',         'sipho.ndlovu@gmail.com',     'Soweto',       '+27 82 111 2010'],
    ['Priya Naidoo',         'priya.naidoo@gmail.com',     'Durban',       '+27 82 111 2011'],
    ['Lungelo Zulu',         'lungelo.zulu@gmail.com',     'Tembisa',      '+27 82 111 2012'],
    ['Karen Botha',          'karen.botha@gmail.com',      'Roodepoort',   '+27 82 111 2013'],
    ['Refilwe Molefe',       'refilwe.molefe@gmail.com',   'Pretoria',     '+27 82 111 2014'],
    ['Johan Engelbrecht',    'johan.engelbrecht@gmail.com','Centurion',    '+27 82 111 2015'],
    ['Ntombi Gumede',        'ntombi.gumede@gmail.com',    'Boksburg',     '+27 82 111 2016'],
    ['Ayesha Patel',         'ayesha.patel@gmail.com',     'Sandton',      '+27 82 111 2017'],
    ['Bongani Dube',         'bongani.dube@gmail.com',     'Johannesburg', '+27 82 111 2018'],
    ['Chantelle Jacobs',     'chantelle.jacobs@gmail.com', 'Germiston',    '+27 82 111 2019'],
    ['Kobus Kruger',         'kobus.kruger@gmail.com',     'Pretoria',     '+27 82 111 2020'],
];

$parentIds = [];
$insertUser = db()->prepare(
    "INSERT IGNORE INTO users (full_name, email, password_hash, role, phone, email_verified, status, created_at)
     VALUES (?, ?, ?, 'parent', ?, 1, 'active', NOW() - INTERVAL ? DAY)"
);
$insertParentProfile = db()->prepare(
    "INSERT IGNORE INTO parent_profiles (user_id, emergency_contact, number_of_children) VALUES (?, ?, ?)"
);

foreach ($parents_data as $i => [$name, $email, $city, $phone]) {
    $insertUser->execute([$name, $email, $PASS, $phone, rand(30, 365)]);
    $uid = (int)db()->lastInsertId();
    if (!$uid) {
        $uid = (int)db()->query("SELECT id FROM users WHERE email=".db()->quote($email))->fetchColumn();
    }
    $parentIds[] = $uid;
    try { $insertParentProfile->execute([$uid, '+27 82 999 '.str_pad($i+1,4,'0',STR_PAD_LEFT), rand(1,3)]); } catch (Throwable) {}
}
seed_log("Inserted/found " . count($parentIds) . " parents.");

/* ════════════════════════════════════════════════════════════
   2. NANNIES (20)
   ════════════════════════════════════════════════════════════ */
$nannies_data = [
    ['Nomsa Khumalo',      'nomsa.khumalo.nanny@gmail.com',  'Sandton',      120, 7,  'isiZulu,English',           'Newborn Care,First Aid,Homework Assistance',                    'Experienced with newborns and toddlers. First Aid certified.'],
    ['Margaret Sithole',   'margaret.sithole@gmail.com',     'Johannesburg', 110, 9,  'English,Sesotho',           'Educational Activities,Cooking & Nutrition,Bedtime Routines',   'Gentle and nurturing with 9 years of experience across multiple families.'],
    ['Thandi Nkosi',       'thandi.nkosi@gmail.com',         'Pretoria',      95, 5,  'isiZulu,English',           'Potty Training,Arts & Crafts,Bedtime Routines',                 'Passionate about early childhood development and creative learning.'],
    ['Lindiwe Mokoena',    'lindiwe.mokoena@gmail.com',      'Midrand',      130, 11, 'Sesotho,English,Setswana',  'Special Needs Care,First Aid,Homework Assistance',              'Specialist in children with learning differences. Calm and patient.'],
    ['Grace Dlamini',      'grace.dlamini.nanny@gmail.com',  'Cape Town',    105, 6,  'Afrikaans,English',         'Cooking & Nutrition,Housekeeping,Bedtime Routines',             'Former au pair in the UK. Excellent cook and organiser.'],
    ['Portia Mthembu',     'portia.mthembu@gmail.com',       'Durban',       100, 8,  'isiZulu,English',           'Newborn Care,First Aid,Baby Sign Language',                     'Specialises in newborn to 18-month care. Gentle touch.'],
    ['Ayanda Cele',        'ayanda.cele@gmail.com',          'Centurion',    115, 10, 'isiZulu,English,Sepedi',    'Transport Assistance,Homework Assistance,Discipline & Boundaries','Reliable and punctual. Experienced in after-school care.'],
    ['Bongiwe Zulu',       'bongiwe.zulu@gmail.com',         'Soweto',        90, 4,  'isiZulu,Sesotho',           'Arts & Crafts,Educational Activities,Potty Training',           'Young, energetic nanny with an ECD qualification.'],
    ['Miriam Ntuli',       'miriam.ntuli@gmail.com',         'Randburg',     125, 13, 'Sesotho,English,Afrikaans', 'First Aid,Special Needs Care,Multilingual Care',                '13 years of experience. Fluent in three languages.'],
    ['Faith Mahlangu',     'faith.mahlangu@gmail.com',       'Benoni',       100, 7,  'Sepedi,English',            'Educational Activities,Homework Assistance,Cooking & Nutrition', 'Former primary school teacher. Excellent homework support.'],
    ['Zodwa Shabalala',    'zodwa.shabalala@gmail.com',      'Tembisa',       85, 3,  'isiZulu,Setswana',          'Bedtime Routines,Cooking & Nutrition,Arts & Crafts',            'Warm and loving. Great with babies and toddlers.'],
    ['Patricia Ngcobo',    'patricia.ngcobo@gmail.com',      'Roodepoort',   140, 15, 'English,isiZulu,Afrikaans', 'Newborn Care,First Aid,Special Needs Care,Swimming Safety',     'Senior nanny. Paediatric First Aid qualified. 15 years experience.'],
    ['Nelisiwe Buthelezi', 'nelisiwe.buthelezi@gmail.com',   'Boksburg',     110, 8,  'isiZulu,English',           'Swimming Safety,Arts & Crafts,Educational Activities',          'Loves water activities and outdoor play. Swimming certified.'],
    ['Thandeka Mhlongo',   'thandeka.mhlongo@gmail.com',     'East London',   95, 5,  'isiXhosa,English',          'Newborn Care,Potty Training,Bedtime Routines',                  'East Cape nanny with a calm, homely approach to childcare.'],
    ['Nomvula Dube',       'nomvula.dube@gmail.com',         'Germiston',    105, 6,  'isiZulu,Sesotho,English',   'Cooking & Nutrition,Housekeeping,Homework Assistance',          'Great all-rounder. Handles school runs, meals and household tasks.'],
    ['Sikelelwa Mqoqi',    'sikelelwa.mqoqi@gmail.com',      'Cape Town',    115, 9,  'isiXhosa,Afrikaans,English','Baby Sign Language,Newborn Care,First Aid',                     'Cape Town nanny specialising in infant sign language.'],
    ['Hlengiwe Msweli',    'hlengiwe.msweli@gmail.com',      'Sandton',      130, 12, 'isiZulu,English',           'Special Needs Care,Discipline & Boundaries,Transport Assistance','Works with children with autism and ADHD. Structured approach.'],
    ['Busi Cele',          'busi.cele@gmail.com',            'Pretoria',     100, 6,  'Sesotho,Setswana,English',  'Educational Activities,Arts & Crafts,Multilingual Care',        'Trilingual nanny with a focus on bilingual early development.'],
    ['Nompilo Khoza',      'nompilo.khoza@gmail.com',        'Durban',       110, 8,  'isiZulu,English',           'Homework Assistance,First Aid,Bedtime Routines',                'Structured and reliable. Excellent for school-age children.'],
    ['Thandiwe Sibiya',    'thandiwe.sibiya@gmail.com',      'Johannesburg', 120, 10, 'isiZulu,Sesotho,English',   'Newborn Care,Cooking & Nutrition,First Aid,Housekeeping',       'A true all-round nanny — cooking, cleaning, and caring with heart.'],
];

$nannyIds = [];
$insertNannyUser = db()->prepare(
    "INSERT IGNORE INTO users (full_name, email, password_hash, role, phone, email_verified, status, created_at)
     VALUES (?, ?, ?, 'nanny', ?, 1, 'active', NOW() - INTERVAL ? DAY)"
);
$insertNannyProfile = db()->prepare(
    "INSERT IGNORE INTO nanny_profiles
        (user_id, bio, location, hourly_rate, experience_years, skills, languages, verification_status, average_rating)
     VALUES (?, ?, ?, ?, ?, ?, ?, 'verified', ?)"
);

foreach ($nannies_data as $i => [$name, $email, $city, $rate, $exp, $langs, $skills, $bio]) {
    $rating = min(5.0, round(3.8 + (mt_rand(0, 999) / 1000) * 1.2, 1));
    $insertNannyUser->execute([$name, $email, $PASS, '+27 83 200 '.str_pad($i+1,4,'0',STR_PAD_LEFT), rand(60, 400)]);
    $uid = (int)db()->lastInsertId();
    if (!$uid) {
        $uid = (int)db()->query("SELECT id FROM users WHERE email=".db()->quote($email))->fetchColumn();
    }
    $nannyIds[] = ['id' => $uid, 'rate' => $rate, 'name' => $name, 'city' => $city];
    try { $insertNannyProfile->execute([$uid, $bio, $city, $rate, $exp, $skills, $langs, $rating]); } catch (Throwable) {}
}
seed_log("Inserted/found " . count($nannyIds) . " nannies.");

/* ════════════════════════════════════════════════════════════
   3. AVAILABILITY (Mon-Fri 08-18, Sat 09-15)
   ════════════════════════════════════════════════════════════ */
$insertAvail = db()->prepare(
    "INSERT IGNORE INTO nanny_availability (nanny_id, day_of_week, is_available, time_start, time_end)
     VALUES (?, ?, 1, ?, ?)"
);
$schedules = [
    [1,'08:00','18:00'], [2,'08:00','18:00'], [3,'08:00','18:00'],
    [4,'08:00','18:00'], [5,'08:00','18:00'], [6,'09:00','15:00'],
];
$availInserted = 0;
foreach ($nannyIds as $n) {
    foreach ($schedules as [$day, $start, $end]) {
        try { $insertAvail->execute([$n['id'], $day, $start, $end]); $availInserted++; } catch (Throwable) {}
    }
}
seed_log("Inserted $availInserted availability slots.");

/* ════════════════════════════════════════════════════════════
   4. CHILDREN (1–3 per parent)
   ════════════════════════════════════════════════════════════ */
$childNames = [
    ['Sipho','Zintle'],['Aiden','Mia'],['Liam','Zoe','Jack'],['Naledi','Keamo'],
    ['Emma','Sophie'],['Tebogo','Lerato'],['Amara','Kai'],['Piet','Marie'],
    ['Zara','Ayaan'],['Lwazi','Nkosi'],['Aarav','Diya'],['Sanele','Ntombi'],
    ['Kyle','Amber'],['Boipelo','Tshegofatso'],['Francois','Maret','Hannes'],
    ['Mbuso','Slindile'],['Zahra','Ibrahim'],['Musa','Nomfundo'],
    ['Luke','Jade'],['Nic','Marelize'],
];
$insertChild = db()->prepare(
    "INSERT IGNORE INTO children (parent_id, name, age, allergies, notes_for_nannies, created_at)
     VALUES (?, ?, ?, ?, ?, NOW() - INTERVAL ? DAY)"
);
$childrenInserted = 0;
foreach ($parentIds as $pi => $pid) {
    $names = $childNames[$pi] ?? ['Thabo', 'Leah'];
    foreach ($names as $ci => $cname) {
        $age     = rand(1, 12);
        $allergy = ['None','None','None','Peanuts','Dairy','Gluten','Eggs','None'][array_rand(['None','None','None','Peanuts','Dairy','Gluten','Eggs','None'])];
        $notes   = $allergy !== 'None' ? "Allergic to $allergy. Please check all food labels." : '';
        try { $insertChild->execute([$pid, $cname, $age, $allergy, $notes, rand(20, 300)]); $childrenInserted++; } catch (Throwable) {}
    }
}
seed_log("Inserted $childrenInserted child profiles.");

/* ════════════════════════════════════════════════════════════
   5. BOOKINGS  (150+ completed | 30+ pending | 25+ confirmed | 15+ cancelled)
   Schema: parent_id, nanny_id, date_time DATETIME, duration DECIMAL, location, notes, status, booking_address
   ════════════════════════════════════════════════════════════ */
$insertBooking = db()->prepare(
    "INSERT INTO bookings (parent_id, nanny_id, date_time, duration, location, notes, status, booking_address, booking_ref, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW() - INTERVAL ? DAY)"
);
$insertPayment = db()->prepare(
    "INSERT IGNORE INTO payments (booking_id, amount, method, status, created_at)
     VALUES (?, ?, 'card', 'paid', NOW() - INTERVAL ? DAY)"
);

$statusPlan = array_merge(
    array_fill(0, 155, 'completed'),
    array_fill(0, 32,  'pending'),
    array_fill(0, 26,  'confirmed'),
    array_fill(0, 16,  'cancelled')
);
shuffle($statusPlan);

$notesPool = [
    'Please bring a first aid kit.', 'Children nap between 13:00 and 15:00.',
    'Our dog is friendly — no need to worry.', 'Twins — extra energy required!',
    'Homework help needed after school.', 'Please prepare a light meal.',
    '', 'No nuts in the house due to allergy.', 'Gate code is 1234.',
    'Please arrive 10 minutes early to meet the kids.',
];

$bookingIds   = [];
$completedBks = [];
$bInserted    = 0;

foreach ($statusPlan as $idx => $status) {
    $pid   = $parentIds[array_rand($parentIds)];
    $nanny = $nannyIds[array_rand($nannyIds)];
    $nid   = $nanny['id'];
    $rate  = $nanny['rate'];
    $hrs   = [2,3,4,5,6,8][array_rand([2,3,4,5,6,8])];
    $total = $hrs * $rate;
    $daysAgo = ($status === 'completed') ? rand(1, 180) : rand(0, 30);
    $bDate   = date('Y-m-d', strtotime("-$daysAgo days"));
    $sHour   = rand(7, 14);
    $dt      = $bDate . ' ' . sprintf('%02d:00:00', $sHour);
    $city    = $SA_CITIES[array_rand($SA_CITIES)];
    $addr    = rand(1,99) . ' ' . $STREETS[array_rand($STREETS)] . ', ' . $city;
    $note    = $notesPool[array_rand($notesPool)];
    $ref     = 'BK' . str_pad($idx + 1000, 6, '0', STR_PAD_LEFT);
    try {
        $insertBooking->execute([$pid, $nid, $dt, $hrs, $city, $note, $status, $addr, $ref, $daysAgo]);
        $bid = (int)db()->lastInsertId();
        if ($bid) {
            $bookingIds[] = ['id'=>$bid,'pid'=>$pid,'nid'=>$nid,'total'=>$total,'status'=>$status,'days_ago'=>$daysAgo];
            if ($status === 'completed') {
                $completedBks[] = ['id'=>$bid,'pid'=>$pid,'nid'=>$nid,'total'=>$total,'days_ago'=>$daysAgo];
                try { $insertPayment->execute([$bid, $total, $daysAgo]); } catch (Throwable) {}
            }
            $bInserted++;
        }
    } catch (Throwable $e) { seed_err("Booking error: " . $e->getMessage()); }
}
seed_log("Inserted $bInserted bookings (" . count($completedBks) . " completed).");

/* ════════════════════════════════════════════════════════════
   6. REVIEWS  (~82% of completed bookings)
   Schema: booking_id, reviewer_id, nanny_id, rating, comment
   ════════════════════════════════════════════════════════════ */
$reviewTexts = [
    5 => [
        'Absolutely wonderful with our children. Could not be happier!',
        'Margaret was amazing with our two children. She went above and beyond.',
        'Very professional and caring. The kids adore her.',
        'Punctual, warm, and incredibly capable. Will book again for sure.',
        'Our baby was happy and settled the entire time. Highly recommend!',
        'She prepared nutritious meals and kept the house tidy. A gem!',
        'Patient with our toddler who can be a handful. Excellent communication.',
        '5 stars is not enough. She treated our children like her own.',
        'I came home to happy, well-fed children and a clean house. Perfect!',
        'Our go-to nanny. Reliable, kind, and always on time.',
    ],
    4 => [
        'Very good experience. Kids were happy and the house was tidy.',
        'Great with the little ones. Would book again.',
        'Professional and reliable. Minor miscommunication on arrival time but sorted quickly.',
        'Good nanny, excellent with our baby. Highly recommended.',
        'The children really warmed up to her quickly. Good session overall.',
        'Solid, dependable. Good with homework and meals.',
        'Very caring and attentive. Would use her services again.',
    ],
    3 => [
        'Decent service, but a bit late on arrival. Kids were fine overall.',
        'Average experience. Nothing wrong but nothing exceptional either.',
        'Our older child liked her but the toddler needed more attention.',
        'She did the job, but we expected a bit more engagement with the kids.',
    ],
];

$insertReview = db()->prepare(
    "INSERT IGNORE INTO reviews (booking_id, reviewer_id, nanny_id, rating, comment, created_at)
     VALUES (?, ?, ?, ?, ?, NOW() - INTERVAL ? DAY)"
);
$reviewsInserted = 0;
foreach ($completedBks as $b) {
    if (mt_rand(0, 99) >= 82) continue;
    $r = (mt_rand(0, 99) < 65) ? 5 : ((mt_rand(0, 99) < 80) ? 4 : 3);
    $comment = $reviewTexts[$r][array_rand($reviewTexts[$r])];
    try {
        $insertReview->execute([$b['id'], $b['pid'], $b['nid'], $r, $comment, max(0, $b['days_ago'] - 1)]);
        $reviewsInserted++;
    } catch (Throwable) {}
}
seed_log("Inserted $reviewsInserted reviews.");

/* ── Recompute nanny average ratings ─────────────────────────────────── */
foreach ($nannyIds as $n) { try { recompute_rating($n['id']); } catch (Throwable) {} }
seed_log("Recomputed ratings for " . count($nannyIds) . " nannies.");

/* ════════════════════════════════════════════════════════════
   7. NOTIFICATIONS
   Schema: user_id, title, message, url, is_read
   ════════════════════════════════════════════════════════════ */
$insertNotif = db()->prepare(
    "INSERT IGNORE INTO notifications (user_id, title, message, is_read, created_at)
     VALUES (?, ?, ?, ?, NOW() - INTERVAL ? DAY)"
);

$parentNotifs = [
    ['Booking Confirmed',  'Your booking has been confirmed by the nanny.'],
    ['Nanny Accepted',     'Your nanny has accepted your booking request.'],
    ['Payment Received',   'Your payment has been processed successfully.'],
    ['Leave a Review',     'How was your session? Share your experience with a review.'],
    ['Session Completed',  'Your childcare session has been marked as completed.'],
    ['New Message',        'You have a new message from your nanny.'],
];

$nannyNotifs = [
    ['New Booking Request', 'You have a new booking request from a parent. Please respond.'],
    ['Profile Verified',    'Congratulations! Your NannyApp profile is now verified.'],
    ['Payment Released',    'Payment for your completed session has been released.'],
    ['New Message',         'You have a new message from a parent.'],
    ['New Review',          'A parent left a review for your recent session. Check it out!'],
];

$adminNotifs = [
    ['New Nanny Application', 'A new nanny has submitted their application for review.'],
    ['Support Ticket Opened', 'A new support ticket has been opened and awaits response.'],
    ['Session Completed',     'A booking has been completed on the platform.'],
    ['New User Registered',   'A new user has registered on the platform.'],
];

$notifsInserted = 0;
foreach ($parentIds as $pid) {
    for ($i = 0; $i < rand(2, 5); $i++) {
        [$title, $msg] = $parentNotifs[array_rand($parentNotifs)];
        try { $insertNotif->execute([$pid, $title, $msg, rand(0,1), rand(0, 30)]); $notifsInserted++; } catch (Throwable) {}
    }
}
foreach ($nannyIds as $n) {
    for ($i = 0; $i < rand(2, 4); $i++) {
        [$title, $msg] = $nannyNotifs[array_rand($nannyNotifs)];
        try { $insertNotif->execute([$n['id'], $title, $msg, rand(0,1), rand(0, 30)]); $notifsInserted++; } catch (Throwable) {}
    }
}
$adminId = (int)db()->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetchColumn();
if ($adminId) {
    foreach ($adminNotifs as [$title, $msg]) {
        try { $insertNotif->execute([$adminId, $title, $msg, 0, rand(0, 7)]); $notifsInserted++; } catch (Throwable) {}
    }
}
seed_log("Inserted $notifsInserted notifications.");

/* ════════════════════════════════════════════════════════════
   8. MESSAGES (sample conversations)
   Schema: sender_id, receiver_id, message, is_read
   ════════════════════════════════════════════════════════════ */
$conversations = [
    ['Hi, are you available tomorrow evening from 5 PM?', 'Yes, I am available from 5 PM until 10 PM. How many children?'],
    ['Hello! I saw your profile and was impressed. Can we talk?', 'Of course! I\'d love to help your family. What age are your children?'],
    ['Quick question — do you cook meals during sessions?', 'Yes, I prepare simple, nutritious meals. Any dietary requirements?'],
    ['My toddler has a peanut allergy — is that okay?', 'Absolutely, I am very careful with allergies and always double-check labels.'],
    ['Can you help with homework for a Grade 3 and Grade 5?', 'Yes, I tutored primary school children for 3 years. Happy to help!'],
    ['We have a dog — hope that\'s not a problem?', 'No problem at all, I love animals! What time do you need me?'],
    ['Would you be able to do pick-up from school at 14:30?', 'Yes, as long as the school is within Sandton. I can manage that.'],
    ['We need someone from Saturday morning. Are you free?', 'Saturday from 9 AM works perfectly for me.'],
    ['My children are 2 and 4 years old. Do you have experience?', 'Yes, toddlers are my speciality! I have worked with that age group for 5 years.'],
    ['What is your rate for a 6-hour Saturday session?', 'My rate is R110 per hour, so R660 for 6 hours. Does that work for you?'],
];

$insertMsg = db()->prepare(
    "INSERT IGNORE INTO chat_messages (sender_id, receiver_id, message, is_read, created_at)
     VALUES (?, ?, ?, 1, NOW() - INTERVAL ? HOUR)"
);
$msgsInserted = 0;
$usedPairs    = [];
$attempts     = 0;
while ($msgsInserted < 40 && $attempts < 300) {
    $attempts++;
    $pid      = $parentIds[array_rand($parentIds)];
    $nanny    = $nannyIds[array_rand($nannyIds)];
    $nid      = $nanny['id'];
    $pairKey  = "$pid-$nid";
    if (isset($usedPairs[$pairKey])) continue;
    $usedPairs[$pairKey] = true;
    [$parentMsg, $nannyMsg] = $conversations[array_rand($conversations)];
    $hoursAgo = rand(2, 72);
    try {
        $insertMsg->execute([$pid, $nid, $parentMsg, $hoursAgo]);
        $insertMsg->execute([$nid, $pid, $nannyMsg, max(0, $hoursAgo - 1)]);
        $msgsInserted += 2;
    } catch (Throwable) {}
}
seed_log("Inserted $msgsInserted chat messages.");

/* ════════════════════════════════════════════════════════════
   9. SAVED NANNIES
   ════════════════════════════════════════════════════════════ */
$insertSaved = db()->prepare(
    "INSERT IGNORE INTO saved_nannies (parent_id, nanny_id, saved_at) VALUES (?, ?, NOW() - INTERVAL ? DAY)"
);
$savedInserted = 0;
foreach ($parentIds as $pid) {
    $count  = rand(1, 4);
    $picked = (array) array_rand($nannyIds, min($count, count($nannyIds)));
    foreach ($picked as $pi) {
        try { $insertSaved->execute([$pid, $nannyIds[$pi]['id'], rand(0, 60)]); $savedInserted++; } catch (Throwable) {}
    }
}
seed_log("Inserted $savedInserted saved-nanny records.");

/* ════════════════════════════════════════════════════════════
   10. SUPPORT TICKETS
   Schema: user_id, name, email, category, subject, message, status
   ════════════════════════════════════════════════════════════ */
$ticketData = [
    ['technical', 'Cannot access my account',        'I have been trying to log in for 2 days but the page keeps saying my credentials are incorrect.'],
    ['booking',   'Booking was not confirmed',        'I submitted a booking request 3 days ago and it still shows as pending. Please help.'],
    ['payment',   'Payment not received',             'I completed a session last week and have not received payment yet.'],
    ['booking',   'Nanny did not arrive',             'The nanny did not show up for the booked session and I could not reach her.'],
    ['technical', 'How do I update my availability?', 'I cannot figure out how to update my weekly availability on my dashboard.'],
    ['payment',   'Incorrect charge on my account',   'I was charged R480 but the booking was for 3 hours at R120/hour, which should be R360.'],
    ['booking',   'I need to cancel a booking',       'I need to cancel my upcoming booking due to a family emergency.'],
    ['technical', 'Profile photo not uploading',      'Every time I try to upload my profile photo it gives an error message.'],
    ['general',   'How do I become a verified nanny?','I submitted my documents two weeks ago but my status still shows as pending.'],
    ['safety',    'Concern about a booking',          'I had an uncomfortable experience during a session and would like to report it.'],
];
$ticketStatuses = ['open', 'open', 'in_progress', 'resolved', 'closed'];
$insertTicket = db()->prepare(
    "INSERT INTO support_tickets (user_id, name, email, category, subject, message, status, created_at)
     SELECT ?, u.full_name, u.email, ?, ?, ?, ?, NOW() - INTERVAL ? DAY
     FROM users u WHERE u.id = ? LIMIT 1"
);
$ticketsInserted = 0;
$allUserIds = [...$parentIds, ...array_column($nannyIds, 'id')];
for ($t = 0; $t < 18; $t++) {
    $uid  = $allUserIds[array_rand($allUserIds)];
    [$cat, $subj, $msg] = $ticketData[array_rand($ticketData)];
    $status  = $ticketStatuses[array_rand($ticketStatuses)];
    $daysAgo = rand(0, 60);
    try {
        $insertTicket->execute([$uid, $cat, $subj, $msg, $status, $daysAgo, $uid]);
        $ticketsInserted++;
    } catch (Throwable $e) { seed_err("Ticket error: " . $e->getMessage()); }
}
seed_log("Inserted $ticketsInserted support tickets.");

/* ════════════════════════════════════════════════════════════
   11. PAGE CONTENT — FAQ entries (DB-driven FAQ page)
   ════════════════════════════════════════════════════════════ */
$faqs = [
    ['faq-01', 'How do I book a nanny?',
     'Create a free account, browse verified nannies near you, then click "Book Now". Our 5-step wizard guides you through the date, time, address, children details and secure payment.'],
    ['faq-02', 'How do payments work?',
     'Payments are processed securely through the platform. Your card is charged at the time of booking and funds are held safely, released to the nanny only after the session is completed.'],
    ['faq-03', 'How do I become a nanny?',
     'Register as a nanny, complete your profile with your bio, skills and hourly rate, then upload your ID and certifications. Our admin team reviews your profile within 2–3 business days.'],
    ['faq-04', 'How are nannies verified?',
     'Every nanny undergoes identity verification, document review, and a background screening. Only nannies who pass all checks receive a Verified badge and appear in family searches.'],
    ['faq-05', 'Can I cancel a booking?',
     'Yes. Cancellations made more than 24 hours before the session start time incur no charge. Within 24 hours a partial fee may apply. Cancel from your Bookings dashboard.'],
    ['faq-06', 'What if the nanny does not arrive?',
     'Contact the nanny via in-app messaging first. If unreachable, open a support ticket immediately. Our team will assist with rebooking and any applicable refund.'],
    ['faq-07', 'Which cities does NannyApp serve?',
     'We currently serve Johannesburg, Sandton, Pretoria, Midrand, Centurion, Randburg, Roodepoort, Cape Town, Durban, Soweto, Boksburg, Benoni, Germiston, and East London.'],
    ['faq-08', 'Can I request the same nanny every week?',
     'Absolutely. Save your favourite nannies from the search page and rebook them directly from your Saved Nannies list. You can also message them to arrange recurring sessions.'],
    ['faq-09', 'Is my child\'s information safe?',
     'Children\'s profiles are visible only to the specific nanny assigned to a booking. We never share children\'s data with third parties. See our Privacy Policy for full details.'],
    ['faq-10', 'How do I leave a review?',
     'After a booking is marked complete you will receive a notification to leave a review. You can also go to My Bookings and click "Leave Review" next to any completed session.'],
];
$insertPage   = db()->prepare("INSERT IGNORE INTO page_content (page_key, title, body, updated_at) VALUES (?, ?, ?, NOW())");
$faqsInserted = 0;
foreach ($faqs as [$key, $title, $body]) {
    try { $insertPage->execute([$key, $title, $body]); $faqsInserted++; } catch (Throwable) {}
}
seed_log("Inserted $faqsInserted FAQ entries.");

$pageTitle = 'Seed Demo Data';
require __DIR__ . '/includes/header.php';
?>
<section class="section section-no-top">
    <div class="card stack migrate-wrap">
        <span class="tag">Admin Tool</span>
        <h1 class="heading-tight">Demo Data Seeder — Complete</h1>
        <p class="muted"><?= count($log) ?> tasks completed<?= $errs ? ', ' . count($errs) . ' error(s)' : ' with no errors' ?>.</p>

        <div class="grid-gap-8">
            <?php foreach ($log as $entry): ?>
                <div class="migrate-row">
                    <span class="migrate-status migrate-status-ok">OK</span>
                    <span class="migrate-sql"><?= e($entry) ?></span>
                </div>
            <?php endforeach; ?>
            <?php foreach ($errs as $entry): ?>
                <div class="migrate-row">
                    <span class="migrate-status migrate-status-bad">ERR</span>
                    <span class="migrate-sql"><?= e($entry) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="hero-cta">
            <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-primary">Admin Dashboard</a>
            <a href="<?= url('index.php') ?>" class="btn">Landing Page</a>
            <a href="<?= url('admin/users.php') ?>" class="btn">All Users</a>
            <a href="<?= url('admin/verifications.php') ?>" class="btn">Nanny Verifications</a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
