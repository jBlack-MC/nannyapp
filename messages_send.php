<?php
/**
 * AJAX endpoint: send a chat message without a full page reload.
 * Expects POST: to, message, csrf. Returns JSON: {ok, error?, id?, content?, time_label?}
 * The plain-form POST in messages.php remains the no-JS fallback.
 */
require_once __DIR__ . '/config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_logged_in()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Please log in to send messages.']);
    exit;
}

verify_csrf();

$me   = (int) current_user()['id'];
$to   = (int) ($_POST['to'] ?? 0);
$body = (string) ($_POST['message'] ?? '');

echo json_encode(send_chat_message($me, $to, $body));
