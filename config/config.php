<?php
/**
 * Global application configuration.
 * Adjust BASE_URL if you deploy the app under a different path/host.
 */

declare(strict_types=1);

// --- App ---------------------------------------------------------------
define('APP_NAME', 'Nanny-App');
define('APP_TAGLINE', 'Care • Connect • Comfort');

// Base URL of the app (folder under htdocs). No trailing slash.
define('BASE_URL', '/nannyapp');

// --- Database (XAMPP defaults) ----------------------------------------
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'nanny_app');
define('DB_USER', 'root');
define('DB_PASS', '');

// --- Error reporting (off in production; errors go to server log only) ---
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// --- HTTPS enforcement ---
function is_https_request(): bool
{
    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    if (is_string($forwardedProto) && strtolower($forwardedProto) === 'https') {
        return true;
    }

    return !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
}

if (!is_https_request() && $_SERVER['SERVER_NAME'] !== 'localhost' && $_SERVER['SERVER_NAME'] !== '127.0.0.1') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}

// --- Session (secure cookie flags before session_start) ---------------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Lax');
    
    // HTTPS only in production
    if (is_https_request() || ($_SERVER['SERVER_NAME'] !== 'localhost' && $_SERVER['SERVER_NAME'] !== '127.0.0.1')) {
        ini_set('session.cookie_secure', '1');
    }
    
    session_start();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email.php';
