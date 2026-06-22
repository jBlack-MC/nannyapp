<?php
require_once __DIR__ . '/config/config.php';
require_login();

$me = current_user()['id'];

// Open a single notification: mark read, then go where it points.
if (isset($_GET['open'])) {
    $id = (int) $_GET['open'];
    $stmt = db()->prepare('SELECT url FROM notifications WHERE id=? AND user_id=?');
    $stmt->execute([$id, $me]);
    $row = $stmt->fetch();
    if ($row) {
        db()->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')->execute([$id, $me]);
        if (!empty($row['url'])) {
            redirect($row['url']);
        }
    }
    redirect('notifications.php');
}

// Mark all read.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$me]);
    flash('All notifications marked as read.');
    redirect('notifications.php');
}

$rows = db()->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100');
$rows->execute([$me]);
$rows = $rows->fetchAll();

$pageTitle = 'Notifications';
require __DIR__ . '/includes/header.php';
?>
<div class="section-head">
    <h1 class="heading-tight">Notifications</h1>
    <?php if ($rows): ?>
        <form method="post" class="form-zero">
            <?= csrf_field() ?>
            <button class="btn btn-sm">Mark all as read</button>
        </form>
    <?php endif; ?>
</div>

<div class="card section notif-card-flat">
    <?php if (!$rows): ?>
        <div class="empty">
            <span class="empty-ico">🔔</span>
            <h3>You're all caught up</h3>
            <p>Booking updates, messages and reviews will show up here as they happen.</p>
        </div>
    <?php else: ?>
        <ul class="notif-list">
            <?php foreach ($rows as $n): ?>
                <li class="notif <?= $n['is_read'] ? '' : 'unread' ?>">
                    <a href="<?= url('notifications.php?open=' . (int)$n['id']) ?>">
                        <div class="notif-body">
                            <strong><?= e($n['title']) ?></strong>
                            <span class="muted"><?= e($n['message']) ?></span>
                        </div>
                        <span class="muted notif-time"><?= e(date('d M, H:i', strtotime($n['created_at']))) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
