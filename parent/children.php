<?php
require_once __DIR__ . '/../config/config.php';
require_role('parent');

$me = current_user()['id'];

// Handle add / edit / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $childId = (int) ($_POST['child_id'] ?? 0);
        try {
            $chk = db()->prepare('SELECT id FROM children WHERE id=? AND parent_id=?');
            $chk->execute([$childId, $me]);
            if ($chk->fetch()) {
                db()->prepare('DELETE FROM children WHERE id=?')->execute([$childId]);
                flash('Child profile removed.');
            }
        } catch (Throwable $e) {
            flash('Could not remove child profile. Please run database migrations first.', 'error');
        }
        redirect('parent/children.php');
    }

    // add / edit
    $childId = (int) ($_POST['child_id'] ?? 0);
    $name    = trim($_POST['name'] ?? '');
    if ($name === '') { flash('Child name is required.', 'error'); redirect('parent/children.php'); }

    $data = [
        'name'                => $name,
        'age'                 => $_POST['age'] !== '' ? (int)$_POST['age'] : null,
        'gender'              => in_array($_POST['gender'] ?? '', ['male','female','other']) ? $_POST['gender'] : null,
        'allergies'           => trim($_POST['allergies'] ?? '') ?: null,
        'medical_conditions'  => trim($_POST['medical_conditions'] ?? '') ?: null,
        'special_needs'       => trim($_POST['special_needs'] ?? '') ?: null,
        'favourite_activities'=> trim($_POST['favourite_activities'] ?? '') ?: null,
        'notes_for_nannies'   => trim($_POST['notes_for_nannies'] ?? '') ?: null,
    ];

    try {
        if ($childId > 0) {
            $chk = db()->prepare('SELECT id FROM children WHERE id=? AND parent_id=?');
            $chk->execute([$childId, $me]);
            if ($chk->fetch()) {
                db()->prepare(
                    'UPDATE children SET name=?,age=?,gender=?,allergies=?,medical_conditions=?,special_needs=?,favourite_activities=?,notes_for_nannies=?
                     WHERE id=?'
                )->execute([...$data, $childId]);
                db()->prepare('UPDATE parent_profiles SET number_of_children=(SELECT COUNT(*) FROM children WHERE parent_id=?) WHERE user_id=?')
                    ->execute([$me, $me]);
                flash('Child profile updated.');
            }
        } else {
            db()->prepare(
                'INSERT INTO children (parent_id,name,age,gender,allergies,medical_conditions,special_needs,favourite_activities,notes_for_nannies)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute([$me, ...$data]);
            db()->prepare('UPDATE parent_profiles SET number_of_children=(SELECT COUNT(*) FROM children WHERE parent_id=?) WHERE user_id=?')
                ->execute([$me, $me]);
            flash('Child profile added.');
        }
    } catch (Throwable $e) {
        flash('Could not save child profile. Please run database migrations first.', 'error');
    }
    redirect('parent/children.php');
}

$children = [];
try {
    $stmt = db()->prepare('SELECT * FROM children WHERE parent_id=? ORDER BY name');
    $stmt->execute([$me]);
    $children = $stmt->fetchAll();
} catch (Throwable $e) {
    $children = [];
}

// Edit mode
$editing = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = db()->prepare('SELECT * FROM children WHERE id=? AND parent_id=?');
        $stmt->execute([(int)$_GET['edit'], $me]);
        $editing = $stmt->fetch();
    } catch (Throwable $e) {}
}

$pageTitle = 'My Children';
require __DIR__ . '/../includes/header.php';

$genderEmoji = ['male' => '👦', 'female' => '👧', 'other' => '🧒', '' => '🧒', null => '🧒'];
?>
<div class="section-head">
    <div>
        <p class="h-eyebrow">Family</p>
        <h1>My children</h1>
        <p class="muted">Keep children's profiles up to date so nannies know exactly how to care for them.</p>
    </div>
    <a class="btn btn-primary" href="#add-form">+ Add child</a>
</div>

<?php if ($children): ?>
<div class="child-cards section">
    <?php foreach ($children as $c): ?>
    <div class="card child-card">
        <div class="child-avatar <?= $c['gender'] === 'female' ? 'girl' : 'boy' ?>"><?= $genderEmoji[$c['gender']] ?? '🧒' ?></div>
        <div class="child-info child-info-main">
            <h3><?= e($c['name']) ?></h3>
            <div class="muted"><?= $c['age'] ? $c['age'] . ' years old' : 'Age not set' ?><?= $c['gender'] ? ' · ' . ucfirst($c['gender']) : '' ?></div>
            <div class="child-tags">
                <?php if ($c['allergies']): ?><span class="tag tag-bad">Allergies</span><?php endif; ?>
                <?php if ($c['medical_conditions']): ?><span class="tag tag-warn">Medical</span><?php endif; ?>
                <?php if ($c['special_needs']): ?><span class="tag">Special needs</span><?php endif; ?>
                <?php if ($c['favourite_activities']): ?><span class="tag"><?= e(explode(',', $c['favourite_activities'])[0]) ?></span><?php endif; ?>
            </div>
            <?php if ($c['notes_for_nannies']): ?>
                <p class="muted child-note">📝 <?= e(substr($c['notes_for_nannies'], 0, 100)) ?><?= strlen($c['notes_for_nannies']) > 100 ? '…' : '' ?></p>
            <?php endif; ?>
            <div class="child-actions">
                <a class="btn btn-sm" href="?edit=<?= $c['id'] ?>#add-form">Edit</a>
                <form method="post" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="child_id" value="<?= $c['id'] ?>">
                    <button class="btn btn-sm btn-danger" data-confirm="Remove <?= e($c['name']) ?>'s profile?">Remove</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty">
    <div class="empty-ico">👶</div>
    <h3>No children added yet</h3>
    <p>Add your children's profiles so nannies have all the information they need.</p>
</div>
<?php endif; ?>

<!-- Add / Edit Form -->
<div class="card stack card-form-shell" id="add-form">
    <h2><?= $editing ? 'Edit ' . e($editing['name']) : 'Add a child' ?></h2>
    <form method="post" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editing ? 'edit' : 'add' ?>">
        <?php if ($editing): ?><input type="hidden" name="child_id" value="<?= $editing['id'] ?>"><?php endif; ?>

        <div class="form-grid-2">
            <div class="field field-zero field-span-all">
                <label>Child's name *</label>
                <input name="name" value="<?= e($editing['name'] ?? '') ?>" required placeholder="First name">
            </div>
            <div class="field field-zero">
                <label>Age</label>
                <input type="number" name="age" min="0" max="18" value="<?= e((string)($editing['age'] ?? '')) ?>">
            </div>
            <div class="field field-zero">
                <label>Gender</label>
                <select name="gender">
                    <option value="">Not specified</option>
                    <option value="male"   <?= ($editing['gender'] ?? '') === 'male'   ? 'selected' : '' ?>>Boy</option>
                    <option value="female" <?= ($editing['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Girl</option>
                    <option value="other"  <?= ($editing['gender'] ?? '') === 'other'  ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            <div class="field field-zero field-span-all">
                <label>Allergies <span class="muted">(food, environment, medicine)</span></label>
                <input name="allergies" value="<?= e($editing['allergies'] ?? '') ?>" placeholder="e.g. Peanuts, Dust, Penicillin">
            </div>
            <div class="field field-zero field-span-all">
                <label>Medical conditions</label>
                <input name="medical_conditions" value="<?= e($editing['medical_conditions'] ?? '') ?>" placeholder="e.g. Asthma, Epilepsy">
            </div>
            <div class="field field-zero field-span-all">
                <label>Special needs / learning differences</label>
                <input name="special_needs" value="<?= e($editing['special_needs'] ?? '') ?>" placeholder="e.g. ADHD, ASD, Hearing impairment">
            </div>
            <div class="field field-zero field-span-all">
                <label>Favourite activities</label>
                <input name="favourite_activities" value="<?= e($editing['favourite_activities'] ?? '') ?>" placeholder="e.g. Drawing, Swimming, Reading">
            </div>
            <div class="field field-zero field-span-all">
                <label>Notes for nannies</label>
                <textarea name="notes_for_nannies" rows="4" placeholder="Routine, bedtime, food preferences, anything the nanny should know..."><?= e($editing['notes_for_nannies'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="grid-actions">
            <button class="btn btn-primary"><?= $editing ? 'Save changes' : 'Add child' ?></button>
            <?php if ($editing): ?><a class="btn" href="<?= url('parent/children.php') ?>">Cancel</a><?php endif; ?>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
