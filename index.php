<?php
/**
 * Alexa Gym — Public Homepage
 */
$page_title = 'Alexa Gym — Build Your Better Self | Premium Fitness & Health Club';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Handle Contact Form Submission
$contact_success = false;
$contact_error   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    $c_name    = trim($_POST['name'] ?? '');
    $c_email   = trim($_POST['email'] ?? '');
    $c_phone   = trim($_POST['phone'] ?? '');
    $c_message = trim($_POST['message'] ?? '');

    if (empty($c_name) || empty($c_email) || empty($c_message)) {
        $contact_error = 'Please fill in all required fields (Name, Email, and Message).';
    } elseif (!filter_var($c_email, FILTER_VALIDATE_EMAIL)) {
        $contact_error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, phone, message, status, created_at) VALUES (?, ?, ?, ?, 'unread', NOW())");
            $stmt->execute([$c_name, $c_email, $c_phone, $c_message]);
            $contact_success = true;
        } catch (Exception $ex) {
            $contact_error = 'Could not submit your message. Please try again.';
        }
    }
}

// Fetch dynamic plans from database
try {
    $plans_stmt = $pdo->query("SELECT * FROM plans WHERE status = 'active' ORDER BY price ASC LIMIT 3");
    $db_plans = $plans_stmt->fetchAll();
} catch (Exception $e) {
    $db_plans = [];
}

$is_user = is_logged_in();
$logged_user = get_logged_in_user();
$user_role = $logged_user['role'] ?? '';

// Fetch dynamic trainers from database
try {
    $trainers_stmt = $pdo->query("SELECT * FROM trainers WHERE status = 'active' ORDER BY id ASC LIMIT 4");
    $db_trainers = $trainers_stmt->fetchAll();
} catch (Exception $e) {
    $db_trainers = [];
}

// Fetch dynamic feedback testimonials
try {
    $fb_stmt = $pdo->query("
        SELECT f.*, m.full_name AS member_name 
        FROM feedback f 
        LEFT JOIN members m ON f.member_id = m.id 
        WHERE f.is_approved = 1 
        ORDER BY f.id DESC 
        LIMIT 3
    ");
    $db_testimonials = $fb_stmt->fetchAll();
} catch (Exception $e) {
    $db_testimonials = [];
}

// Fetch real dynamic counts from database
try {
    $stat_members  = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active' AND plan_id IS NOT NULL")->fetchColumn();
    $stat_trainers = (int)$pdo->query("SELECT COUNT(*) FROM trainers WHERE status = 'active'")->fetchColumn();
    $stat_programs = (int)$pdo->query("SELECT COUNT(*) FROM plans WHERE status = 'active'")->fetchColumn();
} catch (Exception $e) {
    $stat_members  = 0;
    $stat_trainers = 0;
    $stat_programs = 0;
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- 1. HERO SECTION -->
<section class="hero-section">
  <div class="hero-bg-overlay"></div>
  <div class="container hero-grid">
    <div class="hero-content">
      <div class="hero-badge">
        <i class="fa-solid fa-fire"></i> State of the Art Gym in Vadgam, Gujarat
      </div>
      <h1 class="hero-title">
        Build Your <span class="highlight">Better Self</span>
      </h1>
      <p class="hero-desc">
        Train harder. Live stronger. Become the best version of yourself. Join Alexa Gym today and experience elite fitness equipment, world-class coaching, and a driven community.
      </p>
      <div class="hero-actions">
        <?php if ($user_role === 'admin'): ?>
          <a href="<?= $base_url ?>/admin/dashboard.php" class="btn btn-primary">
            <i class="fa-solid fa-gauge-high"></i> Admin Dashboard
          </a>
          <a href="<?= $base_url ?>/admin/plans.php" class="btn btn-outline">
            <i class="fa-solid fa-list-check"></i> Manage Plans
          </a>
        <?php elseif ($user_role === 'member'): ?>
          <a href="<?= $base_url ?>/user/dashboard.php" class="btn btn-primary">
            <i class="fa-solid fa-gauge-high"></i> Member Dashboard
          </a>
          <a href="<?= $base_url ?>/user/plans.php" class="btn btn-outline">
            <i class="fa-solid fa-list-check"></i> My Membership
          </a>
        <?php else: ?>
          <a href="<?= $base_url ?>/auth/register.php" class="btn btn-primary">
            <i class="fa-solid fa-dumbbell"></i> JOIN NOW
          </a>
          <a href="#plans" class="btn btn-outline">
            <i class="fa-solid fa-list-check"></i> EXPLORE PLANS
          </a>
        <?php endif; ?>
      </div>

      <!-- Small Stats Section (Live from MySQL Database) -->
      <div class="hero-stats-row">
        <div class="stat-item">
          <div class="stat-num" data-target="<?= $stat_members ?>"><?= $stat_members ?><span class="plus">+</span></div>
          <div class="stat-label">Active Members</div>
        </div>
        <div class="stat-item">
          <div class="stat-num" data-target="<?= $stat_trainers ?>"><?= $stat_trainers ?><span class="plus">+</span></div>
          <div class="stat-label">Expert Trainers</div>
        </div>
        <div class="stat-item">
          <div class="stat-num" data-target="<?= $stat_programs ?>"><?= $stat_programs ?><span class="plus">+</span></div>
          <div class="stat-label">Fitness Programs</div>
        </div>
        <div class="stat-item">
          <div class="stat-num" data-target="5">5<span class="plus">+</span></div>
          <div class="stat-label">Years Experience</div>
        </div>
      </div>
    </div>

    <!-- Visual Card Showcase -->
    <div class="hero-visual">
      <div class="hero-card-preview">
        <div class="hero-img-wrap">
          <img src="<?= $base_url ?>/assets/images/auth-bg.jpg" alt="Alexa Gym Training Floor">
        </div>
        <div class="hero-floating-badge">
          <div class="badge-icon">
            <i class="fa-solid fa-trophy"></i>
          </div>
          <div>
            <strong style="display:block; font-size: 15px; color:#fff;">#1 Rated Gym</strong>
            <small style="color: var(--cyan);">Certified IFBB &amp; ACE Coaches</small>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 2. ABOUT SECTION -->
<section class="section" id="about" style="background: var(--bg-subtle);">
  <div class="container">
    <div class="about-grid">
      <div>
        <div class="about-img-box" style="border-radius: var(--radius-lg); overflow: hidden; border: 1px solid var(--line); position: relative; box-shadow: var(--shadow-md);">
          <img src="<?= $base_url ?>/assets/images/facility-interior.jpg" alt="Alexa Gym Interior">
          <div style="position: absolute; inset: 0; background: linear-gradient(180deg, transparent 60%, rgba(7, 8, 15, 0.9) 100%);"></div>
        </div>
      </div>
      <div>
        <div class="section-tag"><i class="fa-solid fa-shield-halved"></i> About Alexa Gym</div>
        <h2 class="section-title" style="text-align: left;">Modern Training Ground For Serious Results</h2>
        <p class="section-desc" style="margin-bottom: 16px;">
          At <strong>Alexa Gym</strong>, our mission is simple: fitness is not a seasonal resolution — it is a lifelong habit. Founded over 5 years ago, we empower athletes, everyday lifters, and fitness newcomers to unlock their highest physical and mental potential.
        </p>
        <p style="color: var(--text-muted); font-size: 15px; line-height: 1.7; margin-bottom: 24px;">
          From teenagers stepping into a weight room for the first time to master lifters breaking personal records, we blend heavy iron, functional cardio zones, personalized nutrition counseling, and automated membership tracking into one seamless lifestyle hub.
        </p>

        <!-- Core Pillars from About Page -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 28px;">
          <div style="background: var(--surface); border: 1px solid var(--line); border-radius: 10px; padding: 12px 14px; display: flex; align-items: center; gap: 12px;">
            <i class="fa-solid fa-bullseye" style="color: var(--cyan); font-size: 18px;"></i>
            <span style="font-size: 13.5px; font-weight: 600;">Goal-Driven Training</span>
          </div>
          <div style="background: var(--surface); border: 1px solid var(--line); border-radius: 10px; padding: 12px 14px; display: flex; align-items: center; gap: 12px;">
            <i class="fa-solid fa-medal" style="color: #f59e0b; font-size: 18px;"></i>
            <span style="font-size: 13.5px; font-weight: 600;">IFBB &amp; ACE Coaches</span>
          </div>
          <div style="background: var(--surface); border: 1px solid var(--line); border-radius: 10px; padding: 12px 14px; display: flex; align-items: center; gap: 12px;">
            <i class="fa-solid fa-shield-heart" style="color: var(--ok); font-size: 18px;"></i>
            <span style="font-size: 13.5px; font-weight: 600;">100% Sanitized Hygiene</span>
          </div>
        </div>

        <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
          <a href="#facilities" class="btn btn-outline">
            <i class="fa-solid fa-layer-group"></i> Explore Facilities
          </a>
          <a href="#plans" class="btn btn-primary">
            <i class="fa-solid fa-dumbbell"></i> View Plans
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 3. WHY CHOOSE US -->
<section class="section">
  <div class="container">
    <div class="section-title-wrap">
      <div class="section-tag"><i class="fa-solid fa-star"></i> Why Choose Us</div>
      <h2 class="section-title">Engineered For Your Transformation</h2>
      <p class="section-desc">Experience the competitive advantage of training at Alexa Gym.</p>
    </div>

    <div class="features-grid">
      <!-- Feature 1 -->
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="fa-solid fa-dumbbell"></i>
        </div>
        <h3 class="feature-title">Modern Equipment</h3>
        <p class="feature-desc">Imported biomechanically engineered pin-loaded, plate-loaded, and free-weight equipment from industry leaders.</p>
      </div>
      <!-- Feature 2 -->
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="fa-solid fa-user-ninja"></i>
        </div>
        <h3 class="feature-title">Expert Trainers</h3>
        <p class="feature-desc">Internationally certified personal coaches to guide your technique, motivation, and injury-free progression.</p>
      </div>
      <!-- Feature 3 -->
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="fa-solid fa-chart-line"></i>
        </div>
        <h3 class="feature-title">Personalized Workout</h3>
        <p class="feature-desc">Custom tailored splits (Chest, Back, Shoulders, Arms, Legs, Cardio) assigned directly to your member portal.</p>
      </div>
      <!-- Feature 4 -->
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="fa-solid fa-calendar-check"></i>
        </div>
        <h3 class="feature-title">Flexible Membership</h3>
        <p class="feature-desc">Month-to-month, quarterly, and annual VIP packages with straightforward pricing and zero hidden fees.</p>
      </div>
      <!-- Feature 5 -->
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="fa-solid fa-hand-sparkles"></i>
        </div>
        <h3 class="feature-title">Clean Environment</h3>
        <p class="feature-desc">Air-conditioned, air-purified training bays and spotless locker rooms with 24/7 dedicated maintenance staff.</p>
      </div>
      <!-- Feature 6 -->
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="fa-solid fa-heart-pulse"></i>
        </div>
        <h3 class="feature-title">Healthy Lifestyle</h3>
        <p class="feature-desc">Holistic wellness coaching integrating cardiovascular conditioning, mobility drills, and clinical nutrition support.</p>
      </div>
    </div>
  </div>
</section>

<!-- 4. FACILITIES -->
<section class="section" id="facilities" style="background: var(--bg-subtle);">
  <div class="container">
    <div class="section-title-wrap">
      <div class="section-tag"><i class="fa-solid fa-building-columns"></i> World-Class Amenities</div>
      <h2 class="section-title">Our Gym Facilities</h2>
      <p class="section-desc">Designed to accommodate powerlifters, runners, and fitness enthusiasts alike.</p>
    </div>

    <div class="features-grid">
      <!-- 1 -->
      <div class="facility-card">
        <div class="facility-img-box">
          <img src="<?= $base_url ?>/assets/images/facility-strength.jpg" alt="Weight Training">
          <div class="facility-img-overlay"></div>
        </div>
        <div class="facility-body">
          <h3 class="feature-title"><i class="fa-solid fa-weight-hanging" style="color: var(--cyan); margin-right: 8px;"></i> Weight Training</h3>
          <p class="feature-desc">Full spectrum dumbbells up to 60kg, Olympic barbells, power cages, and heavy deadlift platforms.</p>
        </div>
      </div>
      <!-- 2 -->
      <div class="facility-card">
        <div class="facility-img-box">
          <img src="<?= $base_url ?>/assets/images/facility-cardio.jpg" alt="Cardio Zone">
          <div class="facility-img-overlay"></div>
        </div>
        <div class="facility-body">
          <h3 class="feature-title"><i class="fa-solid fa-person-running" style="color: var(--cyan); margin-right: 8px;"></i> Cardio Zone</h3>
          <p class="feature-desc">Commercial treadmills, spin bikes, assault runners, stair masters, and rowers with real-time biometric tracking.</p>
        </div>
      </div>
      <!-- 3 -->
      <div class="facility-card">
        <div class="facility-img-box">
          <img src="<?= $base_url ?>/assets/images/facility-interior.jpg" alt="Strength Training">
          <div class="facility-img-overlay"></div>
        </div>
        <div class="facility-body">
          <h3 class="feature-title"><i class="fa-solid fa-medal" style="color: var(--cyan); margin-right: 8px;"></i> Strength Training</h3>
          <p class="feature-desc">Dedicated power racks, cable crossover stations, hack squats, and specialized isolation resistance machinery.</p>
        </div>
      </div>
      <!-- 4 -->
      <div class="facility-card">
        <div class="facility-img-box">
          <img src="<?= $base_url ?>/assets/images/trainer-4.jpg" alt="Personal Training">
          <div class="facility-img-overlay"></div>
        </div>
        <div class="facility-body">
          <h3 class="feature-title"><i class="fa-solid fa-user-clock" style="color: var(--cyan); margin-right: 8px;"></i> Personal Training</h3>
          <p class="feature-desc">One-on-one tailored coaching sessions focused on progressive overload, form refinement, and rapid milestones.</p>
        </div>
      </div>
      <!-- 5 -->
      <div class="facility-card">
        <div class="facility-img-box">
          <img src="<?= $base_url ?>/assets/images/facility-crossfit.jpg" alt="Cross Training">
          <div class="facility-img-overlay"></div>
        </div>
        <div class="facility-body">
          <h3 class="feature-title"><i class="fa-solid fa-bolt" style="color: var(--cyan); margin-right: 8px;"></i> Cross Training</h3>
          <p class="feature-desc">Turf functional zone equipped with battle ropes, plyo boxes, sled pushes, kettlebells, and gymnastics rings.</p>
        </div>
      </div>
      <!-- 6 -->
      <div class="facility-card">
        <div class="facility-img-box">
          <img src="<?= $base_url ?>/assets/images/facility-lockers.jpg" alt="Locker & Changing Room">
          <div class="facility-img-overlay"></div>
        </div>
        <div class="facility-body">
          <h3 class="feature-title"><i class="fa-solid fa-lock" style="color: var(--cyan); margin-right: 8px;"></i> Locker &amp; Changing Room</h3>
          <p class="feature-desc">Secure digital lockers, hot &amp; cold showers, steam bath, and dry vanity stations for total post-workout comfort.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 5. MEMBERSHIP PLANS -->
<section class="section" id="plans">
  <div class="container">
    <div class="section-title-wrap">
      <div class="section-tag"><i class="fa-solid fa-tag"></i> Flexible Memberships</div>
      <h2 class="section-title">Choose Your Training Plan</h2>
      <p class="section-desc">Transparent pricing with no sign-up fees or restrictive contracts.</p>
    </div>

    <div class="pricing-grid">
      <!-- Plan 1: BASIC -->
      <div class="pricing-card">
        <div class="plan-name">BASIC</div>
        <div class="plan-desc">Ideal for individuals kickstarting their fitness journey.</div>
        <div class="plan-price-wrap">
          <span class="plan-currency">₹</span>
          <span class="plan-amount">999</span>
          <span class="plan-duration">/ Month</span>
        </div>
        <ul class="plan-features-list">
          <li><i class="fa-solid fa-circle-check"></i> Standard Gym Floor Access</li>
          <li><i class="fa-solid fa-circle-check"></i> Cardio &amp; Resistance Zone</li>
          <li><i class="fa-solid fa-circle-check"></i> Locker &amp; Shower Access</li>
          <li><i class="fa-solid fa-circle-check"></i> Free Fitness Assessment</li>
          <li style="color: var(--text-muted);"><i class="fa-solid fa-circle-xmark" style="color: #64748b;"></i> Personal Trainer Consultation</li>
        </ul>
        <?php
        if ($user_role === 'admin') {
            $p1_url = $base_url . '/admin/plans.php';
            $p1_text = '<i class="fa-solid fa-pen-to-square"></i> Manage Plans';
            $p2_url = $base_url . '/admin/plans.php';
            $p2_text = '<i class="fa-solid fa-pen-to-square"></i> Manage Plans';
            $p3_url = $base_url . '/admin/plans.php';
            $p3_text = '<i class="fa-solid fa-pen-to-square"></i> Manage Plans';
        } elseif ($user_role === 'member') {
            $p1_url = $base_url . '/user/plans.php?plan_id=1';
            $p1_text = '<i class="fa-solid fa-bolt"></i> Choose Plan';
            $p2_url = $base_url . '/user/plans.php?plan_id=2';
            $p2_text = '<i class="fa-solid fa-bolt"></i> Choose Plan';
            $p3_url = $base_url . '/user/plans.php?plan_id=3';
            $p3_text = '<i class="fa-solid fa-bolt"></i> Choose Plan';
        } else {
            $p1_url = $base_url . '/auth/register.php?plan=1';
            $p1_text = 'JOIN NOW';
            $p2_url = $base_url . '/auth/register.php?plan=2';
            $p2_text = 'JOIN NOW';
            $p3_url = $base_url . '/auth/register.php?plan=3';
            $p3_text = 'JOIN NOW';
        }
        ?>
        <a href="<?= $p1_url ?>" class="btn btn-outline btn-block"><?= $p1_text ?></a>
      </div>

      <!-- Plan 2: STANDARD (POPULAR) -->
      <div class="pricing-card popular">
        <div class="popular-badge">MOST POPULAR</div>
        <div class="plan-name">STANDARD</div>
        <div class="plan-desc">Our most popular quarterly plan for consistent results and coaching.</div>
        <div class="plan-price-wrap">
          <span class="plan-currency">₹</span>
          <span class="plan-amount">2,499</span>
          <span class="plan-duration">/ 3 Months</span>
        </div>
        <ul class="plan-features-list">
          <li><i class="fa-solid fa-circle-check"></i> All Basic Features Included</li>
          <li><i class="fa-solid fa-circle-check"></i> Cardio &amp; Strength Training Area</li>
          <li><i class="fa-solid fa-circle-check"></i> 1 Personal Trainer Consultation</li>
          <li><i class="fa-solid fa-circle-check"></i> Customized Nutrition Guidelines</li>
          <li><i class="fa-solid fa-circle-check"></i> Free Steam Bath (2x / Month)</li>
        </ul>
        <a href="<?= $p2_url ?>" class="btn btn-primary btn-block"><?= $p2_text ?></a>
      </div>

      <!-- Plan 3: PREMIUM -->
      <div class="pricing-card">
        <div class="plan-name">PREMIUM</div>
        <div class="plan-desc">Complete 365-day access with dedicated coaching and VIP perks.</div>
        <div class="plan-price-wrap">
          <span class="plan-currency">₹</span>
          <span class="plan-amount">7,999</span>
          <span class="plan-duration">/ Year</span>
        </div>
        <ul class="plan-features-list">
          <li><i class="fa-solid fa-circle-check"></i> Full 365-Day Unlimited Access</li>
          <li><i class="fa-solid fa-circle-check"></i> Dedicated Personal Coach Support</li>
          <li><i class="fa-solid fa-circle-check"></i> Tailored Workout &amp; Diet Plan</li>
          <li><i class="fa-solid fa-circle-check"></i> Unlimited Steam &amp; Sauna Access</li>
          <li><i class="fa-solid fa-circle-check"></i> Complimentary Gym Kit &amp; Bottle</li>
        </ul>
        <a href="<?= $p3_url ?>" class="btn btn-outline btn-block"><?= $p3_text ?></a>
      </div>
    </div>
  </div>
</section>

<!-- 6. TRAINERS -->
<section class="section" id="trainers" style="background: var(--bg-subtle);">
  <div class="container">
    <div class="section-title-wrap">
      <div class="section-tag"><i class="fa-solid fa-certificate"></i> Certified Coaches</div>
      <h2 class="section-title">Meet Our Expert Trainers</h2>
      <p class="section-desc">Passionate fitness professionals committed to accelerating your progress.</p>
    </div>

    <div class="trainers-grid">
      <!-- Trainer 1 -->
      <div class="trainer-card">
        <div class="trainer-photo-wrap">
          <img src="<?= $base_url ?>/assets/images/trainer-1.jpg" alt="Vikram Sharma">
          <div class="trainer-overlay"></div>
        </div>
        <div class="trainer-info">
          <div class="trainer-spec">Strength &amp; Fitness Coach</div>
          <h3 class="trainer-name">Vikram Sharma</h3>
          <div class="trainer-exp"><i class="fa-solid fa-briefcase"></i> 6+ Years Experience</div>
          <p class="trainer-bio">Certified IFBB coach specialized in hypertrophy, progressive overload, and competition conditioning.</p>
        </div>
      </div>

      <!-- Trainer 2 -->
      <div class="trainer-card">
        <div class="trainer-photo-wrap">
          <img src="<?= $base_url ?>/assets/images/trainer-2.jpg" alt="Priya Patel">
          <div class="trainer-overlay"></div>
        </div>
        <div class="trainer-info">
          <div class="trainer-spec">Yoga &amp; Functional Fitness</div>
          <h3 class="trainer-name">Priya Patel</h3>
          <div class="trainer-exp"><i class="fa-solid fa-briefcase"></i> 5+ Years Experience</div>
          <p class="trainer-bio">Yoga Alliance certified instructor focused on postural mobility, core power, and mental clarity.</p>
        </div>
      </div>

      <!-- Trainer 3 -->
      <div class="trainer-card">
        <div class="trainer-photo-wrap">
          <img src="<?= $base_url ?>/assets/images/trainer-3.jpg" alt="Rohit Verma">
          <div class="trainer-overlay"></div>
        </div>
        <div class="trainer-info">
          <div class="trainer-spec">Cardio &amp; Crossfit Coach</div>
          <h3 class="trainer-name">Rohit Verma</h3>
          <div class="trainer-exp"><i class="fa-solid fa-briefcase"></i> 4+ Years Experience</div>
          <p class="trainer-bio">CrossFit Level-2 coach specializing in high-intensity conditioning and explosive athletic speed.</p>
        </div>
      </div>

      <!-- Trainer 4 -->
      <div class="trainer-card">
        <div class="trainer-photo-wrap">
          <img src="<?= $base_url ?>/assets/images/trainer-4.jpg" alt="Neha Roy">
          <div class="trainer-overlay"></div>
        </div>
        <div class="trainer-info">
          <div class="trainer-spec">Diet &amp; Weight Loss Coach</div>
          <h3 class="trainer-name">Neha Roy</h3>
          <div class="trainer-exp"><i class="fa-solid fa-briefcase"></i> 5+ Years Experience</div>
          <p class="trainer-bio">Clinical sports nutritionist helping members build lean muscle while dropping stubborn fat sustainably.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 7. GALLERY -->
<section class="section" id="gallery">
  <div class="container">
    <div class="section-title-wrap">
      <div class="section-tag"><i class="fa-solid fa-images"></i> Facility Tour</div>
      <h2 class="section-title">Alexa Gym Gallery</h2>
      <p class="section-desc">Take a look inside our modern fitness and conditioning facility.</p>
    </div>

    <div class="gallery-grid">
      <div class="gallery-item">
        <img src="<?= $base_url ?>/assets/images/gallery-dumbbells.jpg" alt="Gym Dumbbell Rack">
        <div class="gallery-hover-overlay"><i class="fa-solid fa-magnifying-glass-plus" style="font-size: 28px; color: var(--cyan);"></i></div>
      </div>
      <div class="gallery-item">
        <img src="<?= $base_url ?>/assets/images/gallery-conditioning.jpg" alt="Cardio Training">
        <div class="gallery-hover-overlay"><i class="fa-solid fa-magnifying-glass-plus" style="font-size: 28px; color: var(--cyan);"></i></div>
      </div>
      <div class="gallery-item">
        <img src="<?= $base_url ?>/assets/images/gallery-routine.jpg" alt="Workout Routine">
        <div class="gallery-hover-overlay"><i class="fa-solid fa-magnifying-glass-plus" style="font-size: 28px; color: var(--cyan);"></i></div>
      </div>
      <div class="gallery-item">
        <img src="<?= $base_url ?>/assets/images/gallery-deadlift.jpg" alt="Heavy Lifting">
        <div class="gallery-hover-overlay"><i class="fa-solid fa-magnifying-glass-plus" style="font-size: 28px; color: var(--cyan);"></i></div>
      </div>
    </div>
  </div>
</section>

<!-- 8. TESTIMONIALS -->
<section class="section" style="background: var(--bg-subtle);">
  <div class="container">
    <div class="section-title-wrap">
      <div class="section-tag"><i class="fa-solid fa-comment-dots"></i> Member Reviews</div>
      <h2 class="section-title">What Our Members Say</h2>
      <p class="section-desc">Real stories from athletes and lifters achieving their dreams at Alexa Gym.</p>
    </div>

    <div class="testimonials-grid">
      <div class="testimonial-card">
        <div class="stars-row">
          <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
        </div>
        <p class="testimonial-quote">"The atmosphere here is pure motivation. High-end equipment, helpful coaches, and clean locker rooms make every training session a pleasure."</p>
        <div class="testimonial-user">
          <div class="user-avatar-round">AM</div>
          <div>
            <strong style="display: block; font-size: 15px;">Alex Member</strong>
            <small style="color: var(--text-muted);">Standard Pro Member &bull; 6 Months</small>
          </div>
        </div>
      </div>

      <div class="testimonial-card">
        <div class="stars-row">
          <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
        </div>
        <p class="testimonial-quote">"Vikram sir completely changed my physique. With his personalized diet chart and workout splits, I gained 6kg of lean muscle in 90 days."</p>
        <div class="testimonial-user">
          <div class="user-avatar-round">AK</div>
          <div>
            <strong style="display: block; font-size: 15px;">Amit Kumar</strong>
            <small style="color: var(--text-muted);">Annual VIP Member &bull; 1 Year</small>
          </div>
        </div>
      </div>

      <div class="testimonial-card">
        <div class="stars-row">
          <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
        </div>
        <p class="testimonial-quote">"The best gym in town without a doubt. The member dashboard makes tracking attendance and checking daily workouts super simple!"</p>
        <div class="testimonial-user">
          <div class="user-avatar-round">SG</div>
          <div>
            <strong style="display: block; font-size: 15px;">Sneha Gupta</strong>
            <small style="color: var(--text-muted);">Standard Pro Member &bull; 4 Months</small>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 9. CONTACT SECTION -->
<section class="section" id="contact">
  <div class="container">
    <div class="section-title-wrap">
      <div class="section-tag"><i class="fa-solid fa-envelope"></i> Get In Touch</div>
      <h2 class="section-title">Contact Alexa Gym</h2>
      <p class="section-desc">Have questions or want a free gym tour? Drop us a message below.</p>
    </div>

    <div class="contact-grid">
      <!-- Contact Info Card -->
      <div class="contact-info-card">
        <h3 style="font-size: 22px; font-weight: 800; margin-bottom: 24px;">Gym Information</h3>
        <div class="contact-item">
          <div class="contact-icon"><i class="fa-solid fa-location-dot"></i></div>
          <div>
            <strong style="display: block; font-size: 16px;">Gym Location:</strong>
            <span style="color: var(--text-muted); font-size: 14.5px;">Ground Floor, Shop No. 39, Shukan Plaza, Palanpur Highway, Vadgam, Banaskantha, Gujarat – 385410</span>
          </div>
        </div>
        <div class="contact-item">
          <div class="contact-icon"><i class="fa-solid fa-phone"></i></div>
          <div>
            <strong style="display: block; font-size: 16px;">Direct Phone:</strong>
            <span style="color: var(--text-muted); font-size: 14.5px;">+91 98765 43210 / +91 91234 56780</span>
          </div>
        </div>
        <div class="contact-item">
          <div class="contact-icon"><i class="fa-solid fa-envelope"></i></div>
          <div>
            <strong style="display: block; font-size: 16px;">Email Support:</strong>
            <span style="color: var(--text-muted); font-size: 14.5px;">support@alexagym.com</span>
          </div>
        </div>
        <div class="contact-item">
          <div class="contact-icon"><i class="fa-solid fa-clock"></i></div>
          <div>
            <strong style="display: block; font-size: 16px;">Opening Hours:</strong>
            <span style="color: var(--text-muted); font-size: 14.5px;">
              Monday – Saturday: 06:00 AM to 10:00 PM<br>
              Sunday: 07:00 AM to 01:00 PM
            </span>
          </div>
        </div>
      </div>

      <!-- Contact Form Card -->
      <div class="contact-form-card">
        <h3 style="font-size: 22px; font-weight: 800; margin-bottom: 20px;">Send Us A Message</h3>
        
        <?php if ($contact_success): ?>
          <div class="alert alert-success">
            <i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i>
            Thank you! Your message has been saved. Our team will contact you shortly.
          </div>
        <?php elseif ($contact_error): ?>
          <div class="alert alert-danger">
            <i class="fa-solid fa-triangle-exclamation" style="margin-right: 8px;"></i>
            <?= e($contact_error) ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="<?= $base_url ?>/index.php#contact" id="contact-form">
          <div class="form-group">
            <label class="form-label">Your Full Name *</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Rahul Deshmukh" required value="<?= e($_POST['name'] ?? '') ?>">
          </div>
          <div class="form-row-2">
            <div class="form-group">
              <label class="form-label">Email Address *</label>
              <input type="email" name="email" class="form-control" placeholder="you@example.com" required value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Phone Number</label>
              <input type="tel" name="phone" class="form-control" placeholder="98XXXXXXXX" value="<?= e($_POST['phone'] ?? '') ?>">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Message / Inquiry *</label>
            <textarea name="message" rows="4" class="form-control" placeholder="Ask about memberships, personal training, or trial sessions..." required><?= e($_POST['message'] ?? '') ?></textarea>
          </div>
          <button type="submit" name="contact_submit" class="btn btn-primary btn-block">
            <i class="fa-solid fa-paper-plane"></i> Send Message
          </button>
        </form>
      </div>
    </div>
  </div>
<!-- APP DOWNLOAD BANNER SECTION -->
<section style="padding: 70px 0; background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(0, 242, 254, 0.08) 100%), var(--panel); border-top: 1px solid var(--line); border-bottom: 1px solid var(--line);">
  <div class="container text-center" style="max-width: 800px; margin: 0 auto;">
    <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(16, 185, 129, 0.15); color: #10B981; padding: 6px 14px; border-radius: 30px; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 16px;">
      <i class="fa-brands fa-android"></i> Official Android App
    </div>
    <h2 style="font-size: 32px; font-weight: 900; color: var(--text); margin-bottom: 14px;">
      Get the Alexa Gym <span style="color: #10B981;">Android App (.apk)</span>
    </h2>
    <p style="color: var(--text-muted); font-size: 15px; line-height: 1.6; margin-bottom: 28px;">
      Download our official mobile app for seamless workout logging, attendance check-ins, and membership receipts on your Android phone and tablet.
    </p>
    <div style="display: flex; gap: 14px; justify-content: center; flex-wrap: wrap;">
      <a href="<?= $base_url ?>/download.php" class="btn btn-primary" style="background: linear-gradient(135deg, #10B981, #059669); border-color: #10B981; padding: 14px 28px; font-size: 14px; font-weight: 800; box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3); color: #ffffff !important;" download="AlexaGym.apk">
        <i class="fa-brands fa-android" style="margin-right: 8px;"></i> Download Android App (.apk)
      </a>
      <a href="<?= $base_url ?>/app.php" class="btn btn-outline" style="padding: 14px 26px; font-size: 14px;">
        <i class="fa-solid fa-circle-info" style="margin-right: 8px;"></i> App Details &amp; Guide
      </a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
