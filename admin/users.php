<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

// Verify / reject a nanny, or delete a user.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($action === 'verify' || $action === 'reject') {
        $status = $action === 'verify' ? 'verified' : 'rejected';
        db()->prepare('UPDATE nanny_profiles SET verification_status=? WHERE user_id=?')
            ->execute([$status, $userId]);
        flash('Nanny marked as ' . $status . '.');
    } elseif ($action === 'suspend' || $action === 'unsuspend') {
        if ($userId === (int) current_user()['id']) {
            flash('You cannot suspend your own account.', 'error');
        } else {
            $status = $action === 'suspend' ? 'suspended' : 'active';
            db()->prepare('UPDATE users SET status=? WHERE id=?')->execute([$status, $userId]);
            if ($status === 'suspended') {
                notify($userId, 'Account suspended', 'Your account has been suspended by an administrator.');
            }
            flash('User ' . ($status === 'suspended' ? 'suspended' : 'reactivated') . '.');
        }
    } elseif ($action === 'delete') {
        if ($userId === (int) current_user()['id']) {
            flash('You cannot delete your own admin account.', 'error');
        } else {
            db()->prepare('DELETE FROM users WHERE id=?')->execute([$userId]);
            flash('User deleted.');
        }
    }
    redirect('admin/users.php');
}

$users = db()->query(
    "SELECT u.id, u.full_name, u.email, u.role, u.status, u.created_at, p.verification_status
     FROM users u
     LEFT JOIN nanny_profiles p ON p.user_id = u.id
     ORDER BY (p.verification_status='pending') DESC, u.created_at DESC"
)->fetchAll();

$pageTitle = 'Manage users';
require __DIR__ . '/../includes/header.php';
?>
<h1>Users &amp; verifications</h1>
<div class="card section">
    <table class="table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Verification</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e($u['full_name']) ?></td>
                <td><?= e($u['email']) ?></td>
                <td><span class="tag"><?= e(ucfirst($u['role'])) ?></span></td>
                <td><?= $u['role'] === 'nanny' ? status_badge($u['verification_status'] ?? 'pending') : '<span class="muted">—</span>' ?></td>
                <td><?= ($u['status'] ?? 'active') === 'suspended' ? status_badge('suspended') : '<span class="badge badge-ok">Active</span>' ?></td>
                <td class="muted"><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
                <td>
                    <a class="btn btn-sm" href="<?= url('admin/user_profile.php?id=' . (int)$u['id']) ?>">View profile</a>
                    <form method="post" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <?php if ($u['role'] === 'nanny' && ($u['verification_status'] ?? '') !== 'verified'): ?>
                            <button class="btn btn-sm btn-primary" name="action" value="verify">Verify</button>
                        <?php endif; ?>
                        <?php if ($u['role'] === 'nanny' && ($u['verification_status'] ?? '') === 'verified'): ?>
                            <button class="btn btn-sm" name="action" value="reject">Unverify</button>
                        <?php endif; ?>
                        <?php if ($u['role'] !== 'admin'): ?>
                            <?php if (($u['status'] ?? 'active') === 'suspended'): ?>
                                <button class="btn btn-sm btn-primary" name="action" value="unsuspend">Reactivate</button>
                            <?php else: ?>
                                <button class="btn btn-sm" name="action" value="suspend" data-confirm="Suspend this user?">Suspend</button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-danger" name="action" value="delete" data-confirm="Delete this user permanently?">Delete</button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
