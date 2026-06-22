<?php
require_once __DIR__ . '/../config/config.php';

if (is_logged_in()) {
    redirect(dashboard_path(user_role()));
}

/* ── Rate-limiting table (created once if absent) ───────────────────── */
try {
    db()->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ip        VARCHAR(45)  NOT NULL,
        attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_time (ip, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable) {}

/* ── Rate-limit helpers ─────────────────────────────────────────────── */
function get_client_ip(): string {
    $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    return $forwardedFor !== ''
        ? trim(explode(',', $forwardedFor)[0])
        : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function count_recent_attempts(string $ip): int {
    try {
        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE ip = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );
        $stmt->execute([$ip]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable) { return 0; }
}

function record_attempt(string $ip): void {
    try {
        db()->prepare("INSERT INTO login_attempts (ip) VALUES (?)")->execute([$ip]);
    } catch (Throwable) {}
}

function clear_attempts(string $ip): void {
    try {
        db()->prepare("DELETE FROM login_attempts WHERE ip = ?")->execute([$ip]);
    } catch (Throwable) {}
}

$errors = [];
$email  = '';
$ip     = get_client_ip();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    /* ── Brute-force check ────────────────────────────────────────── */
    if (count_recent_attempts($ip) >= 5) {
        $errors[] = 'Too many login attempts. Please wait 15 minutes and try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {

            /* ── Suspended check ──────────────────────────────────── */
            if (($user['status'] ?? 'active') === 'suspended') {
                $errors[] = 'Your account has been suspended. Please contact support.';

            /* ── Email verification check ─────────────────────────── */
            } elseif (isset($user['email_verified']) && (int)$user['email_verified'] === 0) {
                $errors[] = 'Please verify your email address before logging in. Check your inbox for a verification link.';

            } else {
                clear_attempts($ip);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];

                /* ── Remember me ──────────────────────────────────── */
                if (!empty($_POST['remember'])) {
                    $token = bin2hex(random_bytes(32));
                    try {
                        db()->prepare('UPDATE users SET remember_token=? WHERE id=?')
                           ->execute([$token, $user['id']]);
                        setcookie('na_remember', $token, time() + 60 * 60 * 24 * 30, '/', '', false, true);
                    } catch (Throwable) {}
                }
                flash('Welcome back, ' . $user['full_name'] . '!');
                redirect(dashboard_path($user['role']));
            }
        } else {
            record_attempt($ip);
            $remaining = max(0, 5 - count_recent_attempts($ip));
            if ($remaining > 0) {
                $errors[] = 'Incorrect email or password. Please try again. (' . $remaining . ' attempt' . ($remaining === 1 ? '' : 's') . ' remaining)';
            } else {
                $errors[] = 'Too many failed attempts. Please wait 15 minutes and try again.';
            }
        }
    }
}

$pageTitle = 'Log in';
require __DIR__ . '/../includes/header.php';
?>
<div class="auth">
    <div class="auth-main">
        <p class="h-eyebrow">Welcome back</p>
        <h1>Log in to <?= APP_NAME ?></h1>
        <p class="muted">Pick up where you left off — manage bookings, messages and more.</p>

        <?php foreach ($errors as $err): ?>
            <div class="flash flash-error"><?= e($err) ?></div>
        <?php endforeach; ?>

        <!-- Show resend verification link if email verification error -->
        <?php if (!empty($errors) && strpos(implode(' ', $errors), 'verify your email') !== false): ?>
            <div class="auth-helper-box">
                <p>
                    <strong>📧 Didn't receive the email?</strong>
                    <br><a class="auth-helper-link" href="<?= url('auth/resend-verification.php') ?>">Resend verification email →</a>
                </p>
            </div>
        <?php endif; ?>

        <div class="demo-bar">
            <span class="demo-bar-label">Demo accounts — Log in as</span>
            <div class="demo-bar-btns">
                <button type="button" class="btn btn-sm demo-btn" data-email="parent@nanny.app" data-pass="Password123!">Parent</button>
                <button type="button" class="btn btn-sm demo-btn" data-email="amelia@nanny.app" data-pass="Password123!">Nanny</button>
                <button type="button" class="btn btn-sm demo-btn" data-email="admin@nanny.app"  data-pass="Password123!">Admin</button>
            </div>
        </div>

        <form method="post" class="auth-form stack auth-form-offset">
            <?= csrf_field() ?>
            <div class="field">
                <label for="l-email">Email</label>
                <input id="l-email" type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required autofocus>
            </div>
            <div class="field">
                <label for="l-pw">Password</label>
                <input id="l-pw" type="password" name="password" autocomplete="current-password" required>
                <div class="auth-field-help">
                    <a class="auth-link-xs" href="<?= url('auth/forgot.php') ?>">Forgot password?</a>
                </div>
            </div>
            <label class="auth-remember">
                <input type="checkbox" name="remember" value="1"> Remember me for 30 days
            </label>
            <button class="btn btn-primary btn-block">Log in</button>
        </form>

        <p class="muted auth-footnote">No account yet? <a href="<?= url('auth/register.php') ?>">Create one free →</a></p>
    </div>

    <aside class="auth-aside">
        <span class="pill">✨ Care • Connect • Comfort</span>
        <h2>Trusted childcare, a few taps away.</h2>
        <ul class="auth-list">
            <li><span class="ck">✓</span> Browse vetted, verified nannies near you</li>
            <li><span class="ck">✓</span> Book by the hour and track every session</li>
            <li><span class="ck">✓</span> Message, review and rebook your favourites</li>
        </ul>
        <div class="auth-quote">"Finding reliable care has never been this easy."
            <span>— A happy Nanny-App family</span></div>
    </aside>
</div>
<script>
document.querySelectorAll('.demo-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('l-email').value = btn.dataset.email;
        document.getElementById('l-pw').value    = btn.dataset.pass;
        btn.closest('.auth-main').querySelector('form').submit();
    });
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
