<?php
require_once __DIR__ . '/../config/config.php';
$pageTitle = 'Safety';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <p class="h-eyebrow">Safety &amp; trust</p>
    <h1>How we help keep families safe</h1>
    <p class="muted">Safety is the foundation of Nanny-App. Here's how we protect parents,
        nannies and children at every step.</p>
</div>

<section class="section grid grid-3">
    <div class="card stack">
        <div class="feature-ico">✅</div>
        <h3 class="no-margin">Verified nannies</h3>
        <p class="muted no-margin">Only nannies approved by our admin team appear in search.
            Editing a profile sends it back for re-verification.</p>
    </div>
    <div class="card stack">
        <div class="feature-ico">👀</div>
        <h3 class="no-margin">Human moderation</h3>
        <p class="muted no-margin">Admins review accounts and can suspend or remove anyone who
            breaks our community standards.</p>
    </div>
    <div class="card stack">
        <div class="feature-ico">🔒</div>
        <h3 class="no-margin">Secure by design</h3>
        <p class="muted no-margin">Passwords are hashed, every form is protected against
            cross-site request forgery, and access is role-based.</p>
    </div>
    <div class="card stack">
        <div class="feature-ico">💳</div>
        <h3 class="no-margin">Transparent costs</h3>
        <p class="muted no-margin">See an estimated cost before you request a booking — no
            hidden fees or surprises.</p>
    </div>
    <div class="card stack">
        <div class="feature-ico">📝</div>
        <h3 class="no-margin">Booking records</h3>
        <p class="muted no-margin">Every request, confirmation and completed session is logged
            to your dashboard for full transparency.</p>
    </div>
    <div class="card stack">
        <div class="feature-ico">🚩</div>
        <h3 class="no-margin">Report concerns</h3>
        <p class="muted no-margin">Something not right? <a href="<?= url('pages/contact.php') ?>">Contact
            us</a> and our team will look into it quickly.</p>
    </div>
</section>

<section class="section">
    <div class="card prose stack">
        <h2>Safety tips for parents</h2>
        <ul class="muted">
            <li>Read each nanny's profile, experience and skills carefully before booking.</li>
            <li>Use the notes field to share your children's ages and any special needs.</li>
            <li>Meet your nanny and agree on expectations before the first session.</li>
            <li>Keep all communication and bookings on the platform so there's a clear record.</li>
        </ul>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
