<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) {
    flash('Invalid user selected.', 'error');
    redirect('admin/users.php');
}

$user = db()->prepare(
    "SELECT
        u.id, u.full_name, u.email, u.role, u.status, u.profile_image, u.created_at,
        np.verification_status, np.experience_years, np.hourly_rate, np.location,
        np.skills, np.languages, np.qualifications, np.specialisations, np.bio, np.availability,
        pp.emergency_contact_name, pp.emergency_contact, pp.emergency_contact_relationship, pp.number_of_children
     FROM users u
     LEFT JOIN nanny_profiles np ON np.user_id = u.id
     LEFT JOIN parent_profiles pp ON pp.user_id = u.id
     WHERE u.id = ?
     LIMIT 1"
);
$user->execute([$userId]);
$u = $user->fetch();

if (!$u) {
    flash('User not found.', 'error');
    redirect('admin/users.php');
}

$stats = [
    'bookings_as_parent' => 0,
    'bookings_as_nanny' => 0,
    'completed_as_parent' => 0,
    'completed_as_nanny' => 0,
    'reviews_written' => 0,
    'reviews_received' => 0,
    'messages_sent' => 0,
    'messages_received' => 0,
];

try {
    $q = db()->prepare(
        "SELECT
            (SELECT COUNT(*) FROM bookings WHERE parent_id = ?) AS bookings_as_parent,
            (SELECT COUNT(*) FROM bookings WHERE nanny_id = ?) AS bookings_as_nanny,
            (SELECT COUNT(*) FROM bookings WHERE parent_id = ? AND status = 'completed') AS completed_as_parent,
            (SELECT COUNT(*) FROM bookings WHERE nanny_id = ? AND status = 'completed') AS completed_as_nanny,
            (SELECT COUNT(*) FROM reviews WHERE parent_id = ?) AS reviews_written,
            (SELECT COUNT(*) FROM reviews WHERE nanny_id = ?) AS reviews_received,
            (SELECT COUNT(*) FROM chat_messages WHERE sender_id = ?) AS messages_sent,
            (SELECT COUNT(*) FROM chat_messages WHERE receiver_id = ?) AS messages_received"
    );
    $q->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]);
    $stats = array_merge($stats, (array)$q->fetch());
} catch (Throwable $e) {
    // Keep defaults when optional tables are missing.
}

$recentBookings = [];
try {
    if ($u['role'] === 'parent') {
        $b = db()->prepare(
            "SELECT b.id, b.date_time, b.duration, b.status, b.location,
                    n.full_name AS counterpart
             FROM bookings b
             LEFT JOIN users n ON n.id = b.nanny_id
             WHERE b.parent_id = ?
             ORDER BY b.date_time DESC
             LIMIT 8"
        );
    } elseif ($u['role'] === 'nanny') {
        $b = db()->prepare(
            "SELECT b.id, b.date_time, b.duration, b.status, b.location,
                    p.full_name AS counterpart
             FROM bookings b
             LEFT JOIN users p ON p.id = b.parent_id
             WHERE b.nanny_id = ?
             ORDER BY b.date_time DESC
             LIMIT 8"
        );
    } else {
        $b = null;
    }

    if ($b) {
        $b->execute([$userId]);
        $recentBookings = $b->fetchAll();
    }
} catch (Throwable $e) {
    $recentBookings = [];
}

$skills = array_filter(array_map('trim', explode(',', (string)($u['skills'] ?? ''))));
$languages = array_filter(array_map('trim', explode(',', (string)($u['languages'] ?? ''))));
$quals = array_filter(array_map('trim', explode(',', (string)($u['qualifications'] ?? ''))));
$specs = array_filter(array_map('trim', explode(',', (string)($u['specialisations'] ?? ''))));

$pageTitle = 'User profile';
require __DIR__ . '/../includes/header.php';
?>

<div class="section-head">
    <div>
        <p class="h-eyebrow">Admin</p>
        <h1>User profile</h1>
        <p class="muted">View account details, role profile data, and recent activity.</p>
    </div>
    <div class="panel-row-info">
        <a class="btn" href="<?= url('admin/users.php') ?>">← Back to users</a>
        <a class="btn btn-primary" href="<?= url('messages.php?with=' . (int)$u['id']) ?>">Message user</a>
    </div>
</div>

<div class="card section">
    <div class="panel-row-info">
        <div class="panel-row-info">
            <?= avatar((string)$u['full_name'], $u['profile_image'] ?? null, 'avatar-lg') ?>
            <div>
                <h2 class="heading-tight no-margin"><?= e((string)$u['full_name']) ?></h2>
                <p class="muted no-margin"><?= e((string)$u['email']) ?></p>
                <div class="nanny-badge-row">
                    <span class="tag"><?= e(ucfirst((string)$u['role'])) ?></span>
                    <?= (($u['status'] ?? 'active') === 'suspended') ? status_badge('suspended') : '<span class="badge badge-ok">Active</span>' ?>
                    <?php if ($u['role'] === 'nanny'): ?>
                        <?= status_badge((string)($u['verification_status'] ?? 'pending')) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="muted text-right">
            Joined <?= e(date('d M Y', strtotime((string)$u['created_at']))) ?>
        </div>
    </div>
</div>

<div class="stats section">
    <div class="stat">
        <span class="stat-card-label">Bookings (as parent)</span>
        <b><?= (int)$stats['bookings_as_parent'] ?></b>
    </div>
    <div class="stat">
        <span class="stat-card-label">Bookings (as nanny)</span>
        <b><?= (int)$stats['bookings_as_nanny'] ?></b>
    </div>
    <div class="stat">
        <span class="stat-card-label">Reviews</span>
        <b><?= (int)$stats['reviews_written'] + (int)$stats['reviews_received'] ?></b>
    </div>
    <div class="stat">
        <span class="stat-card-label">Messages</span>
        <b><?= (int)$stats['messages_sent'] + (int)$stats['messages_received'] ?></b>
    </div>
</div>

<?php if ($u['role'] === 'nanny'): ?>
<div class="card section stack">
    <h3>Nanny profile details</h3>
    <div class="grid grid-2">
        <div><strong>Experience</strong><div class="muted"><?= (int)($u['experience_years'] ?? 0) ?> years</div></div>
        <div><strong>Rate</strong><div class="muted">R<?= number_format((float)($u['hourly_rate'] ?? 0), 0) ?>/hr</div></div>
        <div><strong>Location</strong><div class="muted"><?= e((string)($u['location'] ?: 'Not set')) ?></div></div>
        <div><strong>Availability</strong><div class="muted"><?= e((string)($u['availability'] ?: 'Not set')) ?></div></div>
    </div>

    <?php if (!empty($u['bio'])): ?>
        <div>
            <strong>Bio</strong>
            <p class="muted"><?= e((string)$u['bio']) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($skills): ?>
        <div>
            <strong>Skills</strong>
            <div class="tags"><?php foreach ($skills as $s): ?><span class="tag"><?= e($s) ?></span><?php endforeach; ?></div>
        </div>
    <?php endif; ?>

    <?php if ($languages): ?>
        <div>
            <strong>Languages</strong>
            <div class="muted"><?= e(implode(', ', $languages)) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($quals): ?>
        <div>
            <strong>Qualifications</strong>
            <div class="muted"><?= e(implode(' • ', $quals)) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($specs): ?>
        <div>
            <strong>Specialisations</strong>
            <div class="muted"><?= e(implode(' • ', $specs)) ?></div>
        </div>
    <?php endif; ?>
</div>
<?php elseif ($u['role'] === 'parent'): ?>
<div class="card section stack">
    <h3>Parent profile details</h3>
    <div class="grid grid-2">
        <div><strong>Children</strong><div class="muted"><?= (int)($u['number_of_children'] ?? 0) ?></div></div>
        <div><strong>Emergency Contact</strong><div class="muted"><?= e((string)($u['emergency_contact'] ?: 'Not set')) ?></div></div>
        <div><strong>Contact Name</strong><div class="muted"><?= e((string)($u['emergency_contact_name'] ?: 'Not set')) ?></div></div>
        <div><strong>Relationship</strong><div class="muted"><?= e((string)($u['emergency_contact_relationship'] ?: 'Not set')) ?></div></div>
    </div>
</div>
<?php endif; ?>

<div class="card section">
    <h3 class="heading-tight">Recent bookings</h3>
    <?php if (!$recentBookings): ?>
        <p class="muted no-margin">No booking activity found for this user yet.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>With</th>
                    <th>Location</th>
                    <th>Hours</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentBookings as $r): ?>
                <tr>
                    <td>#<?= (int)$r['id'] ?></td>
                    <td><?= e(date('d M Y H:i', strtotime((string)$r['date_time']))) ?></td>
                    <td><?= e((string)($r['counterpart'] ?: 'Unknown')) ?></td>
                    <td><?= e((string)($r['location'] ?: 'N/A')) ?></td>
                    <td><?= e((string)$r['duration']) ?></td>
                    <td><?= status_badge((string)$r['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
