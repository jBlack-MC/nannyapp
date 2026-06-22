<?php
require_once __DIR__ . '/config/config.php';
require_login();

$me = (int) current_user()['id'];

// --- Send a message --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $to   = (int) ($_POST['to'] ?? 0);
    $body = trim($_POST['message'] ?? '');

    $valid = db()->prepare('SELECT full_name FROM users WHERE id=?');
    $valid->execute([$to]);
    $target = $valid->fetch();

    if ($to === $me || !$target) {
        flash('That recipient is not available.', 'error');
    } elseif ($body === '') {
        flash('Please type a message.', 'error');
    } else {
        db()->prepare('INSERT INTO chat_messages (sender_id, receiver_id, content) VALUES (?,?,?)')
            ->execute([$me, $to, mb_substr($body, 0, 1000)]);
        notify($to, 'New message from ' . current_user()['full_name'],
            mb_substr($body, 0, 80), 'messages.php?with=' . $me);
    }
    redirect('messages.php?with=' . $to);
}

$with = (int) ($_GET['with'] ?? 0);

// --- Conversation list (most recent first) ---------------------------
$convos = db()->prepare(
    "SELECT u.id, u.full_name, u.role,
            MAX(m.created_at) AS last_at,
            SUM(m.receiver_id = ? AND m.is_read = 0) AS unread
     FROM chat_messages m
     JOIN users u ON u.id = IF(m.sender_id = ?, m.receiver_id, m.sender_id)
     WHERE m.sender_id = ? OR m.receiver_id = ?
     GROUP BY u.id, u.full_name, u.role
     ORDER BY last_at DESC"
);
$convos->execute([$me, $me, $me, $me]);
$convos = $convos->fetchAll();

// --- Active thread ---------------------------------------------------
$partner = null;
$thread  = [];
if ($with) {
    $p = db()->prepare('SELECT id, full_name, role FROM users WHERE id=?');
    $p->execute([$with]);
    $partner = $p->fetch() ?: null;

    if ($partner) {
        // Mark their messages to me as read.
        db()->prepare('UPDATE chat_messages SET is_read=1 WHERE receiver_id=? AND sender_id=?')
            ->execute([$me, $with]);

        $t = db()->prepare(
            'SELECT * FROM chat_messages
             WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)
             ORDER BY created_at ASC'
        );
        $t->execute([$me, $with, $with, $me]);
        $thread = $t->fetchAll();
    }
}

$pageTitle = 'Messages';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <p class="h-eyebrow">Inbox</p>
    <h1>Messages</h1>
    <p class="muted">Keep every conversation in one place and pick up where you left off.</p>
</div>

<div class="chat-layout section section-no-top">
    <aside class="card chat-list" aria-label="Conversation list">
        <?php if (!$convos): ?>
            <p class="muted">No conversations yet. Start one from a nanny's profile.</p>
        <?php else: ?>
            <?php foreach ($convos as $c): ?>
                <a class="chat-convo <?= (int)$c['id'] === $with ? 'active' : '' ?>"
                   aria-label="Open conversation with <?= e($c['full_name']) ?>"
                   href="<?= url('messages.php?with=' . (int)$c['id']) ?>">
                    <div class="avatar"><?= e(strtoupper(substr($c['full_name'], 0, 1))) ?></div>
                    <div class="chat-convo-info">
                        <strong><?= e($c['full_name']) ?></strong>
                        <div class="muted chat-convo-role"><?= e(ucfirst($c['role'])) ?></div>
                    </div>
                    <?php if ((int)$c['unread'] > 0): ?>
                        <span class="nav-badge"><?= (int)$c['unread'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </aside>

    <div class="card chat-thread" aria-live="polite">
        <?php if (!$partner): ?>
            <div class="empty">
                <span class="empty-ico">💬</span>
                <h3>Your messages</h3>
                <p>Select a conversation on the left, or open a nanny's profile and choose “Message” to start chatting.</p>
            </div>
        <?php else: ?>
            <div class="chat-head"><strong><?= e($partner['full_name']) ?></strong>
                <span class="muted">· <?= e(ucfirst($partner['role'])) ?></span></div>
            <div class="chat-scroll" id="chatScroll">
                <?php if (!$thread): ?>
                    <p class="muted">No messages yet — say hello 👋</p>
                <?php else: ?>
                    <?php foreach ($thread as $m): ?>
                        <div class="bubble <?= (int)$m['sender_id'] === $me ? 'mine' : 'theirs' ?>">
                            <?= e($m['content']) ?>
                            <span class="bubble-time"><?= e(date('d M, H:i', strtotime($m['created_at']))) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <form method="post" class="chat-compose">
                <?= csrf_field() ?>
                <input type="hidden" name="to" value="<?= (int)$partner['id'] ?>">
                <input name="message" placeholder="Type a message…" aria-label="Type a message" autocomplete="off" required>
                <button class="btn btn-primary">Send</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<script>
  var s = document.getElementById('chatScroll');
  if (s) s.scrollTop = s.scrollHeight;
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
