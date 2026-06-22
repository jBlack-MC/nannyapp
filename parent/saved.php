<?php
require_once __DIR__ . '/../config/config.php';
require_role('parent');

$me = current_user()['id'];

$nannies = [];
try {
    $saved = db()->prepare(
        "SELECT u.id, u.full_name, u.profile_image,
                p.bio, p.experience_years, p.hourly_rate, p.location,
                p.skills, p.availability, p.average_rating,
                IFNULL(p.specialisations, '') AS specialisations,
                IFNULL(p.languages, '')       AS languages,
                (SELECT COUNT(*) FROM reviews r WHERE r.nanny_id = u.id) AS review_count,
                sn.created_at AS saved_at
         FROM saved_nannies sn
         JOIN users u ON u.id = sn.nanny_id
         JOIN nanny_profiles p ON p.user_id = u.id
         WHERE sn.parent_id = ? AND p.verification_status = 'verified'
         ORDER BY sn.created_at DESC"
    );
    $saved->execute([$me]);
    $nannies = $saved->fetchAll();
} catch (Throwable $e) {
    $nannies = [];
}
$csrfToken = csrf_token();

$pageTitle = 'Saved Nannies';
require __DIR__ . '/../includes/header.php';
?>
<div class="section-head">
    <div>
        <p class="h-eyebrow">Favourites</p>
        <h1>Saved nannies</h1>
        <p class="muted"><?= count($nannies) ?> nann<?= count($nannies) === 1 ? 'y' : 'ies' ?> saved</p>
    </div>
    <a class="btn btn-primary" href="<?= url('parent/nannies.php') ?>">Find more nannies</a>
</div>

<?php if (!$nannies): ?>
<div class="empty">
    <div class="empty-ico">❤️</div>
    <h3>No saved nannies yet</h3>
    <p>Browse nannies and tap the heart icon to save profiles you love for quick access.</p>
    <a class="btn btn-primary" href="<?= url('parent/nannies.php') ?>">Find a nanny</a>
</div>
<?php else: ?>
<div class="grid grid-3">
    <?php foreach ($nannies as $n):
        $rating  = (float) $n['average_rating'];
        $reviews = (int) $n['review_count'];
        $bookUrl = url('parent/book.php?nanny=' . (int)$n['id']);
    ?>
    <div class="card nanny-card">
        <div class="nanny-top">
            <div class="pn-head-main">
                <?= avatar($n['full_name'], $n['profile_image'] ?? null, 'avatar-lg') ?>
                <div class="nanny-id">
                    <h3 class="pn-title"><a href="<?= $bookUrl ?>"><?= e($n['full_name']) ?></a></h3>
                    <div class="muted pn-loc"><?= e($n['location'] ?: 'Location N/A') ?></div>
                    <div class="rating-line">
                        <span class="stars"><?php for ($i=1;$i<=5;$i++): ?><span class="<?= $i<=round($rating)?'':'off' ?>">★</span><?php endfor; ?></span>
                        <?php if ($reviews > 0): ?>
                            <span><?= number_format($rating,1) ?> (<?= $reviews ?>)</span>
                        <?php else: ?><span class="badge-new">New</span><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="rate">R<?= number_format((float)$n['hourly_rate'],0) ?><div class="muted pn-rate-unit">/hr</div></div>
        </div>
        <div class="nanny-meta">
            <span class="verified-badge">✓ Verified</span>
            <span><?= (int)$n['experience_years'] ?>+ yrs</span>
            <?php if ($n['languages']): ?><span><?= e(explode(',', $n['languages'])[0]) ?></span><?php endif; ?>
        </div>
        <p class="muted nanny-bio pn-bio"><?= e(substr($n['bio'] ?: 'Caring, reliable childcare.', 0, 110)) ?><?= strlen($n['bio'] ?? '') > 110 ? '…' : '' ?></p>
        <div class="tags">
            <?php foreach (array_slice(array_filter(explode(',', (string)$n['skills'])), 0, 3) as $skill): ?>
                <span class="tag"><?= e(trim($skill)) ?></span>
            <?php endforeach; ?>
        </div>
        <div class="muted saved-date">Saved <?= date('d M Y', strtotime($n['saved_at'])) ?></div>
        <div class="nanny-actions">
            <button class="save-btn saved" data-save-nanny="<?= $n['id'] ?>" data-base="<?= BASE_URL ?>" data-csrf="<?= e($csrfToken) ?>">
                <svg viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 21.593c-5.63-5.539-11-10.297-11-14.402 0-3.791 3.068-5.191 5.281-5.191 1.312 0 4.151.501 5.719 4.457 1.59-3.968 4.464-4.447 5.726-4.447 2.54 0 5.274 1.621 5.274 5.181 0 4.069-5.136 8.625-11 14.402z"/></svg>
                <span class="save-label">Saved</span>
            </button>
            <a class="btn btn-sm" href="<?= url('messages.php?with=' . (int)$n['id']) ?>">Message</a>
            <a class="btn btn-sm btn-primary" href="<?= $bookUrl ?>">Book now</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
