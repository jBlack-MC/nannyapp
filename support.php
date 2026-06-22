<?php
require_once __DIR__ . '/config/config.php';

$me   = current_user();
$done = false;
$errors = [];

$categories = [
    'booking'   => 'Booking issue',
    'payment'   => 'Payment or refund',
    'technical' => 'Technical problem',
    'safety'    => 'Safety concern',
    'general'   => 'General question',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name     = trim($_POST['name']     ?? ($me['full_name'] ?? ''));
    $email    = trim($_POST['email']    ?? ($me['email']     ?? ''));
    $category = $_POST['category'] ?? 'general';
    $subject  = trim($_POST['subject']  ?? '');
    $message  = trim($_POST['message']  ?? '');

    if (!$name)                                      $errors[] = 'Please enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = 'Please enter a valid email.';
    if (!array_key_exists($category, $categories))   $category = 'general';
    if (strlen($subject) < 5)                        $errors[] = 'Please enter a subject (at least 5 characters).';
    if (strlen($message) < 20)                       $errors[] = 'Please describe your issue (at least 20 characters).';

    if (!$errors) {
        try {
            $ticketId = create_support_ticket($name, $email, $subject, $message, $category, $me ? $me['id'] : null);

            // Confirmation email to submitter
            $textBody = 'Hi ' . $name . ",\n\nWe have received your support request (Ticket #" . $ticketId . "). Our team will respond within 24 business hours.\n\n" .
                'Subject: ' . $subject . "\n\nThank you for contacting " . APP_NAME . '.';

            $htmlBody = '<p>Hi ' . htmlspecialchars($name) . ',</p>' .
                '<p>We have received your support request (Ticket #' . $ticketId . '). Our team will respond within 24 business hours.</p>' .
                '<p><strong>Subject:</strong> ' . htmlspecialchars($subject) . '</p>' .
                '<p>Thank you for contacting ' . APP_NAME . '.</p>';

            send_email($email, 'Support request received — ' . APP_NAME, $textBody, $htmlBody);

            // Notify admin
            $admins = db()->query("SELECT id FROM users WHERE role='admin' LIMIT 3")->fetchAll();
            foreach ($admins as $admin) {
                notify($admin['id'], 'New support ticket', "#{$ticketId} — {$subject}", 'admin/support.php');
            }

            $done = true;
        } catch (Throwable $e) {
            $errors[] = 'Could not submit your ticket. Please try again.';
        }
    }
}

$pageTitle = 'Support';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <p class="h-eyebrow">Help centre</p>
    <h1>How can we help you?</h1>
    <p class="muted">Submit a support ticket below and our team will respond within 24 hours.</p>
</div>

<div class="grid grid-2 support-grid">

    <!-- Form -->
    <div>
        <?php foreach ($errors as $err): ?>
            <div class="flash flash-error"><?= e($err) ?></div>
        <?php endforeach; ?>

        <?php if ($done): ?>
            <div class="card stack support-success">
                <div class="emoji-xl">✅</div>
                <h2 class="tight-heading">Ticket submitted!</h2>
                <p class="muted">We've sent a confirmation to your email. Our team will be in touch within 24 hours on business days.</p>
                <a class="btn btn-primary btn-align-self-center" href="<?= url('index.php') ?>">Back to home</a>
            </div>
        <?php else: ?>
            <form method="post" class="card stack">
                <?= csrf_field() ?>

                <h2 class="tight-heading">Submit a ticket</h2>

                <div class="grid grid-2 form-grid-2">
                    <div class="field field-tight">
                        <label>Your name</label>
                        <input name="name" value="<?= e($_POST['name'] ?? ($me['full_name'] ?? '')) ?>" required>
                    </div>
                    <div class="field field-tight">
                        <label>Email address</label>
                        <input type="email" name="email" value="<?= e($_POST['email'] ?? ($me['email'] ?? '')) ?>" required>
                    </div>
                </div>

                <div class="field">
                    <label>Category</label>
                    <select name="category">
                        <?php foreach ($categories as $val => $label): ?>
                            <option value="<?= $val ?>" <?= ($_POST['category'] ?? '') === $val ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Subject</label>
                    <input name="subject" value="<?= e($_POST['subject'] ?? '') ?>" placeholder="Brief description of your issue" required>
                </div>

                <div class="field">
                    <label>Message</label>
                    <textarea name="message" rows="6" placeholder="Please describe your issue in as much detail as possible…" required><?= e($_POST['message'] ?? '') ?></textarea>
                </div>

                <button class="btn btn-primary btn-align-self-start">Submit ticket</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="stack">
        <div class="card stack">
            <h3 class="tight-heading">Quick answers</h3>
            <ul class="quick-links">
                <li><a href="<?= url('pages/faq.php') ?>">How do I book a nanny?</a></li>
                <li><a href="<?= url('pages/faq.php') ?>">How do payments work?</a></li>
                <li><a href="<?= url('pages/faq.php') ?>">How do I become a nanny?</a></li>
                <li><a href="<?= url('pages/faq.php') ?>">Can I cancel a booking?</a></li>
                <li><a href="<?= url('pages/safety.php') ?>">How are nannies verified?</a></li>
            </ul>
            <a class="btn" href="<?= url('pages/faq.php') ?>">View all FAQs →</a>
        </div>

        <div class="card stack">
            <h3 class="tight-heading">Response times</h3>
            <div class="response-list">
                <div class="response-row">
                    <span>General questions</span><span class="badge badge-ok">Within 24 hrs</span>
                </div>
                <div class="response-row">
                    <span>Booking issues</span><span class="badge badge-warn">Within 4 hrs</span>
                </div>
                <div class="response-row">
                    <span>Safety concerns</span><span class="badge badge-bad">Urgent</span>
                </div>
            </div>
        </div>

        <div class="card stack">
            <h3 class="tight-heading">Emergency?</h3>
            <p class="muted muted-tight">For immediate safety concerns, please call emergency services or use the emergency button in your dashboard.</p>
            <a class="btn btn-emergency" href="tel:10111">🚨 Emergency: 10111</a>
        </div>
    </div>

</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
