<?php
require_once __DIR__ . '/../config/config.php';

$stats = db()->query(
    "SELECT
        (SELECT COUNT(*) FROM users WHERE role='parent') AS parents,
        (SELECT COUNT(*) FROM users WHERE role='nanny')  AS nannies,
        (SELECT COUNT(*) FROM bookings)                  AS bookings,
        (SELECT COUNT(*) FROM nanny_profiles WHERE verification_status='verified') AS verified"
)->fetch();

$pageTitle = 'Community';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <p class="h-eyebrow">Community</p>
    <h1>Families and nannies, together</h1>
    <p class="muted"><?= APP_NAME ?> is more than a booking platform — it's a growing community built on trust, care and professional childcare across South Africa.</p>
</div>

<!-- Live stats -->
<div class="stat-band section section-no-top">
    <div class="sb reveal-item"><b><?= (int)$stats['parents'] ?></b><span>Registered parents</span></div>
    <div class="sb reveal-item"><b><?= (int)$stats['nannies'] ?></b><span>Registered nannies</span></div>
    <div class="sb reveal-item"><b><?= (int)$stats['verified'] ?></b><span>Verified nannies</span></div>
    <div class="sb reveal-item"><b><?= (int)$stats['bookings'] ?></b><span>Bookings made</span></div>
</div>

<!-- Community values -->
<section class="section">
    <div class="sec-intro">
        <p class="h-eyebrow">Our values</p>
        <h2>What makes our community special</h2>
    </div>
    <div class="grid grid-3">
        <div class="card stack reveal-item">
            <div class="feature-ico">🤝</div>
            <h3 class="no-margin">Lasting relationships</h3>
            <p class="muted no-margin">Parents and nannies build long-term bonds through repeat bookings, trust and shared care of the little ones that matter most.</p>
        </div>
        <div class="card stack reveal-item">
            <div class="feature-ico">⭐</div>
            <h3 class="no-margin">Honest reviews</h3>
            <p class="muted no-margin">After every completed session, parents leave real reviews. This keeps standards high and helps families find the perfect match.</p>
        </div>
        <div class="card stack reveal-item">
            <div class="feature-ico">🌍</div>
            <h3 class="no-margin">South African roots</h3>
            <p class="muted no-margin">Built for South African families. Our nannies speak English, isiZulu, Sesotho, Afrikaans and more — care in your home language.</p>
        </div>
        <div class="card stack reveal-item">
            <div class="feature-ico">🔒</div>
            <h3 class="no-margin">Safe and verified</h3>
            <p class="muted no-margin">Every nanny in our community is manually verified by our admin team before appearing to parents. Safety is never optional.</p>
        </div>
        <div class="card stack reveal-item">
            <div class="feature-ico">💬</div>
            <h3 class="no-margin">Open communication</h3>
            <p class="muted no-margin">Secure in-app messaging lets parents and nannies coordinate, ask questions and stay connected throughout every booking.</p>
        </div>
        <div class="card stack reveal-item">
            <div class="feature-ico">📚</div>
            <h3 class="no-margin">Shared knowledge</h3>
            <p class="muted no-margin">Our resources section is filled with childcare guides, safety tips and development advice contributed by professionals and families.</p>
        </div>
    </div>
</section>

<!-- Community articles -->
<section class="section">
    <div class="section-head">
        <div>
            <p class="h-eyebrow no-margin">Community articles</p>
            <h2 class="no-margin">Tips from our community</h2>
        </div>
        <a href="<?= url('pages/resources.php') ?>">All resources →</a>
    </div>
    <div class="grid grid-3">
        <?php
        $articles = [
            ['🧒', 'Parenting', 'The importance of routine for young children', 'Consistent routines reduce anxiety and help children feel safe, loved and ready to learn. Here\'s how to create one that works.', 'tip-blue'],
            ['🍎', 'Nutrition', 'Healthy snack ideas your nanny can prepare', 'Quick, nutritious snacks that nannies can make with ingredients already in your kitchen — loved by toddlers and school-age children alike.', 'tip-green'],
            ['🛡️', 'Safety', 'Teaching children about stranger safety', 'Age-appropriate ways to talk to your child about personal safety without causing unnecessary fear. Practical scripts for parents and nannies.', 'tip-violet'],
            ['🌱', 'Development', 'Screen time guidelines by age group', 'The South African Paediatric Association\'s recommendations on healthy screen time — and ideas for what to do instead.', 'tip-blue'],
            ['💛', 'Wellbeing', 'Supporting your nanny\'s mental health', 'Childcare is emotionally demanding work. Small gestures of appreciation go a long way in building a healthy, sustainable working relationship.', 'tip-green'],
            ['📖', 'Education', 'Reading aloud every day', 'Research shows that reading 20 minutes a day with a child dramatically boosts vocabulary, empathy and school readiness. Tips on making it fun.', 'tip-violet'],
        ];
        foreach ($articles as $a): ?>
        <a class="card tip-card reveal-item" href="<?= url('pages/resources.php') ?>">
            <div class="tip-head <?= $a[4] ?>"><?= $a[0] ?></div>
            <div class="tip-body">
                <span class="tip-cat"><?= $a[1] ?></span>
                <h3><?= $a[2] ?></h3>
                <p><?= $a[3] ?></p>
                <span class="tip-more">Read more →</span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Community testimonials -->
<section class="section">
    <div class="sec-intro">
        <p class="h-eyebrow">What our community says</p>
        <h2>Families and nannies, in their own words</h2>
    </div>
    <div class="grid grid-3">
        <?php
        $testimonials = [
            ['Margaret was amazing with our two children. She arrived early, kept us updated throughout the evening, and the kids absolutely loved her. We\'ve already booked her again.', 'SD', 'Sarah Dlamini', 'Parent · Sandton, Johannesburg', 'avatar-blue'],
            ['Very professional and caring. My daughter took to her immediately. NannyApp made finding her so easy — I had three great candidates to choose from within 24 hours.', 'MM', 'Michael Mokoena', 'Parent · Centurion, Pretoria', 'avatar-green'],
            ['NannyApp changed my career. I now have a steady flow of families who trust me, the payments are always on time, and the verification badge gives parents confidence in my credentials.', 'NK', 'Nomsa Khumalo', 'Nanny · Sandton · 7 years exp.', 'avatar-gold'],
            ['As a working mom, finding reliable childcare used to be my biggest stress. With NannyApp, I can see reviews, qualifications, and availability all in one place. Game changer.', 'LK', 'Lebo Khumalo', 'Parent · Midrand, Johannesburg', 'avatar-lilac'],
            ['I love that all my documents are in one place and the admin team was thorough but friendly during verification. I felt like they genuinely cared about quality, not just ticking boxes.', 'MS', 'Margaret Sithole', 'Nanny · Johannesburg · 9 years exp.', 'avatar-pink'],
            ['The booking system is so simple my husband set it up in five minutes. We love being able to message the nanny before the booking — it gives us real peace of mind.', 'NN', 'Nomsa Ncube', 'Parent · Durban', 'avatar-mint'],
        ];
        foreach ($testimonials as $t): ?>
        <div class="card reveal-item quote-card">
            <div class="quote-mark">"</div>
            <p class="muted quote-text"><?= e($t[0]) ?></p>
            <div class="quote-person">
                <div class="quote-avatar <?= e($t[4]) ?>"><?= e($t[1]) ?></div>
                <div>
                    <div class="quote-name"><?= e($t[2]) ?></div>
                    <div class="muted quote-meta"><?= e($t[3]) ?></div>
                    <div class="quote-stars">★★★★★</div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- CTA -->
<section class="section">
    <div class="final-cta reveal-item">
        <div class="final-cta-bg" aria-hidden="true"></div>
        <span class="final-ico">💛</span>
        <h2>Be part of our community</h2>
        <p>Join thousands of South African families and nannies who trust <?= APP_NAME ?> for safe, professional childcare.</p>
        <div class="hero-cta actions-center">
            <a class="btn btn-primary" href="<?= url('auth/register.php') ?>">Sign up free</a>
            <a class="btn btn-ghost" href="<?= url('parent/nannies.php') ?>">Browse nannies</a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
