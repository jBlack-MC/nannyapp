<?php
/** Shared page shell — open. Set $pageTitle before including. */
$user      = current_user();
$userRole  = $user ? $user['role'] : null;
$navUnread = $user ? (unread_message_count() + unread_notification_count()) : 0;

$requestPath = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
$isActive = static function (string $path, bool $prefix = false) use ($requestPath): bool {
    $path = ltrim($path, '/');
    $exactMatch = $requestPath === $path || str_ends_with($requestPath, '/' . $path);
    if ($prefix) {
        return $exactMatch || str_contains($requestPath, '/' . $path . '/');
    }
    return $exactMatch;
};
?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1e5f9e">
    <meta name="description" content="Find and book trusted, background-checked nannies near you — safely and confidently, whenever you need childcare.">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?><?= APP_NAME ?></title>
    <script>document.documentElement.className = 'js';</script>
    <link rel="manifest" href="<?= url('manifest.webmanifest') ?>">
    <link rel="icon" href="<?= url('assets/img/icon.svg') ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('assets/css/variables.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/reset.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/layout.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/components.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/forms.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/animations.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/responsive.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/navbar.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/pages.css') ?>">
</head>
<body class="<?= isset($bodyClass) ? e($bodyClass) : '' ?>">
<a class="skip-link" href="#main">Skip to content</a>

<div class="splash" id="splash" role="status" aria-live="polite" aria-label="Loading <?= APP_NAME ?>">
    <div class="splash-inner">
        <img class="splash-logo" src="<?= url('assets/img/logo.png') ?>" alt="<?= APP_NAME ?>">
        <span class="splash-name"><?= APP_NAME ?></span>
        <span class="splash-tagline">Connecting Families With Trusted Childcare</span>
        <span class="splash-bar"><span class="splash-bar-fill"></span></span>
    </div>
</div>
<?php require __DIR__ . '/navbar.php'; ?>

<main class="<?= isset($mainClass) ? e($mainClass) : 'container page' ?>" id="main">
    <?php foreach (get_flashes() as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>" data-toast><?= e($f['message']) ?></div>
    <?php endforeach; ?>
