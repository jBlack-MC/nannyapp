<?php
/**
 * V2 schema migration runner — admin only.
 * Browse to /nannyapp/migrate_v2.php after logging in as admin.
 */
require_once __DIR__ . '/config/config.php';
require_role('admin');

$sql = file_get_contents(__DIR__ . '/database/migrate_v2.sql');
$statements = array_filter(array_map('trim', explode(';', $sql)));

$results = [];
$pdo = db();

foreach ($statements as $stmt) {
    if ($stmt === '' || str_starts_with(ltrim($stmt), '--')) {
        continue;
    }
    try {
        $pdo->exec($stmt);
        $results[] = ['ok' => true,  'sql' => substr($stmt, 0, 80)];
    } catch (PDOException $e) {
        $results[] = ['ok' => false, 'sql' => substr($stmt, 0, 80), 'err' => $e->getMessage()];
    }
}

$pageTitle = 'V2 Migration';
require __DIR__ . '/includes/header.php';
?>
<div class="card stack migrate-wrap">
    <h1>V2 Schema Migration</h1>
    <p class="muted">Each SQL statement is attempted in order. Existing-column errors are expected and safe.</p>
    <table class="table">
        <thead><tr><th>#</th><th>Statement</th><th>Result</th></tr></thead>
        <tbody>
        <?php foreach ($results as $i => $r): ?>
            <tr>
                <td class="muted"><?= $i + 1 ?></td>
                <td class="table-txn"><?= e($r['sql']) ?>…</td>
                <td><?php if ($r['ok']): ?><span class="badge badge-ok">OK</span><?php else: ?>
                    <span class="badge badge-bad" title="<?= e($r['err']) ?>">Error</span>
                <?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <a class="btn" href="<?= url('admin/dashboard.php') ?>">← Back to dashboard</a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
