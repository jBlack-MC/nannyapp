<?php
require_once __DIR__ . '/../config/config.php';
$pageTitle = 'Privacy Policy';
require __DIR__ . '/../includes/header.php';
?>
<section class="section">
    <div class="card prose stack legal-wrap">
        <div class="legal-head">
            <p class="h-eyebrow">Legal</p>
            <h1 class="legal-title">Privacy Policy</h1>
            <p class="muted legal-meta">Last updated: <?= date('d F Y') ?> &nbsp;|&nbsp; Effective date: 1 January 2025</p>
        </div>

        <p class="muted">NannyApp (Pty) Ltd ("<strong>we</strong>", "<strong>us</strong>", or "<strong>our</strong>") is committed to protecting the privacy of every person who uses our platform. This Privacy Policy explains what personal information we collect, how we use it, and your rights regarding that information.</p>
        <p class="muted">By registering for or using NannyApp you agree to the collection and use of information as described in this policy. If you do not agree, please do not use the platform.</p>

        <h2>1. Information We Collect</h2>
        <h3>1.1 Information you provide directly</h3>
        <ul class="muted">
            <li><strong>Account information:</strong> full name, email address, password (stored as a bcrypt hash), phone number, and profile photograph.</li>
            <li><strong>Parent profile:</strong> home address, children's names, ages, allergies, and any medical or special-needs notes you choose to share.</li>
            <li><strong>Nanny profile:</strong> bio, location, hourly rate, skills, languages spoken, qualifications, identification documents, and certification photographs.</li>
            <li><strong>Booking details:</strong> booking dates, times, duration, address, and notes about the session.</li>
            <li><strong>Messages:</strong> content of in-app conversations between parents and nannies.</li>
            <li><strong>Reviews:</strong> star ratings and written feedback you submit after a completed session.</li>
            <li><strong>Payment information:</strong> transaction references and amounts. We do not store full card numbers — payments are processed by a secure third-party provider.</li>
            <li><strong>Support requests:</strong> the content of tickets you submit through our support system.</li>
        </ul>

        <h3>1.2 Information collected automatically</h3>
        <ul class="muted">
            <li>IP address and approximate location.</li>
            <li>Browser type, device type, and operating system.</li>
            <li>Pages visited, time on page, and referral source (collected via server logs).</li>
            <li>Session cookies and a 30-day "remember me" cookie if you enable that option at login.</li>
        </ul>

        <h2>2. How We Use Your Information</h2>
        <p class="muted">We use your personal information to:</p>
        <ul class="muted">
            <li>Create and manage your account and verify your identity.</li>
            <li>Match parents with suitable nannies based on location, skills, and availability.</li>
            <li>Process and manage bookings and payments.</li>
            <li>Send booking confirmations, reminders, and status updates.</li>
            <li>Enable in-app messaging between parents and nannies.</li>
            <li>Verify nanny identities and conduct background checks.</li>
            <li>Display your profile to other users as appropriate to your role.</li>
            <li>Improve the platform through aggregated, anonymised analytics.</li>
            <li>Respond to your support tickets and enquiries.</li>
            <li>Send platform-related notifications (you may opt out of non-essential notifications in your account settings).</li>
            <li>Comply with legal obligations, including the Protection of Personal Information Act (POPIA) and the Electronic Communications and Transactions Act (ECTA).</li>
        </ul>

        <h2>3. Sharing Your Information</h2>
        <p class="muted">We share your information only as follows:</p>
        <ul class="muted">
            <li><strong>Between parents and nannies:</strong> when a booking is created, the relevant parent and nanny can see each other's name, profile photograph, and contact details necessary to carry out the booking.</li>
            <li><strong>Payment processors:</strong> we share transaction data with our payment gateway partner solely to process payments securely.</li>
            <li><strong>Background check providers:</strong> nanny identity and document information is shared with our verification partners for the purpose of conducting background checks.</li>
            <li><strong>Legal requirements:</strong> we may disclose information if required by South African law, court order, or to protect the safety of our users.</li>
        </ul>
        <p class="muted">We do <strong>not</strong> sell, rent, or trade your personal information to third parties for marketing purposes.</p>

        <h2>4. Data Retention</h2>
        <p class="muted">We retain your personal data for as long as your account is active and for a reasonable period afterwards in case you choose to return. Booking records, payment history, and reviews are retained for a minimum of three years for business and legal purposes. You may request deletion of your account at any time (see Section 7).</p>

        <h2>5. Security</h2>
        <p class="muted">We take reasonable technical and organisational measures to protect your data, including:</p>
        <ul class="muted">
            <li>Passwords hashed with bcrypt (never stored in plain text).</li>
            <li>All SQL queries use parameterised statements to prevent injection attacks.</li>
            <li>CSRF tokens on all forms.</li>
            <li>HTTP-only session cookies.</li>
            <li>Restricted direct access to configuration and database files via server rules.</li>
        </ul>
        <p class="muted">No system is completely secure. If you believe your account has been compromised, please contact us immediately at <a href="mailto:privacy@nanny-app.co.za">privacy@nanny-app.co.za</a>.</p>

        <h2>6. Cookies</h2>
        <p class="muted">We use the following cookies:</p>
        <ul class="muted">
            <li><strong>Session cookie:</strong> required for login and CSRF protection. Expires when you close your browser (or after your session timeout).</li>
            <li><strong>Remember-me cookie (na_remember):</strong> optional, 30-day persistent cookie set only if you check "Remember me" at login.</li>
        </ul>
        <p class="muted">We do not use third-party advertising or tracking cookies.</p>

        <h2>7. Your Rights under POPIA</h2>
        <p class="muted">As a South African data subject you have the right to:</p>
        <ul class="muted">
            <li><strong>Access:</strong> request a copy of the personal information we hold about you.</li>
            <li><strong>Correction:</strong> ask us to correct inaccurate or incomplete information.</li>
            <li><strong>Deletion:</strong> request that we delete your account and associated personal data (subject to retention obligations).</li>
            <li><strong>Objection:</strong> object to the processing of your data in certain circumstances.</li>
            <li><strong>Complaint:</strong> lodge a complaint with the Information Regulator of South Africa if you believe we have breached POPIA.</li>
        </ul>
        <p class="muted">To exercise any of these rights, please contact us at <a href="mailto:privacy@nanny-app.co.za">privacy@nanny-app.co.za</a> or submit a request through our <a href="<?= url('support.php') ?>">support system</a>. We will respond within 30 days.</p>

        <h2>8. Children's Privacy</h2>
        <p class="muted">Children's profiles (names, ages, and care notes) are entered by parents or guardians. This information is used solely to facilitate the childcare service and is visible only to the specific nanny assigned to a booking. We do not process children's data for any other purpose.</p>

        <h2>9. Changes to This Policy</h2>
        <p class="muted">We may update this Privacy Policy from time to time. We will notify you of significant changes by email or by a prominent notice on the platform at least 14 days before the change takes effect. Continued use of NannyApp after that date constitutes acceptance of the updated policy.</p>

        <h2>10. Contact Us</h2>
        <p class="muted">For any privacy-related questions, access requests, or concerns, please contact our Information Officer:</p>
        <address class="muted legal-address">
            <strong>NannyApp (Pty) Ltd</strong><br>
            Email: <a href="mailto:privacy@nanny-app.co.za">privacy@nanny-app.co.za</a><br>
            Tel: +27 10 000 0000<br>
            Physical address: Sandton, Johannesburg, Gauteng, South Africa
        </address>

        <div class="legal-actions">
            <a class="btn btn-primary" href="<?= url('pages/contact.php') ?>">Contact Us</a>
            <a class="btn" href="<?= url('pages/terms.php') ?>">Terms of Service</a>
            <a class="btn" href="<?= url('support.php') ?>">Support Centre</a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
