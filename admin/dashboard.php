<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$stats = db()->query(
    "SELECT
        (SELECT COUNT(*) FROM users WHERE role='parent')   AS parents,
        (SELECT COUNT(*) FROM users WHERE role='nanny')    AS nannies,
        (SELECT COUNT(*) FROM users)                       AS users,
        (SELECT COUNT(*) FROM bookings)                    AS bookings,
        (SELECT COUNT(*) FROM bookings WHERE status='pending')  AS pending_bookings,
        (SELECT COUNT(*) FROM bookings WHERE status='confirmed') AS confirmed_bookings,
        (SELECT COUNT(*) FROM nanny_profiles WHERE verification_status='pending') AS pending_verifications,
        (SELECT COUNT(*) FROM nanny_profiles WHERE verification_status='verified') AS verified_nannies,
        (SELECT IFNULL(SUM(amount),0) FROM payments WHERE status='paid') AS revenue,
        (SELECT IFNULL(SUM(amount),0) FROM payments WHERE status='pending') AS pending_revenue"
)->fetch();

// Pending document count
$pendingDocs = 0;
try {
    $pendingDocs = (int) db()->query('SELECT COUNT(*) FROM nanny_portfolio WHERE admin_verified=0')->fetchColumn();
} catch (Throwable $e) {}

// Monthly revenue — last 6 months
$monthlyRevenue = [];
try {
    $mr = db()->query(
        "SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym,
                DATE_FORMAT(created_at,'%b') AS label,
                IFNULL(SUM(amount),0) AS total
         FROM payments WHERE status='paid'
           AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
         GROUP BY ym, label ORDER BY ym ASC"
    );
    $monthlyRevenue = $mr->fetchAll();
} catch (Throwable $e) {}

// Monthly user sign-ups — last 6 months
$monthlyUsers = [];
try {
    $mu = db()->query(
        "SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym,
                DATE_FORMAT(created_at,'%b') AS label,
                COUNT(*) AS total
         FROM users
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
         GROUP BY ym, label ORDER BY ym ASC"
    );
    $monthlyUsers = $mu->fetchAll();
} catch (Throwable $e) {}

$maxRevenue = max(array_column($monthlyRevenue, 'total') ?: [1]);
$maxUsers   = max(array_column($monthlyUsers,   'total') ?: [1]);

// Monthly bookings — last 6 months
$monthlyBookings = [];
try {
    $mb = db()->query(
        "SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym,
                DATE_FORMAT(created_at,'%b') AS label,
                COUNT(*) AS total,
                SUM(status='completed') AS completed_ct
         FROM bookings
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
         GROUP BY ym, label ORDER BY ym ASC"
    );
    $monthlyBookings = $mb->fetchAll();
} catch (Throwable $e) {}
$maxBk = max(array_column($monthlyBookings, 'total') ?: [1]);

// Top booked locations
$topLocations = [];
try {
    $tl = db()->query(
        "SELECT location, COUNT(*) AS total
         FROM bookings WHERE location IS NOT NULL AND location != ''
         GROUP BY location ORDER BY total DESC LIMIT 6"
    );
    $topLocations = $tl->fetchAll();
} catch (Throwable $e) {}
$maxLoc = max(array_column($topLocations, 'total') ?: [1]);

// Support tickets summary
$ticketStats = ['open' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0];
$recentTickets = [];
try {
    $ts = db()->query(
        "SELECT status, COUNT(*) AS cnt FROM support_tickets GROUP BY status"
    );
    foreach ($ts->fetchAll() as $row) {
        $ticketStats[$row['status']] = (int)$row['cnt'];
    }
    $rt = db()->query(
        "SELECT user_id, name, category, subject, status, created_at
         FROM support_tickets ORDER BY created_at DESC LIMIT 6"
    );
    $recentTickets = $rt->fetchAll();
} catch (Throwable $e) {}

$recentUsers = db()->query(
    "SELECT full_name, role, email, created_at FROM users ORDER BY created_at DESC LIMIT 8"
)->fetchAll();

$recentBookings = db()->query(
    "SELECT b.date_time, b.status, p.full_name AS parent_name, n.full_name AS nanny_name
     FROM bookings b JOIN users p ON p.id=b.parent_id JOIN users n ON n.id=b.nanny_id
     ORDER BY b.created_at DESC LIMIT 8"
)->fetchAll();

// Top earning nannies
$topNannies = [];
try {
    $tn = db()->query(
        "SELECT u.full_name, np.average_rating, np.verification_status,
                IFNULL(SUM(pay.amount),0) AS earned,
                COUNT(pay.id) AS jobs
         FROM nanny_profiles np
         JOIN users u ON u.id=np.user_id
         LEFT JOIN bookings b ON b.nanny_id=np.user_id
         LEFT JOIN payments pay ON pay.booking_id=b.id AND pay.status='paid'
         WHERE np.verification_status='verified'
         GROUP BY np.user_id, u.full_name, np.average_rating, np.verification_status
         ORDER BY earned DESC LIMIT 5"
    );
    $topNannies = $tn->fetchAll();
} catch (Throwable $e) {}

$pageTitle = 'Admin Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="section-head">
    <div>
        <p class="h-eyebrow">Admin</p>
        <h1>Platform overview</h1>
        <p class="muted">Real-time snapshot of NannyApp activity.</p>
    </div>
    <div class="dash-wrap-actions">
        <?php if ($stats['pending_verifications'] + $pendingDocs > 0): ?>
        <a class="btn btn-primary btn-badge-wrap" href="<?= url('admin/verifications.php') ?>">
            Verifications
            <span class="btn-badge-pin">
                <?= $stats['pending_verifications'] + $pendingDocs ?>
            </span>
        </a>
        <?php else: ?>
        <a class="btn" href="<?= url('admin/verifications.php') ?>">Verifications</a>
        <?php endif; ?>
        <a class="btn" href="<?= url('admin/users.php') ?>">Users</a>
        <a class="btn" href="<?= url('admin/bookings.php') ?>">Bookings</a>
        <a class="btn" href="<?= url('admin/payments.php') ?>">Payments</a>
        <a class="btn" href="<?= url('admin/reports.php') ?>">Reports</a>
    </div>
</div>

<!-- Primary stats -->
<div class="stats section section-no-top">
    <div class="stat"><span class="stat-ico">👥</span><b><?= (int)$stats['users'] ?></b>Total users</div>
    <div class="stat"><span class="stat-ico">👪</span><b><?= (int)$stats['parents'] ?></b>Parents</div>
    <div class="stat"><span class="stat-ico">🧑‍🍼</span><b><?= (int)$stats['nannies'] ?></b>Nannies</div>
    <div class="stat"><span class="stat-ico">✅</span><b><?= (int)$stats['verified_nannies'] ?></b>Verified nannies</div>
    <div class="stat"><span class="stat-ico">📋</span><b><?= (int)$stats['bookings'] ?></b>Total bookings</div>
    <div class="stat"><span class="stat-ico">⏳</span><b><?= (int)$stats['pending_bookings'] ?></b>Pending bookings</div>
    <div class="stat"><span class="stat-ico">💰</span><b>R<?= number_format((float)$stats['revenue'], 0) ?></b>Revenue (paid)</div>
    <div class="stat"><span class="stat-ico">🕓</span><b><?= (int)$stats['pending_verifications'] ?></b>Profiles pending</div>
</div>

<!-- Charts row -->
<div class="grid grid-2 section section-no-top">

    <!-- Revenue chart -->
    <div class="card">
        <h2 class="head-chart">Revenue — last 6 months</h2>
        <?php if ($monthlyRevenue): ?>
        <div class="earnings-bars">
            <?php foreach ($monthlyRevenue as $m):
                $pct = $maxRevenue > 0 ? round((float)$m['total'] / $maxRevenue * 100) : 0;
            ?>
            <div class="e-bar-col">
                <div class="e-bar-val bar-val-sm">R<?= $m['total']>0 ? number_format((float)$m['total'],0) : '0' ?></div>
                <div class="e-bar" data-pct="<?= $pct ?>" data-height-pct="<?= $pct ?>"></div>
                <div class="e-bar-label"><?= e($m['label']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty"><div class="empty-ico">📊</div><p>No revenue data yet.</p></div>
        <?php endif; ?>
    </div>

    <!-- User growth chart -->
    <div class="card">
        <h2 class="head-chart">New users — last 6 months</h2>
        <?php if ($monthlyUsers): ?>
        <div class="earnings-bars">
            <?php foreach ($monthlyUsers as $m):
                $pct = $maxUsers > 0 ? round((float)$m['total'] / $maxUsers * 100) : 0;
            ?>
            <div class="e-bar-col">
                <div class="e-bar-val bar-val-sm"><?= (int)$m['total'] ?></div>
                <div class="e-bar e-bar-green" data-pct="<?= $pct ?>" data-height-pct="<?= $pct ?>"></div>
                <div class="e-bar-label"><?= e($m['label']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty"><div class="empty-ico">👥</div><p>No sign-up data yet.</p></div>
        <?php endif; ?>
    </div>

</div>

<!-- Second chart row: Bookings + Locations -->
<div class="grid grid-2 section section-no-top">

    <div class="card">
        <h2 class="head-chart">Bookings — last 6 months</h2>
        <?php if ($monthlyBookings): ?>
        <div class="earnings-bars">
            <?php foreach ($monthlyBookings as $m):
                $pct = $maxBk > 0 ? round((float)$m['total'] / $maxBk * 100) : 0;
            ?>
            <div class="e-bar-col">
                <div class="e-bar-val bar-val-sm"><?= (int)$m['total'] ?></div>
                <div class="e-bar e-bar-gold" data-pct="<?= $pct ?>" data-height-pct="<?= $pct ?>"></div>
                <div class="e-bar-label"><?= e($m['label']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty"><div class="empty-ico">📊</div><p>No booking data yet.</p></div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 class="head-chart">Most booked locations</h2>
        <?php if ($topLocations): ?>
        <div class="loc-list">
        <?php foreach ($topLocations as $i => $loc):
            $w = $maxLoc > 0 ? round((float)$loc['total'] / $maxLoc * 100) : 0;
        ?>
        <div>
            <div class="loc-row-head">
                <span><?= e($loc['location']) ?></span>
                <strong><?= (int)$loc['total'] ?> bookings</strong>
            </div>
            <div class="loc-track">
                <div class="loc-fill" data-width-pct="<?= $w ?>"></div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty"><div class="empty-ico">📍</div><p>No location data yet.</p></div>
        <?php endif; ?>
    </div>

</div>

<!-- Support tickets -->
<?php if (array_sum($ticketStats) > 0 || $recentTickets): ?>
<div class="grid grid-2 section section-no-top">

    <div class="card">
        <div class="section-head section-head-tight">
            <h2 class="heading-tight">Support tickets</h2>
            <a href="<?= url('admin/support.php') ?>">Manage all →</a>
        </div>
        <div class="stats stats-four">
            <div class="stat stat-warn">
                <b><?= (int)$ticketStats['open'] ?></b>
                <span>Open</span>
            </div>
            <div class="stat stat-blue">
                <b><?= (int)$ticketStats['in_progress'] ?></b>
                <span>In progress</span>
            </div>
            <div class="stat stat-ok">
                <b><?= (int)$ticketStats['resolved'] ?></b>
                <span>Resolved</span>
            </div>
            <div class="stat">
                <b><?= (int)$ticketStats['closed'] ?></b>
                <span>Closed</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="section-head section-head-tight">
            <h2 class="heading-tight">Recent tickets</h2>
            <a href="<?= url('admin/support.php') ?>">View all →</a>
        </div>
        <?php if ($recentTickets): ?>
        <table class="table">
            <tbody>
            <?php foreach ($recentTickets as $t): ?>
                <tr>
                    <td>
                        <strong class="text-sm"><?= e($t['name']) ?></strong>
                        <div class="muted muted-cut"><?= e(ucfirst($t['category'])) ?> — <?= e(substr($t['subject'], 0, 40)) ?><?= strlen($t['subject']) > 40 ? '…' : '' ?></div>
                    </td>
                    <td class="muted muted-date-xs"><?= e(date('d M', strtotime($t['created_at']))) ?></td>
                    <td><?= status_badge($t['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="muted">No tickets yet.</p>
        <?php endif; ?>
    </div>

</div>
<?php endif; ?>

<!-- Quick-action admin panel -->
<div class="grid grid-3 section section-no-top">
    <a class="card action-card" href="<?= url('admin/verifications.php') ?>">
        <div class="stat-ico">🔍</div>
        <div>
            <strong>Verifications</strong>
            <?php if ($stats['pending_verifications'] + $pendingDocs > 0): ?>
            <div class="meta action-card-note">
                <?= (int)$stats['pending_verifications'] ?> profiles · <?= $pendingDocs ?> documents
            </div>
            <?php else: ?>
            <div class="muted meta">All clear</div>
            <?php endif; ?>
        </div>
    </a>
    <a class="card action-card" href="<?= url('admin/users.php') ?>">
        <div class="stat-ico">👥</div>
        <div><strong>User Management</strong><div class="muted meta"><?= (int)$stats['users'] ?> registered</div></div>
    </a>
    <a class="card action-card" href="<?= url('admin/bookings.php') ?>">
        <div class="stat-ico">📋</div>
        <div><strong>Bookings</strong><div class="muted meta"><?= (int)$stats['confirmed_bookings'] ?> active</div></div>
    </a>
    <a class="card action-card" href="<?= url('admin/payments.php') ?>">
        <div class="stat-ico">💳</div>
        <div><strong>Payments</strong><div class="muted meta">R<?= number_format((float)$stats['pending_revenue'],0) ?> pending</div></div>
    </a>
    <a class="card action-card" href="<?= url('admin/reports.php') ?>">
        <div class="stat-ico">📊</div>
        <div><strong>Reports</strong><div class="muted meta">Export &amp; PDF</div></div>
    </a>
    <a class="card action-card" href="<?= url('admin/notify.php') ?>">
        <div class="stat-ico">📢</div>
        <div><strong>Notifications</strong><div class="muted meta">Send announcements</div></div>
    </a>
</div>

<!-- Data tables row -->
<div class="grid grid-2 section section-no-top">

    <div class="card">
        <div class="section-head section-head-tight">
            <h2 class="heading-tight">Recent sign-ups</h2>
            <a href="<?= url('admin/users.php') ?>">View all →</a>
        </div>
        <?php if (!$recentUsers): ?>
            <p class="muted">No users yet.</p>
        <?php else: ?>
            <table class="table">
                <tbody>
                <?php foreach ($recentUsers as $u): ?>
                    <tr>
                        <td>
                            <strong><?= e($u['full_name']) ?></strong>
                            <div class="muted muted-mail"><?= e($u['email']) ?></div>
                        </td>
                        <td><span class="tag"><?= e(ucfirst($u['role'])) ?></span></td>
                        <td class="muted muted-date-xs"><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="section-head section-head-tight">
            <h2 class="heading-tight">Recent bookings</h2>
            <a href="<?= url('admin/bookings.php') ?>">View all →</a>
        </div>
        <?php if (!$recentBookings): ?>
            <p class="muted">No bookings yet.</p>
        <?php else: ?>
            <table class="table">
                <tbody>
                <?php foreach ($recentBookings as $b): ?>
                    <tr>
                        <td>
                            <strong><?= e($b['parent_name']) ?></strong>
                            <div class="muted muted-mail">→ <?= e($b['nanny_name']) ?></div>
                        </td>
                        <td class="muted muted-date-xs"><?= e(date('d M', strtotime($b['date_time']))) ?></td>
                        <td><?= status_badge($b['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

<!-- Top earning nannies -->
<?php if ($topNannies): ?>
<div class="card section section-no-top">
    <div class="section-head section-head-tight">
        <h2 class="heading-tight">Top performing nannies</h2>
        <a href="<?= url('admin/users.php?role=nanny') ?>">View all →</a>
    </div>
    <table class="table">
        <thead>
            <tr><th>Nanny</th><th>Status</th><th>Rating</th><th>Jobs</th><th>Total earned</th></tr>
        </thead>
        <tbody>
        <?php foreach ($topNannies as $n): ?>
            <tr>
                <td><strong><?= e($n['full_name']) ?></strong></td>
                <td><?= status_badge($n['verification_status']) ?></td>
                <td>⭐ <?= number_format((float)$n['average_rating'],1) ?></td>
                <td><?= (int)$n['jobs'] ?></td>
                <td><strong>R<?= number_format((float)$n['earned'],0) ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- System links -->
<div class="card section section-no-top">
    <h2 class="section-head-tight heading-tight">System</h2>
    <div class="dash-wrap-actions">
        <a class="btn" href="<?= url('admin/support.php') ?>">Support tickets</a>
        <a class="btn" href="<?= url('admin/messages.php') ?>">Contact inbox</a>
        <a class="btn" href="<?= url('admin/notify.php') ?>">Broadcast message</a>
        <a class="btn" href="<?= url('admin/payments.php') ?>">Payments</a>
        <a class="btn" href="<?= url('admin/reports.php') ?>">Reports</a>
        <a class="btn action-card-warn" href="<?= url('migrate_v2.php') ?>">Run DB migration v2</a>
        <a class="btn action-card-warn" href="<?= url('migrate_v3.php') ?>">Run DB migration v3</a>
    </div>
    <p class="muted text-xs mt-4">Only run migrations if you have not yet applied them to this database. Requires admin login.</p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
