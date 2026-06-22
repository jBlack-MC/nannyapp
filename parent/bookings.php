<?php
require_once __DIR__ . '/../config/config.php';
require_role('parent');

$me = current_user()['id'];

// Cancel a pending/confirmed booking.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['booking_id'] ?? 0);
    $upd = db()->prepare(
        "UPDATE bookings SET status='cancelled'
         WHERE id=? AND parent_id=? AND status IN ('pending','confirmed')"
    );
    $upd->execute([$id, $me]);
    if ($upd->rowCount()) {
        db()->prepare(
            "UPDATE payments
             SET status = CASE
                 WHEN status='paid' THEN 'refunded'
                 WHEN status='pending' THEN 'failed'
                 ELSE status
             END
             WHERE booking_id=?"
        )->execute([$id]);
    }
    flash($upd->rowCount() ? 'Booking cancelled.' : 'Could not cancel that booking.', $upd->rowCount() ? 'success' : 'error');
    redirect('parent/bookings.php');
}

$stmt = db()->prepare(
    "SELECT b.*, u.full_name AS nanny_name, pay.amount, pay.status AS pay_status,
            (SELECT COUNT(*) FROM reviews r WHERE r.booking_id = b.id) AS reviewed
     FROM bookings b
     JOIN users u ON u.id = b.nanny_id
     LEFT JOIN payments pay ON pay.booking_id = b.id
     WHERE b.parent_id = ? ORDER BY b.date_time DESC"
);
$stmt->execute([$me]);
$rows = $stmt->fetchAll();

$pageTitle = 'My bookings';
require __DIR__ . '/../includes/header.php';
?>
<h1>My bookings</h1>
<div class="card section">
    <?php if (!$rows): ?>
        <div class="empty">
            <span class="empty-ico">🗓️</span>
            <h3>No bookings yet</h3>
            <p>When you book a nanny, your sessions and their status will show up here.</p>
            <a class="btn btn-primary" href="<?= url('parent/nannies.php') ?>">Find a nanny</a>
        </div>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Nanny</th><th>When</th><th>Hours</th><th>Est. cost</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a href="<?= url('messages.php?with=' . (int)$r['nanny_id']) ?>"><?= e($r['nanny_name']) ?></a></td>
                    <td><?= e(date('D d M Y, H:i', strtotime($r['date_time']))) ?>
                        <?= render_booking_timeline($r) ?>
                    </td>
                    <td><?= e(rtrim(rtrim((string)$r['duration'], '0'), '.')) ?></td>
                    <td>R<?= number_format((float)$r['amount'], 2) ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td>
                        <?php if (in_array($r['status'], ['pending', 'confirmed'], true)): ?>
                            <form method="post" class="form-zero">
                                <?= csrf_field() ?>
                                <input type="hidden" name="booking_id" value="<?= (int)$r['id'] ?>">
                                <button class="btn btn-sm btn-danger" data-confirm="Cancel this booking?">Cancel</button>
                            </form>
                        <?php elseif ($r['status'] === 'completed' && !(int)$r['reviewed']): ?>
                            <a class="btn btn-sm btn-primary" href="<?= url('parent/review.php?booking=' . (int)$r['id']) ?>">Leave review</a>
                        <?php elseif ($r['status'] === 'completed' && (int)$r['reviewed']): ?>
                            <span class="muted">Reviewed ✓</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
