<?php
require_once __DIR__ . '/../config/config.php';
require_role('parent');

auto_release_stale_payments();

$me = current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id     = (int) ($_POST['booking_id'] ?? 0);
    $action = $_POST['action'] ?? 'cancel';

    // Cancel a pending/confirmed booking (before the nanny has checked in).
    if ($action === 'cancel') {
        $upd = db()->prepare(
            "UPDATE bookings SET status='cancelled'
             WHERE id=? AND parent_id=? AND status IN ('pending','confirmed')"
        );
        $upd->execute([$id, $me]);
        if ($upd->rowCount()) {
            db()->prepare(
                "UPDATE payments
                 SET payout_status = CASE WHEN status='paid' THEN 'refunded' ELSE payout_status END,
                     status = CASE WHEN status='paid' THEN 'refunded' WHEN status='pending' THEN 'failed' ELSE status END
                 WHERE booking_id=?"
            )->execute([$id]);
        }
        flash($upd->rowCount() ? 'Booking cancelled.' : 'Could not cancel that booking.', $upd->rowCount() ? 'success' : 'error');
        redirect('parent/bookings.php');
    }

    // Regenerate the check-in PIN (e.g. it was mistyped too many times, or forgotten).
    if ($action === 'resend_code') {
        $code = generate_check_in_code();
        $upd  = db()->prepare(
            "UPDATE bookings SET check_in_code=?, check_in_attempts=0 WHERE id=? AND parent_id=? AND status='confirmed'"
        );
        $upd->execute([$code, $id, $me]);
        flash($upd->rowCount() ? 'New check-in PIN generated below.' : 'Could not update that booking.', $upd->rowCount() ? 'success' : 'error');
        redirect('parent/bookings.php');
    }

    // Confirm the session actually happened — this is what releases the held payment.
    if ($action === 'confirm') {
        $upd = db()->prepare(
            "UPDATE bookings SET status='completed', parent_confirmed_at=NOW()
             WHERE id=? AND parent_id=? AND status='in_progress' AND checked_out_at IS NOT NULL"
        );
        $upd->execute([$id, $me]);
        if ($upd->rowCount()) {
            db()->prepare(
                "UPDATE payments SET payout_status='released', released_at=NOW()
                 WHERE booking_id=? AND status='paid' AND payout_status='held'"
            )->execute([$id]);
            $info = db()->prepare('SELECT nanny_id, date_time FROM bookings WHERE id=?');
            $info->execute([$id]);
            if ($b = $info->fetch()) {
                notify((int) $b['nanny_id'], 'Payment released',
                    current_user()['full_name'] . ' confirmed the session on '
                        . date('D d M, H:i', strtotime($b['date_time'])) . ' — payment has been released to you.',
                    'nanny/earnings.php');
            }
            flash('Thanks for confirming — payment has been released to the nanny.', 'success');
        } else {
            flash('Could not confirm that booking.', 'error');
        }
        redirect('parent/bookings.php');
    }

    // Report a problem (e.g. the nanny never actually arrived). Payment stays
    // held until an admin reviews it — it does not get auto-released while disputed.
    if ($action === 'dispute') {
        $reason = trim($_POST['dispute_reason'] ?? '');
        $upd = db()->prepare(
            "UPDATE bookings SET status='disputed', dispute_reason=?, disputed_at=NOW()
             WHERE id=? AND parent_id=? AND status IN ('confirmed','in_progress')"
        );
        $upd->execute([$reason !== '' ? $reason : 'No details given.', $id, $me]);
        if ($upd->rowCount()) {
            $admins = db()->query("SELECT id FROM users WHERE role='admin'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($admins as $adminId) {
                notify((int) $adminId, 'Booking disputed',
                    current_user()['full_name'] . ' reported a problem with booking #' . $id . '. Payment is on hold pending review.',
                    'admin/payments.php');
            }
            flash('Thanks — we have paused this payment and a team member will review it shortly.', 'success');
        } else {
            flash('Could not report that booking.', 'error');
        }
        redirect('parent/bookings.php');
    }
}

$stmt = db()->prepare(
    "SELECT b.*, u.full_name AS nanny_name, pay.amount, pay.status AS pay_status, pay.payout_status,
            (SELECT COUNT(*) FROM reviews r WHERE r.booking_id = b.id) AS reviewed
     FROM bookings b
     JOIN users u ON u.id = b.nanny_id
     LEFT JOIN payments pay ON pay.booking_id = b.id
     WHERE b.parent_id = ? ORDER BY FIELD(b.status,'pending','confirmed','in_progress','completed','disputed','rejected','cancelled'), b.date_time DESC"
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
                        <?php if ($r['status'] === 'pending'): ?>
                            <form method="post" class="form-zero">
                                <?= csrf_field() ?>
                                <input type="hidden" name="booking_id" value="<?= (int)$r['id'] ?>">
                                <button class="btn btn-sm btn-danger" name="action" value="cancel" data-confirm="Cancel this booking?">Cancel</button>
                            </form>

                        <?php elseif ($r['status'] === 'confirmed'): ?>
                            <div class="checkin-pin-box">
                                <span class="muted text-xs">Check-in PIN — give this to <?= e(explode(' ', $r['nanny_name'])[0]) ?> only once they've arrived:</span>
                                <strong class="checkin-pin"><?= e($r['check_in_code'] ?? '——————') ?></strong>
                            </div>
                            <div class="booking-actions-row">
                                <form method="post" class="form-zero">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="booking_id" value="<?= (int)$r['id'] ?>">
                                    <button class="btn btn-sm btn-danger" name="action" value="cancel" data-confirm="Cancel this booking?">Cancel</button>
                                </form>
                                <form method="post" class="form-zero">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="booking_id" value="<?= (int)$r['id'] ?>">
                                    <button class="btn btn-sm" name="action" value="resend_code" data-confirm="Generate a new PIN? The old one will stop working.">New PIN</button>
                                </form>
                            </div>

                        <?php elseif ($r['status'] === 'in_progress' && $r['checked_out_at']): ?>
                            <form method="post" class="form-zero mb-4">
                                <?= csrf_field() ?>
                                <input type="hidden" name="booking_id" value="<?= (int)$r['id'] ?>">
                                <button class="btn btn-sm btn-primary" name="action" value="confirm" data-confirm="Confirm the session happened and release payment to the nanny?">✓ Confirm &amp; release payment</button>
                            </form>
                            <details class="dispute-details">
                                <summary class="muted text-xs">Something wrong?</summary>
                                <form method="post" class="stack-tight mt-4">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="booking_id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="action" value="dispute">
                                    <textarea name="dispute_reason" rows="2" class="text-xs" placeholder="What happened?" required></textarea>
                                    <button class="btn btn-sm btn-danger" data-confirm="Report a problem with this booking? Payment will be held for review.">Report a problem</button>
                                </form>
                            </details>

                        <?php elseif ($r['status'] === 'in_progress'): ?>
                            <span class="muted">Session in progress</span><br>
                            <details class="dispute-details">
                                <summary class="muted text-xs">Nanny never arrived?</summary>
                                <form method="post" class="stack-tight mt-4">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="booking_id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="action" value="dispute">
                                    <textarea name="dispute_reason" rows="2" class="text-xs" placeholder="What happened?" required></textarea>
                                    <button class="btn btn-sm btn-danger" data-confirm="Report a problem with this booking? Payment will be held for review.">Report a problem</button>
                                </form>
                            </details>

                        <?php elseif ($r['status'] === 'completed' && !(int)$r['reviewed']): ?>
                            <a class="btn btn-sm btn-primary" href="<?= url('parent/review.php?booking=' . (int)$r['id']) ?>">Leave review</a>
                        <?php elseif ($r['status'] === 'completed' && (int)$r['reviewed']): ?>
                            <span class="muted">Reviewed ✓</span>
                        <?php elseif ($r['status'] === 'disputed'): ?>
                            <span class="muted">Under review by support</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
