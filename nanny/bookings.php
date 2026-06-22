<?php
require_once __DIR__ . '/../config/config.php';
require_role('nanny');

$me = current_user()['id'];

// Accept / reject / complete a booking.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id     = (int) ($_POST['booking_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $map = [
        'accept'   => ['confirmed', ['pending']],
        'reject'   => ['rejected',  ['pending']],
        'complete' => ['completed', ['confirmed']],
    ];

    // Cancel an upcoming confirmed booking.
    if ($action === 'cancel') {
        $stmt = db()->prepare(
            "UPDATE bookings SET status='cancelled'
             WHERE id=? AND nanny_id=? AND status='confirmed' AND date_time > NOW()"
        );
        $stmt->execute([$id, $me]);
        if ($stmt->rowCount()) {
            db()->prepare(
                "UPDATE payments SET status = CASE
                     WHEN status='paid'    THEN 'refunded'
                     WHEN status='pending' THEN 'failed'
                     ELSE status
                 END WHERE booking_id=?"
            )->execute([$id]);
            $info = db()->prepare('SELECT parent_id, date_time FROM bookings WHERE id=?');
            $info->execute([$id]);
            if ($b = $info->fetch()) {
                notify((int) $b['parent_id'], 'Booking cancelled',
                    current_user()['full_name'] . ' has cancelled your booking on '
                        . date('D d M, H:i', strtotime($b['date_time'])) . '.',
                    'parent/bookings.php');
            }
        }
        flash($stmt->rowCount() ? 'Booking cancelled.' : 'Could not cancel — the booking may have already started.', $stmt->rowCount() ? 'success' : 'error');
        redirect('nanny/bookings.php');
    }

    if (isset($map[$action])) {
        [$newStatus, $allowedFrom] = $map[$action];
        $in = implode(',', array_fill(0, count($allowedFrom), '?'));
        $sql = "UPDATE bookings SET status=? WHERE id=? AND nanny_id=? AND status IN ($in)";
        $stmt = db()->prepare($sql);
        $stmt->execute(array_merge([$newStatus, $id, $me], $allowedFrom));

        if ($stmt->rowCount()) {
            // Keep payment status in sync with booking state transitions.
            if ($action === 'accept') {
                db()->prepare("UPDATE payments SET status='paid', transaction_id=COALESCE(transaction_id, CONCAT('TXN', booking_id, UNIX_TIMESTAMP())) WHERE booking_id=? AND status='pending'")
                    ->execute([$id]);
            } elseif ($action === 'reject') {
                db()->prepare("UPDATE payments SET status='failed' WHERE booking_id=? AND status='pending'")
                    ->execute([$id]);
            }
            // Notify the parent of the outcome.
            $labels = ['accept' => 'accepted', 'reject' => 'rejected', 'complete' => 'completed'];
            $info = db()->prepare('SELECT parent_id, date_time FROM bookings WHERE id=?');
            $info->execute([$id]);
            if ($b = $info->fetch()) {
                notify((int) $b['parent_id'], 'Booking ' . $labels[$action],
                    current_user()['full_name'] . ' ' . $labels[$action] . ' your booking on '
                        . date('D d M, H:i', strtotime($b['date_time'])) . '.',
                    'parent/bookings.php');
            }
        }
        flash($stmt->rowCount() ? 'Booking updated.' : 'No change made.', $stmt->rowCount() ? 'success' : 'error');
    }
    redirect('nanny/bookings.php');
}

$stmt = db()->prepare(
    "SELECT b.*, u.full_name AS parent_name, pay.amount, pay.status AS pay_status
     FROM bookings b
     JOIN users u ON u.id = b.parent_id
     LEFT JOIN payments pay ON pay.booking_id = b.id
     WHERE b.nanny_id = ? ORDER BY FIELD(b.status,'pending','confirmed','completed','rejected','cancelled'), b.date_time DESC"
);
$stmt->execute([$me]);
$rows = $stmt->fetchAll();

$pageTitle = 'Booking requests';
require __DIR__ . '/../includes/header.php';
?>
<h1>Booking requests</h1>
<div class="card section">
    <?php if (!$rows): ?>
        <div class="empty">
            <span class="empty-ico">📭</span>
            <h3>No booking requests yet</h3>
            <p>New requests from parents will appear here. Make sure your profile is complete and verified to get discovered.</p>
            <a class="btn btn-primary" href="<?= url('nanny/profile.php') ?>">Edit my profile</a>
        </div>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Parent</th><th>When</th><th>Hours</th><th>Location</th><th>Pay</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <a href="<?= url('profile.php?id=' . (int)$r['parent_id']) ?>" class="pfp-link"><?= e($r['parent_name']) ?></a>
                        <a class="pfp-msg-ico" href="<?= url('messages.php?with=' . (int)$r['parent_id']) ?>" title="Message">💬</a>
                    </td>
                    <td><?= e(date('D d M Y, H:i', strtotime($r['date_time']))) ?>
                        <?= render_booking_timeline($r) ?>
                    </td>
                    <td><?= e(rtrim(rtrim((string)$r['duration'], '0'), '.')) ?></td>
                    <td><?= e($r['location']) ?></td>
                    <td>R<?= number_format((float)$r['amount'], 2) ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="booking_id" value="<?= (int)$r['id'] ?>">
                                <button class="btn btn-sm btn-primary" name="action" value="accept">Accept</button>
                                <button class="btn btn-sm btn-danger" name="action" value="reject" data-confirm="Reject this request?">Reject</button>
                            </form>
                        <?php elseif ($r['status'] === 'confirmed'): ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="booking_id" value="<?= (int)$r['id'] ?>">
                                <?php if (strtotime($r['date_time']) > time()): ?>
                                    <button class="btn btn-sm btn-danger" name="action" value="cancel" data-confirm="Cancel this booking? The parent will be notified.">Cancel</button>
                                <?php endif; ?>
                                <button class="btn btn-sm" name="action" value="complete">Mark completed</button>
                            </form>
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
