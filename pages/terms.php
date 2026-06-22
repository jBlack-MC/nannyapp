<?php
require_once __DIR__ . '/../config/config.php';
$pageTitle = 'Terms of Service';
require __DIR__ . '/../includes/header.php';
?>
<section class="section">
    <div class="card prose stack legal-wrap">
        <div class="legal-head">
            <p class="h-eyebrow">Legal</p>
            <h1 class="legal-title">Terms of Service</h1>
            <p class="muted legal-meta">Last updated: <?= date('d F Y') ?> &nbsp;|&nbsp; Effective date: 1 January 2025</p>
        </div>

        <p class="muted">These Terms of Service ("<strong>Terms</strong>") govern your access to and use of the NannyApp platform operated by NannyApp (Pty) Ltd ("<strong>NannyApp</strong>", "<strong>we</strong>", "<strong>us</strong>"). By creating an account or using any part of the platform, you agree to be bound by these Terms. If you do not agree, please do not use NannyApp.</p>

        <h2>1. The NannyApp Platform</h2>
        <p class="muted">NannyApp is an online marketplace that connects parents and guardians ("<strong>Parents</strong>") with childcare providers ("<strong>Nannies</strong>"). NannyApp is a facilitator — we are not an employer of Nannies, nor are we a party to the individual childcare arrangements made through the platform.</p>

        <h2>2. Eligibility and Account Registration</h2>
        <ul class="muted">
            <li>You must be at least 18 years old to create an account.</li>
            <li>You must provide accurate, current, and complete information during registration and keep it updated.</li>
            <li>You are responsible for maintaining the confidentiality of your password and for all activity that occurs under your account.</li>
            <li>You may not create more than one account per person without our written permission.</li>
            <li>Accounts are non-transferable.</li>
        </ul>

        <h2>3. User Roles and Responsibilities</h2>
        <h3>3.1 Parents</h3>
        <ul class="muted">
            <li>Parents are responsible for accurately describing their childcare needs, including any relevant medical information about children in their care.</li>
            <li>Parents must treat Nannies with respect and maintain a safe home environment.</li>
            <li>Parents are responsible for payment of all bookings made through the platform.</li>
            <li>Parents agree to leave only honest, accurate reviews of Nannies.</li>
        </ul>
        <h3>3.2 Nannies</h3>
        <ul class="muted">
            <li>Nannies must accurately represent their qualifications, experience, availability, and skills.</li>
            <li>Nannies must hold any certifications they claim (e.g. First Aid) and keep them current.</li>
            <li>Nannies agree to undergo the NannyApp identity and background verification process and to provide truthful documentation.</li>
            <li>Nannies are responsible for their own tax obligations arising from income earned through the platform.</li>
            <li>Nannies agree to arrive on time, perform the agreed childcare duties professionally, and communicate promptly with Parents.</li>
        </ul>

        <h2>4. Booking and Cancellations</h2>
        <ul class="muted">
            <li>Bookings are confirmed only after the Nanny accepts the request.</li>
            <li>Cancellations by the Parent must be made at least 24 hours before the scheduled start time to avoid a cancellation fee.</li>
            <li>Nannies who repeatedly cancel confirmed bookings may have their accounts reviewed or suspended.</li>
            <li>NannyApp reserves the right to cancel or modify bookings in exceptional circumstances, including safety concerns or platform violations.</li>
        </ul>

        <h2>5. Payments</h2>
        <ul class="muted">
            <li>All payments are processed through our secure payment gateway. NannyApp does not store full card details.</li>
            <li>Payment is due at the time of booking. Funds are held and released to the Nanny upon completion of the session.</li>
            <li>Refunds are processed in accordance with our Refund Policy. NannyApp takes a service fee on each transaction; this fee is non-refundable in cases of Parent-initiated cancellations outside the cancellation window.</li>
            <li>Subscription plan fees (if applicable) are billed monthly and are non-refundable for the current billing period.</li>
        </ul>

        <h2>6. Verification</h2>
        <p class="muted">NannyApp takes reasonable steps to verify Nanny identities and backgrounds but cannot guarantee the accuracy of all information provided by users. Parents are encouraged to conduct their own due diligence. NannyApp's verification badge indicates that a Nanny has passed our standard checks at the time of verification — it is not a guarantee of future conduct.</p>

        <h2>7. Prohibited Conduct</h2>
        <p class="muted">You may not:</p>
        <ul class="muted">
            <li>Provide false, misleading, or fraudulent information on your profile or in bookings.</li>
            <li>Use the platform for any unlawful purpose or in violation of any applicable law.</li>
            <li>Harass, abuse, or threaten other users.</li>
            <li>Circumvent the platform by arranging payment directly with a Nanny to avoid platform fees after making contact through NannyApp (within 12 months of initial contact).</li>
            <li>Post defamatory, discriminatory, or otherwise harmful content in reviews or messages.</li>
            <li>Attempt to access another user's account or any part of the platform you are not authorised to access.</li>
            <li>Scrape, crawl, or extract data from the platform using automated means.</li>
        </ul>

        <h2>8. Intellectual Property</h2>
        <p class="muted">All content on the NannyApp platform (excluding user-generated content) — including the design, code, logos, text, and graphics — is owned by NannyApp (Pty) Ltd and protected by South African copyright law. You may not reproduce, distribute, or create derivative works without our express written permission.</p>
        <p class="muted">By submitting a review, profile content, or other material, you grant NannyApp a non-exclusive, royalty-free licence to display and use that content on the platform.</p>

        <h2>9. Limitation of Liability</h2>
        <p class="muted">To the maximum extent permitted by the Consumer Protection Act (CPA) and other applicable South African law:</p>
        <ul class="muted">
            <li>NannyApp provides the platform "as is" without warranties of any kind.</li>
            <li>NannyApp is not liable for the conduct of any Nanny or Parent on or off the platform.</li>
            <li>NannyApp's total liability to you for any claim arising out of or relating to these Terms or the platform shall not exceed the total fees you paid to NannyApp in the three months preceding the event giving rise to the claim.</li>
        </ul>

        <h2>10. Indemnification</h2>
        <p class="muted">You agree to indemnify and hold harmless NannyApp, its directors, employees, and agents from any claims, damages, losses, or expenses (including legal fees) arising from your use of the platform, your violation of these Terms, or your infringement of any third-party rights.</p>

        <h2>11. Account Suspension and Termination</h2>
        <p class="muted">NannyApp may suspend or terminate your account at any time if we reasonably believe you have violated these Terms, endangered the safety of other users, or acted fraudulently. We will ordinarily notify you before doing so unless the circumstances require immediate action. You may close your account at any time by contacting support.</p>

        <h2>12. Governing Law and Disputes</h2>
        <p class="muted">These Terms are governed by the laws of the Republic of South Africa. Any disputes shall first be referred to mediation in Johannesburg, Gauteng. If mediation fails, disputes shall be resolved by the Johannesburg Magistrate's Court or, where the claim amount warrants it, the High Court of South Africa (Gauteng Local Division).</p>

        <h2>13. Changes to These Terms</h2>
        <p class="muted">We may update these Terms at any time. We will notify you of material changes by email or a notice on the platform at least 14 days before they take effect. Continued use of NannyApp after that date constitutes acceptance of the revised Terms.</p>

        <h2>14. Contact</h2>
        <p class="muted">For questions about these Terms, please contact us:</p>
        <address class="muted legal-address">
            <strong>NannyApp (Pty) Ltd</strong><br>
            Email: <a href="mailto:legal@nanny-app.co.za">legal@nanny-app.co.za</a><br>
            Tel: +27 10 000 0000<br>
            Physical address: Sandton, Johannesburg, Gauteng, South Africa
        </address>

        <div class="legal-actions">
            <a class="btn btn-primary" href="<?= url('pages/contact.php') ?>">Contact Us</a>
            <a class="btn" href="<?= url('pages/privacy.php') ?>">Privacy Policy</a>
            <a class="btn" href="<?= url('support.php') ?>">Support Centre</a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
