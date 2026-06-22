<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/email.php';

if (is_logged_in()) {
    redirect(dashboard_path(user_role()));
}

$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => '', 'role' => 'parent'];
$registered = false;
$registeredEmail = '';
$verificationEmailSent = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['email']     = trim($_POST['email'] ?? '');
    $old['phone']     = trim($_POST['phone'] ?? '');
    $old['role']      = $_POST['role'] ?? 'parent';
    $password         = $_POST['password'] ?? '';

    if ($old['full_name'] === '')                         $errors[] = 'Full name is required.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if (strlen($password) < 8)                            $errors[] = 'Password must be at least 8 characters.';
    if (!in_array($old['role'], ['parent', 'nanny'], true)) $errors[] = 'Please choose a valid role.';

    if (!$errors) {
        $exists = db()->prepare('SELECT 1 FROM users WHERE email = ?');
        $exists->execute([$old['email']]);
        if ($exists->fetch()) {
            $errors[] = 'That email is already registered.';
        }
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO users (full_name, email, phone, password_hash, role, email_verified) VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute([
            $old['full_name'],
            $old['email'],
            $old['phone'] ?: null,
            password_hash($password, PASSWORD_DEFAULT),
            $old['role'],
            0,  // Not verified yet
        ]);
        $userId = (int) $pdo->lastInsertId();

        // A nanny gets an (unverified) profile shell to complete later.
        if ($old['role'] === 'nanny') {
            $pdo->prepare('INSERT INTO nanny_profiles (user_id, verification_status) VALUES (?, "pending")')
                ->execute([$userId]);
        }

        $pdo->commit();

        // Send verification email
        $verificationEmailSent = send_verification_email($userId, $old['email'], $old['full_name']);

        $registered = true;
        $registeredEmail = $old['email'];
    }
}

$pageTitle = 'Create account';
require __DIR__ . '/../includes/header.php';

// If registration successful, show email verification screen
if ($registered):
?>
<div class="card auth-card">
    <!-- Logo -->
    <div class="auth-card-logo">
        <div class="brand-icon auth-brand-icon">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 21.593c-5.63-5.539-11-10.297-11-14.402 0-3.791 3.068-5.191 5.281-5.191 1.312 0 4.151.501 5.719 4.457 1.59-3.968 4.464-4.447 5.726-4.447 2.54 0 5.274 1.621 5.274 5.181 0 4.069-5.136 8.625-11 14.402z"/></svg>
        </div>
        <strong><?= APP_NAME ?></strong>
    </div>

    <h1 class="auth-title">✓ Account Created!</h1>
    <p class="muted auth-subtitle">Welcome to <?= APP_NAME ?>!</p>

    <?php if ($verificationEmailSent): ?>
    <div class="flash flash-success">
        <strong>Check your email!</strong> We've sent a verification link to <?php echo e($registeredEmail); ?> 
        <br><br>
        Click the link in your email to verify your account and get started.
    </div>
    <?php else: ?>
    <div class="flash flash-error">
        <strong>Your account was created, but we could not send the verification email right now.</strong>
        <br><br>
        Please try again from <a href="<?= url('auth/resend-verification.php') ?>">Resend verification email</a>.
    </div>
    <?php endif; ?>

    <div class="auth-panel">
        <strong>What's next?</strong>
        <ul class="auth-list-plain">
            <li>Check your email for the verification link</li>
            <li>Click the link to verify your account</li>
            <li>Log in with your credentials</li>
            <li>Start using <?php echo e(APP_NAME); ?></li>
        </ul>
    </div>

    <p class="muted auth-link-row">
        <a href="<?= url('auth/login.php') ?>">Already verified? Log in here →</a>
    </p>
</div>
<?php
else:
?>
<div class="card auth-card">
    <!-- Logo -->
    <div class="auth-card-logo">
        <div class="brand-icon auth-brand-icon">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 21.593c-5.63-5.539-11-10.297-11-14.402 0-3.791 3.068-5.191 5.281-5.191 1.312 0 4.151.501 5.719 4.457 1.59-3.968 4.464-4.447 5.726-4.447 2.54 0 5.274 1.621 5.274 5.181 0 4.069-5.136 8.625-11 14.402z"/></svg>
        </div>
        <strong><?= APP_NAME ?></strong>
    </div>

    <h1 class="auth-title">Create your account</h1>
    <p class="muted auth-subtitle-tight">Join the <?= APP_NAME ?> family</p>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" id="reg-form">
        <?= csrf_field() ?>

        <!-- Role toggle cards -->
        <div class="role-toggle" role="group" aria-label="Choose your role">
            <label class="role-opt">
                <input type="radio" name="role" value="parent" <?= $old['role'] === 'parent' ? 'checked' : '' ?> required>
                <span class="role-card">
                    <span class="role-ico">👶</span>
                    <strong>I'm a Parent</strong>
                    <small>Find childcare</small>
                </span>
            </label>
            <label class="role-opt">
                <input type="radio" name="role" value="nanny" <?= $old['role'] === 'nanny' ? 'checked' : '' ?>>
                <span class="role-card">
                    <span class="role-ico">✨</span>
                    <strong>I'm a Nanny</strong>
                    <small>Offer your care</small>
                </span>
            </label>
        </div>

        <div class="field">
            <label for="r-name">Full name</label>
            <input id="r-name" name="full_name" value="<?= e($old['full_name']) ?>" placeholder="Jane Doe" autocomplete="name" required autofocus>
        </div>
        <div class="field">
            <label for="r-email">Email</label>
            <input id="r-email" type="email" name="email" value="<?= e($old['email']) ?>" placeholder="you@example.com" autocomplete="email" required>
        </div>
        <div class="field">
            <label for="r-pw">Password</label>
            <input id="r-pw" type="password" name="password" minlength="8" placeholder="At least 8 characters" autocomplete="new-password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block auth-form-btn-gap">Create account</button>
    </form>

    <div class="auth-divider">OR</div>
    <p class="muted auth-center auth-center-tight">
        Already have an account? <a href="<?= url('auth/login.php') ?>">Log in</a>
    </p>
</div>
<?php 
endif;
require __DIR__ . '/../includes/footer.php'; ?>
