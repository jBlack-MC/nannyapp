<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$status = $_GET['status'] ?? '';
$valid  = ['pending', 'confirmed', 'completed', 'rejected', 'cancelled'];

$sql = "SELECT b.id, b.date_time, b.duration, b.status, b.location,
               p.full_name AS parent_name, n.full_name AS nanny_name,
               pay.amount, pay.status AS pay_status
        FROM bookings b
        JOIN users p ON p.id = b.parent_id
        JOIN users n ON n.id = b.nanny_id
        LEFT JOIN payments pay ON pay.booking_id = b.id";
$params = [];
if (in_array($status, $valid, true)) {
    $sql .= ' WHERE b.status = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY b.date_time DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'All bookings';
require __DIR__ . '/../includes/header.php';
?>
<div class="section-head">
    <h1 class="heading-tight">All bookings</h1>
    <div class="filter-chips">
        <a class="chip <?= $status === '' ? 'active' : '' ?>" href="<?= url('admin/bookings.php') ?>">All</a>
        <?php foreach ($valid as $s): ?>
            <a class="chip <?= $status === $s ? 'active' : '' ?>" href="<?= url('admin/bookings.php?status=' . $s) ?>"><?= ucfirst($s) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card section">
    <?php if (!$rows): ?>
        <p class="muted">No bookings found.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>#</th><th>Parent</th><th>Nanny</th><th>When</th><th>Hours</th><th>Amount</th><th>Payment</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="muted">#<?= (int)$r['id'] ?></td>
                    <td><?= e($r['parent_name']) ?></td>
                    <td><?= e($r['nanny_name']) ?></td>
                    <td><?= e(date('D d M Y, H:i', strtotime($r['date_time']))) ?></td>
                    <td><?= e(rtrim(rtrim((string)$r['duration'], '0'), '.')) ?></td>
                    <td>R<?= number_format((float)$r['amount'], 2) ?></td>
                    <td><?= $r['pay_status'] ? status_badge($r['pay_status']) : '<span class="muted">—</span>' ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
