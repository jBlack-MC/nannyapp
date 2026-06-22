<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action  = $_POST['action'] ?? '';
    $userId  = (int) ($_POST['user_id'] ?? 0);

    if (in_array($action, ['verify','reject','suspend']) && $userId) {
        if ($action === 'verify') {
            db()->prepare("UPDATE nanny_profiles SET verification_status='verified' WHERE user_id=?")
                ->execute([$userId]);
            notify($userId, 'Profile verified!',
                'Congratulations — your nanny profile has been verified. You now appear in parent search results.',
                'nanny/dashboard.php');
            flash('Profile verified and nanny notified.');
        } elseif ($action === 'reject') {
            db()->prepare("UPDATE nanny_profiles SET verification_status='rejected' WHERE user_id=?")
                ->execute([$userId]);
            notify($userId, 'Profile needs attention',
                'Your profile verification was not approved. Please update your profile and resubmit.',
                'nanny/profile.php');
            flash('Profile rejected. Nanny notified.');
        }
        redirect('admin/verifications.php');
    }

    // Document verification
    $docId  = (int) ($_POST['doc_id'] ?? 0);
    $docAct = $_POST['doc_action'] ?? '';
    if ($docId && $docAct === 'verify_doc') {
        try {
            db()->prepare('UPDATE nanny_portfolio SET admin_verified=1 WHERE id=?')->execute([$docId]);
            flash('Document marked as verified.');
        } catch (Throwable $e) { flash('Could not update document.', 'error'); }
        redirect('admin/verifications.php');
    }
}

// Load pending nannies
$pending = db()->query(
    "SELECT u.id, u.full_name, u.email, u.profile_image, u.created_at,
            p.bio, p.experience_years, p.hourly_rate, p.location,
            p.skills, p.qualifications, p.specialisations, p.languages,
            p.verification_status
     FROM nanny_profiles p JOIN users u ON u.id=p.user_id
     WHERE p.verification_status IN ('pending','rejected')
     ORDER BY u.created_at ASC"
)->fetchAll();

// Load pending documents
$pendingDocs = [];
try {
    $pd = db()->query(
        "SELECT np.*, u.full_name AS nanny_name
         FROM nanny_portfolio np JOIN users u ON u.id=np.nanny_id
         WHERE np.admin_verified = 0
         ORDER BY np.created_at ASC"
    );
    $pendingDocs = $pd->fetchAll();
} catch (Throwable $e) {}

$pageTitle = 'Verifications';
require __DIR__ . '/../includes/header.php';
?>
<div class="section-head">
    <div>
        <p class="h-eyebrow">Admin</p>
        <h1>Verification queue</h1>
        <p class="muted"><?= count($pending) ?> nann<?= count($pending)===1?'y':'ies' ?> awaiting review · <?= count($pendingDocs) ?> document<?= count($pendingDocs)===1?'':'s' ?> to verify</p>
    </div>
    <a class="btn" href="<?= url('admin/dashboard.php') ?>">← Dashboard</a>
</div>

<!-- Nanny profile verifications -->
<?php if (!$pending): ?>
<div class="empty">
    <div class="empty-ico">✅</div>
    <h3>All profiles verified</h3>
    <p>No nanny profiles are pending review right now.</p>
</div>
<?php else: ?>
<div class="verify-list">
    <?php foreach ($pending as $n):
        $skills = array_filter(array_map('trim', explode(',', (string)$n['skills'])));
        $quals  = array_filter(array_map('trim', explode(',', (string)($n['qualifications'] ?? ''))));
        $langs  = array_filter(array_map('trim', explode(',', (string)($n['languages'] ?? ''))));
    ?>
    <div class="card verify-card">
        <div>
            <div class="verify-head">
                <?= avatar($n['full_name'], $n['profile_image'] ?? null, 'avatar-lg') ?>
                <div>
                    <strong class="verify-name"><?= e($n['full_name']) ?></strong>
                    <div class="muted"><?= e($n['email']) ?> · <?= e($n['location'] ?: 'No location') ?></div>
                    <div class="verify-status-row">
                        <?= status_badge($n['verification_status']) ?>
                        <span class="muted text-xs verify-reg-date">Registered <?= e(date('d M Y', strtotime($n['created_at']))) ?></span>
                    </div>
                </div>
            </div>

            <div class="verify-grid">
                <div><strong>Experience</strong><div class="muted"><?= (int)$n['experience_years'] ?> years</div></div>
                <div><strong>Rate</strong><div class="muted">R<?= number_format((float)$n['hourly_rate'],0) ?>/hr</div></div>
                <?php if ($langs): ?><div><strong>Languages</strong><div class="muted"><?= e(implode(', ', $langs)) ?></div></div><?php endif; ?>
            </div>

            <?php if ($n['bio']): ?>
                <p class="muted verify-bio"><?= e(substr($n['bio'], 0, 200)) ?><?= strlen($n['bio'])>200?'…':'' ?></p>
            <?php endif; ?>

            <?php if ($skills): ?>
            <div class="tags tags-tight">
                <?php foreach ($skills as $s): ?><span class="tag"><?= e($s) ?></span><?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($quals): ?>
            <div class="muted qual-line">📋 <?= e(implode(' · ', $quals)) ?></div>
            <?php endif; ?>
        </div>

        <div class="verify-actions">
            <a class="btn btn-sm w-110" href="<?= url('admin/user_profile.php?id=' . (int)$n['id']) ?>">View profile</a>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= $n['id'] ?>">
                <input type="hidden" name="action" value="verify">
                <button class="btn btn-sm btn-ok w-110"
                        data-confirm="Verify <?= e($n['full_name']) ?>'s profile?">
                    ✓ Verify
                </button>
            </form>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= $n['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <button class="btn btn-sm btn-danger w-110"
                        data-confirm="Reject <?= e($n['full_name']) ?>'s profile?">
                    ✗ Reject
                </button>
            </form>
            <a class="btn btn-sm w-110" href="<?= url('messages.php?with=' . $n['id']) ?>">Message</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Document verifications -->
<?php if ($pendingDocs): ?>
<div class="card">
    <h2 class="doc-title">Documents pending verification (<?= count($pendingDocs) ?>)</h2>
    <div class="doc-grid">
        <?php foreach ($pendingDocs as $doc): ?>
        <div class="card doc-card">
            <div class="doc-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
            <div class="doc-info">
                <h4><?= e($doc['title']) ?></h4>
                <div class="muted"><?= e($doc['nanny_name']) ?> · <?= ucfirst($doc['type']) ?></div>
                <div class="muted doc-date"><?= e(date('d M Y', strtotime($doc['created_at']))) ?></div>
                <div class="doc-actions">
                    <a class="btn btn-sm" href="<?= e(url($doc['file_path'])) ?>" target="_blank">View</a>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                        <input type="hidden" name="doc_action" value="verify_doc">
                        <button class="btn btn-sm btn-ok">✓ Verify</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
