<?php
require_once __DIR__ . '/../config/config.php';
require_role('nanny');

$me = current_user()['id'];

db()->prepare('INSERT IGNORE INTO nanny_profiles (user_id, verification_status) VALUES (?, "pending")')
    ->execute([$me]);

$stmt = db()->prepare('SELECT * FROM nanny_profiles WHERE user_id = ?');
$stmt->execute([$me]);
$p = $stmt->fetch();

// Load portfolio items
$portfolio = [];
try {
    $pf = db()->prepare('SELECT * FROM nanny_portfolio WHERE nanny_id=? ORDER BY created_at DESC');
    $pf->execute([$me]);
    $portfolio = $pf->fetchAll();
} catch (Throwable $e) {}

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'profile';

    if ($action === 'delete_doc') {
        $docId = (int) ($_POST['doc_id'] ?? 0);
        try {
            $doc = db()->prepare('SELECT file_path FROM nanny_portfolio WHERE id=? AND nanny_id=?');
            $doc->execute([$docId, $me]);
            if ($row = $doc->fetch()) {
                @unlink(__DIR__ . '/../' . $row['file_path']);
                db()->prepare('DELETE FROM nanny_portfolio WHERE id=?')->execute([$docId]);
                flash('Document removed.');
            }
        } catch (Throwable $e) {}
        redirect('nanny/profile.php');
    }

    if ($action === 'upload_doc') {
        $title   = trim($_POST['doc_title'] ?? '');
        $docType = $_POST['doc_type'] ?? 'certificate';
        if ($title && isset($_FILES['doc_file'])) {
            $up = save_uploaded_image($_FILES['doc_file'], 'uploads/docs');
            if ($up['ok']) {
                try {
                    db()->prepare(
                        'INSERT INTO nanny_portfolio (nanny_id, type, title, file_path) VALUES (?,?,?,?)'
                    )->execute([$me, $docType, $title, $up['path']]);
                    flash('Document uploaded.');
                } catch (Throwable $e) { flash('Could not save document.', 'error'); }
            } else {
                flash($up['error'] ?? 'Upload failed.', 'error');
            }
        }
        redirect('nanny/profile.php');
    }

    // Main profile save
    $bio        = trim($_POST['bio'] ?? '');
    $experience = (int) ($_POST['experience_years'] ?? 0);
    $rate       = (float) ($_POST['hourly_rate'] ?? 0);
    $location   = trim($_POST['location'] ?? '');
    $skills     = trim($_POST['skills'] ?? '');
    $avail      = trim($_POST['availability'] ?? 'Weekdays');
    $languages  = trim($_POST['languages'] ?? 'English');
    $quals      = trim($_POST['qualifications'] ?? '');
    $specs      = trim($_POST['specialisations'] ?? '');
    $gender     = in_array($_POST['gender'] ?? '', ['male','female','non-binary','prefer_not_to_say'])
                    ? $_POST['gender'] : null;
    $dob        = $_POST['date_of_birth'] ?? '';

    // Photo upload
    $newPhoto = null;
    $upPhoto = save_uploaded_image($_FILES['photo'] ?? [], 'uploads/nannies');
    if ($upPhoto['ok']) $newPhoto = $upPhoto['path'];
    elseif (!($upPhoto['skip'] ?? false)) { flash($upPhoto['error'], 'error'); redirect('nanny/profile.php'); }

    // Banner upload
    $newBanner = null;
    $upBanner = save_uploaded_image($_FILES['banner'] ?? [], 'uploads/banners');
    if ($upBanner['ok']) $newBanner = $upBanner['path'];
    elseif (!($upBanner['skip'] ?? false)) { flash($upBanner['error'], 'error'); redirect('nanny/profile.php'); }

    $sql = 'UPDATE nanny_profiles
            SET bio=?, experience_years=?, hourly_rate=?, location=?,
                skills=?, availability=?, languages=?, qualifications=?, specialisations=?,
                gender=?, date_of_birth=?,
                verification_status = IF(verification_status="verified","pending",verification_status)';
    $params = [$bio, $experience, $rate, $location, $skills, $avail, $languages, $quals ?: null, $specs ?: null, $gender, $dob ?: null];
    if ($newPhoto) { $sql .= ', photo_url=?';   $params[] = $newPhoto; }
    if ($newBanner) { $sql .= ', banner_image=?'; $params[] = $newBanner; }
    $sql .= ' WHERE user_id=?';
    $params[] = $me;
    db()->prepare($sql)->execute($params);

    // Sync photo to users table too if uploaded
    if ($newPhoto) {
        db()->prepare('UPDATE users SET profile_image=? WHERE id=?')->execute([$newPhoto, $me]);
    }

    flash('Profile saved. An admin will re-verify your changes before you appear in search results.');
    redirect('nanny/profile.php');
}

// Completeness score
$fields = ['bio','experience_years','hourly_rate','location','skills','availability','languages'];
$filled = array_filter($fields, fn($f) => !empty($p[$f]));
if ($p['photo_url']) $filled[] = 'photo';
if ($p['banner_image']) $filled[] = 'banner';
$complete = (int)(count($filled) / (count($fields) + 2) * 100);

$pageTitle = 'My Profile';
require __DIR__ . '/../includes/header.php';
?>
<div class="section-head">
    <div>
        <p class="h-eyebrow">Professional</p>
        <h1>My nanny profile</h1>
        <div class="muted">Status: <?= status_badge($p['verification_status']) ?>
            <?php if ($p['verification_status'] !== 'verified'): ?>
                — An admin will review and verify your profile.
            <?php endif; ?>
        </div>
    </div>
    <a class="btn" href="<?= url('nanny/dashboard.php') ?>">← Dashboard</a>
</div>

<?php if ($complete < 80): ?>
<div class="profile-complete-bar profile-complete-gap">
    <div class="profile-complete-main">
        <div class="profile-complete-title">Profile <?= $complete ?>% complete — fill in more to attract parents</div>
        <div class="pc-progress"><div class="pc-fill" data-width-pct="<?= $complete ?>"></div></div>
    </div>
    <span class="pc-label"><?= 100 - $complete ?>% remaining</span>
</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="profile">

    <div class="profile-grid">
        <!-- ---- Images ---- -->
        <div class="card stack">
            <h2 class="heading-tight">Photos</h2>

            <?php if ($p['banner_image']): ?>
                <div class="banner-wrap">
                    <img src="<?= e(url($p['banner_image'])) ?>" alt="Banner">
                </div>
            <?php else: ?>
                <div class="banner-empty">
                    No banner image yet
                </div>
            <?php endif; ?>

            <div class="form-grid-2">
                <div class="field field-zero">
                    <label>Banner image (1200×300 recommended)</label>
                    <input type="file" name="banner" accept="image/*">
                </div>
                <div class="field field-zero">
                    <label>Profile photo</label>
                    <input type="file" name="photo" accept="image/*">
                </div>
            </div>
        </div>

        <!-- ---- Personal ---- -->
        <div class="card stack">
            <h2 class="heading-tight">Personal details</h2>
            <div class="form-grid-2">
                <div class="field field-zero">
                    <label>Gender</label>
                    <select name="gender">
                        <option value="">Prefer not to say</option>
                        <?php foreach (['male'=>'Male','female'=>'Female','non-binary'=>'Non-binary','prefer_not_to_say'=>'Prefer not to say'] as $v=>$l): ?>
                            <option value="<?= $v ?>" <?= ($p['gender']??'') === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field field-zero">
                    <label>Date of birth</label>
                    <input type="date" name="date_of_birth" value="<?= e($p['date_of_birth'] ?? '') ?>">
                </div>
            </div>
        </div>

        <!-- ---- Professional ---- -->
        <div class="card stack">
            <h2 class="heading-tight">Professional information</h2>

            <div class="field">
                <label>About me <span class="muted">(tell parents your story)</span></label>
                <textarea name="bio" rows="5" placeholder="Share your childcare philosophy, experience highlights, and what makes you unique…"><?= e($p['bio'] ?? '') ?></textarea>
            </div>

            <div class="form-grid-2">
                <div class="field field-zero">
                    <label>Years of experience</label>
                    <input type="number" name="experience_years" min="0" value="<?= e((string)($p['experience_years'] ?? 0)) ?>">
                </div>
                <div class="field field-zero">
                    <label>Hourly rate (R)</label>
                    <input type="number" name="hourly_rate" min="0" step="0.5" value="<?= e((string)($p['hourly_rate'] ?? 0)) ?>">
                </div>
                <div class="field field-zero">
                    <label>Location / area</label>
                    <input name="location" value="<?= e($p['location'] ?? '') ?>" placeholder="e.g. Soweto, Johannesburg">
                </div>
                <div class="field field-zero">
                    <label>Availability</label>
                    <select name="availability">
                        <?php foreach (['Weekdays','Weekends','Flexible','Full-time','Part-time','Evenings','Overnight'] as $a): ?>
                            <option <?= ($p['availability']??'') === $a ? 'selected' : '' ?>><?= $a ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="field">
                <label>Skills <span class="muted">(comma separated)</span></label>
                <input name="skills" value="<?= e($p['skills'] ?? '') ?>" placeholder="Newborn care, Cooking, Tutoring, First Aid, Homework help">
            </div>

            <div class="field">
                <label>Specialisations <span class="muted">(comma separated)</span></label>
                <input name="specialisations" value="<?= e($p['specialisations'] ?? '') ?>"
                       placeholder="Newborn care, Toddlers, School children, Special needs, Homework assistance">
            </div>

            <div class="field">
                <label>Languages spoken <span class="muted">(comma separated)</span></label>
                <input name="languages" value="<?= e($p['languages'] ?? 'English') ?>"
                       placeholder="English, isiZulu, Sesotho, Afrikaans">
            </div>

            <div class="field">
                <label>Qualifications &amp; certifications <span class="muted">(comma separated)</span></label>
                <input name="qualifications" value="<?= e($p['qualifications'] ?? '') ?>"
                       placeholder="Childcare Diploma, First Aid Certified, Background Checked, ECE Certificate">
            </div>
        </div>

        <button class="btn btn-primary btn-min-200">Save profile</button>
    </div>
</form>

<!-- Portfolio / Documents -->
<div class="card stack card-gap-top">
    <div class="section-head section-head-tight">
        <h2 class="heading-tight">Documents &amp; portfolio</h2>
    </div>

    <?php if ($portfolio): ?>
    <div class="doc-grid">
        <?php foreach ($portfolio as $doc): ?>
        <div class="card doc-card">
            <div class="doc-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
            <div class="doc-info">
                <h4><?= e($doc['title']) ?></h4>
                <div class="muted"><?= ucfirst($doc['type']) ?></div>
                <?php if ($doc['admin_verified']): ?>
                    <div class="doc-verified">✓ Admin verified</div>
                <?php else: ?>
                    <div class="muted doc-pending">Pending verification</div>
                <?php endif; ?>
                <form method="post" class="doc-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_doc">
                    <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                    <button class="btn btn-sm btn-danger doc-remove-btn" data-confirm="Remove this document?">Remove</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <hr class="hr-line">
    <h3 class="heading-tight">Upload a document</h3>
    <form method="post" enctype="multipart/form-data" class="upload-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload_doc">
        <div class="field field-zero">
            <label>Document title</label>
            <input name="doc_title" placeholder="e.g. First Aid Certificate" required>
        </div>
        <div class="field field-zero">
            <label>Type</label>
            <select name="doc_type">
                <option value="certificate">Certificate</option>
                <option value="id">ID document</option>
                <option value="reference">Reference letter</option>
                <option value="photo">Photo</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div class="field field-zero">
            <label>File (image, max 2 MB)</label>
            <input type="file" name="doc_file" accept="image/*" required>
        </div>
        <button class="btn btn-primary btn-upload-wide">Upload document</button>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
