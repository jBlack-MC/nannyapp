<?php
/**
 * Footer for guests (not logged in).
 * Purpose: Build trust and help new users explore the platform.
 */
?>

<footer class="footer footer-guest">
    <div class="container">
        <div class="footer-grid">
            <!-- Brand / About -->
            <div class="footer-brand">
                <a class="brand" href="<?= url('index.php') ?>">
                    <span class="brand-icon brand-logo-wrap">
                        <img class="brand-logo" src="<?= url('assets/img/logo.png') ?>" alt="<?= APP_NAME ?> logo">
                    </span>
                    <div class="brand-text">
                        <span class="brand-name"><?= APP_NAME ?></span>
                        <small>Trusted Childcare</small>
                    </div>
                </a>
                <p class="muted footer-tagline"><?= APP_TAGLINE ?></p>
                <p class="muted footer-subline">Serving families across South Africa.</p>
                <p class="muted footer-subline" style="margin-top: 12px; font-size: 0.85rem;">
                    ✓ Verified Nannies • ✓ Safe & Secure • ✓ 24/7 Support
                </p>
            </div>

            <!-- For Parents -->
            <div class="footer-col">
                <h4>For Parents</h4>
                <a href="<?= url('parent/nannies.php') ?>">Find a Nanny</a>
                <a href="<?= url('index.php') ?>#how-it-works">How It Works</a>
                <a href="<?= url('pages/pricing.php') ?>">Pricing</a>
                <a href="<?= url('pages/safety.php') ?>">Safety & Trust</a>
                <a href="<?= url('pages/faq.php') ?>">FAQ</a>
            </div>

            <!-- For Nannies -->
            <div class="footer-col">
                <h4>For Nannies</h4>
                <a href="<?= url('auth/register.php') ?>">Become a Nanny</a>
                <a href="<?= url('pages/resources.php') ?>">Resources</a>
                <a href="<?= url('pages/community.php') ?>">Community</a>
                <a href="<?= url('pages/pricing.php') ?>">Earnings</a>
                <a href="<?= url('pages/faq.php') ?>">FAQ</a>
            </div>

            <!-- Company Info -->
            <div class="footer-col">
                <h4>Company</h4>
                <a href="<?= url('pages/about.php') ?>">About Us</a>
                <a href="<?= url('pages/contact.php') ?>">Contact Us</a>
                <a href="<?= url('support.php') ?>">Support</a>
                <a href="<?= url('pages/contact.php') ?>#newsletter">Newsletter</a>
                <a href="<?= url('pages/safety.php') ?>">Safety Center</a>
            </div>
        </div>

        <!-- Legal & Social -->
        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</span>
            <span class="footer-legal">
                <a href="<?= url('pages/privacy.php') ?>">Privacy Policy</a>
                <a href="<?= url('pages/terms.php') ?>">Terms of Service</a>
            </span>
        </div>
    </div>
</footer>
