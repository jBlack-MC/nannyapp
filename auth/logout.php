<?php
require_once __DIR__ . '/../config/config.php';

/* ── Clear remember-me token from DB before destroying session ───────── */
if (isset($_SESSION['user_id'])) {
    try {
        db()->prepare('UPDATE users SET remember_token = NULL WHERE id = ?')
           ->execute([$_SESSION['user_id']]);
    } catch (Throwable) {}
}

/* ── Expire the remember-me cookie ──────────────────────────────────── */
if (isset($_COOKIE['na_remember'])) {
    setcookie('na_remember', '', time() - 3600, '/', '', false, true);
}

/* ── Destroy the session ─────────────────────────────────────────────── */
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

/* ── Fresh session to carry the goodbye flash ────────────────────────── */
session_start();
flash('You have been logged out successfully.');
redirect('index.php');
