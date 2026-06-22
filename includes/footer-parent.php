<?php
/**
 * Footer for parents (logged in).
 * Purpose: Help parents manage childcare quickly and access frequent actions.
 */

$user = current_user();
?>

<footer class="footer footer-parent">
    <div class="container">
        <!-- Quick Actions Bar -->
        <div class="footer-parent-actions">
            <a class="btn btn-sm" href="<?= url('parent/nannies.php') ?>">
                🔍 Find a Nanny
            </a>
            <a class="btn btn-sm" href="<?= url('parent/bookings.php') ?>">
                📅 My Bookings
            </a>
            <a class="btn btn-sm" href="<?= url('parent/messages.php') ?>">
                💬 Messages
            </a>
            <a class="btn btn-sm" href="<?= url('parent/payments.php') ?>">
                💳 Payments
            </a>
            <a class="btn btn-sm" href="<?= url('support.php') ?>">
                🆘 Emergency Support
            </a>
        </div>

        <!-- Main Footer Grid -->
        <div class="footer-grid">
            <!-- Brand / Dashboard Link -->
            <div class="footer-brand">
                <a class="brand" href="<?= url('parent/dashboard.php') ?>">
                    <span class="brand-icon brand-logo-wrap">
                        <img class="brand-logo" src="<?= url('assets/img/logo.png') ?>" alt="<?= APP_NAME ?> logo">
                    </span>
                    <div class="brand-text">
                        <span class="brand-name"><?= APP_NAME ?></span>
                        <small>Parent Dashboard</small>
                    </div>
                </a>
                <p class="muted footer-tagline" style="margin-top: 12px;">
                    Welcome, <?= e(explode(' ', $user['full_name'] ?? 'Parent')[0]) ?>!
                </p>
                <p class="muted" style="font-size: 0.85rem;">
                    Your trusted partner in childcare.
                </p>
            </div>

            <!-- My Management -->
            <div class="footer-col">
                <h4>My Management</h4>
                <a href="<?= url('parent/dashboard.php') ?>">Dashboard</a>
                <a href="<?= url('parent/bookings.php') ?>">My Bookings</a>
                <a href="<?= url('parent/children.php') ?>">My Children</a>
                <a href="<?= url('parent/saved.php') ?>">Saved Nannies</a>
                <a href="<?= url('parent/payments.php') ?>">Payment Methods</a>
            </div>

            <!-- Support & Safety -->
            <div class="footer-col">
                <h4>Help & Safety</h4>
                <a href="<?= url('support.php') ?>">Contact Support</a>
                <a href="<?= url('pages/faq.php') ?>">FAQ</a>
                <a href="<?= url('pages/safety.php') ?>">Safety Center</a>
                <a href="<?= url('pages/privacy.php') ?>">Privacy & Data</a>
                <a href="<?= url('support.php') ?>">Report an Issue</a>
            </div>

            <!-- Account -->
            <div class="footer-col">
                <h4>Account</h4>
                <a href="<?= url('account.php') ?>">Profile Settings</a>
                <a href="<?= url('notifications.php') ?>">Notifications</a>
                <a href="<?= url('messages.php') ?>">All Messages</a>
                <a href="<?= url('pages/privacy.php') ?>">Privacy Policy</a>
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
