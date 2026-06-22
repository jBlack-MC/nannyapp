<?php
require_once __DIR__ . '/../config/config.php';
require_role('nanny');

$me = current_user()['id'];

// All-time totals
$totals = db()->prepare(
    "SELECT
        IFNULL(SUM(CASE WHEN pay.status='paid' AND b.status='completed' THEN pay.amount END), 0) AS total_earned,
        IFNULL(SUM(CASE WHEN pay.status='pending' THEN pay.amount END), 0) AS pending,
        COUNT(CASE WHEN pay.status='paid' AND b.status='completed' THEN 1 END) AS paid_jobs,
        COUNT(DISTINCT MONTH(pay.created_at)) AS active_months
     FROM payments pay JOIN bookings b ON b.id=pay.booking_id
     WHERE b.nanny_id=?"
);
$totals->execute([$me]);
$t = $totals->fetch();

// Monthly breakdown — last 6 months
$monthly = db()->prepare(
    "SELECT DATE_FORMAT(pay.created_at, '%Y-%m') AS ym,
            DATE_FORMAT(pay.created_at, '%b %Y') AS label,
                        IFNULL(SUM(CASE WHEN pay.status='paid' AND b.status='completed' THEN pay.amount END), 0) AS earned,
                        COUNT(CASE WHEN pay.status='paid' AND b.status='completed' THEN 1 END) AS jobs
     FROM payments pay JOIN bookings b ON b.id=pay.booking_id
     WHERE b.nanny_id=?
       AND pay.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY ym, label ORDER BY ym ASC"
);
$monthly->execute([$me]);
$months = $monthly->fetchAll();

$maxEarning = max(array_column($months, 'earned') ?: [1]);

// Recent completed bookings with payment
$recent = db()->prepare(
    "SELECT b.date_time, b.duration, b.location, pay.amount, pay.status AS pay_status,
            u.full_name AS parent_name
     FROM bookings b
     JOIN payments pay ON pay.booking_id = b.id
     JOIN users u ON u.id = b.parent_id
     WHERE b.nanny_id=? AND b.status='completed'
     ORDER BY b.date_time DESC LIMIT 10"
);
$recent->execute([$me]);
$recentRows = $recent->fetchAll();

// This month
$thisMonth = db()->prepare(
    "SELECT IFNULL(SUM(pay.amount),0)
     FROM payments pay JOIN bookings b ON b.id=pay.booking_id
    WHERE b.nanny_id=? AND pay.status='paid' AND b.status='completed'
       AND MONTH(pay.created_at)=MONTH(NOW()) AND YEAR(pay.created_at)=YEAR(NOW())"
);
$thisMonth->execute([$me]);
$thisMonthEarning = (float) $thisMonth->fetchColumn();

$profile = db()->prepare('SELECT hourly_rate FROM nanny_profiles WHERE user_id=?');
$profile->execute([$me]);
$prof = $profile->fetch();

$pageTitle = 'Earnings';
require __DIR__ . '/../includes/header.php';
?>
<div class="section-head">
    <div>
        <p class="h-eyebrow">Finances</p>
        <h1>My earnings</h1>
    </div>
    <a class="btn" href="<?= url('nanny/dashboard.php') ?>">← Dashboard</a>
</div>

<div class="earnings-summary-grid section earnings-tight">
    <div class="card earn-stat">
        <b>R<?= number_format((float)$t['total_earned'], 0) ?></b>
        <span>Total earned</span>
    </div>
    <div class="card earn-stat">
        <b>R<?= number_format($thisMonthEarning, 0) ?></b>
        <span>This month</span>
    </div>
    <div class="card earn-stat">
        <b>R<?= number_format((float)$t['pending'], 0) ?></b>
        <span>Pending</span>
    </div>
    <div class="card earn-stat">
        <b><?= (int)$t['paid_jobs'] ?></b>
        <span>Paid jobs</span>
    </div>
    <div class="card earn-stat">
        <b>R<?= number_format((float)($prof['hourly_rate'] ?? 0), 0) ?></b>
        <span>Your rate/hr</span>
    </div>
</div>

<!-- Monthly chart -->
<div class="card section earnings-tight">
    <h2 class="chart-head">Earnings — last 6 months</h2>
    <?php if ($months): ?>
        <div class="earnings-chart-wrap">
            <div class="earnings-bars">
                <?php foreach ($months as $m):
                    $pct = $maxEarning > 0 ? round((float)$m['earned'] / $maxEarning * 100) : 0;
                ?>
                <div class="e-bar-col">
                    <div class="e-bar-val">R<?= $m['earned'] > 0 ? number_format((float)$m['earned'],0) : '0' ?></div>
                    <div class="e-bar" data-pct="<?= $pct ?>" data-height-pct="<?= $pct ?>"></div>
                    <div class="e-bar-label"><?= e($m['label']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="table-wrap-top">
            <table class="table">
                <thead><tr><th>Month</th><th>Jobs completed</th><th>Amount earned</th></tr></thead>
                <tbody>
                <?php foreach ($months as $m): ?>
                    <tr>
                        <td><strong><?= e($m['label']) ?></strong></td>
                        <td><?= (int)$m['jobs'] ?></td>
                        <td><strong>R<?= number_format((float)$m['earned'], 2) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty">
            <div class="empty-ico">📊</div>
            <h3>No earnings data yet</h3>
            <p>Accept bookings and complete them to start seeing your earnings here.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Recent bookings -->
<?php if ($recentRows): ?>
<div class="card">
    <h2 class="section-head-tight heading-tight">Recent completed bookings</h2>
    <div class="table-wrap-scroll">
        <table class="table">
            <thead><tr><th>Date</th><th>Parent</th><th>Location</th><th>Hours</th><th>Amount</th><th>Payment</th></tr></thead>
            <tbody>
            <?php foreach ($recentRows as $r): ?>
                <tr>
                    <td class="muted"><?= e(date('d M Y', strtotime($r['date_time']))) ?></td>
                    <td><strong><?= e($r['parent_name']) ?></strong></td>
                    <td class="muted"><?= e(substr($r['location'] ?? '', 0, 30)) ?></td>
                    <td><?= e(rtrim(rtrim((string)$r['duration'],'0'),'.')) ?></td>
                    <td><strong>R<?= number_format((float)$r['amount'], 2) ?></strong></td>
                    <td><?= status_badge($r['pay_status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
