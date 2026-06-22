<?php
require_once __DIR__ . '/../config/config.php';

if (is_logged_in()) redirect(dashboard_path(user_role()));

$token  = trim($_GET['token'] ?? '');
$errors = [];
$done   = false;
$row    = null;

// Validate token on every load
if ($token) {
    try {
        $stmt = db()->prepare(
            'SELECT pr.*, u.full_name, u.email
             FROM password_resets pr JOIN users u ON u.id=pr.user_id
             WHERE pr.token=? AND pr.expires_at > NOW()'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {}
}

if (!$token || !$row) {
    $pageTitle = 'Invalid link';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="auth">
        <div class="auth-main">
            <div class="flash flash-error">This reset link is invalid or has expired.</div>
            <p class="muted auth-footnote">
                <a href="<?= url('auth/forgot.php') ?>">Request a new reset link →</a>
            </p>
        </div>
    </div>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pw1 = $_POST['password']  ?? '';
    $pw2 = $_POST['password2'] ?? '';

    if (strlen($pw1) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif ($pw1 !== $pw2) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $hash = password_hash($pw1, PASSWORD_DEFAULT);
        db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash, $row['user_id']]);
        db()->prepare('DELETE FROM password_resets WHERE token=?')->execute([$token]);
        $done = true;
    }
}

$pageTitle = 'Choose new password';
require __DIR__ . '/../includes/header.php';
?>
<div class="auth">
    <div class="auth-main">
        <p class="h-eyebrow">Account recovery</p>
        <h1>Choose a new password</h1>
        <p class="muted">Hi <?= e($row['full_name']) ?> — choose a strong new password below.</p>

        <?php foreach ($errors as $err): ?>
            <div class="flash flash-error"><?= e($err) ?></div>
        <?php endforeach; ?>

        <?php if ($done): ?>
            <div class="flash flash-success auth-footnote">
                <strong>Password changed!</strong> You can now log in with your new password.
            </div>
            <a class="btn btn-primary mt-12" href="<?= url('auth/login.php') ?>">Log in now →</a>
        <?php else: ?>
            <form method="post" class="auth-form stack auth-form-offset">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="field">
                    <label for="rp-pw">New password <span class="muted">(min. 8 characters)</span></label>
                    <input id="rp-pw" type="password" name="password" autocomplete="new-password" required minlength="8" autofocus>
                </div>
                <div class="field">
                    <label for="rp-pw2">Confirm new password</label>
                    <input id="rp-pw2" type="password" name="password2" autocomplete="new-password" required minlength="8">
                </div>
                <button class="btn btn-primary btn-block">Set new password</button>
            </form>
        <?php endif; ?>
    </div>

    <aside class="auth-aside">
        <span class="pill">🔒 Secure reset</span>
        <h2>Your security matters.</h2>
        <ul class="auth-list">
            <li><span class="ck">✓</span> Use at least 8 characters</li>
            <li><span class="ck">✓</span> Mix letters, numbers and symbols</li>
            <li><span class="ck">✓</span> Never share your password</li>
        </ul>
    </aside>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
