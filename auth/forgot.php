<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/email.php';

if (is_logged_in()) redirect(dashboard_path(user_role()));

$sent   = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim(strtolower($_POST['email'] ?? ''));

    // Rate limiting: max 3 attempts per 15 minutes
    $rateLimitKey = 'forgot_password_' . $email;
    if (is_rate_limited($rateLimitKey, 3, 900)) {
        $errors[] = 'Too many reset requests. Please try again in 15 minutes.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        increment_rate_limit($rateLimitKey);
        
        $stmt = db()->prepare('SELECT id, full_name FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Invalidate old tokens for this user
            try {
                db()->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([$user['id']]);
            } catch (Throwable $e) {}

            // Send password reset email
            if (send_password_reset_email((int) $user['id'], $email, $user['full_name'])) {
                $sent = true;
            } else {
                $errors[] = 'Failed to send email. Please try again later.';
            }
        } else {
            // Don't reveal whether the email exists
            $sent = true;
        }
    }
}


$pageTitle = 'Forgot password';
require __DIR__ . '/../includes/header.php';
?>
<div class="auth">
    <div class="auth-main">
        <p class="h-eyebrow">Account recovery</p>
        <h1>Reset your password</h1>
        <p class="muted">Enter your email and we'll send you a link to choose a new password.</p>

        <?php foreach ($errors as $err): ?>
            <div class="flash flash-error"><?= e($err) ?></div>
        <?php endforeach; ?>

        <?php if ($sent): ?>
            <div class="flash flash-success auth-footnote">
                <strong>Check your email.</strong> If an account exists for that address, we've sent a password reset link. It expires in 1 hour.
            </div>
            <p class="muted auth-footnote"><a href="<?= url('auth/login.php') ?>">← Back to log in</a></p>
        <?php else: ?>
            <form method="post" class="auth-form stack auth-form-offset">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="fp-email">Email address</label>
                    <input id="fp-email" type="email" name="email" autocomplete="email" required autofocus
                           value="<?= e($_POST['email'] ?? '') ?>">
                </div>
                <button class="btn btn-primary btn-block">Send reset link</button>
            </form>
            <p class="muted auth-footnote"><a href="<?= url('auth/login.php') ?>">← Back to log in</a></p>
        <?php endif; ?>
    </div>

    <aside class="auth-aside">
        <span class="pill">🔒 Account security</span>
        <h2>Keep your account safe.</h2>
        <ul class="auth-list">
            <li><span class="ck">✓</span> Reset link sent to your email only</li>
            <li><span class="ck">✓</span> Link expires in 1 hour</li>
            <li><span class="ck">✓</span> Old passwords are never stored</li>
        </ul>
    </aside>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
