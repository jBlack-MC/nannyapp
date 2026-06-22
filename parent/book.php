<?php
require_once __DIR__ . '/../config/config.php';

$nannyId = (int) ($_GET['nanny'] ?? 0);

// Column introspection — some columns only exist after migrate_v2 runs
$_npCols = [];
try {
    foreach (db()->query('SHOW COLUMNS FROM nanny_profiles')->fetchAll(PDO::FETCH_COLUMN) as $c) {
        $_npCols[strtolower((string)$c)] = true;
    }
} catch (Throwable) {}
$_npCol = static function (string $name, string $fallback) use ($_npCols): string {
    return isset($_npCols[strtolower($name)]) ? "p.$name" : $fallback;
};

$stmt = db()->prepare(
    "SELECT u.id, u.full_name, u.profile_image, u.created_at AS member_since,
            p.bio, p.experience_years, p.hourly_rate, p.location,
            p.skills, p.availability, p.average_rating, p.verification_status,
            " . $_npCol('languages', "''")      . " AS languages,
            " . $_npCol('qualifications', "''") . " AS qualifications,
            " . $_npCol('specialisations', "''") . " AS specialisations,
            " . $_npCol('banner_image', 'NULL')  . " AS banner_image,
            (SELECT COUNT(*) FROM reviews r WHERE r.nanny_id = u.id) AS review_count,
            (SELECT COUNT(*) FROM bookings b WHERE b.nanny_id = u.id AND b.status IN ('completed','confirmed')) AS booking_count
     FROM users u JOIN nanny_profiles p ON p.user_id = u.id
     WHERE u.id = ? AND u.role = 'nanny'"
);
$stmt->execute([$nannyId]);
$nanny = $stmt->fetch();

if (!$nanny) {
    flash('That nanny could not be found.', 'error');
    redirect('parent/nannies.php');
}

// Profile views
try {
    db()->prepare('UPDATE nanny_profiles SET profile_views = profile_views + 1 WHERE user_id=?')->execute([$nannyId]);
} catch (Throwable) {}

// Load nanny availability
$availRows = [];
try {
    $av = db()->prepare('SELECT * FROM nanny_availability WHERE nanny_id=? ORDER BY day_of_week');
    $av->execute([$nannyId]);
    foreach ($av->fetchAll() as $row) $availRows[$row['day_of_week']] = $row;
} catch (Throwable) {}

// Reviews
$reviews = db()->prepare(
    "SELECT r.rating, r.comment, r.created_at, u.full_name AS parent_name
     FROM reviews r JOIN users u ON u.id=r.reviewer_id
     WHERE r.nanny_id=? ORDER BY r.created_at DESC LIMIT 6"
);
$reviews->execute([$nannyId]);
$reviews = $reviews->fetchAll();

// Saved state for logged-in parents
$isSaved = false;
$csrfToken = csrf_token();
if (is_logged_in() && user_role() === 'parent') {
    try {
        $sv = db()->prepare('SELECT 1 FROM saved_nannies WHERE parent_id=? AND nanny_id=?');
        $sv->execute([current_user()['id'], $nannyId]);
        $isSaved = (bool) $sv->fetch();
    } catch (Throwable) {}
}

// =====================================================================
//  BOOKING WIZARD  (multi-step, session-backed)
// =====================================================================
$wizardKey = 'bwiz_' . $nannyId;
$step      = (int) ($_GET['step'] ?? 0);    // 0 = profile view; 1-5 = wizard steps
$errors    = [];

if ($step >= 1 && !is_logged_in()) {
    flash('Please log in to book a nanny.', 'error');
    redirect('auth/login.php');
}
if ($step >= 1 && user_role() !== 'parent') {
    flash('Only parents can book nannies.', 'error');
    redirect(dashboard_path(user_role()));
}

// --- Wizard state stored in session ---
if (!isset($_SESSION[$wizardKey])) $_SESSION[$wizardKey] = [];
$wz = &$_SESSION[$wizardKey];

// --- STEP 2 POST: datetime / duration ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    verify_csrf();
    $dt       = trim($_POST['datetime'] ?? '');
    $duration = (float) ($_POST['duration'] ?? 0);

    try {
        $chosen = new DateTimeImmutable($dt);
    } catch (Throwable $e) {
        $chosen = null;
    }

    if (!$chosen || $chosen->getTimestamp() <= time()) {
        $errors[] = 'Please choose a future date and time.';
    }
    if ($duration < 1 || $duration > 24) {
        $errors[] = 'Duration must be between 1 and 24 hours.';
    }
    if (!$errors) {
        if (nanny_has_booking_conflict($nannyId, $chosen->format('Y-m-d H:i:s'), $duration)) {
            $errors[] = 'This nanny is already booked at that time. Please choose a different slot.';
        }
    }

    if (!$errors) {
        $wz['date_time'] = $chosen->format('Y-m-d H:i:s');
        $wz['duration']  = $duration;
        $wz['amount']    = $duration * (float) $nanny['hourly_rate'];
        redirect('parent/book.php?nanny=' . $nannyId . '&step=3');
    }
}

// --- STEP 3 POST: address / children details / notes ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 3) {
    verify_csrf();
    $address  = trim($_POST['address']  ?? '');
    $children = trim($_POST['children'] ?? '');
    $notes    = trim($_POST['notes']    ?? '');

    if (!$address) $errors[] = 'Please enter the care address.';

    if (!$errors) {
        $wz['address']  = $address;
        $wz['children'] = $children;
        $wz['notes']    = $notes;
        redirect('parent/book.php?nanny=' . $nannyId . '&step=4');
    }
}

// --- STEP 4 POST: confirm & create booking ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 4) {
    verify_csrf();

    if (empty($wz['date_time']) || empty($wz['address'])) {
        flash('Your booking session expired. Please start again.', 'error');
        redirect('parent/book.php?nanny=' . $nannyId . '&step=2');
    }

    // Re-check just before insert in case availability changed after step 2.
    if (nanny_has_booking_conflict($nannyId, (string) $wz['date_time'], (float) $wz['duration'])) {
        flash('That time slot is no longer available. Please choose another time.', 'error');
        redirect('parent/book.php?nanny=' . $nannyId . '&step=2');
    }

    $pdo = db();
    $pdo->beginTransaction();

    $ref = 'BK' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

    try {
        // Insert booking
        $ins = $pdo->prepare(
            'INSERT INTO bookings (parent_id, nanny_id, date_time, duration, location, notes, status)
             VALUES (?,?,?,?,?,?,"pending")'
        );
        $ins->execute([
            current_user()['id'], $nannyId,
            $wz['date_time'], $wz['duration'],
            $wz['address'],
            ($wz['notes'] ?: null),
        ]);
        $bookingId = (int) $pdo->lastInsertId();

        // Set booking_ref if column exists
        try {
            $pdo->prepare('UPDATE bookings SET booking_ref=?, children_details=?, booking_address=? WHERE id=?')
                ->execute([$ref, ($wz['children'] ?: null), $wz['address'], $bookingId]);
        } catch (Throwable) {}

        // Payment record
        $pdo->prepare('INSERT INTO payments (booking_id, amount, status) VALUES (?,?,"pending")')
            ->execute([$bookingId, $wz['amount']]);

        $pdo->commit();

        // Notify nanny
        notify($nannyId, 'New booking request',
            current_user()['full_name'] . ' requested care on '
                . date('D d M \a\t H:i', strtotime($wz['date_time'])) . '.',
            'nanny/bookings.php'
        );

        // Send confirmation email to parent
        $me = current_user();
        $textBody = 'Hi ' . $me['full_name'] . ",\n\nYour booking request with " . $nanny['full_name'] . " has been submitted.\n\n" .
            'Date & time: ' . date('D d M Y \a\t H:i', strtotime($wz['date_time'])) . "\n" .
            'Duration: ' . $wz['duration'] . ' hours\n' .
            'Address: ' . $wz['address'] . "\n" .
            'Estimated cost: R' . number_format($wz['amount'], 2) . "\n" .
            'Reference: ' . $ref . "\n\n" .
            'Your nanny will confirm or respond shortly. You can track your booking on your dashboard.';

        $htmlBody = '<p>Hi ' . htmlspecialchars($me['full_name']) . ',</p>' .
            '<p>Your booking request with <strong>' . htmlspecialchars($nanny['full_name']) . '</strong> has been submitted.</p>' .
            '<ul>' .
            '<li><strong>Date &amp; time:</strong> ' . date('D d M Y \a\t H:i', strtotime($wz['date_time'])) . '</li>' .
            '<li><strong>Duration:</strong> ' . $wz['duration'] . ' hours</li>' .
            '<li><strong>Address:</strong> ' . htmlspecialchars($wz['address']) . '</li>' .
            '<li><strong>Estimated cost:</strong> R' . number_format($wz['amount'], 2) . '</li>' .
            '<li><strong>Reference:</strong> ' . $ref . '</li>' .
            '</ul>' .
            '<p>Your nanny will confirm or respond shortly. You can track your booking on your dashboard.</p>';

        send_email($me['email'], 'Booking confirmed — ' . APP_NAME, $textBody, $htmlBody);

        $wz['booking_id'] = $bookingId;
        $wz['ref']        = $ref;
        redirect('parent/book.php?nanny=' . $nannyId . '&step=5');

    } catch (Throwable $e) {
        $pdo->rollBack();
        $errors[] = 'Something went wrong. Please try again.';
    }
}

// Computed nanny stats
$rating   = (float) $nanny['average_rating'];
$reviewN  = (int) $nanny['review_count'];
$bookings = (int) $nanny['booking_count'];
$rate     = (float) $nanny['hourly_rate'];
$skills   = array_filter(array_map('trim', explode(',', (string) $nanny['skills'])));
$langs    = array_filter(array_map('trim', explode(',', (string) $nanny['languages'])));
$quals    = array_filter(array_map('trim', explode(',', (string) ($nanny['qualifications'] ?? ''))));
$specs    = array_filter(array_map('trim', explode(',', (string) ($nanny['specialisations'] ?? ''))));
$dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

$trust = 30 + min(40, (int)($rating / 5 * 40)) + min(30, (int)(min($bookings, 50) / 50 * 30));

$pageTitle = $step >= 1 ? 'Book ' . $nanny['full_name'] : $nanny['full_name'];
require __DIR__ . '/../includes/header.php';

// =====================================================================
//  STEP 5: SUCCESS
// =====================================================================
if ($step === 5):
?>
<div class="auth pb-auth-success">
    <div class="auth-main pb-auth-main">
        <div class="font-4xl auth-footnote">🎉</div>
        <h1 class="heading-tight">Booking sent!</h1>
        <p class="muted">Your request has been sent to <?= e($nanny['full_name']) ?>. They will confirm shortly.</p>

        <div class="card pb-card-left">
            <table class="pb-success-table">
                <tr><td class="muted pb-cell">Reference</td><td><strong><?= e($wz['ref'] ?? '—') ?></strong></td></tr>
                <tr><td class="muted pb-cell pb-cell-top">Nanny</td><td><?= e($nanny['full_name']) ?></td></tr>
                <tr><td class="muted pb-cell pb-cell-top">Date &amp; time</td><td><?= date('D d M Y \a\t H:i', strtotime($wz['date_time'] ?? '')) ?></td></tr>
                <tr><td class="muted pb-cell pb-cell-top">Duration</td><td><?= ($wz['duration'] ?? 0) ?> hours</td></tr>
                <tr><td class="muted pb-cell pb-cell-top">Address</td><td><?= e($wz['address'] ?? '') ?></td></tr>
                <tr><td class="muted pb-cell pb-cell-top">Estimated cost</td><td><strong>R<?= number_format((float)($wz['amount'] ?? 0), 2) ?></strong></td></tr>
            </table>
        </div>

        <div class="pb-actions">
            <a class="btn btn-primary" href="<?= url('parent/bookings.php') ?>">View my bookings →</a>
            <a class="btn" href="<?= url('parent/nannies.php') ?>">Find more nannies</a>
        </div>
        <?php unset($_SESSION[$wizardKey]); ?>
    </div>
</div>
<?php
    require __DIR__ . '/../includes/footer.php';
    exit;
endif;

// =====================================================================
//  WIZARD STEPS 1–4: Shared step indicator + content
// =====================================================================

// Helper: step indicator
function wizard_steps(int $current): void {
    $steps = ['Profile', 'Date & time', 'Details', 'Confirm'];
    echo '<div class="wizard-steps">';
    foreach ($steps as $i => $label) {
        $n   = $i + 1;
        $cls = $n < $current ? 'ws-done' : ($n === $current ? 'ws-active' : 'ws-pending');
        echo '<div class="ws ' . $cls . '">';
        if ($n < $current) {
            echo '<span class="ws-num">✓</span>';
        } else {
            echo '<span class="ws-num">' . $n . '</span>';
        }
        echo '<span class="ws-label">' . htmlspecialchars($label) . '</span></div>';
        if ($n < 4) echo '<div class="ws-line ' . ($n < $current ? 'ws-line-done' : '') . '"></div>';
    }
    echo '</div>';
}

if ($step >= 1):
?>
<a class="back-link" href="<?= url($step > 1 ? 'parent/book.php?nanny=' . $nannyId . '&step=' . ($step - 1) : 'parent/book.php?nanny=' . $nannyId) ?>">← Back</a>
<?php wizard_steps($step); ?>

<?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>
<?php endif; ?>

<?php
// =====================================================================
//  STEP 0 / PROFILE VIEW
// =====================================================================
if ($step === 0):
?>
<a class="back-link" href="<?= url('parent/nannies.php') ?>">← Back to all nannies</a>

<div class="profile-layout">
    <!-- Nanny profile -->
    <div>
        <div class="card pb-profile-card">
            <!-- Banner -->
            <div class="pro-banner">
                <?php if ($nanny['banner_image']): ?>
                    <img src="<?= e(url($nanny['banner_image'])) ?>" alt="">
                    <div class="pro-banner-overlay"></div>
                <?php endif; ?>
            </div>

            <!-- Header strip -->
            <div class="pro-header">
                <div class="pro-avatar-wrap">
                    <?= avatar($nanny['full_name'], $nanny['profile_image'] ?? null, 'avatar avatar-xl') ?>
                    <?php if ($nanny['verification_status'] === 'verified'): ?>
                        <span class="pro-verified-badge" title="Verified nanny">✓</span>
                    <?php endif; ?>
                </div>
                <div class="pb-flex-1">
                    <h1 class="heading-tight"><?= e($nanny['full_name']) ?></h1>
                    <?php if ($specs): ?><div class="muted mt-2"><?= e(implode(' · ', array_slice($specs, 0, 2))) ?></div><?php endif; ?>
                    <div class="pb-meta-head">
                        <?php if ($nanny['location']): ?><span>📍 <?= e($nanny['location']) ?></span><?php endif; ?>
                        <span>⏳ <?= (int)$nanny['experience_years'] ?> yrs experience</span>
                        <span>💰 R<?= number_format($rate, 0) ?>/hr</span>
                    </div>
                    <div class="pb-stars">
                        <?php for ($i=1;$i<=5;$i++): ?><span class="<?= $i<=round($rating)?'star-on':'star-off' ?>">★</span><?php endfor; ?>
                        <span class="muted pb-stars-note"><?= number_format($rating,1) ?> (<?= $reviewN ?> review<?= $reviewN===1?'':'s' ?>)</span>
                    </div>
                </div>
                <div class="pb-side-actions">
                    <button class="save-btn <?= $isSaved ? 'saved' : '' ?>"
                            data-save-nanny="<?= $nannyId ?>"
                            data-base="<?= BASE_URL ?>"
                            data-csrf="<?= e($csrfToken) ?>">
                        <?= $isSaved ? '❤ Saved' : '♡ Save' ?>
                    </button>
                    <a class="btn btn-sm" href="<?= url('messages.php?with=' . $nannyId) ?>">💬 Message</a>
                </div>
            </div>

            <!-- Stats strip -->
            <div class="pb-stat-grid">
                <div class="pb-stat-cell"><b><?= (int)$nanny['experience_years'] ?></b><div class="muted pb-stat-caption">Years exp.</div></div>
                <div class="pb-stat-cell"><b><?= $bookings ?></b><div class="muted pb-stat-caption">Bookings</div></div>
                <div class="pb-stat-cell"><b><?= $reviewN ?></b><div class="muted pb-stat-caption">Reviews</div></div>
                <div class="pb-stat-cell"><b><?= date('Y') - date('Y', strtotime($nanny['member_since'])) ?>y</b><div class="muted pb-stat-caption">Member</div></div>
            </div>
        </div>

        <!-- Trust score -->
        <div class="card pb-trust-card">
            <svg class="trust-ring" width="72" height="72" viewBox="0 0 72 72">
                <circle cx="36" cy="36" r="29" class="bg" stroke="var(--line)"/>
                <circle cx="36" cy="36" r="29" class="fg"
                        stroke-dasharray="<?= round($trust / 100 * 182) ?> 182"/>
            </svg>
            <div>
                <div class="pb-trust-score"><?= $trust ?>% Trust Score</div>
                <div class="muted pb-trust-note">Based on verification, rating &amp; completed bookings</div>
            </div>
        </div>

        <!-- About -->
        <?php if ($nanny['bio']): ?>
        <div class="card pb-card-mt">
            <h3>About <?= e(explode(' ', $nanny['full_name'])[0]) ?></h3>
            <p class="pb-bio"><?= e($nanny['bio']) ?></p>
        </div>
        <?php endif; ?>

        <!-- Skills + Qualifications -->
        <?php if ($skills || $quals): ?>
        <div class="card pb-card-mt pb-card-stack">
            <?php if ($skills): ?>
            <div>
                <h4 class="pb-subhead">Skills</h4>
                <div class="tags">
                    <?php foreach ($skills as $s): ?><span class="tag"><?= e($s) ?></span><?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($quals): ?>
            <div>
                <h4 class="pb-subhead">Qualifications</h4>
                <ul class="pb-list">
                    <?php foreach ($quals as $q): ?><li>✓ <?= e($q) ?></li><?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <?php if ($langs): ?>
            <div>
                <h4 class="pb-subhead">Languages</h4>
                <div class="tags">
                    <?php foreach ($langs as $l): ?><span class="tag"><?= e($l) ?></span><?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Weekly availability -->
        <?php if ($availRows): ?>
        <div class="card pb-card-mt">
            <h3>Weekly availability</h3>
            <div class="avail-week">
                <?php for ($d = 0; $d < 7; $d++):
                    $row  = $availRows[$d] ?? null;
                    $avl  = $row && $row['is_available'];
                    $cls  = $avl ? '' : 'off';
                ?>
                <div class="avail-day <?= $cls ?>">
                    <div><?= $dayNames[$d] ?></div>
                    <?php if ($avl): ?>
                        <div class="avail-on">
                            <?= substr($row['time_start'],0,5) ?>–<?= substr($row['time_end'],0,5) ?>
                        </div>
                    <?php else: ?>
                        <div class="avail-off">Off</div>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Reviews -->
        <?php if ($reviews): ?>
        <div class="card pb-card-mt">
            <h3>Reviews <span class="badge badge-ok"><?= $reviewN ?></span></h3>
            <div class="pb-reviews">
                <?php foreach ($reviews as $rv): ?>
                <div class="pb-review-item">
                    <div class="pb-review-head">
                        <?= avatar($rv['parent_name'], null, 'avatar-sm') ?>
                        <div>
                            <strong><?= e($rv['parent_name']) ?></strong>
                            <div class="pb-review-stars"><?= str_repeat('★', (int)$rv['rating']) ?><?= str_repeat('☆', 5 - (int)$rv['rating']) ?></div>
                        </div>
                        <span class="muted pb-review-date"><?= e(date('d M Y', strtotime($rv['created_at']))) ?></span>
                    </div>
                    <?php if ($rv['comment']): ?>
                        <p class="muted pb-review-text"><?= e($rv['comment']) ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Booking sidebar -->
    <div>
        <div class="card stack booking-sidebar">
            <div class="pb-price">
                <div class="pb-price-amount">R<?= number_format($rate, 0) ?></div>
                <div class="muted">per hour</div>
            </div>
            <?php if (is_logged_in() && user_role() === 'parent'): ?>
                <a class="btn btn-primary btn-block" href="<?= url('parent/book.php?nanny=' . $nannyId . '&step=1') ?>">
                    Book <?= e(explode(' ', $nanny['full_name'])[0]) ?> →
                </a>
                <a class="btn btn-block" href="<?= url('messages.php?with=' . $nannyId) ?>">Send a message</a>
            <?php elseif (!is_logged_in()): ?>
                <a class="btn btn-primary btn-block" href="<?= url('auth/register.php') ?>">Create account to book</a>
                <a class="btn btn-block" href="<?= url('auth/login.php') ?>">Log in</a>
            <?php else: ?>
                <p class="muted pb-parent-only">Only parents can book nannies.</p>
            <?php endif; ?>
            <ul class="pb-side-list">
                <li>✅ No booking fees</li>
                <li>✅ Free to message first</li>
                <li>✅ Pay only when confirmed</li>
                <li>✅ Cancel up to 24hrs before</li>
            </ul>
        </div>
    </div>
</div>

<?php
// =====================================================================
//  STEP 1: Confirm nanny choice
// =====================================================================
elseif ($step === 1):
?>
<div class="mw-600">
    <h1 class="pb-step-title">You chose:</h1>

    <div class="card pb-choice-card">
        <?= avatar($nanny['full_name'], $nanny['profile_image'] ?? null, 'avatar-lg') ?>
        <div class="pb-flex-1">
            <h3 class="heading-tight"><?= e($nanny['full_name']) ?></h3>
            <div class="muted"><?= e($nanny['location'] ?: '') ?> · R<?= number_format($rate,0) ?>/hr</div>
            <div class="pb-choice-stars"><?= str_repeat('★', (int)round($rating)) ?> <span class="muted"><?= number_format($rating,1) ?> (<?= $reviewN ?>)</span></div>
        </div>
        <a class="btn btn-sm" href="<?= url('parent/nannies.php') ?>">Change</a>
    </div>

    <a class="btn btn-primary btn-block-cta" href="<?= url('parent/book.php?nanny=' . $nannyId . '&step=2') ?>">
        Proceed to choose date &amp; time →
    </a>
</div>

<?php
// =====================================================================
//  STEP 2: Date, time, duration
// =====================================================================
elseif ($step === 2):
$minDateTime = date('Y-m-d\TH:i', strtotime('+30 minutes'));
$prevDateTime = isset($wz['date_time']) ? date('Y-m-d\TH:i', strtotime($wz['date_time'])) : $minDateTime;
$prevDur  = $wz['duration'] ?? 3;
?>
<div class="mw-560">
    <h1>Choose a date &amp; time</h1>
    <p class="muted">When do you need <?= e(explode(' ', $nanny['full_name'])[0]) ?> to arrive?</p>

    <form method="post" class="card stack">
        <?= csrf_field() ?>
        <div class="grid grid-2 grid-gap-12">
            <div class="field field-zero">
                <label for="bk-datetime">Date &amp; time</label>
                <input id="bk-datetime" type="datetime-local" name="datetime" min="<?= $minDateTime ?>" value="<?= e($prevDateTime) ?>" required>
            </div>
            <div class="field field-zero">
                <label for="bk-dur">Duration (hours)</label>
                <select id="bk-dur" name="duration">
                    <?php foreach ([1,1.5,2,2.5,3,4,5,6,7,8,10,12] as $h): ?>
                        <option value="<?= $h ?>" <?= (float)$prevDur === $h ? 'selected' : '' ?>>
                            <?= $h == (int)$h ? $h : $h ?> hour<?= $h > 1 ? 's' : '' ?>
                            — R<?= number_format($h * $rate, 0) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Availability hint -->
        <?php if ($availRows): ?>
        <div class="flash flash-info flash-no-margin">
            <strong><?= e(explode(' ', $nanny['full_name'])[0]) ?>'s availability:</strong>
            <?php
            $open = [];
            foreach ($availRows as $d => $row) {
                if ($row['is_available']) $open[] = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][$d];
            }
            echo e($open ? implode(', ', $open) : 'Contact nanny to confirm');
            ?>
        </div>
        <?php endif; ?>

        <button class="btn btn-primary">Continue →</button>
    </form>
</div>

<?php
// =====================================================================
//  STEP 3: Address & children details
// =====================================================================
elseif ($step === 3):
$me = current_user();
// Try to prefill from parent profile
$parentAddr = '';
try {
    $pa = db()->prepare('SELECT address FROM users WHERE id=?');
    $pa->execute([$me['id']]);
    $parentAddr = $pa->fetchColumn() ?: '';
} catch (Throwable) {}

$childCount = 0;
$childNames = '';
try {
    $pch = db()->prepare('SELECT full_name FROM children WHERE parent_id=? ORDER BY id LIMIT 5');
    $pch->execute([$me['id']]);
    $rows = $pch->fetchAll();
    $childCount = count($rows);
    $childNames = implode(', ', array_column($rows, 'full_name'));
} catch (Throwable) {}
?>
<div class="mw-560">
    <h1>Where &amp; who?</h1>
    <p class="muted">Tell us where care will happen and a little about your children.</p>

    <form method="post" class="card stack">
        <?= csrf_field() ?>

        <div class="field">
            <label>Care address <span class="muted">(where the nanny should come to)</span></label>
            <input name="address" value="<?= e($_POST['address'] ?? ($wz['address'] ?? $parentAddr)) ?>"
                   placeholder="e.g. 12 Oak Street, Sandton, Johannesburg" required>
        </div>

        <div class="field">
            <label>Children details <span class="muted">(ages, names, any allergies)</span></label>
            <textarea name="children" rows="3"
                      placeholder="e.g. Emma (3yrs, peanut allergy) and James (6yrs, loves dinosaurs)"><?= e($_POST['children'] ?? ($wz['children'] ?? ($childCount ? "I have $childCount child" . ($childCount > 1 ? 'ren' : '') . ($childNames ? ": $childNames" : '') : ''))) ?></textarea>
        </div>

        <div class="field">
            <label>Special instructions <span class="muted">(optional)</span></label>
            <textarea name="notes" rows="3"
                      placeholder="Nap schedule, dietary restrictions, access instructions, pets at home…"><?= e($_POST['notes'] ?? ($wz['notes'] ?? '')) ?></textarea>
        </div>

        <button class="btn btn-primary">Review booking →</button>
    </form>
</div>

<?php
// =====================================================================
//  STEP 4: Review & confirm
// =====================================================================
elseif ($step === 4):

if (empty($wz['date_time'])) {
    flash('Your session expired. Please start again.', 'error');
    redirect('parent/book.php?nanny=' . $nannyId . '&step=2');
}

$totalCost = $wz['amount'];
?>
<div class="mw-600">
    <h1>Review &amp; confirm</h1>
    <p class="muted">Check the details below before confirming your booking.</p>

    <div class="card stack pb-summary-card">
        <div class="section-head pb-summary-head">
            <h3 class="heading-tight">Booking summary</h3>
            <a class="pb-edit-link" href="<?= url('parent/book.php?nanny=' . $nannyId . '&step=2') ?>">Edit</a>
        </div>
        <table class="pb-summary-table">
            <?php
            $rows = [
                'Nanny'         => e($nanny['full_name']),
                'Date &amp; time' => date('l, d F Y \a\t H:i', strtotime($wz['date_time'])),
                'Duration'      => $wz['duration'] . ' hour' . ($wz['duration'] > 1 ? 's' : ''),
                'Location'      => e($wz['address']),
            ];
            if (!empty($wz['children'])) $rows['Children'] = e($wz['children']);
            if (!empty($wz['notes']))    $rows['Notes']    = e($wz['notes']);
            foreach ($rows as $k => $v): ?>
            <tr>
                <td class="muted pb-summary-key"><?= $k ?></td>
                <td class="pb-summary-val"><?= $v ?></td>
            </tr>
            <?php endforeach; ?>
            <tr>
                <td class="muted pb-summary-rate-key">Rate</td>
                <td class="pb-summary-rate-val">R<?= number_format($rate, 0) ?>/hr</td>
            </tr>
            <tr>
                <td class="pb-summary-total-key">Total</td>
                <td class="pb-summary-total-val">R<?= number_format($totalCost, 2) ?></td>
            </tr>
        </table>
    </div>

    <div class="flash flash-info">
        <strong>How payment works:</strong> Your card will be charged after the nanny confirms the booking. Payment is held securely until the session is complete.
    </div>

    <form method="post" class="pb-form-actions">
        <?= csrf_field() ?>
        <button class="btn btn-primary pb-form-submit">
            ✓ Confirm booking — R<?= number_format($totalCost, 2) ?>
        </button>
        <a class="btn pb-cancel" href="<?= url('parent/nannies.php') ?>">Cancel</a>
    </form>
</div>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
