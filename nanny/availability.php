<?php
require_once __DIR__ . '/../config/config.php';
require_role('nanny');

$me = current_user()['id'];
$days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pdo = db();
    for ($d = 0; $d < 7; $d++) {
        $isAvail = isset($_POST['avail_' . $d]) ? 1 : 0;
        $start   = $_POST['start_' . $d] ?? '08:00';
        $end     = $_POST['end_' . $d] ?? '18:00';
        $pdo->prepare(
            'INSERT INTO nanny_availability (nanny_id, day_of_week, is_available, time_start, time_end)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE is_available=VALUES(is_available),
                                     time_start=VALUES(time_start),
                                     time_end=VALUES(time_end)'
        )->execute([$me, $d, $isAvail, $start . ':00', $end . ':00']);
    }
    // Mirror to legacy text field
    $openDays = [];
    for ($d = 0; $d < 7; $d++) {
        if (isset($_POST['avail_' . $d])) $openDays[] = $days[$d];
    }
    $legacyText = $openDays ? implode(', ', $openDays) : 'Unavailable';
    db()->prepare('UPDATE nanny_profiles SET availability=? WHERE user_id=?')->execute([$legacyText, $me]);

    flash('Availability updated.');
    redirect('nanny/availability.php');
}

// Load current availability
$avail = [];
try {
    $av = db()->prepare('SELECT * FROM nanny_availability WHERE nanny_id=? ORDER BY day_of_week');
    $av->execute([$me]);
    foreach ($av->fetchAll() as $row) $avail[$row['day_of_week']] = $row;
} catch (Throwable $e) {}

$pageTitle = 'My Availability';
require __DIR__ . '/../includes/header.php';
?>
<div class="section-head">
    <div>
        <p class="h-eyebrow">Schedule</p>
        <h1>Weekly availability</h1>
        <p class="muted">Set your working days and hours so parents can plan ahead.</p>
    </div>
    <a class="btn" href="<?= url('nanny/dashboard.php') ?>">← Dashboard</a>
</div>

<form method="post" class="availability-shell">
    <?= csrf_field() ?>
    <div class="card stack">
        <div class="avail-editor">
            <?php for ($d = 0; $d < 7; $d++):
                $row      = $avail[$d] ?? null;
                $checked  = $row ? (bool)$row['is_available'] : ($d >= 1 && $d <= 5);
                $tStart   = $row ? substr($row['time_start'], 0, 5) : '08:00';
                $tEnd     = $row ? substr($row['time_end'],   0, 5) : '18:00';
            ?>
            <div class="avail-row <?= $checked ? '' : 'unavail' ?>">
                <span class="avail-day-name-cell"><?= $days[$d] ?></span>

                <label class="avail-toggle">
                    <input type="checkbox" name="avail_<?= $d ?>" <?= $checked ? 'checked' : '' ?>>
                    <span class="avail-slider"></span>
                </label>

                <div class="avail-time">From</div>
                <div class="field field-zero">
                    <input type="time" name="start_<?= $d ?>" value="<?= e($tStart) ?>" <?= $checked ? '' : 'disabled' ?>>
                </div>
                <div class="avail-time">To</div>
                <div class="field field-zero">
                    <input type="time" name="end_<?= $d ?>" value="<?= e($tEnd) ?>" <?= $checked ? '' : 'disabled' ?>>
                </div>
            </div>
            <?php endfor; ?>
        </div>

        <div class="flash flash-info info-note-sm">
            Changes to your availability will show on your public profile immediately.
            Verifying nannies are still shown to parents.
        </div>

        <div class="actions-inline">
            <button class="btn btn-primary">Save availability</button>
            <a class="btn" href="<?= url('nanny/profile.php') ?>">Edit profile</a>
        </div>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
