<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$users = db()->query(
    "SELECT
        SUM(role='parent') AS parents,
        SUM(role='nanny')  AS nannies,
        SUM(role='admin')  AS admins,
        SUM(status='suspended') AS suspended,
        COUNT(*) AS total
     FROM users"
)->fetch();

$bookings = db()->query(
    "SELECT
        SUM(status='pending')   AS pending,
        SUM(status='confirmed') AS confirmed,
        SUM(status='completed') AS completed,
        SUM(status='rejected')  AS rejected,
        SUM(status='cancelled') AS cancelled,
        COUNT(*) AS total
     FROM bookings"
)->fetch();

$revenue = db()->query(
    "SELECT
        IFNULL(SUM(CASE WHEN status='paid' THEN amount END),0)    AS collected,
        IFNULL(SUM(CASE WHEN status='pending' THEN amount END),0) AS outstanding
     FROM payments"
)->fetch();

$verif = db()->query(
    "SELECT
        SUM(verification_status='pending')  AS pending,
        SUM(verification_status='verified') AS verified,
        SUM(verification_status='rejected') AS rejected
     FROM nanny_profiles"
)->fetch();

$pageTitle = 'Reports';
require __DIR__ . '/../includes/header.php';

/** Tiny helper to render a labelled count row. */
function report_row(string $label, $value): void
{
    echo '<tr><td>' . e($label) . '</td><td class="text-right"><strong>' . (int)$value . '</strong></td></tr>';
}
?>
<h1>Reports &amp; analytics</h1>

<div class="grid grid-2 section">
    <div class="card">
        <h2>User report</h2>
        <table class="table">
            <?php
                report_row('Parents', $users['parents']);
                report_row('Nannies', $users['nannies']);
                report_row('Administrators', $users['admins']);
                report_row('Suspended', $users['suspended']);
                report_row('Total users', $users['total']);
            ?>
        </table>
    </div>

    <div class="card">
        <h2>Booking report</h2>
        <table class="table">
            <?php
                report_row('Pending', $bookings['pending']);
                report_row('Confirmed', $bookings['confirmed']);
                report_row('Completed', $bookings['completed']);
                report_row('Rejected', $bookings['rejected']);
                report_row('Cancelled', $bookings['cancelled']);
                report_row('Total bookings', $bookings['total']);
            ?>
        </table>
    </div>

    <div class="card">
        <h2>Verification report</h2>
        <table class="table">
            <?php
                report_row('Pending', $verif['pending']);
                report_row('Verified', $verif['verified']);
                report_row('Rejected', $verif['rejected']);
            ?>
        </table>
    </div>

    <div class="card">
        <h2>Revenue report</h2>
        <table class="table">
            <tr><td>Collected (paid)</td><td class="text-right"><strong>R<?= number_format((float)$revenue['collected'], 2) ?></strong></td></tr>
            <tr><td>Outstanding (pending)</td><td class="text-right"><strong>R<?= number_format((float)$revenue['outstanding'], 2) ?></strong></td></tr>
        </table>
        <p class="muted text-sm">Note: payments are recorded by the in-app demo flow until a live gateway is connected.</p>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
