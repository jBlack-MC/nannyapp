<?php
require_once __DIR__ . '/config/config.php';
require_login();

$me     = current_user();
$role   = $me['role'];
$isParent = $role === 'parent';
$isNanny  = $role === 'nanny';

// Load role-specific extras
$pp = ['emergency_contact' => '', 'emergency_contact_name' => '', 'emergency_contact_relationship' => '', 'number_of_children' => 0];
if ($isParent) {
    $s = db()->prepare('SELECT * FROM parent_profiles WHERE user_id=?');
    $s->execute([$me['id']]);
    if ($row = $s->fetch()) $pp = $row;
}

$errors = [];
$old = [
    'full_name'       => $me['full_name'],
    'email'           => $me['email'],
    'phone'           => $me['phone'] ?? '',
    'date_of_birth'   => $me['date_of_birth'] ?? '',
    'address'         => $me['address'] ?? '',
    'gender'          => $me['gender'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old['full_name']     = trim($_POST['full_name'] ?? '');
    $old['email']         = trim($_POST['email'] ?? '');
    $old['phone']         = trim($_POST['phone'] ?? '');
    $old['date_of_birth'] = trim($_POST['date_of_birth'] ?? '');
    $old['address']       = trim($_POST['address'] ?? '');
    $old['gender']        = $_POST['gender'] ?? '';
    $newPassword          = $_POST['password'] ?? '';
    $currentPassword      = $_POST['current_password'] ?? '';

    if ($old['full_name'] === '')                          $errors[] = 'Full name is required.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if ($newPassword !== '') {
        if ($currentPassword === '')                       $errors[] = 'Enter your current password to set a new one.';
        elseif (!password_verify($currentPassword, $me['password_hash']))
                                                           $errors[] = 'Current password is incorrect.';
        elseif (strlen($newPassword) < 8)                 $errors[] = 'New password must be at least 8 characters.';
    }

    if (!$errors) {
        $chk = db()->prepare('SELECT 1 FROM users WHERE email=? AND id<>?');
        $chk->execute([$old['email'], $me['id']]);
        if ($chk->fetch()) $errors[] = 'That email is already in use by another account.';
    }

    $newImage = null;
    if (!$errors) {
        $up = save_uploaded_image($_FILES['avatar'] ?? [], 'uploads');
        if (!($up['ok'] || ($up['skip'] ?? false))) {
            $errors[] = $up['error'];
        } elseif ($up['ok']) {
            $newImage = $up['path'];
        }
    }

    if (!$errors) {
        $sql    = 'UPDATE users SET full_name=?, email=?, phone=?, date_of_birth=?, address=?, gender=?';
        $params = [
            $old['full_name'], $old['email'],
            $old['phone'] ?: null,
            $old['date_of_birth'] ?: null,
            $old['address']  ?: null,
            in_array($old['gender'], ['male','female','non-binary','prefer_not_to_say']) ? $old['gender'] : null,
        ];
        if ($newPassword !== '') { $sql .= ', password_hash=?'; $params[] = password_hash($newPassword, PASSWORD_DEFAULT); }
        if ($newImage !== null)  { $sql .= ', profile_image=?';  $params[] = $newImage; }
        $sql .= ' WHERE id=?';
        $params[] = $me['id'];
        db()->prepare($sql)->execute($params);

        if ($isParent) {
            db()->prepare(
                'INSERT INTO parent_profiles (user_id, emergency_contact, emergency_contact_name, emergency_contact_relationship, number_of_children)
                 VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                     emergency_contact=VALUES(emergency_contact),
                     emergency_contact_name=VALUES(emergency_contact_name),
                     emergency_contact_relationship=VALUES(emergency_contact_relationship),
                     number_of_children=VALUES(number_of_children)'
            )->execute([
                $me['id'],
                trim($_POST['emergency_contact'] ?? '') ?: null,
                trim($_POST['emergency_contact_name'] ?? '') ?: null,
                trim($_POST['emergency_contact_relationship'] ?? '') ?: null,
                (int) ($_POST['number_of_children'] ?? 0),
            ]);
        }

        flash('Your settings have been saved.');
        redirect('account.php');
    }
}

// Compute profile completeness %
$fields = ['full_name','email','phone','date_of_birth','address','gender'];
$filled = array_filter($fields, fn($f) => !empty($old[$f]));
if ($me['profile_image']) $filled[] = 'photo';
$completeness = (int) (count($filled) / (count($fields) + 1) * 100);

$pageTitle = 'Account Settings';
require __DIR__ . '/includes/header.php';
?>
<div class="mw-700">
    <?php if ($completeness < 100): ?>
    <div class="profile-complete-bar">
        <div>
            <div class="text-compact-strong">Profile <?= $completeness ?>% complete</div>
            <div class="pc-progress"><div class="pc-fill" data-width-pct="<?= $completeness ?>"></div></div>
        </div>
        <span class="pc-label"><?= 100 - $completeness ?>% remaining</span>
    </div>
    <?php endif; ?>

    <div class="card stack">
        <div class="flex-wrap-row">
            <div class="rel">
                <?= avatar($me['full_name'], $me['profile_image'] ?? null, 'avatar-lg') ?>
            </div>
            <div>
                <strong class="name-strong"><?= e($me['full_name']) ?></strong>
                <div class="muted small-muted">
                    <?= ucfirst($role) ?> account
                    <?php if ($role === 'nanny'): ?>
                        · <a href="<?= url('nanny/profile.php') ?>">Edit professional profile →</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>

        <form method="post" enctype="multipart/form-data" class="stack">
            <?= csrf_field() ?>

            <div class="form-grid-2">
                <div class="field field-tight">
                    <label for="a-name">Full name *</label>
                    <input id="a-name" name="full_name" value="<?= e($old['full_name']) ?>" required>
                </div>
                <div class="field field-tight">
                    <label for="a-email">Email *</label>
                    <input id="a-email" type="email" name="email" value="<?= e($old['email']) ?>" required>
                </div>
                <div class="field field-tight">
                    <label for="a-phone">Phone number</label>
                    <input id="a-phone" name="phone" value="<?= e($old['phone']) ?>" placeholder="+27 67 123 4567">
                </div>
                <div class="field field-tight">
                    <label for="a-dob">Date of birth</label>
                    <input id="a-dob" type="date" name="date_of_birth" value="<?= e($old['date_of_birth']) ?>">
                </div>
                <div class="field field-tight field-span-full">
                    <label for="a-address">Address</label>
                    <input id="a-address" name="address" value="<?= e($old['address']) ?>" placeholder="Street, City, Province">
                </div>
                <div class="field field-tight">
                    <label for="a-gender">Gender</label>
                    <select id="a-gender" name="gender">
                        <option value="">Prefer not to say</option>
                        <option value="male"   <?= $old['gender']==='male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= $old['gender']==='female' ? 'selected' : '' ?>>Female</option>
                        <option value="non-binary" <?= $old['gender']==='non-binary' ? 'selected' : '' ?>>Non-binary</option>
                        <option value="prefer_not_to_say" <?= $old['gender']==='prefer_not_to_say' ? 'selected' : '' ?>>Prefer not to say</option>
                    </select>
                </div>
                <div class="field field-tight">
                    <label for="a-photo">Profile photo (JPG/PNG/WEBP, max 2 MB)</label>
                    <input id="a-photo" type="file" name="avatar" accept="image/*" data-preview="avatarPreview">
                </div>
            </div>

            <?php if ($isParent): ?>
            <hr class="rule">
            <h3 class="h3-tight">Emergency contact</h3>
            <div class="form-grid-2">
                <div class="field field-tight">
                    <label>Contact name</label>
                    <input name="emergency_contact_name" value="<?= e($pp['emergency_contact_name'] ?? '') ?>" placeholder="Full name">
                </div>
                <div class="field field-tight">
                    <label>Relationship</label>
                    <input name="emergency_contact_relationship" value="<?= e($pp['emergency_contact_relationship'] ?? '') ?>" placeholder="e.g. Spouse, Mother">
                </div>
                <div class="field field-tight">
                    <label>Contact phone</label>
                    <input name="emergency_contact" value="<?= e($pp['emergency_contact'] ?? '') ?>" placeholder="+27 67 000 0000">
                </div>
                <div class="field field-tight">
                    <label>Number of children</label>
                    <input type="number" name="number_of_children" min="0" value="<?= e((string)($pp['number_of_children'] ?? 0)) ?>">
                </div>
            </div>
            <?php endif; ?>

            <hr class="rule">
            <h3 class="h3-tight">Change password</h3>
            <div class="field">
                <label for="a-pw-current">Current password</label>
                <input id="a-pw-current" type="password" name="current_password" autocomplete="current-password" placeholder="Required to change password">
            </div>
            <div class="field">
                <label for="a-pw">New password</label>
                <input id="a-pw" type="password" name="password" minlength="8" autocomplete="new-password" placeholder="Minimum 8 characters">
            </div>

            <div class="actions-wrap">
                <button class="btn btn-primary">Save settings</button>
                <a class="btn" href="<?= url(dashboard_path($role)) ?>">← Dashboard</a>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
