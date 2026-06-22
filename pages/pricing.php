<?php
require_once __DIR__ . '/../config/config.php';

// Pull live rate range from verified nannies
$rates = ['min' => 80, 'max' => 250, 'avg' => 140]; // sensible SA defaults
try {
    $r = db()->query(
        "SELECT MIN(hourly_rate) AS min, MAX(hourly_rate) AS max,
                ROUND(AVG(hourly_rate)) AS avg
         FROM nanny_profiles WHERE verification_status='verified' AND hourly_rate > 0"
    )->fetch();
    if ($r && $r['min'] > 0) $rates = $r;
} catch (Throwable) {}

$pageTitle = 'Pricing';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <p class="h-eyebrow">Pricing</p>
    <h1>Simple, honest pricing</h1>
    <p class="muted">No subscriptions, no hidden fees. Browse for free and only pay for the care you book.</p>
</div>

<!-- Plans -->
<div class="grid grid-3 pricing section section-no-top">
    <div class="card price-box reveal-item">
        <h3 class="price-name">Pay As You Go</h3>
        <p class="price-blurb">Perfect for occasional childcare with no commitment.</p>
        <div class="price-group"><span class="dollar">R</span><span class="price">0</span><span class="time">/mo</span></div>
        <ul class="price-feature">
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Browse verified nannies</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Book by the hour</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Secure in-app payments</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Messaging &amp; reviews</li>
            <li class="off"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Priority matching</li>
        </ul>
        <a class="btn" href="<?= url('auth/register.php') ?>">Get started free</a>
    </div>

    <div class="card price-box popular reveal-item">
        <span class="price-tag">Most popular</span>
        <h3 class="price-name">Family</h3>
        <p class="price-blurb">For families who book childcare regularly.</p>
        <div class="price-group"><span class="dollar">R</span><span class="price">199</span><span class="time">/mo</span></div>
        <ul class="price-feature">
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Everything in Pay As You Go</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Reduced service fees</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Priority booking</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Save favourite nannies</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Priority support</li>
        </ul>
        <a class="btn btn-primary" href="<?= url('auth/register.php') ?>">Choose Family</a>
    </div>

    <div class="card price-box reveal-item">
        <h3 class="price-name">Premium</h3>
        <p class="price-blurb">Full peace of mind with concierge support.</p>
        <div class="price-group"><span class="dollar">R</span><span class="price">399</span><span class="time">/mo</span></div>
        <ul class="price-feature">
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Everything in Family</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Dedicated care coordinator</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Last-minute bookings</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Background-check reports</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> 24/7 phone support</li>
        </ul>
        <a class="btn" href="<?= url('auth/register.php') ?>">Choose Premium</a>
    </div>
</div>

<!-- Live rate banner -->
<div class="card reveal-item pricing-rate-card">
    <div class="pricing-rate-row">
        <div>
            <p class="h-eyebrow no-margin">Current nanny rates on our platform</p>
            <h3 class="pricing-rate-title">R<?= number_format((float)$rates['min'],0) ?> – R<?= number_format((float)$rates['max'],0) ?> per hour</h3>
            <p class="muted pricing-rate-copy">Average: <strong>R<?= number_format((float)$rates['avg'],0) ?>/hr</strong> · Based on verified nannies in our network</p>
        </div>
        <a class="btn btn-primary pricing-rate-btn" href="<?= url('parent/nannies.php') ?>">Browse nannies →</a>
    </div>
</div>

<!-- FAQ section -->
<div class="card prose stack section section-no-top">
    <h2>Common pricing questions</h2>

    <div class="faq">
        <div class="faq-item">
            <button class="faq-q" aria-expanded="false">Is there a monthly fee?<span class="faq-plus">+</span></button>
            <div class="faq-a"><div class="faq-a-inner">Creating an account is completely free for parents and nannies. You only pay the nanny's hourly rate when you book.</div></div>
        </div>
        <div class="faq-item">
            <button class="faq-q" aria-expanded="false">How is the total cost calculated?<span class="faq-plus">+</span></button>
            <div class="faq-a"><div class="faq-a-inner">Cost = the nanny's hourly rate × the number of hours booked. You see the full estimate before confirming — no surprises.</div></div>
        </div>
        <div class="faq-item">
            <button class="faq-q" aria-expanded="false">Can nannies set their own rate?<span class="faq-plus">+</span></button>
            <div class="faq-a"><div class="faq-a-inner">Yes. Every nanny sets and can update their hourly rate any time from their profile page.</div></div>
        </div>
        <div class="faq-item">
            <button class="faq-q" aria-expanded="false">What payment methods are accepted?<span class="faq-plus">+</span></button>
            <div class="faq-a"><div class="faq-a-inner">We accept all major South African debit and credit cards via Paystack. EFT options are also available for qualifying bookings.</div></div>
        </div>
        <div class="faq-item">
            <button class="faq-q" aria-expanded="false">What is the refund policy?<span class="faq-plus">+</span></button>
            <div class="faq-a"><div class="faq-a-inner">Cancellations made more than 24 hours before a booking receive a full refund. Late cancellations may incur a fee. Contact support for disputes.</div></div>
        </div>
    </div>

    <a class="btn" href="<?= url('pages/faq.php') ?>">View all FAQs →</a>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
