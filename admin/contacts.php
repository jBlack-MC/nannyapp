<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

// Open a single message for full view
$openId = (int) ($_GET['id'] ?? 0);
$open   = null;
if ($openId) {
    $s = db()->prepare('SELECT * FROM contact_messages WHERE id=?');
    $s->execute([$openId]);
    $open = $s->fetch();
}

// Pagination
$perPage = 20;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$total      = (int) db()->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$rows = db()->prepare(
    'SELECT id, name, email, subject, created_at FROM contact_messages ORDER BY created_at DESC LIMIT ? OFFSET ?'
);
$rows->execute([$perPage, $offset]);
$rows = $rows->fetchAll();

$pageTitle = 'Contact messages';
require __DIR__ . '/../includes/header.php';
?>

<div class="section-head">
    <div>
        <p class="h-eyebrow">Admin</p>
        <h1>Contact messages</h1>
        <p class="muted">Enquiries submitted through the public contact form.</p>
    </div>
    <span class="badge badge-neutral"><?= $total ?> total</span>
</div>

<?php if ($open): ?>
<div class="card section contact-open-card">
    <div class="contact-open-head">
        <div>
            <h2 class="heading-tight"><?= e($open['subject']) ?></h2>
            <p class="muted">From <strong><?= e($open['name']) ?></strong>
                &lt;<a href="mailto:<?= e($open['email']) ?>"><?= e($open['email']) ?></a>&gt;
                &mdash; <?= e(date('D d M Y, H:i', strtotime($open['created_at']))) ?>
            </p>
        </div>
        <a class="btn btn-sm" href="<?= url('admin/contacts.php') ?>">&#x2715; Close</a>
    </div>
    <div class="contact-open-body">
        <?= nl2br(e($open['message'])) ?>
    </div>
    <div class="contact-open-actions">
        <a class="btn btn-sm btn-primary" href="mailto:<?= e($open['email']) ?>?subject=Re: <?= urlencode($open['subject']) ?>">Reply by email</a>
    </div>
</div>
<?php endif; ?>

<div class="card section">
    <?php if (!$rows): ?>
        <div class="empty">
            <span class="empty-ico">📭</span>
            <h3>No contact messages yet</h3>
            <p>Messages submitted through the public contact form will appear here.</p>
        </div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Subject</th>
                    <th>Received</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr class="<?= $openId === (int)$r['id'] ? 'row-active' : '' ?>">
                    <td class="muted">#<?= (int)$r['id'] ?></td>
                    <td><?= e($r['name']) ?></td>
                    <td><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></td>
                    <td><?= e($r['subject']) ?></td>
                    <td class="muted"><?= e(date('d M Y, H:i', strtotime($r['created_at']))) ?></td>
                    <td>
                        <a class="btn btn-sm" href="<?= url('admin/contacts.php?id=' . (int)$r['id']) ?>">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Contact messages pages">
            <?php if ($page > 1): ?>
                <a class="btn btn-sm" href="<?= url('admin/contacts.php?page=' . ($page - 1)) ?>">&#8592; Prev</a>
            <?php endif; ?>
            <span class="pager-info">Page <?= $page ?> of <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a class="btn btn-sm" href="<?= url('admin/contacts.php?page=' . ($page + 1)) ?>">Next &#8594;</a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
