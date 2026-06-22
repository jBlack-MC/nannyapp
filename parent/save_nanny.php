<?php
/**
 * AJAX endpoint: save or unsave a nanny.
 * Expects POST: nanny_id, action (save|unsave), csrf
 * Returns JSON: {ok:bool, message:string}
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (!is_logged_in() || user_role() !== 'parent') {
    echo json_encode(['ok' => false, 'message' => 'Please log in as a parent to save nannies.']);
    exit;
}

verify_csrf();

$nannyId = (int) ($_POST['nanny_id'] ?? 0);
$action  = $_POST['action'] ?? '';
$me      = current_user()['id'];

if (!$nannyId || !in_array($action, ['save', 'unsave'])) {
    echo json_encode(['ok' => false, 'message' => 'Invalid request.']);
    exit;
}

// Verify the nanny exists
$check = db()->prepare('SELECT 1 FROM users WHERE id=? AND role="nanny"');
$check->execute([$nannyId]);
if (!$check->fetch()) {
    echo json_encode(['ok' => false, 'message' => 'Nanny not found.']);
    exit;
}

if ($action === 'save') {
    db()->prepare('INSERT IGNORE INTO saved_nannies (parent_id, nanny_id) VALUES (?,?)')->execute([$me, $nannyId]);
    echo json_encode(['ok' => true, 'message' => 'Nanny saved to your favourites.']);
} else {
    db()->prepare('DELETE FROM saved_nannies WHERE parent_id=? AND nanny_id=?')->execute([$me, $nannyId]);
    echo json_encode(['ok' => true, 'message' => 'Nanny removed from favourites.']);
}
