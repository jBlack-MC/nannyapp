<?php
/**
 * Shared helpers: auth, role-guards, CSRF, flash messages, escaping.
 */

declare(strict_types=1);

// ----------------------------------------------------------------------
//  URL / output helpers
// ----------------------------------------------------------------------
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

// ----------------------------------------------------------------------
//  Flash messages (one-shot notices across a redirect)
// ----------------------------------------------------------------------
function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function get_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

// ----------------------------------------------------------------------
//  CSRF protection
// ----------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid or expired form token. Please go back and try again.');
    }
}

// ----------------------------------------------------------------------
//  Authentication & role guards  ("live roles")
// ----------------------------------------------------------------------
function current_user(): ?array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    if (empty($_SESSION['user_id']) && !empty($_COOKIE['na_remember'])) {
        $token = (string) $_COOKIE['na_remember'];
        if (preg_match('/^[a-f0-9]{64}$/', $token)) {
            try {
                $stmt = db()->prepare('SELECT id FROM users WHERE remember_token = ? AND email_verified = 1 AND status = "active" LIMIT 1');
                $stmt->execute([$token]);
                $remembered = $stmt->fetch();
                if ($remembered) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int) $remembered['id'];
                } else {
                    setcookie('na_remember', '', time() - 3600, '/', '', false, true);
                }
            } catch (Throwable) {
                // Ignore remember-me failures and continue as guest.
            }
        } else {
            setcookie('na_remember', '', time() - 3600, '/', '', false, true);
        }
    }

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    if ($cache === null) {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $cache = $stmt->fetch() ?: null;
    }
    return $cache;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function user_role(): ?string
{
    return current_user()['role'] ?? null;
}

/** Redirect to login unless authenticated; bounce suspended accounts. */
function require_login(): void
{
    if (!is_logged_in()) {
        flash('Please log in to continue.', 'error');
        redirect('auth/login.php');
    }
    if ((current_user()['status'] ?? 'active') === 'suspended') {
        $_SESSION = [];
        session_destroy();
        session_start();
        flash('Your account has been suspended. Please contact support.', 'error');
        redirect('auth/login.php');
    }
}

/** Allow only the given role(s); otherwise bounce to the right dashboard. */
function require_role(string ...$roles): void
{
    // Admin is web-only by design — never usable from the packaged app / installed PWA.
    if (in_array('admin', $roles, true) && is_native_app_request()) {
        redirect('admin-unavailable.php');
    }
    require_login();
    if (!in_array(user_role(), $roles, true)) {
        flash('You do not have access to that area.', 'error');
        redirect(dashboard_path(user_role()));
    }
}

// ----------------------------------------------------------------------
//  Native app / installed PWA detection — admin is web-only by design
// ----------------------------------------------------------------------
/**
 * True when this request is coming from the packaged Cordova app (tagged via
 * config.xml's AppendUserAgent) or an installed PWA (tagged client-side via
 * a cookie set in includes/header.php when display-mode is standalone).
 * Used to keep the admin panel reachable only from a normal web browser.
 */
function is_native_app_request(): bool
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (str_contains($ua, 'NannyAppCordova')) {
        return true;
    }
    return ($_COOKIE['na_app_shell'] ?? '') === '1';
}

/** Where each role lands after login. */
function dashboard_path(?string $role): string
{
    return match ($role) {
        'admin'  => 'admin/dashboard.php',
        'nanny'  => 'nanny/dashboard.php',
        'parent' => 'parent/dashboard.php',
        default  => 'index.php',
    };
}

// ----------------------------------------------------------------------
//  Rate Limiting  (brute-force protection)
// ----------------------------------------------------------------------
function is_rate_limited(string $key, int $maxAttempts = 5, int $windowSeconds = 300): bool
{
    $cacheKey = 'rate_limit_' . md5($key);
    
    if (!isset($_SESSION[$cacheKey])) {
        $_SESSION[$cacheKey] = ['attempts' => 0, 'reset_at' => time() + $windowSeconds];
    }
    
    // Reset if window expired
    if (time() > $_SESSION[$cacheKey]['reset_at']) {
        $_SESSION[$cacheKey] = ['attempts' => 0, 'reset_at' => time() + $windowSeconds];
    }
    
    return $_SESSION[$cacheKey]['attempts'] >= $maxAttempts;
}

function increment_rate_limit(string $key): void
{
    $cacheKey = 'rate_limit_' . md5($key);
    if (!isset($_SESSION[$cacheKey])) {
        $_SESSION[$cacheKey] = ['attempts' => 0, 'reset_at' => time() + 300];
    }
    $_SESSION[$cacheKey]['attempts']++;
}

// ----------------------------------------------------------------------
//  Chat messages — shared by messages.php (form fallback) and the
//  messages_send.php / messages_poll.php AJAX endpoints, so validation
//  and rate limiting stay in one place regardless of entry point.
// ----------------------------------------------------------------------
/**
 * Validate and persist a chat message.
 * @return array{ok:bool, error?:string, id?:int, content?:string, created_at?:string, time_label?:string}
 */
function send_chat_message(int $fromId, int $toId, string $body): array
{
    $body = trim($body);

    if ($toId <= 0 || $toId === $fromId) {
        return ['ok' => false, 'error' => 'That recipient is not available.'];
    }

    $valid = db()->prepare('SELECT 1 FROM users WHERE id=?');
    $valid->execute([$toId]);
    if (!$valid->fetch()) {
        return ['ok' => false, 'error' => 'That recipient is not available.'];
    }

    if ($body === '') {
        return ['ok' => false, 'error' => 'Please type a message.'];
    }

    $rateLimitKey = 'chat_msg_' . $fromId;
    if (is_rate_limited($rateLimitKey, 30, 300)) {
        return ['ok' => false, 'error' => 'You are sending messages too quickly. Please wait a moment and try again.'];
    }
    increment_rate_limit($rateLimitKey);

    $content = mb_substr($body, 0, 1000);
    db()->prepare('INSERT INTO chat_messages (sender_id, receiver_id, content) VALUES (?,?,?)')
        ->execute([$fromId, $toId, $content]);
    $id = (int) db()->lastInsertId();

    $sender = db()->prepare('SELECT full_name FROM users WHERE id=?');
    $sender->execute([$fromId]);
    $senderName = (string) $sender->fetchColumn();

    notify($toId, 'New message from ' . $senderName, mb_substr($content, 0, 80), 'messages.php?with=' . $fromId);

    return [
        'ok'         => true,
        'id'         => $id,
        'content'    => $content,
        'created_at' => date('Y-m-d H:i:s'),
        'time_label' => date('d M, H:i'),
    ];
}

// ----------------------------------------------------------------------
//  Notifications  (non-critical: never let them break a core flow)
// ----------------------------------------------------------------------
function notify(int $userId, string $title, string $message, ?string $url = null): void
{
    try {
        db()->prepare(
            'INSERT INTO notifications (user_id, title, message, url) VALUES (?,?,?,?)'
        )->execute([$userId, $title, $message, $url]);
    } catch (Throwable) {
        // Notifications table may not exist yet — ignore.
    }
}

function unread_notification_count(): int
{
    $u = current_user();
    if (!$u) {
        return 0;
    }
    try {
        $s = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
        $s->execute([$u['id']]);
        return (int) $s->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function unread_message_count(): int
{
    $u = current_user();
    if (!$u) {
        return 0;
    }
    try {
        $s = db()->prepare('SELECT COUNT(*) FROM chat_messages WHERE receiver_id=? AND is_read=0');
        $s->execute([$u['id']]);
        return (int) $s->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

// ----------------------------------------------------------------------
//  Reviews: recompute a nanny's stored average rating from the reviews table
// ----------------------------------------------------------------------
function recompute_rating(int $nannyUserId): void
{
    db()->prepare(
        'UPDATE nanny_profiles
         SET average_rating = (SELECT IFNULL(ROUND(AVG(rating), 2), 0) FROM reviews WHERE nanny_id = ?)
         WHERE user_id = ?'
    )->execute([$nannyUserId, $nannyUserId]);
}

// ----------------------------------------------------------------------
//  Avatars & image uploads
// ----------------------------------------------------------------------
/** Render an avatar: the uploaded image if present, else coloured initials. */
function avatar(string $name, ?string $image = null, string $extraClass = ''): string
{
    $cls = trim('avatar ' . $extraClass);
    if ($image) {
        return '<img class="' . e($cls) . '" src="' . e(url($image)) . '" alt="' . e($name) . '">';
    }
    $initials = strtoupper(substr($name, 0, 1)
        . (strpos($name, ' ') ? substr(strstr($name, ' '), 1, 1) : ''));
    return '<div class="' . e($cls) . '">' . e($initials) . '</div>';
}

/**
 * Validate & store an uploaded image under assets/<subdir>/.
 * Returns ['ok'=>true,'path'=>...] | ['ok'=>false,'error'=>...] | ['ok'=>false,'skip'=>true].
 */
function save_uploaded_image(array $file, string $subdir = 'uploads'): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'skip' => true];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'The image failed to upload. Please try again.'];
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Image must be smaller than 2 MB.'];
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return ['ok' => false, 'error' => 'That file is not a valid image.'];
    }
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$info['mime']] ?? null;
    if ($ext === null) {
        return ['ok' => false, 'error' => 'Only JPG, PNG, WEBP or GIF images are allowed.'];
    }
    $dir = __DIR__ . '/../assets/' . $subdir;
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return ['ok' => false, 'error' => 'Could not save the image on the server.'];
    }
    return ['ok' => true, 'path' => 'assets/' . $subdir . '/' . $name];
}

function stars_html(float $rating): string
{
    $full = (int) round($rating);
    $out = '<span class="stars">';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<span class="' . ($i <= $full ? '' : 'off') . '">★</span>';
    }
    return $out . '</span>';
}

// ----------------------------------------------------------------------
//  Small view helper
// ----------------------------------------------------------------------

// ----------------------------------------------------------------------
//  Support ticket helper
// ----------------------------------------------------------------------
function create_support_ticket(
    string $name, string $email, string $subject,
    string $message, string $category = 'general', ?int $userId = null
): int {
    $stmt = db()->prepare(
        'INSERT INTO support_tickets (user_id, name, email, category, subject, message)
         VALUES (?,?,?,?,?,?)'
    );
    $stmt->execute([$userId, $name, $email, $category, $subject, $message]);
    return (int) db()->lastInsertId();
}

function nanny_has_booking_conflict(int $nannyId, string $startDateTime, float $durationHours): bool
{
    $start = new DateTimeImmutable($startDateTime);
    $seconds = (int) round($durationHours * 3600);
    $end = $start->add(new DateInterval('PT' . $seconds . 'S'));

    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM bookings
         WHERE nanny_id = ?
           AND status IN ("pending", "confirmed")
           AND date_time < ?
           AND TIMESTAMPADD(SECOND, ROUND(duration * 3600), date_time) > ?'
    );
    $stmt->execute([$nannyId, $end->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s')]);
    return (int) $stmt->fetchColumn() > 0;
}

function render_booking_timeline(array $booking): string
{
    $status = $booking['status'] ?? 'pending';

    $accepted  = in_array($status, ['confirmed', 'in_progress', 'completed'], true);
    $checkedIn = !empty($booking['checked_in_at']) || in_array($status, ['in_progress', 'completed'], true);
    $checkedOut = !empty($booking['checked_out_at']) || $status === 'completed';
    $confirmed = !empty($booking['parent_confirmed_at']) || $status === 'completed';

    $steps = [
        'Pending'      => $status === 'pending',
        'Accepted'     => $accepted,
        'Nanny arrived' => $checkedIn,
        'Session done' => $checkedOut,
        'Confirmed & paid' => $confirmed,
        'Reviewed'     => !empty($booking['reviewed']) && $status === 'completed',
    ];

    $html = '<div class="booking-timeline">';
    foreach ($steps as $label => $active) {
        $html .= '<span class="timeline-step' . ($active ? ' active' : '') . '">' . e($label) . '</span>';
    }
    $html .= '</div>';
    return $html;
}

function status_badge(string $status): string
{
    $map = [
        'pending'     => 'badge-warn',
        'confirmed'   => 'badge-ok',
        'verified'    => 'badge-ok',
        'paid'        => 'badge-ok',
        'held'        => 'badge-warn',
        'released'    => 'badge-ok',
        'completed'   => 'badge-ok',
        'resolved'    => 'badge-ok',
        'rejected'    => 'badge-bad',
        'cancelled'   => 'badge-bad',
        'failed'      => 'badge-bad',
        'suspended'   => 'badge-bad',
        'disputed'    => 'badge-bad',
        'open'        => 'badge-warn',
        'in_progress' => 'badge-warn',
        'closed'      => 'badge-muted',
    ];
    $labels = [
        'in_progress' => 'In progress',
        'held'        => 'Held in escrow',
        'released'    => 'Released to nanny',
        'disputed'    => 'Disputed',
    ];
    $cls   = $map[$status] ?? 'badge-muted';
    $label = $labels[$status] ?? ucfirst($status);
    return '<span class="badge ' . $cls . '">' . e($label) . '</span>';
}

// ----------------------------------------------------------------------
//  Booking presence verification (check-in PIN) & escrow payouts
// ----------------------------------------------------------------------

/** Generate a fresh 6-digit numeric check-in PIN. */
function generate_check_in_code(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Safety net: if a nanny checked out and the parent never confirmed or
 * disputed within the grace window, auto-release the held payment so the
 * nanny isn't stuck waiting forever. Cheap + idempotent — safe to call on
 * any page load for pages that display bookings/payments.
 */
function auto_release_stale_payments(int $graceHours = 48): void
{
    try {
        $stmt = db()->prepare(
            "SELECT id FROM bookings
             WHERE status = 'in_progress'
               AND checked_out_at IS NOT NULL
               AND parent_confirmed_at IS NULL
               AND checked_out_at < DATE_SUB(NOW(), INTERVAL ? HOUR)"
        );
        $stmt->execute([$graceHours]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($ids as $id) {
            $id = (int) $id;
            db()->prepare(
                "UPDATE bookings SET status='completed', parent_confirmed_at=NOW() WHERE id=? AND status='in_progress'"
            )->execute([$id]);
            db()->prepare(
                "UPDATE payments SET payout_status='released', released_at=NOW()
                 WHERE booking_id=? AND status='paid' AND payout_status='held'"
            )->execute([$id]);

            $info = db()->prepare('SELECT parent_id, nanny_id, date_time FROM bookings WHERE id=?');
            $info->execute([$id]);
            if ($b = $info->fetch()) {
                notify((int) $b['nanny_id'], 'Payment released',
                    'Your booking on ' . date('D d M, H:i', strtotime($b['date_time']))
                        . ' was auto-confirmed after 48 hours and payment has been released to you.',
                    'nanny/earnings.php');
            }
        }
    } catch (Throwable) {
        // Escrow columns may not exist yet on older schemas — ignore until migrate_v4 runs.
    }
}

function show_alert(string $type, string $message): string
{
    $map = [
        'success' => 'app-alert-success',
        'error'   => 'app-alert-danger',
        'warning' => 'app-alert-warning',
        'info'    => 'app-alert-info',
    ];
    $cls = $map[$type] ?? 'app-alert-info';
    return '<div class="app-alert ' . $cls . '" role="status">' . e($message) . '</div>';
}

function badge(string $text, string $tone = 'neutral'): string
{
    $map = [
        'success' => 'badge-ok',
        'warning' => 'badge-warn',
        'danger'  => 'badge-bad',
        'neutral' => 'badge-muted',
    ];
    $cls = $map[$tone] ?? 'badge-muted';
    return '<span class="badge ' . $cls . '">' . e($text) . '</span>';
}

function stat_card(string $label, string|int|float $value, string $icon = ''): string
{
    $iconHtml = $icon !== '' ? '<span class="stat-card-icon" aria-hidden="true">' . e($icon) . '</span>' : '';
    return '<div class="stat-card">'
        . $iconHtml
        . '<span class="stat-card-label">' . e($label) . '</span>'
        . '<strong class="stat-card-value">' . e((string)$value) . '</strong>'
        . '</div>';
}

function empty_state(string $title, string $description, ?string $actionHref = null, ?string $actionLabel = null): string
{
    $action = '';
    if ($actionHref && $actionLabel) {
        $action = '<a class="btn btn-primary" href="' . e($actionHref) . '">' . e($actionLabel) . '</a>';
    }
    return '<div class="empty-state">'
        . '<h3>' . e($title) . '</h3>'
        . '<p class="muted">' . e($description) . '</p>'
        . $action
        . '</div>';
}
