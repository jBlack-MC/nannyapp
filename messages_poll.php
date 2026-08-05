<?php
/**
 * AJAX endpoint: poll for new messages in a thread since a given message id.
 * Expects GET: with, after_id. Returns JSON: {ok, messages:[{id,mine,content,time_label}]}
 * Marks any newly-fetched incoming messages as read (the thread is open).
 */
require_once __DIR__ . '/config/config.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'messages' => []]);
    exit;
}

$me      = (int) current_user()['id'];
$with    = (int) ($_GET['with'] ?? 0);
$afterId = (int) ($_GET['after_id'] ?? 0);

if ($with <= 0) {
    echo json_encode(['ok' => false, 'messages' => []]);
    exit;
}

db()->prepare('UPDATE chat_messages SET is_read=1 WHERE receiver_id=? AND sender_id=? AND is_read=0')
    ->execute([$me, $with]);

$stmt = db()->prepare(
    'SELECT id, sender_id, content, created_at FROM chat_messages
     WHERE ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) AND id > ?
     ORDER BY created_at ASC'
);
$stmt->execute([$me, $with, $with, $me, $afterId]);

$messages = array_map(static function (array $m) use ($me): array {
    return [
        'id'         => (int) $m['id'],
        'mine'       => (int) $m['sender_id'] === $me,
        'content'    => $m['content'],
        'time_label' => date('d M, H:i', strtotime($m['created_at'])),
    ];
}, $stmt->fetchAll());

echo json_encode(['ok' => true, 'messages' => $messages]);
