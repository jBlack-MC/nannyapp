<?php
require_once __DIR__ . '/../config/config.php';
require_role('parent');

$me   = current_user();
$meId = $me['id'];

$counts = db()->prepare(
    "SELECT
        SUM(status='pending')   AS pending,
        SUM(status='confirmed') AS confirmed,
        SUM(status='completed') AS completed,
        SUM(date_time > NOW() AND status IN ('pending','confirmed')) AS upcoming,
        COUNT(*)                AS total
     FROM bookings WHERE parent_id = ?"
);
$counts->execute([$meId]);
$c = $counts->fetch();

$spent = db()->prepare(
    "SELECT IFNULL(SUM(pay.amount),0) FROM payments pay JOIN bookings b ON b.id=pay.booking_id
     WHERE b.parent_id=? AND pay.status='paid'"
);
$spent->execute([$meId]);
$totalSpent = (float) $spent->fetchColumn();

$unreadMsgs   = unread_message_count();
$unreadNotifs = unread_notification_count();

$savedCount = 0;
try {
    $sc = db()->prepare('SELECT COUNT(*) FROM saved_nannies WHERE parent_id=?');
    $sc->execute([$meId]);
    $savedCount = (int) $sc->fetchColumn();
} catch (Throwable $e) {}

$childCount = 0;
try {
    $cc = db()->prepare('SELECT COUNT(*) FROM children WHERE parent_id=?');
    $cc->execute([$meId]);
    $childCount = (int) $cc->fetchColumn();
} catch (Throwable $e) {}

$recent = db()->prepare(
    "SELECT b.*, u.full_name AS nanny_name, u.profile_image AS nanny_img,
            p.hourly_rate, pay.amount AS payment_amount, pay.status AS pay_status
     FROM bookings b
     JOIN users u ON u.id = b.nanny_id
     JOIN nanny_profiles p ON p.user_id = u.id
     LEFT JOIN payments pay ON pay.booking_id = b.id
     WHERE b.parent_id = ? ORDER BY b.created_at DESC LIMIT 5"
);
$recent->execute([$meId]);
$rows = $recent->fetchAll();

$upcoming = db()->prepare(
    "SELECT b.*, u.full_name AS nanny_name
     FROM bookings b JOIN users u ON u.id = b.nanny_id
     WHERE b.parent_id=? AND b.date_time > NOW() AND b.status IN ('pending','confirmed')
     ORDER BY b.date_time ASC LIMIT 3"
);
$upcoming->execute([$meId]);
$upcomingRows = $upcoming->fetchAll();

// Monthly bookings — last 6 months
$monthlyBookings = db()->prepare(
    "SELECT DATE_FORMAT(date_time,'%Y-%m') AS ym,
            DATE_FORMAT(date_time,'%b') AS label,
            COUNT(*) AS total
     FROM bookings WHERE parent_id=?
       AND date_time >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY ym, label ORDER BY ym ASC"
);
$monthlyBookings->execute([$meId]);
$bookingMonths = $monthlyBookings->fetchAll();
$maxBookings   = max(array_column($bookingMonths, 'total') ?: [1]);

// Monthly spending — last 6 months
$monthlySpend = db()->prepare(
    "SELECT DATE_FORMAT(pay.created_at,'%Y-%m') AS ym,
            DATE_FORMAT(pay.created_at,'%b') AS label,
            IFNULL(SUM(pay.amount),0) AS total
     FROM payments pay JOIN bookings b ON b.id=pay.booking_id
     WHERE b.parent_id=? AND pay.status='paid'
       AND pay.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY ym, label ORDER BY ym ASC"
);
$monthlySpend->execute([$meId]);
$spendMonths = $monthlySpend->fetchAll();
$maxSpend    = max(array_column($spendMonths, 'total') ?: [1]);

// Activity feed from notifications
$activityRows = [];
try {
    $act = db()->prepare(
        "SELECT title, message, is_read, created_at FROM notifications
         WHERE user_id=? ORDER BY created_at DESC LIMIT 8"
    );
    $act->execute([$meId]);
    $activityRows = $act->fetchAll();
} catch (Throwable $e) {}

$pageTitle = 'Parent Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head">
    <div>
        <h1>Hi, <?= e(explode(' ', $me['full_name'])[0]) ?> 👋</h1>
        <p class="muted dash-head-sub">Here's your childcare overview</p>
    </div>
    <a class="btn btn-primary" href="<?= url('parent/nannies.php') ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Find a nanny
    </a>
</div>

<div class="stats section">
    <div class="stat"><span class="stat-ico">🗓️</span><b><?= (int)($c['upcoming'] ?? 0) ?></b>Upcoming</div>
    <div class="stat"><span class="stat-ico">✅</span><b><?= (int)($c['completed'] ?? 0) ?></b>Completed</div>
    <div class="stat"><span class="stat-ico">💰</span><b>R<?= number_format($totalSpent, 0) ?></b>Total spent</div>
    <div class="stat"><span class="stat-ico">❤️</span><b><?= $savedCount ?></b>Saved nannies</div>
    <div class="stat"><span class="stat-ico">👶</span><b><?= $childCount ?></b>Children</div>
    <div class="stat"><span class="stat-ico">💬</span><b><?= $unreadMsgs ?></b>Unread messages</div>
</div>

<?php if ($upcomingRows): ?>
<div class="card section">
    <div class="section-head section-head-tight">
        <h2 class="heading-tight">Upcoming bookings</h2>
        <a href="<?= url('parent/bookings.php') ?>">See all →</a>
    </div>
    <div class="grid-gap-10">
    <?php foreach ($upcomingRows as $b): ?>
        <div class="panel-row panel-row-blue">
            <?= avatar($b['nanny_name'], null, 'avatar-sm') ?>
            <div class="panel-row-info">
                <strong><?= e($b['nanny_name']) ?></strong>
                <div class="muted panel-row-muted"><?= e(date('D d M Y \a\t H:i', strtotime($b['date_time']))) ?> · <?= e(rtrim(rtrim((string)$b['duration'],'0'),'.')) ?> hrs</div>
            </div>
            <?= status_badge($b['status']) ?>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Charts -->
<?php if ($bookingMonths || $spendMonths): ?>
<div class="grid grid-2 section">
    <div class="card">
        <h2 class="heading-chart">Bookings — last 6 months</h2>
        <?php if ($bookingMonths): ?>
        <div class="earnings-bars">
            <?php foreach ($bookingMonths as $m):
                $pct = $maxBookings > 0 ? round((float)$m['total'] / $maxBookings * 100) : 0;
            ?>
            <div class="e-bar-col">
                <div class="e-bar-val bar-val-sm"><?= (int)$m['total'] ?></div>
                <div class="e-bar e-bar-blue" data-pct="<?= $pct ?>" data-height-pct="<?= $pct ?>"></div>
                <div class="e-bar-label"><?= e($m['label']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty"><div class="empty-ico">📊</div><p class="muted">No bookings in the last 6 months.</p></div>
        <?php endif; ?>
    </div>
    <div class="card">
        <h2 class="heading-chart">Spending — last 6 months</h2>
        <?php if ($spendMonths): ?>
        <div class="earnings-bars">
            <?php foreach ($spendMonths as $m):
                $pct = $maxSpend > 0 ? round((float)$m['total'] / $maxSpend * 100) : 0;
            ?>
            <div class="e-bar-col">
                <div class="e-bar-val bar-val-xs">R<?= number_format((float)$m['total'],0) ?></div>
                <div class="e-bar e-bar-green" data-pct="<?= $pct ?>" data-height-pct="<?= $pct ?>"></div>
                <div class="e-bar-label"><?= e($m['label']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty"><div class="empty-ico">💰</div><p class="muted">No payments yet.</p></div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Quick actions -->
<div class="grid grid-3 section">
    <a class="card action-card" href="<?= url('parent/children.php') ?>">
        <div class="stat-ico">👶</div>
        <div><strong>My Children</strong><div class="muted meta"><?= $childCount ?> profile<?= $childCount === 1 ? '' : 's' ?></div></div>
    </a>
    <a class="card action-card" href="<?= url('parent/saved.php') ?>">
        <div class="stat-ico">❤️</div>
        <div><strong>Saved Nannies</strong><div class="muted meta"><?= $savedCount ?> favourite<?= $savedCount === 1 ? '' : 's' ?></div></div>
    </a>
    <a class="card action-card" href="<?= url('parent/payments.php') ?>">
        <div class="stat-ico">💳</div>
        <div><strong>Payments</strong><div class="muted meta">R<?= number_format($totalSpent, 0) ?> total</div></div>
    </a>
    <a class="card action-card" href="<?= url('parent/bookings.php') ?>">
        <div class="stat-ico">📋</div>
        <div><strong>All Bookings</strong><div class="muted meta"><?= (int)$c['total'] ?> total</div></div>
    </a>
    <a class="card action-card" href="<?= url('messages.php') ?>">
        <div class="stat-ico">💬</div>
        <div><strong>Messages</strong><?php if ($unreadMsgs): ?><div class="muted meta"><?= $unreadMsgs ?> unread</div><?php else: ?><div class="muted meta">All caught up</div><?php endif; ?></div>
    </a>
    <a class="card action-card" href="<?= url('account.php') ?>">
        <div class="stat-ico">⚙️</div>
        <div><strong>Account Settings</strong><div class="muted meta">Profile &amp; security</div></div>
    </a>
</div>

<div class="card section">
    <div class="section-head section-head-tight">
        <h2 class="heading-tight">Recent activity</h2>
        <a href="<?= url('parent/bookings.php') ?>">View all →</a>
    </div>
    <?php if (!$rows): ?>
        <div class="empty">
            <div class="empty-ico">🗓️</div>
            <h3>No bookings yet</h3>
            <p><a href="<?= url('parent/nannies.php') ?>">Find a nanny</a> to get started.</p>
        </div>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Nanny</th><th>When</th><th>Hrs</th><th>Amount</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><strong><?= e($r['nanny_name']) ?></strong></td>
                    <td class="muted"><?= e(date('d M Y', strtotime($r['date_time']))) ?></td>
                    <td><?= e(rtrim(rtrim((string)$r['duration'],'0'),'.')) ?></td>
                    <td><?= $r['payment_amount'] ? 'R' . number_format((float)$r['payment_amount'], 2) : '—' ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if ($activityRows): ?>
<div class="card section">
    <div class="section-head section-head-tight">
        <h2 class="heading-tight">Notifications</h2>
        <a href="<?= url('notifications.php') ?>">View all →</a>
    </div>
    <div class="grid-gap-8">
    <?php foreach ($activityRows as $n): ?>
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
