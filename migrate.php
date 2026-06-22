<?php
/**
 * One-time migration runner.
 * Adds tables/columns introduced after the initial schema.sql import so an
 * existing database can be upgraded WITHOUT dropping data.
 *
 * Visit  http://localhost/nannyapp/migrate.php  once. Safe to re-run.
 * 
 * SECURITY: Only run from CLI or with admin token to prevent unauthorized access.
 */

// Allow from CLI or with admin authentication
$isCli = php_sapi_name() === 'cli';
if (!$isCli) {
    require_once __DIR__ . '/config/config.php';
    
    // Check if user is logged in and is admin
    if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        die('🔒 Access Denied: Migration can only be run from command line or by administrators.');
    }
}

require_once __DIR__ . '/config/config.php';

$pdo = db();
$done = [];

function column_exists(PDO $pdo, string $table, string $col): bool
{
    $s = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $s->execute([$table, $col]);
    return (int) $s->fetchColumn() > 0;
}

// users.status + users.profile_image (Profiles + Admin slice)
if (!column_exists($pdo, 'users', 'status')) {
    $pdo->exec("ALTER TABLE users ADD status ENUM('active','suspended') NOT NULL DEFAULT 'active' AFTER role");
    $done[] = 'users.status added';
}
if (!column_exists($pdo, 'users', 'profile_image')) {
    $pdo->exec('ALTER TABLE users ADD profile_image VARCHAR(255) DEFAULT NULL AFTER status');
    $done[] = 'users.profile_image added';
}

// parent_profiles (emergency contact + number of children)
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS parent_profiles (
        id                 INT AUTO_INCREMENT PRIMARY KEY,
        user_id            INT          NOT NULL UNIQUE,
        emergency_contact  VARCHAR(20)  DEFAULT NULL,
        number_of_children INT          DEFAULT 0,
        CONSTRAINT fk_parent_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB"
);
$done[] = 'parent_profiles table ready';

// notifications (Reviews + Notifications + Chat slice)
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS notifications (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        user_id     INT             NOT NULL,
        title       VARCHAR(150)    NOT NULL,
        message     VARCHAR(500)    NOT NULL,
        url         VARCHAR(255)    DEFAULT NULL,
        is_read     TINYINT(1)      NOT NULL DEFAULT 0,
        created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB"
);
$done[] = 'notifications table ready';

// contact_messages (Contact page)
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS contact_messages (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(100)    NOT NULL,
        email       VARCHAR(150)    NOT NULL,
        subject     VARCHAR(150)    DEFAULT NULL,
        message     VARCHAR(2000)   NOT NULL,
        created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB"
);
$done[] = 'contact_messages table ready';

if ($isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Migration complete:\n - " . implode("\n - ", $done) . "\n";
    return;
}

$pageTitle = 'Migration';
require __DIR__ . '/includes/header.php';
?>
<section class="section section-no-top">
    <div class="card stack migrate-wrap">
        <h1 class="heading-tight">Database migration</h1>
        <p class="muted">Migration completed. Results are listed below.</p>
        <div class="grid-gap-8">
            <?php foreach ($done as $item): ?>
                <div class="migrate-row">
                    <span class="migrate-status migrate-status-ok">OK</span>
                    <span class="migrate-sql"><?= e($item) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="hero-cta">
            <a class="btn" href="<?= url('admin/dashboard.php') ?>">← Back to dashboard</a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
