<?php
/**
 * Footer for nannies (logged in).
 * Purpose: Help nannies manage work, earnings, and professional development.
 */

$user = current_user();
?>

<footer class="footer footer-nanny">
    <div class="container">
        <!-- Quick Actions Bar -->
        <div class="footer-nanny-actions">
            <a class="btn btn-sm" href="<?= url('nanny/dashboard.php') ?>">
                📊 Dashboard
            </a>
            <a class="btn btn-sm" href="<?= url('nanny/bookings.php') ?>">
                📅 Bookings
            </a>
            <a class="btn btn-sm" href="<?= url('nanny/availability.php') ?>">
                ⏰ Set Availability
            </a>
            <a class="btn btn-sm" href="<?= url('nanny/earnings.php') ?>">
                💰 Earnings
            </a>
            <a class="btn btn-sm" href="<?= url('support.php') ?>">
                💬 Support
            </a>
        </div>

        <!-- Main Footer Grid -->
        <div class="footer-grid">
            <!-- Brand / Dashboard Link -->
            <div class="footer-brand">
                <a class="brand" href="<?= url('nanny/dashboard.php') ?>">
                    <span class="brand-icon brand-logo-wrap">
                        <img class="brand-logo" src="<?= url('assets/img/logo.png') ?>" alt="<?= APP_NAME ?> logo">
                    </span>
                    <div class="brand-text">
                        <span class="brand-name"><?= APP_NAME ?></span>
                        <small>Nanny Dashboard</small>
                    </div>
                </a>
                <p class="muted footer-tagline" style="margin-top: 12px;">
                    Welcome, <?= e(explode(' ', $user['full_name'] ?? 'Nanny')[0]) ?>!
                </p>
                <p class="muted" style="font-size: 0.85rem;">
                    Manage your schedule and grow your business.
                </p>
            </div>

            <!-- Work Management -->
            <div class="footer-col">
                <h4>Manage Work</h4>
                <a href="<?= url('nanny/dashboard.php') ?>">Dashboard</a>
                <a href="<?= url('nanny/bookings.php') ?>">My Bookings</a>
                <a href="<?= url('nanny/availability.php') ?>">Availability</a>
                <a href="<?= url('nanny/profile.php') ?>">My Profile</a>
                <a href="<?= url('nanny/reviews.php') ?>">Reviews</a>
            </div>

            <!-- Growth & Resources -->
            <div class="footer-col">
                <h4>Professional Growth</h4>
                <a href="<?= url('pages/resources.php') ?>">Resources</a>
                <a href="<?= url('pages/community.php') ?>">Community</a>
                <a href="<?= url('nanny/earnings.php') ?>">Earnings & Payments</a>
                <a href="<?= url('pages/safety.php') ?>">Safety Tips</a>
                <a href="<?= url('pages/faq.php') ?>">FAQ</a>
            </div>

            <!-- Support & Account -->
            <div class="footer-col">
                <h4>Help & Account</h4>
                <a href="<?= url('account.php') ?>">Account Settings</a>
                <a href="<?= url('notifications.php') ?>">Notifications</a>
                <a href="<?= url('messages.php') ?>">Messages</a>
                <a href="<?= url('support.php') ?>">Contact Support</a>
                <a href="<?= url('auth/logout.php') ?>">Log Out</a>
            </div>
        </div>

        <!-- Bottom Info -->
        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</span>
            <span class="footer-legal">
                <a href="<?= url('pages/privacy.php') ?>">Privacy Policy</a>
                <a href="<?= url('pages/terms.php') ?>">Terms of Service</a>
            </span>
        </div>
    </div>
</footer>
