<?php
require_once __DIR__ . '/../config/config.php';

// Load FAQs from page_content (slug prefix 'faq-') then fall back to static list
$faqs = [];
try {
    $rows = db()->query(
        "SELECT title AS question, body AS answer FROM page_content WHERE page_key LIKE 'faq-%' ORDER BY id ASC"
    )->fetchAll();
    $faqs = $rows;
} catch (Throwable) {}

// Static fallback if table or rows missing
if (empty($faqs)) {
    $faqs = [
        ['question' => 'How do I book a nanny?',
         'answer'   => 'Create a free account, browse verified nannies near you, then click "Book Now". Our 5-step booking wizard guides you through choosing the date, time, address, children details and completing payment securely.'],
        ['question' => 'How do payments work?',
         'answer'   => 'All payments are processed securely via Paystack. Your card is charged only after the nanny confirms the booking. Money is held safely and only released to the nanny once the session is completed.'],
        ['question' => 'How do I become a nanny?',
         'answer'   => 'Register and choose the "Nanny" role. Complete your profile with your bio, experience, qualifications and ID. Our team reviews and verifies applications within 1–3 business days. Once verified, you appear in parent search results.'],
        ['question' => 'Can I cancel or reschedule a booking?',
         'answer'   => 'Yes. Cancel or reschedule from your Bookings page. Cancellations made more than 24 hours before the booking receive a full refund. Late cancellations may incur a small fee depending on the nanny\'s policy.'],
        ['question' => 'How are nannies verified?',
         'answer'   => 'Every nanny completes identity verification, a criminal background check, reference checks and a qualifications review. Our admin team manually reviews all documentation before awarding the Verified badge.'],
        ['question' => 'How do I contact support?',
         'answer'   => 'Submit a ticket via our Support page, email us, or use the contact form. Our team responds within 24 hours on business days. For urgent safety concerns, use the Emergency button in your dashboard.'],
        ['question' => 'What languages do nannies speak?',
         'answer'   => 'Our nannies speak a variety of South African languages including English, isiZulu, Sesotho, and Afrikaans. Use the language filter on the search page to find nannies who speak your preferred language.'],
        ['question' => 'What if there is a problem during a booking?',
         'answer'   => 'Contact support immediately via the Support page or the in-app emergency button. We investigate all disputes fairly and can issue refunds or take action against any party who violates our community standards.'],
        ['question' => 'Is my personal information safe?',
         'answer'   => 'Yes. We use industry-standard encryption, secure sessions, and we never share your personal information with third parties without your consent. Passwords are hashed using bcrypt.'],
        ['question' => 'How do I leave a review?',
         'answer'   => 'After a booking is marked as completed, you will receive a notification inviting you to rate your nanny out of 5 stars and leave a comment. Reviews appear publicly on the nanny\'s profile.'],
    ];
}

$search = trim($_GET['q'] ?? '');
if ($search) {
    $sl = strtolower($search);
    $faqs = array_filter($faqs, fn($f) =>
        str_contains(strtolower($f['question']), $sl) ||
        str_contains(strtolower($f['answer']), $sl)
    );
}

$pageTitle = 'FAQ';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <p class="h-eyebrow">Help centre</p>
    <h1>Frequently asked questions</h1>
    <p class="muted">Find answers to the most common questions about <?= APP_NAME ?>.</p>
</div>

<!-- Search -->
<form method="get" class="faq-search-form">
    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search questions…"
           class="faq-search-input">
    <button class="btn btn-primary">Search</button>
    <?php if ($search): ?>
        <a class="btn" href="<?= url('pages/faq.php') ?>">Clear</a>
    <?php endif; ?>
</form>

<?php if ($search && !$faqs): ?>
    <div class="empty">
        <div class="empty-ico">🔍</div>
        <h3>No results for "<?= e($search) ?>"</h3>
        <p>Try different keywords or <a href="<?= url('support.php') ?>">contact support</a>.</p>
    </div>
<?php else: ?>
    <?php if ($search): ?>
        <p class="muted faq-search-result"><?= count($faqs) ?> result<?= count($faqs)===1?'':'s' ?> for "<?= e($search) ?>"</p>
    <?php endif; ?>

    <div class="faq">
        <?php foreach ($faqs as $f): ?>
        <div class="faq-item">
            <button class="faq-q" aria-expanded="false">
                <?= e($f['question']) ?><span class="faq-plus">+</span>
            </button>
            <div class="faq-a">
                <div class="faq-a-inner"><?= e($f['answer']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card faq-help-card">
        <div>
            <h3 class="no-margin">Still have a question?</h3>
            <p class="muted faq-help-copy">Our support team is here to help — we respond within 24 hours.</p>
        </div>
        <a class="btn btn-primary" href="<?= url('support.php') ?>">Contact support →</a>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
