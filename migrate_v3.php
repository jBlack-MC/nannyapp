<?php
require_once __DIR__ . '/config/config.php';
require_role('admin');

$sql  = file_get_contents(__DIR__ . '/database/migrate_v3.sql');
$stmts = array_filter(array_map('trim', explode(';', $sql)));
$results = [];

foreach ($stmts as $s) {
    if ($s === '' || strncmp(ltrim($s), '--', 2) === 0) continue;
    try {
        db()->exec($s);
        $results[] = ['ok' => true,  'sql' => substr($s, 0, 80)];
    } catch (Throwable $e) {
        $results[] = ['ok' => false, 'sql' => substr($s, 0, 80), 'err' => $e->getMessage()];
    }
}

$pageTitle = 'DB Migration v3';
require __DIR__ . '/includes/header.php';
?>
<div class="section-head">
    <div>
        <p class="h-eyebrow">System</p>
        <h1>Database migration v3</h1>
    </div>
    <a class="btn" href="<?= url('admin/dashboard.php') ?>">← Dashboard</a>
</div>
<div class="card stack">
    <?php foreach ($results as $r): ?>
    <div class="migrate-row">
        <span class="migrate-status <?= $r['ok'] ? 'migrate-status-ok' : 'migrate-status-bad' ?>"><?= $r['ok'] ? 'OK' : 'ERR' ?></span>
        <span class="migrate-sql"><?= e($r['sql']) ?>…</span>
        <?php if (!$r['ok']): ?><span class="migrate-error"><?= e($r['err']) ?></span><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <p class="muted">Migration complete. <a href="<?= url('admin/dashboard.php') ?>">Return to dashboard</a>.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
