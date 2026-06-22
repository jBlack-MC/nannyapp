<?php
require_once __DIR__ . '/../config/config.php';

// Ensure the contact inbox table exists (safe to run on every request, so the
// form works on an existing database without re-importing schema.sql).
db()->exec(
    "CREATE TABLE IF NOT EXISTS contact_messages (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(100)  NOT NULL,
        email       VARCHAR(150)  NOT NULL,
        subject     VARCHAR(150)  DEFAULT NULL,
        message     VARCHAR(2000) NOT NULL,
        created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB"
);

$errors = [];
$old = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];

// Pre-fill from the logged-in user, if any.
if ($u = current_user()) {
    $old['name']  = $u['full_name'];
    $old['email'] = $u['email'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old['name']    = trim($_POST['name'] ?? '');
    $old['email']   = trim($_POST['email'] ?? '');
    $old['subject'] = trim($_POST['subject'] ?? '');
    $old['message'] = trim($_POST['message'] ?? '');

    if ($old['name'] === '')                                $errors[] = 'Please enter your name.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))  $errors[] = 'A valid email is required.';
    if (mb_strlen($old['message']) < 10)                    $errors[] = 'Please enter a message (at least 10 characters).';

    if (!$errors) {
        db()->prepare(
            'INSERT INTO contact_messages (name, email, subject, message) VALUES (?,?,?,?)'
        )->execute([$old['name'], $old['email'], $old['subject'] ?: null, $old['message']]);

        flash('Thanks, ' . $old['name'] . '! Your message has been sent — we\'ll be in touch.');
        redirect('pages/contact.php');
    }
}

$pageTitle = 'Contact';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <p class="h-eyebrow">Contact</p>
    <h1>Get in touch</h1>
    <p class="muted">Questions, feedback or a safety concern? Send us a message and our team will
        get back to you.</p>
</div>

<section class="section section-no-top">
    <div class="grid grid-2">
        <div class="card stack contact-panel">
            <p class="h-eyebrow">Message us</p>
        <h2>Send a message</h2>
        <?php foreach ($errors as $err): ?>
            <div class="flash flash-error"><?= e($err) ?></div>
        <?php endforeach; ?>
        <form method="post" class="stack">
            <?= csrf_field() ?>
            <div class="field">
                <label for="c-name">Your name</label>
                <input id="c-name" name="name" value="<?= e($old['name']) ?>" required>
            </div>
            <div class="field">
                <label for="c-email">Email</label>
                <input id="c-email" type="email" name="email" value="<?= e($old['email']) ?>" required>
            </div>
            <div class="field">
                <label for="c-subject">Subject (optional)</label>
                <input id="c-subject" name="subject" value="<?= e($old['subject']) ?>" placeholder="How can we help?">
            </div>
            <div class="field">
                <label for="c-message">Message</label>
                <textarea id="c-message" name="message" rows="5" required placeholder="Tell us a bit more…"><?= e($old['message']) ?></textarea>
            </div>
            <button class="btn btn-primary btn-block">Send message</button>
        </form>
        </div>

        <aside class="card stack contact-panel contact-panel-soft">
            <p class="h-eyebrow">Support</p>
            <h2>Other ways to reach us</h2>
            <div class="feature contact-feature">
                <span class="feature-ico">📧</span>
                <div><strong>Email</strong><br><span class="muted">support@nanny.app</span></div>
            </div>
            <div class="feature contact-feature">
                <span class="feature-ico">📞</span>
                <div><strong>Phone</strong><br><span class="muted">+27 67 000 0000 (Mon–Fri, 9–5)</span></div>
            </div>
            <div class="feature contact-feature">
                <span class="feature-ico">🛡️</span>
                <div><strong>Safety concerns</strong><br><span class="muted">Read our <a href="<?= url('pages/safety.php') ?>">safety guide</a> or report an issue here.</span></div>
            </div>
            <div class="feature contact-feature">
                <span class="feature-ico">📍</span>
                <div><strong>Team Knights</strong><br><span class="muted">Built for the Nanny-App project.</span></div>
            </div>
        </aside>
    </div>
</section>

<section class="section section-no-top">
    <div class="card contact-cta text-center stack">
        <p class="h-eyebrow">Need urgent help?</p>
        <h2>We take safety reports seriously</h2>
        <p class="muted">If this is a safeguarding issue, include as much detail as possible and we will prioritize your message.</p>
        <div class="hero-cta actions-center">
            <a class="btn btn-primary" href="<?= url('pages/safety.php') ?>">View safety guide</a>
            <a class="btn btn-outline" href="<?= url('support.php') ?>">Open support center</a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
