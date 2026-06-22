<?php
/**
 * Resend Verification Email
 * User can request a new verification email if they didn't receive the first one
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/email.php';

// Already logged in and verified? Redirect
if (is_logged_in()) {
    $user = current_user();
    if ($user['email_verified']) {
        redirect(dashboard_path(user_role()));
    }
}

$sent = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim(strtolower($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        // Rate limiting: max 3 resend attempts per 15 minutes
        $rateLimitKey = 'resend_verification_' . $email;
        if (is_rate_limited($rateLimitKey, 3, 900)) {
            $errors[] = 'Too many verification email requests. Please try again in 15 minutes.';
        } else {
            increment_rate_limit($rateLimitKey);

            // Find unverified user
            $stmt = db()->prepare('SELECT id, full_name FROM users WHERE email = ? AND email_verified = 0');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Send verification email
                if (send_verification_email((int) $user['id'], $email, $user['full_name'])) {
                    $sent = true;
                } else {
                    $errors[] = 'Failed to send verification email. Please try again later.';
                }
            } else {
                // Account either doesn't exist or is already verified
                // Security: don't reveal which
                $sent = true;
            }
        }
    }
}

$pageTitle = 'Resend Verification Email';
require __DIR__ . '/../includes/header.php';
?>
<div class="card auth-card">
    <!-- Logo -->
    <div class="auth-card-logo">
        <div class="brand-icon auth-brand-icon">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 21.593c-5.63-5.539-11-10.297-11-14.402 0-3.791 3.068-5.191 5.281-5.191 1.312 0 4.151.501 5.719 4.457 1.59-3.968 4.464-4.447 5.726-4.447 2.54 0 5.274 1.621 5.274 5.181 0 4.069-5.136 8.625-11 14.402z"/></svg>
        </div>
        <strong><?= APP_NAME ?></strong>
    </div>

    <h1 class="auth-title">📧 Resend Verification</h1>
    <p class="muted auth-subtitle">Didn't receive the verification email?</p>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <?php if ($sent): ?>
        <div class="flash flash-success">
            <strong>Email sent!</strong> Check your inbox and spam folder for a verification link.
        </div>
        <div class="auth-panel auth-panel-note">
            <p>Once verified, you can log in with your credentials.</p>
        </div>
        <p class="muted auth-link-row">
            <a href="<?= url('auth/login.php') ?>">Back to Login →</a>
        </p>
    <?php else: ?>
        <form method="post">
            <?= csrf_field() ?>

            <div class="field">
                <label for="rv-email">Email address</label>
                <input 
                    id="rv-email" 
                    type="email" 
                    name="email" 
                    placeholder="you@example.com" 
                    value="<?= e($_POST['email'] ?? '') ?>"
                    required 
                    autofocus
                >
            </div>

            <button class="btn btn-primary btn-block">Send Verification Email</button>
        </form>

        <p class="muted auth-link-row">
            <a href="<?= url('auth/login.php') ?>">Back to Login</a> | 
            <a href="<?= url('auth/register.php') ?>">Create Account</a>
        </p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
