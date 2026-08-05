<?php
require_once __DIR__ . '/../config/config.php';
require_role('parent');

auto_release_stale_payments();

$me = current_user()['id'];

$payments = db()->prepare(
    "SELECT pay.*, b.date_time, b.duration, b.location, b.status AS booking_status, b.dispute_reason,
            u.full_name AS nanny_name
     FROM payments pay
     JOIN bookings b  ON b.id  = pay.booking_id
     JOIN users    u  ON u.id  = b.nanny_id
     WHERE b.parent_id = ?
     ORDER BY pay.created_at DESC"
);
$payments->execute([$me]);
$rows = $payments->fetchAll();

$totals = db()->prepare(
    "SELECT
        IFNULL(SUM(CASE WHEN pay.status='paid' THEN pay.amount END), 0) AS total_paid,
        IFNULL(SUM(CASE WHEN pay.status='pending' THEN pay.amount END), 0) AS total_pending,
        COUNT(CASE WHEN pay.status='paid' THEN 1 END) AS paid_count,
        COUNT(*) AS total_count
     FROM payments pay JOIN bookings b ON b.id=pay.booking_id
     WHERE b.parent_id=?"
);
$totals->execute([$me]);
$t = $totals->fetch();

$pageTitle = 'Payments';
require __DIR__ . '/../includes/header.php';
?>
<div class="section-head">
    <div>
        <p class="h-eyebrow">Finances</p>
        <h1>Payment history</h1>
    </div>
    <a class="btn" href="<?= url('parent/bookings.php') ?>">← My bookings</a>
</div>

<div class="stats stats-wide">
    <div class="stat"><span class="stat-ico">💳</span><b>R<?= number_format((float)$t['total_paid'], 0) ?></b>Total paid</div>
    <div class="stat"><span class="stat-ico">⏳</span><b>R<?= number_format((float)$t['total_pending'], 0) ?></b>Pending</div>
    <div class="stat"><span class="stat-ico">🧾</span><b><?= (int)$t['paid_count'] ?></b>Completed payments</div>
    <div class="stat"><span class="stat-ico">📋</span><b><?= (int)$t['total_count'] ?></b>Total transactions</div>
</div>

<div class="card">
    <?php if (!$rows): ?>
        <div class="empty">
            <div class="empty-ico">💳</div>
            <h3>No payments yet</h3>
            <p>Your payment history will appear here once you book a nanny.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Nanny</th>
                        <th>Service date</th>
                        <th>Duration</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Payout</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="muted"><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
                        <td><strong><?= e($r['nanny_name']) ?></strong></td>
                        <td class="muted"><?= e(date('D d M, H:i', strtotime($r['date_time']))) ?></td>
                        <td><?= e(rtrim(rtrim((string)$r['duration'],'0'),'.')) ?> hrs</td>
                        <td><strong>R<?= number_format((float)$r['amount'], 2) ?></strong></td>
                        <td class="muted"><?= e(ucfirst($r['method'] ?? 'Card')) ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td>
                            <?php if ($r['booking_status'] === 'disputed'): ?>
                                <span class="muted text-xs">Under review</span>
                            <?php elseif ($r['payout_status']): ?>
                                <?= status_badge($r['payout_status']) ?>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="muted table-txn"><?= $r['transaction_id'] ? e(substr($r['transaction_id'], 0, 18)) . '…' : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card card-note-info">
    <h3>🔒 Secure, held payments</h3>
    <p class="muted">When a nanny accepts a booking your card is charged, but the money is held in escrow — not the nanny's yet. It's only released once you confirm the session actually happened (or automatically after 48 hours). If a nanny never arrives, cancel the booking or report the problem from <a href="<?= url('parent/bookings.php') ?>">My bookings</a> and you'll be refunded.</p>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
