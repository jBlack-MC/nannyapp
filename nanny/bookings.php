<?php
require_once __DIR__ . '/../config/config.php';
require_role('nanny');

auto_release_stale_payments();

$me = current_user()['id'];

// Accept / reject / check-in / check-out / cancel a booking.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id     = (int) ($_POST['booking_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    // Cancel an upcoming confirmed booking (only before the nanny has checked in).
    if ($action === 'cancel') {
        $stmt = db()->prepare(
            "UPDATE bookings SET status='cancelled'
             WHERE id=? AND nanny_id=? AND status='confirmed' AND date_time > NOW()"
        );
        $stmt->execute([$id, $me]);
        if ($stmt->rowCount()) {
            db()->prepare(
                "UPDATE payments SET
                     payout_status = CASE WHEN status='paid' THEN 'refunded' ELSE payout_status END,
                     status = CASE
                         WHEN status='paid'    THEN 'refunded'
                         WHEN status='pending' THEN 'failed'
                         ELSE status
                     END
                 WHERE booking_id=?"
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

    // Check in: proves the nanny is physically at the home. The PIN is only
    // ever shown to the parent, who hands it over once the nanny has arrived.
    if ($action === 'check_in') {
        $code = trim($_POST['check_in_code'] ?? '');
        $chk  = db()->prepare("SELECT id, check_in_code, check_in_attempts FROM bookings WHERE id=? AND nanny_id=? AND status='confirmed'");
        $chk->execute([$id, $me]);
        $bk = $chk->fetch();

        if (!$bk) {
            flash('That booking is not ready for check-in.', 'error');
        } elseif ((int) $bk['check_in_attempts'] >= 5) {
            flash('Too many incorrect attempts. Ask the parent to resend the check-in PIN from their bookings page.', 'error');
        } elseif ($code === '' || !hash_equals((string) $bk['check_in_code'], $code)) {
            db()->prepare('UPDATE bookings SET check_in_attempts = check_in_attempts + 1 WHERE id=?')->execute([$id]);
            flash('Incorrect PIN. Ask the parent for the check-in code once you have arrived at the home.', 'error');
        } else {
            db()->prepare("UPDATE bookings SET status='in_progress', checked_in_at=NOW() WHERE id=?")->execute([$id]);
            $info = db()->prepare('SELECT parent_id, date_time FROM bookings WHERE id=?');
            $info->execute([$id]);
            if ($b = $info->fetch()) {
                notify((int) $b['parent_id'], 'Nanny checked in',
                    current_user()['full_name'] . ' checked in for the booking on '
                        . date('D d M, H:i', strtotime($b['date_time'])) . '.',
                    'parent/bookings.php');
            }
            flash('Checked in — have a great session!', 'success');
        }
        redirect('nanny/bookings.php');
    }

    // Check out: marks the session as finished on the nanny's side. Payment
    // stays held until the parent confirms the job was actually done.
    if ($action === 'check_out') {
        $stmt = db()->prepare(
            "UPDATE bookings SET checked_out_at=NOW()
             WHERE id=? AND nanny_id=? AND status='in_progress' AND checked_out_at IS NULL"
        );
        $stmt->execute([$id, $me]);
        if ($stmt->rowCount()) {
            $info = db()->prepare('SELECT parent_id, date_time FROM bookings WHERE id=?');
            $info->execute([$id]);
            if ($b = $info->fetch()) {
                notify((int) $b['parent_id'], 'Session finished — please confirm',
                    current_user()['full_name'] . ' marked the session on '
                        . date('D d M, H:i', strtotime($b['date_time'])) . ' as finished. Confirm it in your bookings to release payment.',
                    'parent/bookings.php');
            }
            flash('Session marked as finished. Payment is released once the parent confirms (or automatically after 48 hours).', 'success');
        } else {
            flash('Could not update that booking.', 'error');
        }
        redirect('nanny/bookings.php');
    }

    $map = [
        'accept' => ['confirmed', ['pending']],
        'reject' => ['rejected',  ['pending']],
    ];

    if (isset($map[$action])) {
        [$newStatus, $allowedFrom] = $map[$action];
        $in = implode(',', array_fill(0, count($allowedFrom), '?'));
        $sql = "UPDATE bookings SET status=? WHERE id=? AND nanny_id=? AND status IN ($in)";
        $stmt = db()->prepare($sql);
        $stmt->execute(array_merge([$newStatus, $id, $me], $allowedFrom));

        if ($stmt->rowCount()) {
            $info = db()->prepare(
                'SELECT b.parent_id, b.date_time, u.full_name AS parent_name, u.email AS parent_email
                 FROM bookings b JOIN users u ON u.id = b.parent_id WHERE b.id=?'
            );
            $info->execute([$id]);
            $b = $info->fetch();

            if ($action === 'accept') {
                $code = generate_check_in_code();
                db()->prepare(
                    "UPDATE payments SET status='paid', payout_status='held',
                        transaction_id=COALESCE(transaction_id, CONCAT('TXN', booking_id, UNIX_TIMESTAMP()))
                     WHERE booking_id=? AND status='pending'"
                )->execute([$id]);
                db()->prepare('UPDATE bookings SET check_in_code=?, check_in_attempts=0 WHERE id=?')->execute([$code, $id]);

                if ($b) {
                    $when = date('D d M, H:i', strtotime($b['date_time']));
                    notify((int) $b['parent_id'], 'Booking accepted',
                        current_user()['full_name'] . ' accepted your booking on ' . $when
                            . '. Your check-in PIN is ' . $code . ' — only give it to the nanny once they have arrived.',
                        'parent/bookings.php');

                    $textBody = "Hi " . $b['parent_name'] . ",\n\n" . current_user()['full_name']
                        . " accepted your booking on " . $when . ".\n\n"
                        . "Your check-in PIN is: " . $code . "\n\n"
                        . "For your safety, only hand this PIN to the nanny once they have actually arrived at your home. "
                        . "They need it to check in, and payment is only released to them after you confirm the session took place.\n\n"
                        . "View your booking: " . url('parent/bookings.php');
                    $htmlBody = '<p>Hi ' . htmlspecialchars($b['parent_name']) . ',</p>'
                        . '<p><strong>' . htmlspecialchars(current_user()['full_name']) . '</strong> accepted your booking on ' . htmlspecialchars($when) . '.</p>'
                        . '<p>Your check-in PIN is: <strong style="font-size:20px;letter-spacing:2px;">' . htmlspecialchars($code) . '</strong></p>'
                        . '<p>For your safety, only hand this PIN to the nanny once they have actually arrived at your home. '
                        . 'They need it to check in, and payment is only released to them after you confirm the session took place.</p>'
                        . '<p><a href="' . url('parent/bookings.php') . '">View your booking</a></p>';
                    send_email($b['parent_email'], 'Booking accepted — your check-in PIN', $textBody, $htmlBody);
                }
            } elseif ($action === 'reject') {
                db()->prepare("UPDATE payments SET status='failed' WHERE booking_id=? AND status='pending'")
                    ->execute([$id]);
                if ($b) {
                    notify((int) $b['parent_id'], 'Booking rejected',
                        current_user()['full_name'] . ' rejected your booking on '
                            . date('D d M, H:i', strtotime($b['date_time'])) . '.',
                        'parent/bookings.php');
                }
            }
        }
        flash($stmt->rowCount() ? 'Booking updated.' : 'No change made.', $stmt->rowCount() ? 'success' : 'error');
    }
    redirect('nanny/bookings.php');
}

$stmt = db()->prepare(
    "SELECT b.*, u.full_name AS parent_name, pay.amount, pay.status AS pay_status, pay.payout_status
     FROM bookings b
     JOIN users u ON u.id = b.parent_id
     LEFT JOIN payments pay ON pay.booking_id = b.id
     WHERE b.nanny_id = ? ORDER BY FIELD(b.status,'pending','confirmed','in_progress','completed','disputed','rejected','cancelled'), b.date_time DESC"
);
$stmt->execute([$me]);
$rows = $stmt->fetchAll();

$pageTitle = 'Booking requests';
require __DIR__ . '/../includes/header.php';
?>
<h1>Booking requests</h1>
<div class="card card-note-info">
    <h3>🔒 How payment protection works</h3>
    <p class="muted">When you accept a job, the parent's payment is charged and held securely — it is not yours yet. The parent gets a one-time PIN to hand you in person once you arrive. Enter it to check in, then check out when the session ends. Payment is released to you once the parent confirms (or automatically after 48 hours if they don't respond).</p>
</div>
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
                            <form method="post" class="checkin-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="booking_id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="action" value="check_in">
                                <input type="text" name="check_in_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                                       placeholder="PIN from parent" class="checkin-input" required>
                                <button class="btn btn-sm btn-primary">Check in</button>
                            </form>
                            <?php if (strtotime($r['date_time']) > time()): ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="booking_id" value="<?= (int)$r['id'] ?>">
                                <button class="btn btn-sm btn-danger" name="action" value="cancel" data-confirm="Cancel this booking? The parent will be notified.">Cancel</button>
                            </form>
                            <?php endif; ?>
                        <?php elseif ($r['status'] === 'in_progress' && !$r['checked_out_at']): ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="booking_id" value="<?= (int)$r['id'] ?>">
                                <button class="btn btn-sm" name="action" value="check_out" data-confirm="Mark this session as finished?">Finish session</button>
                            </form>
                        <?php elseif ($r['status'] === 'in_progress' && $r['checked_out_at']): ?>
                            <span class="muted">Awaiting parent confirmation</span>
                        <?php elseif ($r['status'] === 'disputed'): ?>
                            <span class="muted">Under review by support</span>
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
