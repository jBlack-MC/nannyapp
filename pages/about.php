<?php
require_once __DIR__ . '/../config/config.php';
$pageTitle = 'About';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <p class="h-eyebrow">About us</p>
    <h1>Childcare you can trust</h1>
    <p class="muted">Nanny-App connects families with vetted, caring nannies — and gives nannies a
        simple way to find work they love. We handle the searching, booking and moderation so
        everyone can focus on what matters: happy, well-cared-for children.</p>
</div>

<section class="section grid grid-3">
    <div class="card stack">
        <div class="feature-ico">🛡️</div>
        <h3 class="no-margin">Trust first</h3>
        <p class="muted no-margin">Every nanny is reviewed and verified by our admin team
            before they appear in search results.</p>
    </div>
    <div class="card stack">
        <div class="feature-ico">💙</div>
        <h3 class="no-margin">Built on care</h3>
        <p class="muted no-margin">From newborn specialists to playful all-rounders, we help
            you find the right match for your family.</p>
    </div>
    <div class="card stack">
        <div class="feature-ico">⚡</div>
        <h3 class="no-margin">Refreshingly simple</h3>
        <p class="muted no-margin">Search, request a booking and track everything in one
            place — on your phone or computer.</p>
    </div>
</section>

<section class="section">
    <div class="card prose stack">
        <h2>Our story</h2>
        <p class="muted">NannyApp is a South African childcare booking platform that puts safety and
            simplicity first. Parents browse verified nannies, send booking requests and see an
            estimated cost up front. Nannies manage their own profile, rates and availability, and
            accept or decline requests on their terms. An admin team moderates the platform and
            verifies every nanny.</p>
        <p class="muted">We believe finding childcare shouldn't be stressful — so we made it feel as
            warm and reassuring as the care itself.</p>
        <div class="hero-cta">
            <a class="btn btn-primary" href="<?= url('parent/nannies.php') ?>">Find a nanny</a>
            <a class="btn" href="<?= url('auth/register.php') ?>">Become a nanny</a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
