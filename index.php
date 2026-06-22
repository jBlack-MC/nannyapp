<?php
require_once __DIR__ . '/config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['newsletter_email'])) {
    verify_csrf();
    $email = trim((string)($_POST['newsletter_email'] ?? ''));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            db()->exec("CREATE TABLE IF NOT EXISTS newsletter_subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(150) NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB");
            $ins = db()->prepare('INSERT IGNORE INTO newsletter_subscriptions (email) VALUES (?)');
            $ins->execute([$email]);
            flash('Subscribed successfully. Welcome to the community.');
        } catch (Throwable $e) {
            flash('Could not subscribe right now. Please try again.', 'error');
        }
    } else {
        flash('Please enter a valid email address.', 'error');
    }
    redirect('index.php');
}

try {
    $featured = db()->query(
        "SELECT u.id, u.full_name,
                p.bio, p.location, p.hourly_rate, p.average_rating, p.experience_years,
                (SELECT COUNT(*) FROM reviews r WHERE r.nanny_id = u.id) AS review_count
         FROM nanny_profiles p
         JOIN users u ON u.id = p.user_id
         WHERE p.verification_status='verified'
         ORDER BY p.average_rating DESC, p.experience_years DESC
         LIMIT 6"
    )->fetchAll();
} catch (Throwable $e) {
    $featured = [];
}

try {
    $stats = db()->query(
        "SELECT
            (SELECT COUNT(*) FROM users WHERE role='parent') AS families,
            (SELECT COUNT(*) FROM users u2 JOIN nanny_profiles p2 ON p2.user_id=u2.id WHERE u2.role='nanny' AND p2.verification_status='verified') AS nannies,
            (SELECT COUNT(*) FROM bookings WHERE status='completed') AS bookings,
            IFNULL((SELECT ROUND(AVG(rating),1) FROM reviews), 5.0) AS rating"
    )->fetch();
} catch (Throwable $e) {
    $stats = ['families' => 10000, 'nannies' => 1500, 'bookings' => 8923, 'rating' => 4.9];
}

$galleryFiles = [];
$galleryDir = __DIR__ . '/assets/img/gallery';
if (is_dir($galleryDir)) {
    $entries = scandir($galleryDir);
    if (is_array($entries)) {
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $filePath = $galleryDir . '/' . $entry;
            if (!is_file($filePath)) {
                continue;
            }

            if (!preg_match('/\.(?:jpe?g|png|webp|gif)$/i', $entry)) {
                continue;
            }

            $galleryFiles[] = 'gallery/' . $entry;
        }
    }
}

if ($galleryFiles) {
    shuffle($galleryFiles);
    $galleryFiles = array_slice($galleryFiles, 0, 8);
} else {
    $galleryFiles = [
        'hero-nanny-SMceVYR7.jpg',
        'download.jpg',
        'download (1).jpg',
        'download (2).jpg',
        'download (3).jpg',
        'download (4).jpg',
    ];
}

$avatarCycle = [
    'assets/img/avatar-amelia.svg',
    'assets/img/avatar-jasmine.svg',
    'assets/img/avatar-margaret.svg',
];

$isParent = is_logged_in() && user_role() === 'parent';
$findCareUrl = url('parent/nannies.php');

try {
    $testimonials = db()->query(
        "SELECT
            p.full_name AS parent_name,
            n.full_name AS nanny_name,
            r.comment,
            r.rating
         FROM reviews r
         JOIN users p ON p.id = r.reviewer_id
         JOIN users n ON n.id = r.nanny_id
         WHERE COALESCE(r.comment, '') <> ''
         ORDER BY r.id DESC
         LIMIT 8"
    )->fetchAll();
} catch (Throwable $e) {
    $testimonials = [];
}

$pageTitle = 'Trusted Childcare Platform';
$bodyClass = 'home-page';
$mainClass = 'home-main';
require __DIR__ . '/includes/header.php';
?>

<section class="home-hero" id="home">
    <div class="home-hero-overlay"></div>
    <div class="container-wide home-hero-inner">
        <div class="home-hero-copy appear-up">
            <p class="h-eyebrow">Trusted childcare, on your schedule</p>
            <h1>Find professional childcare with confidence.</h1>
            <p class="muted">NannyApp helps families discover verified nannies, compare profiles, and book care securely from one polished platform.</p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="<?= $findCareUrl ?>">Find Care</a>
                <a class="btn" href="<?= url('auth/register.php') ?>">Become a Nanny</a>
            </div>
            <div class="hero-points">
                <span>Background Checked</span>
                <span>Verified Reviews</span>
                <span>Secure Payments</span>
            </div>
        </div>
        <div class="home-hero-media card appear-up">
            <img src="<?= url('assets/img/hero-nanny-SMceVYR7.jpg') ?>" alt="Nanny caring for children" class="home-hero-image">
        </div>
    </div>
</section>

<section class="home-stats section-pad">
    <div class="container-wide home-stats-grid">
        <?= stat_card('Happy Families', number_format((int)$stats['families']) . '+', '1,254') ?>
        <?= stat_card('Verified Nannies', number_format((int)$stats['nannies']) . '+', '324') ?>
        <?= stat_card('Satisfaction', max(95, min(99, (int)round((float)$stats['rating'] * 20))) . '%', '98%') ?>
        <?= stat_card('Support', '24/7', '24/7') ?>
    </div>
</section>

<section id="about" class="section section-pad">
    <div class="container-wide home-split">
        <div class="card reveal-item about-card">
            <p class="h-eyebrow">About</p>
            <h2>Childcare that feels personal, not transactional.</h2>
            <p class="muted">We connect families with compassionate caregivers through a transparent marketplace built for trust, safety, and consistency.</p>
            <p class="muted">From profile verification to secure booking workflows, every step is designed to keep parents informed and children supported.</p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="<?= $findCareUrl ?>">Meet Our Nannies</a>
                <a class="btn" href="<?= url('pages/about.php') ?>">Learn More</a>
            </div>
        </div>
        <div class="card reveal-item about-media">
            <img src="<?= url('assets/img/download.jpg') ?>" alt="Caregiver reading with a child" class="section-image" loading="lazy" decoding="async">
        </div>
    </div>
</section>

<section id="services" class="section section-pad section-soft">
    <div class="container-wide">
        <div class="section-title-wrap">
            <p class="h-eyebrow">Services</p>
            <h2>Flexible support for every household rhythm</h2>
        </div>
        <div class="grid grid-4 service-grid">
            <article class="card reveal-item service-card">
                <span class="service-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                <h3>Full-Time Care</h3>
                <p class="muted">Reliable weekday childcare with stable routines and ongoing developmental support.</p>
            </article>
            <article class="card reveal-item service-card">
                <span class="service-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
                <h3>Part-Time Care</h3>
                <p class="muted">Practical help for after-school windows, school runs, and structured afternoon care.</p>
            </article>
            <article class="card reveal-item service-card">
                <span class="service-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></span>
                <h3>Babysitting</h3>
                <p class="muted">On-demand bookings for date nights, events, and short-notice childcare needs.</p>
            </article>
            <article class="card reveal-item service-card">
                <span class="service-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></span>
                <h3>Overnight Care</h3>
                <p class="muted">Trusted overnight supervision with calm evening routines and attentive morning care.</p>
            </article>
        </div>
    </div>
</section>

<section id="how-it-works" class="section section-pad">
    <div class="container-wide">
        <div class="section-title-wrap">
            <p class="h-eyebrow">Why Choose Us</p>
            <h2>Safety, quality, and transparency at every step</h2>
        </div>
        <div class="grid grid-4 feature-grid">
            <article class="card reveal-item feature-card">
                <span class="service-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>
                <h3>Background Checks</h3>
                <p class="muted">Every verified nanny completes profile and trust checks before being listed.</p>
            </article>
            <article class="card reveal-item feature-card">
                <span class="service-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
                <h3>Verified Reviews</h3>
                <p class="muted">Read feedback from real bookings to make informed childcare decisions.</p>
            </article>
            <article class="card reveal-item feature-card">
                <span class="service-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></span>
                <h3>Personalized Matching</h3>
                <p class="muted">Filter by location, rates, and experience to find the best fit for your family.</p>
            </article>
            <article class="card reveal-item feature-card">
                <span class="service-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
                <h3>Secure Payments</h3>
                <p class="muted">Book and pay through protected workflows with clear records and confirmations.</p>
            </article>
        </div>
    </div>
</section>

<section id="nannies" class="section section-pad section-soft">
    <div class="container-wide">
        <div class="section-head">
            <div>
                <p class="h-eyebrow">Nannies</p>
                <h2>Top-rated caregivers</h2>
            </div>
            <a class="btn btn-sm" href="<?= $findCareUrl ?>">View all</a>
        </div>

        <?php if (!$featured): ?>
            <?= empty_state('No nannies listed yet', 'Complete profiles and ratings will appear here shortly.', url('auth/register.php'), 'Get Started') ?>
        <?php else: ?>
            <div class="grid grid-3">
                <?php foreach ($featured as $idx => $n): ?>
                    <article class="card nanny-card reveal-item marketplace-card">
                        <div class="nanny-top-row">
                            <img class="nanny-avatar" src="<?= url($avatarCycle[$idx % count($avatarCycle)]) ?>" alt="<?= e($n['full_name']) ?> avatar" loading="lazy" decoding="async">
                            <div class="nanny-id-wrap">
                                <h3 class="heading-tight"><?= e($n['full_name']) ?></h3>
                                <p class="muted"><?= e($n['location'] ?: 'South Africa') ?></p>
                            </div>
                        </div>
                        <p class="muted">
                            <?php $bio = (string)($n['bio'] ?: 'Experienced childcare professional focused on safe, engaging routines.'); ?>
                            <?= e(strlen($bio) > 120 ? substr($bio, 0, 117) . '...' : $bio) ?>
                        </p>
                        <div class="nanny-badge-row">
                            <span class="badge verified-badge">Verified</span>
                            <span class="badge experience-badge"><?= (int)$n['experience_years'] ?>+ yrs</span>
                            <span class="badge online-badge">Available</span>
                        </div>
                        <div class="panel-row-info">
                            <span class="badge badge-ok">Rating <?= number_format((float)$n['average_rating'], 1) ?> (<?= (int)$n['review_count'] ?>)</span>
                            <strong>R<?= number_format((float)$n['hourly_rate'], 0) ?>/hr</strong>
                        </div>
                        <a class="btn btn-primary" href="<?= url('parent/book.php?nanny=' . (int)$n['id']) ?>">Book now</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section id="gallery" class="section section-pad">
    <div class="container-wide">
        <div class="section-title-wrap">
            <p class="h-eyebrow">Gallery</p>
            <h2>Moments of care</h2>
        </div>
        <div class="home-gallery-grid">
            <?php foreach ($galleryFiles as $file): ?>
                <?php $imgUrl = url('assets/img/' . str_replace('%2F', '/', rawurlencode($file))); ?>
                <figure class="home-gallery-item card reveal-item">
                    <a class="gallery-link" href="<?= e($imgUrl) ?>" data-toggle="lightbox" aria-label="Open gallery image">
                        <img src="<?= e($imgUrl) ?>" alt="Childcare gallery image" loading="lazy" decoding="async">
                    </a>
                </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section id="testimonials" class="section section-pad section-soft" data-autoplay="5200">
    <div class="container-wide">
        <div class="section-title-wrap">
            <p class="h-eyebrow">Testimonials</p>
            <h2>Families trust NannyApp</h2>
        </div>
        <?php if (!$testimonials): ?>
            <?= empty_state('No testimonials yet', 'Verified booking feedback will appear here as families submit reviews.') ?>
        <?php else: ?>
            <div class="t-viewport">
                <div class="t-track">
                    <?php foreach ($testimonials as $idx => $t): ?>
                        <article class="card tcard reveal-item">
                            <div class="t-stars" aria-label="Rated <?= (int)$t['rating'] ?> out of 5">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?= $i <= (int)$t['rating'] ? '★' : '☆' ?>
                                <?php endfor; ?>
                            </div>
                            <blockquote>"<?= e($t['comment']) ?>"</blockquote>
                            <div class="t-who">
                                <img src="<?= url($avatarCycle[$idx % count($avatarCycle)]) ?>" alt="Parent avatar" class="nanny-avatar" loading="lazy" decoding="async">
                                <div>
                                    <strong><?= e($t['parent_name']) ?></strong>
                                    <p class="muted">Booked with <?= e($t['nanny_name']) ?></p>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="t-controls" data-for="testimonials">
                <button class="t-arrow" type="button" data-t="prev" aria-label="Previous testimonial">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <div class="t-dots" aria-hidden="true"></div>
                <button class="t-arrow" type="button" data-t="next" aria-label="Next testimonial">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>
        <?php endif; ?>
    </div>
</section>

<section id="pricing" class="section section-pad">
    <div class="container-wide">
        <div class="section-title-wrap">
            <p class="h-eyebrow">Pricing</p>
            <h2>Plans for every family stage</h2>
        </div>
        <div class="grid grid-3 pricing-grid">
            <article class="card reveal-item price-card">
                <h3>Starter</h3>
                <p class="muted">For occasional bookings</p>
                <strong>R0/mo</strong>
                <ul>
                    <li>Browse verified nannies</li>
                    <li>Secure booking flow</li>
                    <li>In-app messaging</li>
                </ul>
                <a class="btn" href="<?= url('auth/register.php') ?>">Get Started</a>
            </article>
            <article class="card reveal-item price-card featured-plan">
                <span class="plan-badge">Recommended</span>
                <h3>Family</h3>
                <p class="muted">Priority matching and lower fees</p>
                <strong>R199/mo</strong>
                <ul>
                    <li>Everything in Starter</li>
                    <li>Priority shortlist support</li>
                    <li>Saved nanny collections</li>
                </ul>
                <a class="btn btn-primary" href="<?= url('pages/pricing.php') ?>">Choose Family</a>
            </article>
            <article class="card reveal-item price-card">
                <h3>Premium</h3>
                <p class="muted">Dedicated support and fast response</p>
                <strong>R399/mo</strong>
                <ul>
                    <li>Everything in Family</li>
                    <li>Dedicated care support</li>
                    <li>Fast-track urgent requests</li>
                </ul>
                <a class="btn" href="<?= url('pages/pricing.php') ?>">Choose Premium</a>
            </article>
        </div>
    </div>
</section>

<section id="contact-cta" class="section section-pad home-cta">
    <div class="container-wide">
        <div class="card reveal-item cta-shell">
            <div class="cta-bg"></div>
            <div class="dashboard-header cta-head">
                <div>
                    <p class="h-eyebrow">Call to action</p>
                    <h2>Ready to find your perfect nanny?</h2>
                    <p class="muted">Start with verified profiles, trusted reviews, and secure booking workflows built for modern families.</p>
                </div>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="<?= $findCareUrl ?>">Find Care</a>
                    <a class="btn" href="<?= url('pages/contact.php') ?>">Talk to Us</a>
                </div>
            </div>
            <form method="post" class="home-newsletter subscribe-form" action="<?= url('index.php') ?>">
                <?= csrf_field() ?>
                <label class="sr-only" for="newsletter_email">Email</label>
                <input class="form-control" id="newsletter_email" type="email" name="newsletter_email" placeholder="you@example.com" required>
                <button class="btn btn-primary" type="submit">Subscribe</button>
            </form>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
