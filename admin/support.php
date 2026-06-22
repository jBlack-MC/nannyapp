<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$categories = [
    'booking'   => 'Booking issue',
    'payment'   => 'Payment or refund',
    'technical' => 'Technical problem',
    'safety'    => 'Safety concern',
    'general'   => 'General question',
];

$statuses = ['open','in_progress','resolved','closed'];

// Handle status update or admin note
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id         = (int) ($_POST['ticket_id'] ?? 0);
    $newStatus  = $_POST['new_status']  ?? '';
    $adminNotes = trim($_POST['admin_notes'] ?? '');

    if ($id && in_array($newStatus, $statuses)) {
        db()->prepare(
            'UPDATE support_tickets SET status=?, admin_notes=? WHERE id=?'
        )->execute([$newStatus, $adminNotes ?: null, $id]);

        // Notify user if ticket is now resolved
        if ($newStatus === 'resolved') {
            $t = db()->prepare('SELECT user_id, subject FROM support_tickets WHERE id=?');
            $t->execute([$id]);
            $ticket = $t->fetch();
            if ($ticket && $ticket['user_id']) {
                notify($ticket['user_id'], 'Support ticket resolved',
                    'Your ticket "' . $ticket['subject'] . '" has been resolved. Check your email for details.',
                    'support.php');
            }
        }
        flash('Ticket #' . $id . ' updated.');
    }
    redirect('admin/support.php');
}

// Filters
$filterStatus   = $_GET['status']   ?? '';
$filterCategory = $_GET['category'] ?? '';

$where  = [];
$params = [];
if ($filterStatus && in_array($filterStatus, $statuses)) {
    $where[]  = 'status = ?';
    $params[] = $filterStatus;
}
if ($filterCategory && array_key_exists($filterCategory, $categories)) {
    $where[]  = 'category = ?';
    $params[] = $filterCategory;
}

$sql = 'SELECT st.*, u.full_name AS user_name
        FROM support_tickets st
        LEFT JOIN users u ON u.id = st.user_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY FIELD(st.status,"open","in_progress","resolved","closed"), st.created_at DESC';

$tickets = db()->prepare($sql);
$tickets->execute($params);
$tickets = $tickets->fetchAll();

// Counts by status
$counts = db()->query(
    "SELECT status, COUNT(*) AS n FROM support_tickets GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

// Open selected ticket for inline view
$openId = (int) ($_GET['id'] ?? 0);
$openTicket = null;
if ($openId) {
    $s = db()->prepare('SELECT st.*, u.full_name AS user_name FROM support_tickets st LEFT JOIN users u ON u.id=st.user_id WHERE st.id=?');
    $s->execute([$openId]);
    $openTicket = $s->fetch();
}

$pageTitle = 'Support Tickets';
require __DIR__ . '/../includes/header.php';
?>
<div class="section-head">
    <div>
        <p class="h-eyebrow">Admin</p>
        <h1>Support tickets</h1>
        <p class="muted"><?= (int)($counts['open'] ?? 0) ?> open · <?= (int)($counts['in_progress'] ?? 0) ?> in progress · <?= (int)($counts['resolved'] ?? 0) ?> resolved</p>
    </div>
    <a class="btn" href="<?= url('admin/dashboard.php') ?>">← Dashboard</a>
</div>

<!-- Status summary pills -->
<div class="admin-filter-row">
    <a class="btn btn-sm <?= !$filterStatus ? 'btn-primary' : '' ?>" href="<?= url('admin/support.php') ?>">All (<?= array_sum($counts) ?>)</a>
    <?php foreach (['open' => 'badge-bad', 'in_progress' => 'badge-warn', 'resolved' => 'badge-ok', 'closed' => 'badge-muted'] as $s => $cls): ?>
        <a class="btn btn-sm <?= $filterStatus === $s ? 'btn-primary' : '' ?>"
           href="<?= url('admin/support.php?status=' . $s . ($filterCategory ? '&category=' . $filterCategory : '')) ?>">
            <?= ucfirst(str_replace('_', ' ', $s)) ?> (<?= (int)($counts[$s] ?? 0) ?>)
        </a>
    <?php endforeach; ?>

    <span class="admin-filter-spacer"></span>
    <select class="admin-filter-select" onchange="location='<?= url('admin/support.php') ?>?category='+this.value+'<?= $filterStatus ? '&status=' . $filterStatus : '' ?>'">
        <option value="">All categories</option>
        <?php foreach ($categories as $val => $label): ?>
            <option value="<?= $val ?>" <?= $filterCategory === $val ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="grid grid-2 admin-ticket-grid">

    <!-- Ticket list -->
    <div class="card card-shell">
        <?php if (!$tickets): ?>
            <div class="empty empty-pad">
                <div class="empty-ico">✅</div>
                <h3>No tickets found</h3>
                <p>No support tickets match your current filters.</p>
            </div>
        <?php else: ?>
            <?php foreach ($tickets as $t): ?>
            <a href="<?= url('admin/support.php?id=' . $t['id'] . ($filterStatus ? '&status=' . $filterStatus : '') . ($filterCategory ? '&category=' . $filterCategory : '')) ?>"
               class="ticket-link <?= $openId === $t['id'] ? 'is-active' : '' ?>">
                <div class="ticket-row">
                    <span class="ticket-id">#<?= $t['id'] ?></span>
                    <?= status_badge($t['status']) ?>
                    <span class="tag tag-xs"><?= e($categories[$t['category']] ?? $t['category']) ?></span>
                    <span class="muted date-xs"><?= e(date('d M Y', strtotime($t['created_at']))) ?></span>
                </div>
                <div class="ticket-subject"><?= e($t['subject']) ?></div>
                <div class="muted text-xs"><?= e($t['name']) ?> · <?= e($t['email']) ?></div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Ticket detail panel -->
    <?php if ($openTicket): ?>
    <div class="card stack">
        <div class="detail-head">
            <div>
                <span class="muted detail-kicker">Ticket #<?= $openTicket['id'] ?></span>
                <h2 class="detail-title"><?= e($openTicket['subject']) ?></h2>
                <div class="detail-tags">
                    <?= status_badge($openTicket['status']) ?>
                    <span class="tag"><?= e($categories[$openTicket['category']] ?? $openTicket['category']) ?></span>
                </div>
            </div>
            <div class="muted detail-meta">
                <?= e(date('d M Y H:i', strtotime($openTicket['created_at']))) ?><br>
                <?php if ($openTicket['user_name']): ?>
                    <a href="<?= url('admin/users.php') ?>">Registered user</a>
                <?php else: ?>
                    Guest
                <?php endif; ?>
            </div>
        </div>

        <div class="card card-muted">
            <div class="mb-6"><strong><?= e($openTicket['name']) ?> &lt;<?= e($openTicket['email']) ?>&gt;</strong></div>
            <p class="pre-wrap"><?= e($openTicket['message']) ?></p>
        </div>

        <?php if ($openTicket['admin_notes']): ?>
        <div class="flash flash-info flash-no-margin">
            <strong>Admin notes:</strong><br><?= e($openTicket['admin_notes']) ?>
        </div>
        <?php endif; ?>

        <form method="post" class="stack">
            <?= csrf_field() ?>
            <input type="hidden" name="ticket_id" value="<?= $openTicket['id'] ?>">

            <div class="field field-tight">
                <label>Update status</label>
                <select name="new_status">
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= $openTicket['status'] === $s ? 'selected' : '' ?>>
                            <?= ucfirst(str_replace('_', ' ', $s)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field field-tight">
                <label>Admin notes <span class="muted">(internal only)</span></label>
                <textarea name="admin_notes" rows="3" placeholder="Add notes about this ticket…"><?= e($openTicket['admin_notes'] ?? '') ?></textarea>
            </div>

            <div class="actions-inline">
                <button class="btn btn-primary">Update ticket</button>
                <a class="btn" href="mailto:<?= e($openTicket['email']) ?>?subject=Re: <?= urlencode('[Ticket #' . $openTicket['id'] . '] ' . $openTicket['subject']) ?>">Reply via email</a>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div class="card ticket-empty">
        <div class="center">
            <div class="emoji-lg">📋</div>
            <p class="muted-tight">Select a ticket to view details</p>
        </div>
    </div>
    <?php endif; ?>

</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
