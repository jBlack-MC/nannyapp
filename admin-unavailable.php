<?php
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Admin not available in the app';
require __DIR__ . '/includes/header.php';
?>
<div class="mw-600 text-center">
    <div class="font-4xl auth-footnote">🖥️</div>
    <h1 class="heading-tight">Admin isn't available here</h1>
    <p class="muted">For security, the admin dashboard only works in a regular web browser — not inside the <?= APP_NAME ?> mobile app or an installed home-screen app. Open the site in Chrome, Safari or another browser on a computer or phone to sign in as an admin.</p>
    <a class="btn btn-primary" href="<?= url('index.php') ?>">← Back to <?= APP_NAME ?></a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
