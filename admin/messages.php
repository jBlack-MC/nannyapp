<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

// Delete a handled message.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    db()->prepare('DELETE FROM contact_messages WHERE id=?')->execute([$id]);
    flash('Message removed.');
    redirect('admin/messages.php');
}

$rows = [];
try {
    $rows = db()->query('SELECT * FROM contact_messages ORDER BY created_at DESC')->fetchAll();
} catch (Throwable $e) {
    // contact_messages may not exist until someone uses the contact form / migrate runs.
}

$pageTitle = 'Contact inbox';
require __DIR__ . '/../includes/header.php';
?>
<h1>Contact inbox</h1>
<p class="muted">Messages submitted through the public contact form.</p>

<div class="card section">
    <?php if (!$rows): ?>
        <p class="muted">No messages yet.</p>
    <?php else: ?>
        <div class="stack">
            <?php foreach ($rows as $m): ?>
                <div class="review-item">
                    <div class="section-head section-head-zero">
                        <div>
                            <strong><?= e($m['subject'] ?: '(no subject)') ?></strong>
                            <div class="muted message-meta">
                                <?= e($m['name']) ?> · <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a>
                                · <?= e(date('d M Y, H:i', strtotime($m['created_at']))) ?>
                            </div>
                        </div>
                        <form method="post" class="form-zero">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                            <button class="btn btn-sm btn-danger" data-confirm="Delete this message?">Delete</button>
                        </form>
                    </div>
                    <p class="muted message-body"><?= nl2br(e($m['message'])) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
