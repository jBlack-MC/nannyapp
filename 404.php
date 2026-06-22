<?php
declare(strict_types=1);
http_response_code(404);
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Page Not Found';
require __DIR__ . '/includes/header.php';
?>
<section class="section error-shell">
    <div class="card stack error-card">
        <div class="empty-ico empty-ico-tight">🔍</div>
        <p class="h-eyebrow">404 Error</p>
        <h1 class="heading-tight">Page Not Found</h1>
        <p class="muted error-body error-body-sm">
            The page you requested does not exist or may have been moved.
            Use the links below to continue browsing.
        </p>
        <div class="actions-wrap actions-center">
            <a class="btn btn-primary" href="<?= url('index.php') ?>">Go Home</a>
            <a class="btn" href="<?= url('support.php') ?>">Contact Support</a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
