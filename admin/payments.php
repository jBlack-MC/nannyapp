<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

auto_release_stale_payments();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $action    = $_POST['action'] ?? '';

    if ($action === 'release' && $bookingId) {
        db()->prepare("UPDATE bookings SET status='completed', parent_confirmed_at=NOW() WHERE id=? AND status IN ('in_progress','disputed')")
            ->execute([$bookingId]);
        $upd = db()->prepare(
            "UPDATE payments SET payout_status='released', released_at=NOW()
             WHERE booking_id=? AND status='paid' AND payout_status IN ('held')"
        );
        $upd->execute([$bookingId]);
        $info = db()->prepare('SELECT nanny_id, date_time FROM bookings WHERE id=?');
        $info->execute([$bookingId]);
        if ($b = $info->fetch()) {
            notify((int) $b['nanny_id'], 'Payment released',
                'An admin reviewed booking #' . $bookingId . ' and released the held payment to you.', 'nanny/earnings.php');
        }
        flash($upd->rowCount() ? 'Payment released to the nanny.' : 'Nothing to release for that booking.', $upd->rowCount() ? 'success' : 'error');
    } elseif ($action === 'refund' && $bookingId) {
        db()->prepare("UPDATE bookings SET status='cancelled' WHERE id=? AND status IN ('in_progress','disputed','confirmed')")
            ->execute([$bookingId]);
        $upd = db()->prepare(
            "UPDATE payments SET status='refunded', payout_status='refunded'
             WHERE booking_id=? AND status='paid'"
        );
        $upd->execute([$bookingId]);
        $info = db()->prepare('SELECT parent_id, nanny_id, date_time FROM bookings WHERE id=?');
        $info->execute([$bookingId]);
        if ($b = $info->fetch()) {
            notify((int) $b['parent_id'], 'Payment refunded',
                'An admin reviewed booking #' . $bookingId . ' and refunded your payment.', 'parent/payments.php');
            notify((int) $b['nanny_id'], 'Booking refunded',
                'An admin reviewed booking #' . $bookingId . ' and refunded the parent — no payment will be released for this job.', 'nanny/bookings.php');
        }
        flash($upd->rowCount() ? 'Payment refunded to the parent.' : 'Nothing to refund for that booking.', $upd->rowCount() ? 'success' : 'error');
    }
    redirect('admin/payments.php');
}

$rows = db()->query(
    "SELECT pay.id, pay.amount, pay.method, pay.transaction_id, pay.status, pay.payout_status, pay.created_at,
            pay.booking_id, b.status AS booking_status, b.dispute_reason,
            p.full_name AS parent_name, n.full_name AS nanny_name
     FROM payments pay
     JOIN bookings b ON b.id = pay.booking_id
     JOIN users p ON p.id = b.parent_id
     JOIN users n ON n.id = b.nanny_id
     ORDER BY (b.status='disputed') DESC, pay.created_at DESC"
)->fetchAll();

$totals = db()->query(
    "SELECT
        IFNULL(SUM(CASE WHEN status='paid' THEN amount END),0) AS paid,
        IFNULL(SUM(CASE WHEN payout_status='held' THEN amount END),0) AS held,
        IFNULL(SUM(CASE WHEN payout_status='released' THEN amount END),0) AS released,
        IFNULL(SUM(CASE WHEN status='pending' THEN amount END),0) AS pending
     FROM payments"
)->fetch();

$disputedCount = 0;
foreach ($rows as $r) {
    if ($r['booking_status'] === 'disputed') $disputedCount++;
}

$pageTitle = 'Payments';
require __DIR__ . '/../includes/header.php';
?>
<h1>Payments</h1>

<?php if ($disputedCount > 0): ?>
<div class="card card-note-info section">
    <h3>⚠️ <?= $disputedCount ?> disputed booking<?= $disputedCount===1?'':'s' ?> need review</h3>
    <p class="muted">A parent reported a problem — likely the nanny never arrived. Payment is on hold. Review each case below and either release the funds to the nanny or refund the parent.</p>
</div>
<?php endif; ?>

<div class="stats section">
    <div class="stat"><b>R<?= number_format((float)$totals['paid'], 0) ?></b>Total charged</div>
    <div class="stat"><b>R<?= number_format((float)$totals['held'], 0) ?></b>Held in escrow</div>
    <div class="stat"><b>R<?= number_format((float)$totals['released'], 0) ?></b>Released to nannies</div>
    <div class="stat"><b>R<?= number_format((float)$totals['pending'], 0) ?></b>Awaiting charge</div>
</div>

<div class="card section">
    <?php if (!$rows): ?>
        <p class="muted">No payments yet.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>#</th><th>Booking</th><th>Parent</th><th>Nanny</th><th>Amount</th><th>Charge</th><th>Payout</th><th>Booking status</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="muted">#<?= (int)$r['id'] ?></td>
                    <td class="muted">#<?= (int)$r['booking_id'] ?></td>
                    <td><?= e($r['parent_name']) ?></td>
                    <td><?= e($r['nanny_name']) ?></td>
                    <td>R<?= number_format((float)$r['amount'], 2) ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td><?= $r['payout_status'] ? status_badge($r['payout_status']) : '<span class="muted">—</span>' ?></td>
                    <td>
                        <?= status_badge($r['booking_status']) ?>
                        <?php if ($r['booking_status'] === 'disputed' && $r['dispute_reason']): ?>
                            <div class="muted text-xs mt-4"><?= e($r['dispute_reason']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="muted"><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
                    <td>
                        <?php if (in_array($r['booking_status'], ['in_progress','disputed'], true) && $r['payout_status'] === 'held'): ?>
                            <div class="booking-actions-row">
                                <form method="post" class="form-zero">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="booking_id" value="<?= (int)$r['booking_id'] ?>">
                                    <button class="btn btn-sm btn-primary" name="action" value="release" data-confirm="Release this payment to the nanny?">Release</button>
                                </form>
                                <form method="post" class="form-zero">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="booking_id" value="<?= (int)$r['booking_id'] ?>">
                                    <button class="btn btn-sm btn-danger" name="action" value="refund" data-confirm="Refund this payment to the parent? The nanny will get nothing for this job.">Refund</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
