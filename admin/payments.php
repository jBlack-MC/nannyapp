<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$rows = db()->query(
    "SELECT pay.id, pay.amount, pay.method, pay.transaction_id, pay.status, pay.created_at,
            pay.booking_id, p.full_name AS parent_name, n.full_name AS nanny_name
     FROM payments pay
     JOIN bookings b ON b.id = pay.booking_id
     JOIN users p ON p.id = b.parent_id
     JOIN users n ON n.id = b.nanny_id
     ORDER BY pay.created_at DESC"
)->fetchAll();

$totals = db()->query(
    "SELECT
        IFNULL(SUM(CASE WHEN status='paid' THEN amount END),0) AS paid,
        IFNULL(SUM(CASE WHEN status='pending' THEN amount END),0) AS pending
     FROM payments"
)->fetch();

$pageTitle = 'Payments';
require __DIR__ . '/../includes/header.php';
?>
<h1>Payments</h1>
<div class="stats section">
    <div class="stat"><b>R<?= number_format((float)$totals['paid'], 0) ?></b>Collected (paid)</div>
    <div class="stat"><b>R<?= number_format((float)$totals['pending'], 0) ?></b>Pending</div>
    <div class="stat"><b><?= count($rows) ?></b>Transactions</div>
</div>

<div class="card section">
    <?php if (!$rows): ?>
        <p class="muted">No payments yet.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>#</th><th>Booking</th><th>Parent</th><th>Nanny</th><th>Amount</th><th>Method</th><th>Transaction</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="muted">#<?= (int)$r['id'] ?></td>
                    <td class="muted">#<?= (int)$r['booking_id'] ?></td>
                    <td><?= e($r['parent_name']) ?></td>
                    <td><?= e($r['nanny_name']) ?></td>
                            <td>R<?= number_format((float)$r['amount'], 2) ?></td>
                    <td><?= e($r['method'] ?: '—') ?></td>
                    <td class="muted"><?= e($r['transaction_id'] ?: '—') ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td class="muted"><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
