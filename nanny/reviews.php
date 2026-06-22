<?php
require_once __DIR__ . '/../config/config.php';
require_role('nanny');

$me = (int) current_user()['id'];

$profile = db()->prepare('SELECT average_rating FROM nanny_profiles WHERE user_id=?');
$profile->execute([$me]);
$avg = (float) ($profile->fetchColumn() ?: 0);

$stmt = db()->prepare(
    "SELECT r.rating, r.comment, r.created_at, u.full_name AS parent_name
     FROM reviews r JOIN users u ON u.id = r.reviewer_id
     WHERE r.nanny_id = ? ORDER BY r.created_at DESC"
);
$stmt->execute([$me]);
$reviews = $stmt->fetchAll();

$pageTitle = 'My reviews';
require __DIR__ . '/../includes/header.php';
?>
<div class="section-head">
    <h1 class="heading-tight">My reviews</h1>
    <div class="rating-line"><?= stars_html($avg) ?>
        <span><?= number_format($avg, 1) ?> · <?= count($reviews) ?> review<?= count($reviews) === 1 ? '' : 's' ?></span>
    </div>
</div>

<div class="card section">
    <?php if (!$reviews): ?>
        <p class="muted">No reviews yet. They'll appear here once parents review your completed bookings.</p>
    <?php else: ?>
        <div class="stack">
            <?php foreach ($reviews as $r): ?>
                <div class="review-item">
                    <div class="rating-line"><?= stars_html((float)$r['rating']) ?>
                        <strong><?= e($r['parent_name']) ?></strong>
                        <span class="muted">· <?= e(date('d M Y', strtotime($r['created_at']))) ?></span>
                    </div>
                    <?php if ($r['comment']): ?>
                        <p class="muted review-comment"><?= e($r['comment']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
