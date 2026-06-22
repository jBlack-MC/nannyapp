<?php
require_once __DIR__ . '/../config/config.php';

$q        = trim($_GET['q'] ?? '');
$maxRate  = $_GET['max_rate'] ?? '';
$minRate  = $_GET['min_rating'] ?? '';
$minExp   = $_GET['min_exp'] ?? '';
$avail    = trim($_GET['availability'] ?? '');
$lang     = trim($_GET['language'] ?? '');
$spec     = trim($_GET['specialisation'] ?? '');
$sort     = $_GET['sort'] ?? 'rating';

$profileColumns = [];
try {
    $cols = db()->query('SHOW COLUMNS FROM nanny_profiles')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($cols as $c) {
        $profileColumns[strtolower((string) $c)] = true;
    }
} catch (Throwable $e) {
    // Keep defaults below if schema introspection fails.
}

$hasCol = static function (string $name) use ($profileColumns): bool {
    return isset($profileColumns[strtolower($name)]);
};

$colExpr = static function (string $name, string $fallback) use ($hasCol): string {
    return $hasCol($name) ? "p.$name" : $fallback;
};

$sql = "SELECT u.id, u.full_name, u.profile_image,
               " . $colExpr('bio', "''") . " AS bio,
               " . $colExpr('experience_years', '0') . " AS experience_years,
               " . $colExpr('hourly_rate', '0') . " AS hourly_rate,
               " . $colExpr('location', "''") . " AS location,
               " . $colExpr('skills', "''") . " AS skills,
               " . $colExpr('availability', "''") . " AS availability,
               " . $colExpr('average_rating', '0') . " AS average_rating,
               " . $colExpr('languages', "''") . " AS languages,
               " . $colExpr('specialisations', "''") . " AS specialisations,
               " . $colExpr('profile_views', '0') . " AS profile_views,
               (SELECT COUNT(*) FROM reviews r WHERE r.nanny_id = u.id) AS review_count,
               (SELECT COUNT(*) FROM bookings b WHERE b.nanny_id = u.id AND b.status='completed') AS completed_bookings
        FROM nanny_profiles p
        JOIN users u ON u.id = p.user_id
        WHERE 1=1";

if ($hasCol('verification_status')) {
    $sql .= " AND p.verification_status = 'verified'";
}
$params = [];

if ($q !== '') {
    $sql .= ' AND (u.full_name LIKE ? OR p.location LIKE ? OR p.skills LIKE ? OR p.specialisations LIKE ?)';
    $like = "%$q%";
    array_push($params, $like, $like, $like, $like);
}
if (is_numeric($maxRate)) { $sql .= ' AND hourly_rate <= ?'; $params[] = $maxRate; }
if (is_numeric($minRate)) { $sql .= ' AND average_rating >= ?'; $params[] = $minRate; }
if (is_numeric($minExp))  { $sql .= ' AND experience_years >= ?'; $params[] = $minExp; }
if ($avail !== '' && $hasCol('availability'))      { $sql .= ' AND availability LIKE ?'; $params[] = "%$avail%"; }
if ($lang !== '' && $hasCol('languages'))          { $sql .= ' AND languages LIKE ?'; $params[] = "%$lang%"; }
if ($spec !== '' && $hasCol('specialisations'))    { $sql .= ' AND specialisations LIKE ?'; $params[] = "%$spec%"; }

$orders = [
    'rating'    => 'average_rating DESC, hourly_rate ASC',
    'rate_asc'  => 'hourly_rate ASC',
    'rate_desc' => 'hourly_rate DESC',
    'exp'       => 'experience_years DESC',
    'bookings'  => 'completed_bookings DESC',
];

// Pagination — count before adding ORDER BY so the subquery stays lightweight.
$perPage = 12;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$countStmt = db()->prepare("SELECT COUNT(*) FROM ($sql) AS _ct");
$countStmt->execute($params);
$totalNannies = (int) $countStmt->fetchColumn();
$totalPages   = max(1, (int) ceil($totalNannies / $perPage));
$page         = min($page, $totalPages);
$offset       = ($page - 1) * $perPage;

$sql .= ' ORDER BY ' . ($orders[$sort] ?? $orders['rating']) . ' LIMIT ? OFFSET ?';
$stmt = db()->prepare($sql);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$nannies = $stmt->fetchAll();

// Saved set (for logged-in parents)
$savedSet = [];
$csrfToken = csrf_token();
if (is_logged_in() && user_role() === 'parent') {
    try {
        $sv = db()->prepare('SELECT nanny_id FROM saved_nannies WHERE parent_id=?');
        $sv->execute([current_user()['id']]);
        $savedSet = array_column($sv->fetchAll(), 'nanny_id');
    } catch (Throwable $e) {}
}

$pageTitle = 'Find a Nanny';
require __DIR__ . '/../includes/header.php';
?>
<div class="section">
    <p class="h-eyebrow">Verified professionals</p>
    <h1>Find your perfect nanny</h1>

    <!-- Filters -->
    <form class="card pn-filter-card" method="get">
        <div class="pn-filter-row">
            <div class="field field-zero field-flex-2">
                <label>Search name, location, or skill</label>
                <input name="q" value="<?= e($q) ?>" placeholder="e.g. Cape Town, cooking, first aid">
            </div>
            <div class="field field-zero field-w-130">
                <label>Max rate (R/hr)</label>
                <input type="number" name="max_rate" value="<?= e((string)$maxRate) ?>" min="0" placeholder="Any">
            </div>
            <div class="field field-zero field-w-120">
                <label>Min rating</label>
                <select name="min_rating">
                    <option value="">Any</option>
                    <?php foreach ([5,4,3] as $r): ?>
                        <option value="<?= $r ?>" <?= (string)$minRate===(string)$r?'selected':'' ?>><?= $r ?>★+</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field field-zero field-w-120">
                <label>Min experience</label>
                <select name="min_exp">
                    <option value="">Any</option>
                    <?php foreach ([1,3,5,10,15] as $x): ?>
                        <option value="<?= $x ?>" <?= (string)$minExp===(string)$x?'selected':'' ?>><?= $x ?>+ yrs</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field field-zero field-w-130">
                <label>Availability</label>
                <select name="availability">
                    <option value="">Any</option>
                    <?php foreach (['Weekdays','Weekends','Flexible','Full-time','Part-time'] as $a): ?>
                        <option <?= $avail===$a?'selected':'' ?>><?= $a ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field field-zero field-w-120">
                <label>Language</label>
                <input name="language" value="<?= e($lang) ?>" placeholder="e.g. Zulu">
            </div>
            <div class="field field-zero field-w-150">
                <label>Sort by</label>
                <select name="sort">
                    <option value="rating"   <?= $sort==='rating'?'selected':'' ?>>Top rated</option>
                    <option value="rate_asc" <?= $sort==='rate_asc'?'selected':'' ?>>Price: low → high</option>
                    <option value="rate_desc" <?= $sort==='rate_desc'?'selected':'' ?>>Price: high → low</option>
                    <option value="exp"      <?= $sort==='exp'?'selected':'' ?>>Most experienced</option>
                    <option value="bookings" <?= $sort==='bookings'?'selected':'' ?>>Most booked</option>
                </select>
            </div>
            <div class="pn-filter-actions">
                <button class="btn btn-primary">Search</button>
                <a class="btn" href="<?= url('parent/nannies.php') ?>">Clear</a>
            </div>
        </div>
    </form>
</div>

<?php if (!$nannies): ?>
    <div class="empty">
        <div class="empty-ico">🔍</div>
        <h3>No nannies match your search</h3>
        <p>Try broadening your filters or <a href="<?= url('parent/nannies.php') ?>">view all nannies</a>.</p>
    </div>
<?php else: ?>
    <p class="muted pn-results"><?= $totalNannies ?> verified <?= $totalNannies === 1 ? 'nanny' : 'nannies' ?> available</p>
    <div class="grid grid-3">
        <?php foreach ($nannies as $n):
            $rating  = (float) $n['average_rating'];
            $reviews = (int) $n['review_count'];
            $bookUrl = url('parent/book.php?nanny=' . (int)$n['id']);
            $isSaved = in_array($n['id'], $savedSet);
        ?>
        <div class="card nanny-card reveal-item">
            <div class="nanny-top">
                <div class="pn-head-main">
                    <?= avatar($n['full_name'], $n['profile_image'] ?? null, 'avatar-lg') ?>
                    <div class="nanny-id">
                        <h3 class="pn-title"><a href="<?= $bookUrl ?>"><?= e($n['full_name']) ?></a></h3>
                        <div class="muted pn-loc"><?= e($n['location'] ?: 'Location N/A') ?></div>
                        <div class="rating-line">
                            <span class="stars"><?php for($i=1;$i<=5;$i++): ?><span class="<?=$i<=round($rating)?'':'off'?>">★</span><?php endfor; ?></span>
                            <?php if ($reviews>0): ?><span><?= number_format($rating,1) ?> (<?= $reviews ?>)</span>
                            <?php else: ?><span class="badge-new">New</span><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="pn-rate-wrap">
                    <div class="rate">R<?= number_format((float)$n['hourly_rate'],0) ?></div>
                    <div class="muted pn-rate-unit">per hour</div>
                </div>
            </div>

            <div class="nanny-meta">
                <span class="verified-badge">✓ Verified</span>
                <span class="avail-dot"><?= e($n['availability'] ?: 'Flexible') ?></span>
                <span><?= (int)$n['experience_years'] ?>+ yrs</span>
                <?php if ($n['languages']): ?><span><?= e(explode(',', $n['languages'])[0]) ?></span><?php endif; ?>
            </div>

            <?php if ($n['specialisations']): ?>
            <div class="nanny-meta pn-meta-tight">
                <?php foreach (array_slice(explode(',', $n['specialisations']), 0, 2) as $sp): ?>
                    <span class="pn-pill"><?= e(trim($sp)) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <p class="muted nanny-bio pn-bio"><?= e(substr($n['bio'] ?: 'Caring, reliable childcare tailored to your family\'s needs.', 0, 110)) ?><?= strlen($n['bio']??'')>110?'…':'' ?></p>

            <div class="tags">
                <?php foreach (array_slice(array_filter(array_map('trim', explode(',', (string)$n['skills']))), 0, 4) as $skill): ?>
                    <span class="tag"><?= e($skill) ?></span>
                <?php endforeach; ?>
            </div>

            <div class="nanny-actions">
                <?php if (is_logged_in() && user_role() === 'parent'): ?>
                <button class="save-btn <?= $isSaved ? 'saved' : '' ?>"
                        data-save-nanny="<?= $n['id'] ?>"
                        data-base="<?= BASE_URL ?>"
                        data-csrf="<?= e($csrfToken) ?>"
                        aria-label="<?= $isSaved ? 'Unsave' : 'Save' ?> <?= e($n['full_name']) ?>">
                    <svg viewBox="0 0 24 24" fill="<?= $isSaved ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2"><path d="M12 21.593c-5.63-5.539-11-10.297-11-14.402 0-3.791 3.068-5.191 5.281-5.191 1.312 0 4.151.501 5.719 4.457 1.59-3.968 4.464-4.447 5.726-4.447 2.54 0 5.274 1.621 5.274 5.181 0 4.069-5.136 8.625-11 14.402z"/></svg>
                    <span class="save-label"><?= $isSaved ? 'Saved' : 'Save' ?></span>
                </button>
                <?php endif; ?>
                <a class="btn btn-sm" href="<?= $bookUrl ?>">View Profile</a>
                <a class="btn btn-sm btn-primary" href="<?= url('parent/book.php?nanny=' . (int)$n['id'] . '&step=1') ?>">Book Now</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1):
        $pagerParams = array_filter([
            'q'              => $q,
            'max_rate'       => $maxRate,
            'min_rating'     => $minRate,
            'min_exp'        => $minExp,
            'availability'   => $avail,
            'language'       => $lang,
            'specialisation' => $spec,
            'sort'           => $sort !== 'rating' ? $sort : '',
        ]);
        $pagerBase = url('parent/nannies.php?' . http_build_query($pagerParams));
    ?>
    <nav class="pagination" aria-label="Search results pages">
        <?php if ($page > 1): ?>
            <a class="btn btn-sm" href="<?= $pagerBase ?>&page=<?= $page - 1 ?>">&#8592; Prev</a>
        <?php endif; ?>
        <span class="pager-info">Page <?= $page ?> of <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
            <a class="btn btn-sm" href="<?= $pagerBase ?>&page=<?= $page + 1 ?>">Next &#8594;</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
