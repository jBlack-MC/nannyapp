<?php
require_once __DIR__ . '/../config/config.php';
require_role('parent');

$me = (int) current_user()['id'];
$bookingId = (int) ($_GET['booking'] ?? $_POST['booking_id'] ?? 0);

// Load the booking — must belong to me, be completed, and not yet reviewed.
$stmt = db()->prepare(
    "SELECT b.id, b.nanny_id, b.status, u.full_name AS nanny_name,
            (SELECT COUNT(*) FROM reviews r WHERE r.booking_id = b.id) AS already
     FROM bookings b JOIN users u ON u.id = b.nanny_id
     WHERE b.id = ? AND b.parent_id = ?"
);
$stmt->execute([$bookingId, $me]);
$booking = $stmt->fetch();

if (!$booking || $booking['status'] !== 'completed') {
    flash('You can only review completed bookings.', 'error');
    redirect('parent/bookings.php');
}
if ((int) $booking['already'] > 0) {
    flash('You have already reviewed this booking.', 'error');
    redirect('parent/bookings.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $rating  = (int) ($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $errors[] = 'Please choose a rating from 1 to 5 stars.';
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare(
            'INSERT INTO reviews (booking_id, reviewer_id, nanny_id, rating, comment) VALUES (?,?,?,?,?)'
        )->execute([$bookingId, $me, (int) $booking['nanny_id'], $rating, $comment ?: null]);
        recompute_rating((int) $booking['nanny_id']);
        $pdo->commit();

        notify((int) $booking['nanny_id'], 'New review',
            current_user()['full_name'] . ' left you a ' . $rating . '★ review.',
            'nanny/reviews.php');

        flash('Thanks for your review of ' . $booking['nanny_name'] . '!');
        redirect('parent/bookings.php');
    }
}

$pageTitle = 'Leave a review';
require __DIR__ . '/../includes/header.php';
?>
<div class="card form stack">
    <h1>Review <?= e($booking['nanny_name']) ?></h1>
    <p class="muted">How was your booking? Your feedback helps other families choose.</p>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="booking_id" value="<?= (int)$bookingId ?>">
        <div class="field">
            <label>Your rating</label>
            <div class="star-input">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" <?= $i === 5 ? 'required' : '' ?>>
                    <label for="star<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">★</label>
                <?php endfor; ?>
            </div>
        </div>
        <div class="field">
            <label>Comment (optional)</label>
            <textarea name="comment" rows="4" placeholder="Tell us about your experience"></textarea>
        </div>
        <button class="btn btn-primary btn-block">Submit review</button>
        <a href="<?= url('parent/bookings.php') ?>">Cancel</a>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
