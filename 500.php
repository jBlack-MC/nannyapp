<?php
declare(strict_types=1);
http_response_code(500);
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Server Error';
require __DIR__ . '/includes/header.php';
?>
<section class="section error-shell">
    <div class="card stack error-card">
        <div class="empty-ico empty-ico-tight">⚠️</div>
        <p class="h-eyebrow">500 Error</p>
        <h1 class="heading-tight">Something Went Wrong</h1>
        <p class="muted error-body error-body-lg">
            We hit an unexpected server issue. Please try again in a moment,
            or contact support if the problem keeps happening.
        </p>
        <div class="actions-wrap actions-center">
            <a class="btn btn-primary" href="<?= url('index.php') ?>">Go Home</a>
            <a class="btn" href="<?= url('support.php') ?>">Contact Support</a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
