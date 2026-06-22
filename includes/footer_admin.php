</main>

<footer class="footer footer-admin" id="admin-footer">
    <div class="container">
        <div class="footer-admin-inner">
            <a class="brand footer-admin-brand" href="<?= url('admin/dashboard.php') ?>">
                <span class="brand-icon brand-logo-wrap">
                    <img class="brand-logo" src="<?= url('assets/img/logo.png') ?>" alt="<?= APP_NAME ?> logo">
                </span>
                <div class="brand-text">
                    <span class="brand-name"><?= APP_NAME ?></span>
                    <small>Admin Panel</small>
                </div>
            </a>

            <nav class="footer-admin-nav" aria-label="Admin quick links">
                <a href="<?= url('admin/dashboard.php') ?>">Dashboard</a>
                <a href="<?= url('admin/users.php') ?>">Users</a>
                <a href="<?= url('admin/bookings.php') ?>">Bookings</a>
                <a href="<?= url('admin/reports.php') ?>">Reports</a>
                <a href="<?= url('admin/verifications.php') ?>">Verifications</a>
                <a href="<?= url('admin/messages.php') ?>">Messages</a>
                <a href="<?= url('admin/contacts.php') ?>">Contacts</a>
            </nav>

            <div class="footer-admin-meta">
                <span>&copy; <?= date('Y') ?> <?= APP_NAME ?></span>
                <span class="footer-admin-sep">·</span>
                <span>PHP <?= PHP_MAJOR_VERSION ?>.<?= PHP_MINOR_VERSION ?></span>
                <span class="footer-admin-sep">·</span>
                <a href="<?= url('index.php') ?>" target="_blank" rel="noopener">View site</a>
            </div>
        </div>
    </div>
</footer>

<button class="scroll-top" id="scrollTop" type="button" aria-label="Back to top">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
</button>
<?php require_once __DIR__ . '/scripts.php'; ?>
