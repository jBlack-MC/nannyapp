<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $target  = $_POST['target'] ?? 'all';
    $title   = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($title === '')   $errors[] = 'Please enter a title.';
    if ($message === '') $errors[] = 'Please enter a message.';

    if (!$errors) {
        if (in_array($target, ['all', 'parents', 'nannies'], true)) {
            $sql = 'INSERT INTO notifications (user_id, title, message) SELECT id, ?, ? FROM users';
            $params = [$title, $message];
            if ($target === 'parents') { $sql .= " WHERE role='parent'"; }
            if ($target === 'nannies') { $sql .= " WHERE role='nanny'"; }
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            $count = $stmt->rowCount();
        } else {
            notify((int) $target, $title, $message);
            $count = 1;
        }
        flash("Notification sent to {$count} user(s).");
        redirect('admin/notify.php');
    }
}

$people = db()->query("SELECT id, full_name, role FROM users WHERE role<>'admin' ORDER BY full_name")->fetchAll();

$pageTitle = 'Send notification';
require __DIR__ . '/../includes/header.php';
?>
<div class="card form stack">
    <h1>Send a notification</h1>
    <p class="muted">Broadcast an in-app notification to your users.</p>

    <?php foreach ($errors as $err): ?>
        <div class="flash flash-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" class="stack">
        <?= csrf_field() ?>
        <div class="field">
            <label for="n-target">Recipients</label>
            <select id="n-target" name="target">
                <option value="all">All users</option>
                <option value="parents">All parents</option>
                <option value="nannies">All nannies</option>
                <optgroup label="Specific user">
                    <?php foreach ($people as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= e($p['full_name']) ?> (<?= e($p['role']) ?>)</option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
        </div>
        <div class="field">
            <label for="n-title">Title</label>
            <input id="n-title" name="title" maxlength="150" required>
        </div>
        <div class="field">
            <label for="n-message">Message</label>
            <textarea id="n-message" name="message" rows="4" maxlength="500" required></textarea>
        </div>
        <button class="btn btn-primary btn-block">Send notification</button>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
