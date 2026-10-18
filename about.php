<?php
/**
 * Alexa Gym — About Page
 */
$page_title = 'About Alexa Gym — Our Mission, Culture & Equipment';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Banner -->
<section style="padding: 130px 0 60px 0; background: radial-gradient(circle at top right, rgba(0, 242, 254, 0.15), transparent 40%), var(--bg); border-bottom: 1px solid var(--line);">
  <div class="container text-center" style="text-align: center;">
    <div class="section-tag"><i class="fa-solid fa-fire"></i> Our Story &amp; Vision</div>
    <h1 class="section-title">About Alexa Gym</h1>
    <p class="section-desc" style="margin: 0 auto; max-width: 650px;">
      Empowering athletes, everyday lifters, and fitness newcomers to build their best physical strength, endurance, and mental grit.
    </p>
  </div>
</section>

<!-- Mission & Vision -->
<section class="section">
  <div class="container">
    <div class="about-grid about-mission-grid">
      <div>
        <h2 style="font-size: 32px; font-weight: 800; margin-bottom: 18px;">Our Core Mission</h2>
        <p style="color: var(--text-muted); font-size: 16px; line-height: 1.8; margin-bottom: 20px;">
          At Alexa Gym, we believe fitness is not a seasonal resolution — it is a lifelong habit. Founded over 5 years ago, our goal was to remove generic cookie-cutter routines and build a gym where every member receives structured coaching, access to heavy iron, and advanced cardiovascular machinery.
        </p>
        <p style="color: var(--text-muted); font-size: 16px; line-height: 1.8;">
          From teenagers stepping into a weight room for the first time to master powerlifters breaking state records, Alexa Gym provides the supportive community and scientific methodology needed to excel.
        </p>
      </div>
      <div>
        <div class="about-img-box" style="border-radius: var(--radius-lg); overflow: hidden; border: 1px solid var(--line); box-shadow: var(--shadow-md);">
          <img src="<?= $base_url ?>/assets/images/facility-strength.jpg" alt="Alexa Gym Strength Floor">
        </div>
      </div>
    </div>

    <!-- Values Grid -->
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon-box"><i class="fa-solid fa-bullseye"></i></div>
        <h3 class="feature-title">Goal-Driven Training</h3>
        <p class="feature-desc">We do not believe in aimless workouts. Every exercise split is calibrated for hypertrophy, power, or fat oxidation.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon-box"><i class="fa-solid fa-medal"></i></div>
        <h3 class="feature-title">Certified Guidance</h3>
        <p class="feature-desc">All coaches hold recognized IFBB, ACE, or NSCA certifications and undergo regular biomechanics workshops.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon-box"><i class="fa-solid fa-shield-heart"></i></div>
        <h3 class="feature-title">Uncompromising Hygiene</h3>
        <p class="feature-desc">Hourly equipment sanitization, fresh towel service, and temperature-regulated locker rooms ensure pristine safety.</p>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section style="padding: 70px 0; background: linear-gradient(135deg, rgba(0, 242, 254, 0.1) 0%, rgba(124, 58, 237, 0.15) 100%); border-top: 1px solid var(--line);">
  <div class="container" style="text-align: center;">
    <h2 style="font-size: 32px; font-weight: 800; margin-bottom: 14px;">Ready To Start Your Transformation?</h2>
    <?php
    $is_user = is_logged_in();
    $logged_user = get_logged_in_user();
    $user_role = $logged_user['role'] ?? '';
    if ($user_role === 'admin') {
        $about_cta_url = $base_url . '/admin/dashboard.php';
        $about_cta_text = 'ADMIN DASHBOARD';
    } elseif ($user_role === 'member') {
        $about_cta_url = $base_url . '/user/dashboard.php';
        $about_cta_text = 'MY DASHBOARD';
    } else {
        $about_cta_url = $base_url . '/auth/register.php';
        $about_cta_text = 'JOIN ALEXA GYM';
    }
    ?>
    <a href="<?= $about_cta_url ?>" class="btn btn-primary"><i class="fa-solid fa-dumbbell"></i> <?= $about_cta_text ?></a>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
