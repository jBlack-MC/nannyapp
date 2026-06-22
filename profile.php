<?php
require_once __DIR__ . '/config/config.php';

$profileId = (int) ($_GET['id'] ?? 0);
if (!$profileId) { redirect('index.php'); }

$stmt = db()->prepare(
    'SELECT id, full_name, profile_image, role, status, email_verified, created_at
     FROM users WHERE id = ? AND status = "active"'
);
$stmt->execute([$profileId]);
$profile = $stmt->fetch();

if (!$profile) {
    flash('Profile not found.', 'error');
    redirect('index.php');
}

// Nannies have their own dedicated profile/booking page
if ($profile['role'] === 'nanny') {
    redirect('parent/book.php?nanny=' . $profileId);
}

if ($profile['role'] !== 'parent') { redirect('index.php'); }

// --- Stats ---
$completedBookings = (int) db()->prepare('SELECT COUNT(*) FROM bookings WHERE parent_id=? AND status="completed"')
    ->execute([$profileId]) ? db()->query("SELECT COUNT(*) FROM bookings WHERE parent_id=$profileId AND status='completed'")->fetchColumn() : 0;

$stmt2 = db()->prepare('SELECT COUNT(*) FROM bookings WHERE parent_id=? AND status="completed"');
$stmt2->execute([$profileId]);
$completedBookings = (int) $stmt2->fetchColumn();

$stmt3 = db()->prepare('SELECT COUNT(*) FROM reviews WHERE reviewer_id=?');
$stmt3->execute([$profileId]);
$reviewCount = (int) $stmt3->fetchColumn();

$childCount = 0;
try {
    $stmt4 = db()->prepare('SELECT COUNT(*) FROM children WHERE parent_id=?');
    $stmt4->execute([$profileId]);
    $childCount = (int) $stmt4->fetchColumn();
} catch (Throwable) {}

$memberYear  = date('Y', strtotime($profile['created_at']));
$memberYears = (int) date('Y') - (int) $memberYear;

// Reviews this parent has left (shows communication style to nannies)
$revStmt = db()->prepare(
    'SELECT r.rating, r.comment, r.created_at, u.full_name AS nanny_name, u.profile_image AS nanny_img
     FROM reviews r
     JOIN users u ON u.id = r.nanny_id
     WHERE r.reviewer_id = ?
     ORDER BY r.created_at DESC LIMIT 6'
);
$revStmt->execute([$profileId]);
$reviews = $revStmt->fetchAll();

$pageTitle = e($profile['full_name']);
require __DIR__ . '/includes/header.php';
?>
<a class="back-link" href="javascript:history.back()">← Back</a>

<div class="profile-layout">

    <!-- Left column -->
    <div>
        <!-- Profile card -->
        <div class="card pb-profile-card">
            <div class="pro-banner">
                <!-- gradient from CSS default — parents don't have banner photos -->
                <div class="pfp-banner-inner">
                    <span class="pfp-banner-ico">👨‍👩‍👧‍👦</span>
                    <span class="pfp-banner-label">Parent Profile</span>
                </div>
            </div>

            <div class="pro-header">
                <div class="pro-avatar-wrap">
                    <?= avatar($profile['full_name'], $profile['profile_image'] ?? null, 'avatar avatar-xl') ?>
                    <?php if ($profile['email_verified']): ?>
                        <span class="pro-verified-badge" title="Verified account">✓</span>
                    <?php endif; ?>
                </div>
                <div class="pb-flex-1">
                    <h1 class="heading-tight"><?= e($profile['full_name']) ?></h1>
                    <div class="muted mt-2">Parent · Member since <?= $memberYear ?></div>
                    <?php if ($profile['email_verified']): ?>
                        <div style="margin-top:6px"><span class="badge badge-ok">✓ Verified account</span></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="pb-stat-grid">
                <div class="pb-stat-cell">
                    <b><?= $completedBookings ?></b>
                    <div class="muted pb-stat-caption">Bookings</div>
                </div>
                <div class="pb-stat-cell">
                    <b><?= $reviewCount ?></b>
                    <div class="muted pb-stat-caption">Reviews left</div>
                </div>
                <div class="pb-stat-cell">
                    <b><?= $childCount ?: '—' ?></b>
                    <div class="muted pb-stat-caption">Children</div>
                </div>
                <div class="pb-stat-cell">
                    <b><?= $memberYears > 0 ? $memberYears . 'y' : '<1y' ?></b>
                    <div class="muted pb-stat-caption">Member</div>
                </div>
            </div>
        </div>

        <!-- Trust signals -->
        <div class="card pfp-trust-card">
            <h3 class="pfp-trust-title">Why trust this parent?</h3>
            <ul class="pfp-trust-list">
                <?php if ($profile['email_verified']): ?>
                    <li>✅ Email verified — real person, real account</li>
                <?php endif; ?>
                <?php if ($completedBookings >= 1): ?>
                    <li>✅ <?= $completedBookings ?> completed booking<?= $completedBookings > 1 ? 's' : '' ?> on NannyApp</li>
                <?php endif; ?>
                <?php if ($reviewCount >= 1): ?>
                    <li>✅ Actively leaves reviews — communicates with nannies</li>
                <?php endif; ?>
                <?php if ($childCount): ?>
                    <li>✅ <?= $childCount ?> child profile<?= $childCount > 1 ? 's' : '' ?> added to their account</li>
                <?php endif; ?>
                <?php if ($memberYears >= 1): ?>
                    <li>✅ Established member — joined in <?= $memberYear ?></li>
                <?php endif; ?>
                <?php if (!$profile['email_verified'] && !$completedBookings && !$reviewCount): ?>
                    <li class="muted">This parent is new to NannyApp. All payments are protected by the platform.</li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Reviews left by this parent -->
        <?php if ($reviews): ?>
        <div class="card pb-card-mt">
            <h3>Reviews this parent has written</h3>
            <p class="muted" style="margin-bottom:16px;font-size:.88rem">This shows how they communicate and treat nannies.</p>
            <div class="pb-reviews">
                <?php foreach ($reviews as $rv): ?>
                <div class="pb-review-item">
                    <div class="pb-review-head">
                        <?= avatar($rv['nanny_name'], $rv['nanny_img'] ?? null, 'avatar-sm') ?>
                        <div>
                            <strong>For <?= e($rv['nanny_name']) ?></strong>
                            <div class="pb-review-stars"><?= str_repeat('★', (int)$rv['rating']) ?><?= str_repeat('☆', 5-(int)$rv['rating']) ?></div>
                        </div>
                        <span class="muted pb-review-date"><?= date('d M Y', strtotime($rv['created_at'])) ?></span>
                    </div>
                    <?php if ($rv['comment']): ?>
                        <p class="muted pb-review-text"><?= e($rv['comment']) ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="card pb-card-mt">
            <h3>Reviews</h3>
            <div class="empty" style="padding:24px 0">
                <div class="empty-ico" style="font-size:2rem">📝</div>
                <p class="muted">No reviews written yet — this may be a new parent.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div>
        <div class="card stack booking-sidebar">
            <?php if (!is_logged_in()): ?>
                <p class="muted" style="font-size:.9rem;margin:0">Log in to connect with this parent.</p>
                <a class="btn btn-primary btn-block" href="<?= url('auth/login.php') ?>">Log in</a>
            <?php elseif (user_role() === 'nanny'): ?>
                <p class="muted" style="font-size:.88rem;margin:0">Start a conversation before or after a booking.</p>
                <a class="btn btn-primary btn-block" href="<?= url('messages.php?with=' . $profileId) ?>">💬 Message this parent</a>
            <?php elseif (current_user()['id'] === $profileId): ?>
                <a class="btn btn-primary btn-block" href="<?= url('account.php') ?>">Edit my profile</a>
            <?php else: ?>
                <a class="btn btn-block" href="<?= url('messages.php?with=' . $profileId) ?>">💬 Send a message</a>
            <?php endif; ?>

            <ul class="pb-side-list">
                <li>✅ All payments held securely</li>
                <li>✅ Profiles verified by admin</li>
                <li>✅ 24/7 support available</li>
            </ul>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
