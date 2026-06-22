<?php
/**
 * Footer for admin.
 * Purpose: Provide administration tools and issue resolution.
 * This is NOT a marketing footer.
 */
?>

<footer class="footer footer-admin">
    <div class="container">
        <div class="footer-admin-shell">
            <!-- Header / Branding -->
            <div class="footer-admin-top">
                <div class="footer-admin-brand">
                    <a class="brand" href="<?= url('admin/dashboard.php') ?>">
                        <span class="brand-icon brand-logo-wrap">
                            <img class="brand-logo" src="<?= url('assets/img/logo.png') ?>" alt="<?= APP_NAME ?> logo">
                        </span>
                        <div class="brand-text">
                            <span class="brand-name"><?= APP_NAME ?></span>
                            <small>Admin Operations Console</small>
                        </div>
                    </a>
                    <p class="muted footer-admin-note">
                        Moderation, user support, and incident resolution tools in one place.
                    </p>
                </div>

                <!-- Quick Action Buttons -->
                <div class="footer-admin-actions">
                    <a class="btn btn-sm" href="<?= url('admin/support.php') ?>">
                        🎫 Resolve Support Tickets
                    </a>
                    <a class="btn btn-sm" href="<?= url('admin/verifications.php') ?>">
                        ✓ Review Verifications
                    </a>
                    <a class="btn btn-sm btn-primary" href="<?= url('admin/notify.php') ?>">
                        📢 Broadcast Alert
                    </a>
                </div>
            </div>

            <!-- Main Tool Grid -->
            <div class="footer-admin-grid">
                <!-- System Management -->
                <div class="footer-admin-col">
                    <h4>🔧 System Management</h4>
                    <a href="<?= url('admin/dashboard.php') ?>">Dashboard</a>
                    <a href="<?= url('admin/users.php') ?>">User Management</a>
                    <a href="<?= url('admin/bookings.php') ?>">Bookings</a>
                    <a href="<?= url('admin/verifications.php') ?>">Verifications</a>
                    <a href="<?= url('admin/payments.php') ?>">Payments</a>
                    <a href="<?= url('admin/messages.php') ?>">Messages</a>
                </div>

                <!-- Support & Moderation -->
                <div class="footer-admin-col">
                    <h4>🛡️ Support & Moderation</h4>
                    <a href="<?= url('admin/support.php') ?>">Support Tickets</a>
                    <a href="<?= url('admin/contacts.php') ?>">Contact Requests</a>
                    <a href="<?= url('admin/messages.php') ?>">Disputes & Reports</a>
                    <a href="<?= url('admin/users.php') ?>">User Actions</a>
                    <a href="<?= url('admin/verifications.php') ?>">Pending Reviews</a>
                    <a href="<?= url('admin/notify.php') ?>">Notifications</a>
                </div>

                <!-- Analytics & Operations -->
                <div class="footer-admin-col">
                    <h4>📊 Analytics & Operations</h4>
                    <a href="<?= url('admin/reports.php') ?>">Reports & Analytics</a>
                    <a href="<?= url('admin/dashboard.php') ?>">System Status</a>
                    <a href="<?= url('admin/payments.php') ?>">Revenue Report</a>
                    <a href="<?= url('admin/users.php') ?>">User Activity</a>
                    <a href="<?= url('admin/messages.php') ?>">Platform Health</a>
                </div>
            </div>

            <!-- Meta / Footer Info -->
            <div class="footer-admin-meta">
                <span>&copy; <?= date('Y') ?> <?= APP_NAME ?> Admin Panel</span>
                <span class="footer-admin-sep">•</span>
                <span>Environment: <strong><?= defined('ENVIRONMENT') ? ENVIRONMENT : 'Development' ?></strong></span>
                <span class="footer-admin-sep">•</span>
                <a href="<?= url('pages/privacy.php') ?>">Privacy</a>
                <span class="footer-admin-sep">•</span>
                <a href="<?= url('auth/logout.php') ?>">Log Out</a>
            </div>
        </div>
    </div>
</footer>
