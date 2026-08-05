<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$me = current_user()['id'];

db()->prepare('INSERT IGNORE INTO admin_profiles (user_id, access_level) VALUES (?, "support")')
    ->execute([$me]);

$stmt = db()->prepare('SELECT * FROM admin_profiles WHERE user_id = ?');
$stmt->execute([$me]);
$p = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $department = trim($_POST['department'] ?? '');
    $phoneExt   = trim($_POST['phone_ext'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    db()->prepare('UPDATE admin_profiles SET department=?, phone_ext=?, notes=? WHERE user_id=?')
        ->execute([$department ?: null, $phoneExt ?: null, $notes ?: null, $me]);

    flash('Admin profile saved.');
    redirect('admin/profile.php');
}

// Platform snapshot (context for the admin, not per-admin attribution — the
// schema doesn't track which admin actioned what, so we show overall state).
$snapshot = [
    'users' => 0, 'pending_verifications' => 0, 'open_tickets' => 0, 'disputed_bookings' => 0,
];
try {
    $snapshot['users'] = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $snapshot['pending_verifications'] = (int) db()->query(
        "SELECT COUNT(*) FROM nanny_profiles WHERE verification_status='pending'"
    )->fetchColumn();
} catch (Throwable) {}
try {
    $snapshot['open_tickets'] = (int) db()->query(
        "SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','in_progress')"
    )->fetchColumn();
} catch (Throwable) {
    // support_tickets is created by migrate_v3 — ignore until it has been run.
}
try {
    $snapshot['disputed_bookings'] = (int) db()->query(
        "SELECT COUNT(*) FROM bookings WHERE status='disputed'"
    )->fetchColumn();
} catch (Throwable) {}

$levelLabels = [
    'super_admin' => 'Super admin',
    'support'     => 'Support',
    'moderator'   => 'Moderator',
];
$levelTone = [
    'super_admin' => 'success',
    'support'     => 'neutral',
    'moderator'   => 'warning',
];

$fields = ['department', 'phone_ext', 'notes'];
$filled = array_filter($fields, fn($f) => !empty($p[$f]));
$complete = (int) (count($filled) / count($fields) * 100);

$pageTitle = 'My Profile';
require __DIR__ . '/../includes/header.php';
?>
<div class="section-head">
    <div>
        <p class="h-eyebrow">Admin</p>
        <h1>My profile</h1>
    </div>
    <a class="btn" href="<?= url('admin/dashboard.php') ?>">← Dashboard</a>
</div>

<?php if ($complete < 100): ?>
<div class="profile-complete-bar profile-complete-gap">
    <div class="profile-complete-main">
        <div class="profile-complete-title">Profile <?= $complete ?>% complete</div>
        <div class="pc-progress"><div class="pc-fill" data-width-pct="<?= $complete ?>"></div></div>
    </div>
    <span class="pc-label"><?= 100 - $complete ?>% remaining</span>
</div>
<?php endif; ?>

<div class="card section">
    <div class="panel-row-info">
        <div class="panel-row-info">
            <?= avatar((string)current_user()['full_name'], current_user()['profile_image'] ?? null, 'avatar-lg') ?>
            <div>
                <h2 class="heading-tight no-margin"><?= e((string)current_user()['full_name']) ?></h2>
                <p class="muted no-margin"><?= e((string)current_user()['email']) ?></p>
                <div class="nanny-badge-row">
                    <span class="tag">Admin</span>
                    <?= badge($levelLabels[$p['access_level']] ?? ucfirst((string)$p['access_level']), $levelTone[$p['access_level']] ?? 'neutral') ?>
                </div>
            </div>
        </div>
    </div>
    <p class="muted mt-4 text-xs">Access level is assigned by a super admin and can't be changed from this page. Update your name, email, phone or password from <a href="<?= url('account.php') ?>">Account settings</a>.</p>
</div>

<div class="stats section section-no-top">
    <div class="stat"><span class="stat-ico">👥</span><b><?= (int)$snapshot['users'] ?></b>Total users</div>
    <div class="stat"><span class="stat-ico">🔍</span><b><?= (int)$snapshot['pending_verifications'] ?></b>Verifications pending</div>
    <div class="stat"><span class="stat-ico">🎫</span><b><?= (int)$snapshot['open_tickets'] ?></b>Open tickets</div>
    <div class="stat"><span class="stat-ico">⚠️</span><b><?= (int)$snapshot['disputed_bookings'] ?></b>Disputed bookings</div>
</div>

<form method="post" class="card stack section section-no-top">
    <?= csrf_field() ?>
    <h2 class="heading-tight">Admin details</h2>
    <div class="form-grid-2">
        <div class="field field-zero">
            <label for="ap-dept">Department</label>
            <input id="ap-dept" name="department" value="<?= e($p['department'] ?? '') ?>" placeholder="e.g. Trust & Safety, Support">
        </div>
        <div class="field field-zero">
            <label for="ap-ext">Phone extension</label>
            <input id="ap-ext" name="phone_ext" value="<?= e($p['phone_ext'] ?? '') ?>" placeholder="e.g. 204">
        </div>
    </div>
    <div class="field">
        <label for="ap-notes">Internal notes <span class="muted">(visible to other admins only)</span></label>
        <textarea id="ap-notes" name="notes" rows="4" placeholder="Anything the rest of the team should know…"><?= e($p['notes'] ?? '') ?></textarea>
    </div>
    <button class="btn btn-primary btn-min-200">Save profile</button>
</form>

<?php if ((int) $snapshot['disputed_bookings'] > 0): ?>
<div class="card card-note-info section section-no-top">
    <h3>⚠️ Disputed bookings need review</h3>
    <p class="muted">There are bookings where a parent reported the nanny never arrived. Review and resolve them from <a href="<?= url('admin/payments.php') ?>">Payments</a>.</p>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
