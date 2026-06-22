<?php
$user = $user ?? current_user();
$userRole = $userRole ?? ($user['role'] ?? null);
if (!isset($isActive) || !is_callable($isActive)) {
    $requestPath = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $isActive = static function (string $path, bool $prefix = false) use ($requestPath): bool {
        $path = ltrim($path, '/');
        $exactMatch = $requestPath === $path || str_ends_with($requestPath, '/' . $path);
        if ($prefix) {
            return $exactMatch || str_contains($requestPath, '/' . $path . '/');
        }
        return $exactMatch;
    };
}
?>
<header class="topbar" id="topbar">
    <div class="container">
        <div class="navbar">
            <a class="brand" href="<?= url('index.php') ?>">
                <span class="brand-icon brand-logo-wrap">
                    <img class="brand-logo" src="<?= url('assets/img/logo.png') ?>" alt="<?= APP_NAME ?> logo">
                </span>
                <div class="brand-text">
                    <span class="brand-name"><?= APP_NAME ?></span>
                </div>
            </a>

            <nav class="nav" id="primary-nav" aria-label="Primary navigation">
                <div class="nav-links">
                    <?php if ($userRole === 'parent'): ?>
                        <a class="<?= $isActive('parent/dashboard.php') ? 'active' : '' ?>" href="<?= url('parent/dashboard.php') ?>">Dashboard</a>
                        <a class="<?= $isActive('parent/nannies.php') ? 'active' : '' ?>" href="<?= url('parent/nannies.php') ?>">Find Care</a>
                        <a class="<?= $isActive('parent/bookings.php') ? 'active' : '' ?>" href="<?= url('parent/bookings.php') ?>">Bookings</a>
                        <?php $mUnread = unread_message_count(); ?>
                        <a class="<?= $isActive('messages.php') ? 'active' : '' ?>" href="<?= url('messages.php') ?>">Messages<?php if ($mUnread): ?> <span class="nav-badge"><?= $mUnread ?></span><?php endif; ?></a>
                    <?php elseif ($userRole === 'nanny'): ?>
                        <a class="<?= $isActive('nanny/dashboard.php') ? 'active' : '' ?>" href="<?= url('nanny/dashboard.php') ?>">Dashboard</a>
                        <a class="<?= $isActive('nanny/bookings.php') ? 'active' : '' ?>" href="<?= url('nanny/bookings.php') ?>">Bookings</a>
                        <a class="<?= $isActive('nanny/availability.php') ? 'active' : '' ?>" href="<?= url('nanny/availability.php') ?>">Availability</a>
                        <?php $mUnread = unread_message_count(); ?>
                        <a class="<?= $isActive('messages.php') ? 'active' : '' ?>" href="<?= url('messages.php') ?>">Messages<?php if ($mUnread): ?> <span class="nav-badge"><?= $mUnread ?></span><?php endif; ?></a>
                    <?php elseif ($userRole === 'admin'): ?>
                        <a class="<?= $isActive('admin/dashboard.php') ? 'active' : '' ?>" href="<?= url('admin/dashboard.php') ?>">Dashboard</a>
                        <a class="<?= $isActive('admin/users.php') ? 'active' : '' ?>" href="<?= url('admin/users.php') ?>">Users</a>
                        <a class="<?= $isActive('admin/bookings.php') ? 'active' : '' ?>" href="<?= url('admin/bookings.php') ?>">Bookings</a>
                        <a class="<?= $isActive('admin/reports.php') ? 'active' : '' ?>" href="<?= url('admin/reports.php') ?>">Reports</a>
                    <?php else: ?>
                        <a class="<?= $isActive('parent/nannies.php') ? 'active' : '' ?>" href="<?= url('parent/nannies.php') ?>">Find Care</a>
                        <a class="<?= $isActive('auth/register.php') ? 'active' : '' ?>" href="<?= url('auth/register.php') ?>">Become a Nanny</a>
                        <a class="<?= $isActive('pages/about.php') ? 'active' : '' ?>" href="<?= url('pages/about.php') ?>">About</a>
                    <?php endif; ?>
                </div>

                <div class="nav-actions">
                    <button class="dark-toggle" id="darkToggle" aria-label="Toggle dark mode" type="button">
                        <svg class="ico-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                        <svg class="ico-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    </button>

                    <?php if ($user): ?>
                        <?php $nUnread = unread_notification_count(); ?>
                        <a class="nav-icon <?= $isActive('notifications.php') ? 'active' : '' ?>" href="<?= url('notifications.php') ?>" aria-label="Notifications">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                            <?php if ($nUnread): ?><span class="nav-badge"><?= $nUnread ?></span><?php endif; ?>
                        </a>
                        <div class="nav-dropdown" id="navUserMenu">
                            <button class="nav-user nav-user-trigger" type="button"
                                    aria-expanded="false" aria-haspopup="true"
                                    aria-controls="navUserMenuPanel" id="navUserToggle">
                                <?= avatar($user['full_name'], $user['profile_image'] ?? null, 'avatar-sm') ?>
                                <span class="nav-user-name"><?= e(explode(' ', $user['full_name'])[0]) ?></span>
                                <svg class="nav-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <div class="nav-dropdown-menu" role="menu" id="navUserMenuPanel">
                                <a class="nav-dropdown-item" href="<?= url('account.php') ?>" role="menuitem">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    Account settings
                                </a>
                                <?php if ($userRole === 'nanny'): ?>
                                <a class="nav-dropdown-item" href="<?= url('nanny/profile.php') ?>" role="menuitem">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                                    My profile
                                </a>
                                <?php elseif ($userRole === 'parent'): ?>
                                <a class="nav-dropdown-item" href="<?= url('parent/children.php') ?>" role="menuitem">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                    My children
                                </a>
                                <?php endif; ?>
                                <div class="nav-dropdown-divider"></div>
                                <a class="nav-dropdown-item nav-dropdown-logout" href="<?= url('auth/logout.php') ?>" role="menuitem">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                    Log out
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a class="nav-login" href="<?= url('auth/login.php') ?>">Login</a>
                        <a class="cta-btn" href="<?= url('auth/register.php') ?>">Get Started
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </a>
                    <?php endif; ?>
                </div>
            </nav>

            <button class="menu-btn" id="navToggle" aria-label="Toggle menu" aria-expanded="false" aria-controls="primary-nav">
                <svg class="icon-bars" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                <svg class="icon-close" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
    </div>
</header>
