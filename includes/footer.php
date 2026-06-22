</main>
<?php
/**
 * Role-based footer loader.
 * Automatically detects user role and includes the appropriate footer component.
 *
 * Route logic:
 * - Admin: includes footer-admin.php (operations-focused)
 * - Parent: includes footer-parent.php (family management)
 * - Nanny: includes footer-nanny.php (work management)
 * - Guest: includes footer-guest.php (trust & discovery)
 */

if (is_logged_in()) {
    $role = user_role();
    
    if ($role === 'admin') {
        require_once __DIR__ . '/footer-admin.php';
    } elseif ($role === 'parent') {
        require_once __DIR__ . '/footer-parent.php';
    } elseif ($role === 'nanny') {
        require_once __DIR__ . '/footer-nanny.php';
    } else {
        // Logged in but unknown role; fallback to guest footer
        require_once __DIR__ . '/footer-guest.php';
    }
} else {
    // Not logged in; show guest footer
    require_once __DIR__ . '/footer-guest.php';
}
?>

<button class="scroll-top" id="scrollTop" type="button" aria-label="Back to top">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
</button>
<?php require_once __DIR__ . '/scripts.php'; ?>
