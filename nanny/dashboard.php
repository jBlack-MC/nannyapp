<?php
require_once __DIR__ . '/../config/config.php';
require_role('nanny');

$me  = current_user();
$uid = $me['id'];

$profile = db()->prepare('SELECT * FROM nanny_profiles WHERE user_id = ?');
$profile->execute([$uid]);
$p = $profile->fetch();

$counts = db()->prepare(
    "SELECT
        SUM(status='pending')   AS pending,
        SUM(status='confirmed') AS confirmed,
        SUM(status='completed') AS completed,
        SUM(date_time > NOW() AND status='confirmed') AS upcoming
     FROM bookings WHERE nanny_id = ?"
);
$counts->execute([$uid]);
$c = $counts->fetch();

$earn = db()->prepare(
    "SELECT IFNULL(SUM(pay.amount),0)
     FROM payments pay JOIN bookings b ON b.id=pay.booking_id
    WHERE b.nanny_id=? AND pay.status='paid' AND b.status='completed'
       AND MONTH(pay.created_at)=MONTH(NOW()) AND YEAR(pay.created_at)=YEAR(NOW())"
);
$earn->execute([$uid]);
$monthEarnings = (float) $earn->fetchColumn();

$totalEarned = db()->prepare(
    "SELECT IFNULL(SUM(pay.amount),0) FROM payments pay
     JOIN bookings b ON b.id=pay.booking_id
    WHERE b.nanny_id=? AND pay.status='paid' AND b.status='completed'"
);
$totalEarned->execute([$uid]);
$totalEarned = (float) $totalEarned->fetchColumn();

$reviewCount = db()->prepare('SELECT COUNT(*) FROM reviews WHERE nanny_id=?');
$reviewCount->execute([$uid]);
$reviewCount = (int) $reviewCount->fetchColumn();

$unreadMsgs   = unread_message_count();
$unreadNotifs = unread_notification_count();

// Trust score
$trust = 30; // verified badge
$trust += min(40, (int)(((float)($p['average_rating'] ?? 0)) / 5 * 40));
$trust += min(30, (int)(min((int)($c['completed'] ?? 0), 50) / 50 * 30));

// Profile completeness
$fields = ['bio','location','skills','hourly_rate','languages'];
$filled = array_filter($fields, fn($f) => !empty($p[$f]));
if ($p['photo_url'])   $filled[] = 'photo';
if ($p['banner_image']) $filled[] = 'banner';
$complete = (int)(count($filled) / (count($fields) + 2) * 100);

// Pending requests
$pending = db()->prepare(
    "SELECT b.*, u.full_name AS parent_name
     FROM bookings b JOIN users u ON u.id=b.parent_id
     WHERE b.nanny_id=? AND b.status='pending'
     ORDER BY b.created_at DESC LIMIT 5"
);
$pending->execute([$uid]);
$pendingRows = $pending->fetchAll();

// Monthly earnings — last 6 months
$monthlyEarn = db()->prepare(
    "SELECT DATE_FORMAT(pay.created_at,'%Y-%m') AS ym,
            DATE_FORMAT(pay.created_at,'%b') AS label,
            IFNULL(SUM(pay.amount),0) AS total,
            COUNT(*) AS jobs
     FROM payments pay JOIN bookings b ON b.id=pay.booking_id
    WHERE b.nanny_id=? AND pay.status='paid' AND b.status='completed'
       AND pay.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY ym, label ORDER BY ym ASC"
);
$monthlyEarn->execute([$uid]);
$earnMonths  = $monthlyEarn->fetchAll();
$maxEarnMo   = max(array_column($earnMonths, 'total') ?: [1]);

// Bookings by month — last 6 months (all statuses)
$monthlyBk = db()->prepare(
    "SELECT DATE_FORMAT(date_time,'%Y-%m') AS ym,
            DATE_FORMAT(date_time,'%b') AS label,
            COUNT(*) AS total,
            SUM(status='completed') AS completed_ct
     FROM bookings WHERE nanny_id=?
       AND date_time >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY ym, label ORDER BY ym ASC"
);
$monthlyBk->execute([$uid]);
$bkMonths  = $monthlyBk->fetchAll();
$maxBkMo   = max(array_column($bkMonths, 'total') ?: [1]);

// Activity feed
$nannyActivity = [];
try {
    $na = db()->prepare(
        "SELECT title, message, is_read, created_at FROM notifications
         WHERE user_id=? ORDER BY created_at DESC LIMIT 8"
    );
    $na->execute([$uid]);
    $nannyActivity = $na->fetchAll();
} catch (Throwable $e) {}

// Recent reviews
$recentReviews = [];
try {
    $rr = db()->prepare(
        "SELECT r.rating, r.comment, r.created_at, u.full_name AS reviewer_name
         FROM reviews r JOIN users u ON u.id=r.reviewer_id
         WHERE r.nanny_id=? ORDER BY r.created_at DESC LIMIT 3"
    );
    $rr->execute([$uid]);
    $recentReviews = $rr->fetchAll();
} catch (Throwable $e) {}

$pageTitle = 'Nanny Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head">
    <div>
        <h1>Hi, <?= e(explode(' ', $me['full_name'])[0]) ?> 👋</h1>
        <p class="muted dash-head-sub">Your childcare professional overview</p>
    </div>
    <div class="dash-head-actions">
        <span class="ts-pill">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Trust score: <?= $trust ?>%
        </span>
        <?= status_badge($p['verification_status'] ?? 'pending') ?>
    </div>
</div>

<?php if (($p['verification_status'] ?? 'pending') !== 'verified'): ?>
<div class="flash flash-info">
    Your profile is <strong><?= e($p['verification_status'] ?? 'pending') ?></strong> — complete your
    <a href="<?= url('nanny/profile.php') ?>">professional profile</a> to appear in parent search results.
</div>
<?php endif; ?>

<?php if ($complete < 80): ?>
<div class="profile-complete-bar">
    <div class="panel-row-info">
        <div class="text-compact-strong">Profile <?= $complete ?>% complete — add more details to get more bookings</div>
        <div class="pc-progress"><div class="pc-fill" data-width-pct="<?= $complete ?>"></div></div>
    </div>
    <a class="btn btn-sm" href="<?= url('nanny/profile.php') ?>">Complete profile →</a>
</div>
<?php endif; ?>

<div class="stats section">
    <div class="stat"><span class="stat-ico">📥</span><b><?= (int)($c['pending'] ?? 0) ?></b>New requests</div>
    <div class="stat"><span class="stat-ico">🗓️</span><b><?= (int)($c['upcoming'] ?? 0) ?></b>Upcoming</div>
    <div class="stat"><span class="stat-ico">✅</span><b><?= (int)($c['completed'] ?? 0) ?></b>Completed</div>
    <div class="stat"><span class="stat-ico">💰</span><b>R<?= number_format($monthEarnings, 0) ?></b>This month</div>
    <div class="stat"><span class="stat-ico">💎</span><b>R<?= number_format($totalEarned, 0) ?></b>Total earned</div>
    <div class="stat"><span class="stat-ico">⭐</span><b><?= number_format((float)($p['average_rating'] ?? 0), 1) ?></b>Rating (<?= $reviewCount ?>)</div>
</div>

<!-- Pending requests -->
<?php if ($pendingRows): ?>
<div class="card section">
    <div class="section-head section-head-tight">
        <h2 class="heading-tight">Pending requests <span class="badge badge-warn"><?= count($pendingRows) ?></span></h2>
        <a href="<?= url('nanny/bookings.php') ?>">Manage all →</a>
    </div>
    <div class="grid-gap-10">
    <?php foreach ($pendingRows as $b): ?>
        <div class="panel-row panel-row-warn">
            <?= avatar($b['parent_name'], null, 'avatar-sm') ?>
            <div class="panel-row-info">
                <strong><?= e($b['parent_name']) ?></strong>
                <div class="muted panel-row-muted"><?= e(date('D d M Y \a\t H:i', strtotime($b['date_time']))) ?> · <?= e(rtrim(rtrim((string)$b['duration'],'0'),'.')) ?> hrs</div>
                <?php if ($b['notes']): ?><div class="muted panel-row-note">📝 <?= e(substr($b['notes'],0,60)) ?>…</div><?php endif; ?>
            </div>
            <a class="btn btn-sm btn-primary" href="<?= url('nanny/bookings.php') ?>">Respond</a>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Charts -->
<?php if ($earnMonths || $bkMonths): ?>
<div class="grid grid-2 section">
    <div class="card">
        <h2 class="heading-chart">Earnings — last 6 months</h2>
        <?php if ($earnMonths): ?>
        <div class="earnings-bars">
            <?php foreach ($earnMonths as $m):
                $pct = $maxEarnMo > 0 ? round((float)$m['total'] / $maxEarnMo * 100) : 0;
            ?>
            <div class="e-bar-col">
                <div class="e-bar-val bar-val-xs">R<?= number_format((float)$m['total'],0) ?></div>
                <div class="e-bar e-bar-gold" data-pct="<?= $pct ?>" data-height-pct="<?= $pct ?>"></div>
                <div class="e-bar-label"><?= e($m['label']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="muted text-xs mt-12 centered">
            <a href="<?= url('nanny/earnings.php') ?>">Full earnings breakdown →</a>
        </div>
        <?php else: ?>
        <div class="empty"><div class="empty-ico">💰</div><p class="muted">Complete bookings to see earnings here.</p></div>
        <?php endif; ?>
    </div>
    <div class="card">
        <h2 class="heading-chart">Bookings — last 6 months</h2>
        <?php if ($bkMonths): ?>
        <div class="earnings-bars">
            <?php foreach ($bkMonths as $m):
                $pct = $maxBkMo > 0 ? round((float)$m['total'] / $maxBkMo * 100) : 0;
            ?>
            <div class="e-bar-col">
                <div class="e-bar-val bar-val-sm"><?= (int)$m['total'] ?></div>
                <div class="e-bar e-bar-blue" data-pct="<?= $pct ?>" data-height-pct="<?= $pct ?>"></div>
                <div class="e-bar-label"><?= e($m['label']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="muted text-xs mt-12 centered">
            <a href="<?= url('nanny/bookings.php') ?>">Manage all bookings →</a>
        </div>
        <?php else: ?>
        <div class="empty"><div class="empty-ico">📊</div><p class="muted">No bookings in the last 6 months.</p></div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Recent reviews -->
<?php if ($recentReviews): ?>
<div class="card section">
    <div class="section-head section-head-tight">
        <h2 class="heading-tight">Recent reviews</h2>
        <a href="<?= url('nanny/reviews.php') ?>">All reviews →</a>
    </div>
    <div class="grid-gap-12">
    <?php foreach ($recentReviews as $rv): ?>
        <div class="card-muted review-item-card">
            <div class="detail-head mb-6 review-item-head">
                <strong class="feed-title"><?= e($rv['reviewer_name']) ?></strong>
                <span class="review-stars-sm"><?= str_repeat('★', (int)$rv['rating']) ?><?= str_repeat('☆', 5 - (int)$rv['rating']) ?></span>
            </div>
            <?php if ($rv['comment']): ?>
            <p class="muted no-margin text-sm review-quote">"<?= e(substr($rv['comment'], 0, 120)) ?><?= strlen($rv['comment']) > 120 ? '…' : '' ?>"</p>
            <?php endif; ?>
            <div class="muted text-xxs mt-4"><?= e(date('d M Y', strtotime($rv['created_at']))) ?></div>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Quick actions -->
<div class="grid grid-3 section">
    <a class="card action-card" href="<?= url('nanny/bookings.php') ?>">
        <div class="stat-ico">📋</div>
        <div><strong>Manage Bookings</strong><div class="muted meta"><?= (int)($c['pending'] ?? 0) ?> pending</div></div>
    </a>
    <a class="card action-card" href="<?= url('nanny/profile.php') ?>">
        <div class="stat-ico">👤</div>
        <div><strong>Edit Profile</strong><div class="muted meta"><?= $complete ?>% complete</div></div>
    </a>
    <a class="card action-card" href="<?= url('nanny/availability.php') ?>">
        <div class="stat-ico">🗓️</div>
        <div><strong>Availability</strong><div class="muted meta">Set your schedule</div></div>
    </a>
    <a class="card action-card" href="<?= url('nanny/earnings.php') ?>">
        <div class="stat-ico">💰</div>
        <div><strong>Earnings</strong><div class="muted meta">R<?= number_format($totalEarned, 0) ?> total</div></div>
    </a>
    <a class="card action-card" href="<?= url('nanny/reviews.php') ?>">
        <div class="stat-ico">⭐</div>
        <div><strong>My Reviews</strong><div class="muted meta"><?= $reviewCount ?> review<?= $reviewCount===1?'':'s' ?></div></div>
    </a>
    <a class="card action-card" href="<?= url('messages.php') ?>">
        <div class="stat-ico">💬</div>
        <div><strong>Messages</strong><?php if ($unreadMsgs): ?><div class="muted meta"><?= $unreadMsgs ?> unread</div><?php else: ?><div class="muted meta">All read</div><?php endif; ?></div>
    </a>
</div>

<?php if ($nannyActivity): ?>
<div class="card section">
    <div class="section-head section-head-tight">
        <h2 class="heading-tight">Notifications</h2>
        <a href="<?= url('notifications.php') ?>">View all →</a>
    </div>
    <div class="grid-gap-8">
    <?php foreach ($nannyActivity as $n): ?>
        <div class="feed-item <?= $n['is_read'] ? '' : 'unread' ?>">
            <div class="feed-dot"></div>
            <div class="feed-body">
                <div class="feed-title"><?= e($n['title']) ?></div>
                <div class="muted feed-message"><?= e($n['message']) ?></div>
                <div class="muted feed-time"><?= e(date('d M Y, H:i', strtotime($n['created_at']))) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
