<?php
require_once __DIR__ . '/config/config.php';
require_login();

$me = (int) current_user()['id'];

// --- Send a message (no-JS fallback; the JS path posts to messages_send.php) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $to     = (int) ($_POST['to'] ?? 0);
    $body   = (string) ($_POST['message'] ?? '');
    $result = send_chat_message($me, $to, $body);
    if (!$result['ok']) {
        flash($result['error'], 'error');
    }
    redirect('messages.php?with=' . $to);
}

$with = (int) ($_GET['with'] ?? 0);

// --- Conversation list (most recent first, with a last-message preview) ---
$convos = db()->prepare(
    "SELECT u.id, u.full_name, u.role,
            MAX(m.created_at) AS last_at,
            SUM(m.receiver_id = ? AND m.is_read = 0) AS unread,
            (SELECT m2.content FROM chat_messages m2
             WHERE (m2.sender_id = u.id AND m2.receiver_id = ?) OR (m2.sender_id = ? AND m2.receiver_id = u.id)
             ORDER BY m2.created_at DESC LIMIT 1) AS last_content
     FROM chat_messages m
     JOIN users u ON u.id = IF(m.sender_id = ?, m.receiver_id, m.sender_id)
     WHERE m.sender_id = ? OR m.receiver_id = ?
     GROUP BY u.id, u.full_name, u.role
     ORDER BY last_at DESC"
);
$convos->execute([$me, $me, $me, $me, $me, $me]);
$convos = $convos->fetchAll();

// --- Active thread ---------------------------------------------------
$partner = null;
$thread  = [];
if ($with) {
    $p = db()->prepare('SELECT id, full_name, role FROM users WHERE id=?');
    $p->execute([$with]);
    $partner = $p->fetch() ?: null;

    if (!$partner) {
        flash('That user could not be found.', 'error');
    } else {
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
$lastId = $thread ? (int) end($thread)['id'] : 0;

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
                        <div class="muted chat-convo-preview">
                            <?php if ($c['last_content']): ?>
                                <?= e(mb_substr((string)$c['last_content'], 0, 42)) ?><?= mb_strlen((string)$c['last_content']) > 42 ? '…' : '' ?>
                            <?php else: ?>
                                <?= e(ucfirst($c['role'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="chat-convo-meta">
                        <?php if ($c['last_at']): ?>
                            <span class="muted chat-convo-time"><?= e(date('d M', strtotime($c['last_at']))) ?></span>
                        <?php endif; ?>
                        <?php if ((int)$c['unread'] > 0): ?>
                            <span class="nav-badge"><?= (int)$c['unread'] ?></span>
                        <?php endif; ?>
                    </div>
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
            <div class="chat-scroll" id="chatScroll" data-with="<?= (int)$partner['id'] ?>" data-last-id="<?= $lastId ?>">
                <?php if (!$thread): ?>
                    <p class="muted" id="chatEmptyHint">No messages yet — say hello 👋</p>
                <?php else: ?>
                    <?php foreach ($thread as $m): ?>
                        <div class="bubble <?= (int)$m['sender_id'] === $me ? 'mine' : 'theirs' ?>">
                            <?= e($m['content']) ?>
                            <span class="bubble-time"><?= e(date('d M, H:i', strtotime($m['created_at']))) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <form method="post" class="chat-compose" id="chatComposeForm" data-base="<?= BASE_URL ?>" data-csrf="<?= e(csrf_token()) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="to" value="<?= (int)$partner['id'] ?>">
                <input name="message" id="chatInput" placeholder="Type a message…" aria-label="Type a message" autocomplete="off" maxlength="1000" required>
                <button class="btn btn-primary" id="chatSendBtn">Send</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
  var scroller = document.getElementById('chatScroll');
  if (!scroller) return;

  function nearBottom() {
    return scroller.scrollTop + scroller.clientHeight >= scroller.scrollHeight - 40;
  }
  scroller.scrollTop = scroller.scrollHeight;

  var withId  = parseInt(scroller.getAttribute('data-with'), 10) || 0;
  var lastId  = parseInt(scroller.getAttribute('data-last-id'), 10) || 0;
  var form    = document.getElementById('chatComposeForm');
  var input   = document.getElementById('chatInput');
  var sendBtn = document.getElementById('chatSendBtn');
  var base    = (form && form.getAttribute('data-base')) || '';
  var csrf    = (form && form.getAttribute('data-csrf')) || '';

  function addBubble(msg) {
    var hint = document.getElementById('chatEmptyHint');
    if (hint) hint.remove();
    var div = document.createElement('div');
    div.className = 'bubble ' + (msg.mine ? 'mine' : 'theirs');
    div.appendChild(document.createTextNode(msg.content));
    var time = document.createElement('span');
    time.className = 'bubble-time';
    time.textContent = msg.time_label;
    div.appendChild(time);
    scroller.appendChild(div);
    if (msg.id > lastId) lastId = msg.id;
  }

  // ---- AJAX send (progressive enhancement over the plain form POST) ----
  if (form && withId) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var text = input.value.trim();
      if (!text) return;

      sendBtn.disabled = true;
      var wasNearBottom = true;

      fetch(base + '/messages_send.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'to=' + encodeURIComponent(withId)
             + '&message=' + encodeURIComponent(text)
             + '&csrf=' + encodeURIComponent(csrf)
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          sendBtn.disabled = false;
          if (data.ok) {
            input.value = '';
            addBubble({ id: data.id, mine: true, content: data.content, time_label: data.time_label });
            if (wasNearBottom) scroller.scrollTop = scroller.scrollHeight;
          } else if (window.showToast) {
            showToast(data.error || 'Could not send that message.', 'error');
          }
        })
        .catch(function () {
          sendBtn.disabled = false;
          // Fall back to a normal form submit if the network/AJAX path fails.
          form.submit();
        });
    });
  }

  // ---- Lightweight polling for new incoming messages ----
  if (withId) {
    var pollTimer = null;
    function poll() {
      if (document.hidden) return;
      fetch(base + '/messages_poll.php?with=' + withId + '&after_id=' + lastId)
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.ok || !data.messages.length) return;
          var wasNearBottom = nearBottom();
          data.messages.forEach(addBubble);
          if (wasNearBottom) scroller.scrollTop = scroller.scrollHeight;
        })
        .catch(function () {});
    }
    pollTimer = setInterval(poll, 4000);
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) poll();
    });
  }
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
