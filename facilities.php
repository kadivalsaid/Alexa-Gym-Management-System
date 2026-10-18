<?php
/**
 * Alexa Gym — Facilities Page
 */
$page_title = 'Gym Facilities & Equipment — Alexa Gym';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<section style="padding: 130px 0 60px 0; background: radial-gradient(circle at top left, rgba(0, 242, 254, 0.12), transparent 40%), var(--bg); border-bottom: 1px solid var(--line);">
  <div class="container text-center" style="text-align: center;">
    <div class="section-tag"><i class="fa-solid fa-building-columns"></i> Premium Amenities</div>
    <h1 class="section-title">World-Class Facilities</h1>
    <p class="section-desc" style="margin: 0 auto; max-width: 650px;">
      Explore our fully equipped training zones engineered to elevate every dimension of your athletic performance.
    </p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="features-grid">
      <!-- 1 -->
      <div class="facility-card">
        <div class="facility-img-box">
          <img src="<?= $base_url ?>/assets/images/facility-strength.jpg" alt="Weight Training Zone">
          <div class="facility-img-overlay"></div>
        </div>
        <div class="facility-body">
          <h3 class="feature-title"><i class="fa-solid fa-weight-hanging" style="color: var(--cyan); margin-right: 8px;"></i> Weight Training</h3>
          <p class="feature-desc">
            Heavy free-weight zone with urethane dumbbells from 2.5kg up to 60kg, calibrated Olympic plates, 6 power racks, and deadlift shock-absorption platforms.
          </p>
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
          <p class="feature-desc">
            High-tech commercial running decks, curved assault treadmills, Concept2 rowing machines, air bikes, and spin stations equipped with interactive biometric consoles.
          </p>
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
          <p class="feature-desc">
            Dual-pulley cable towers, linear hack squat stations, pendulum squats, seated chest press, and lateral raise machinery for targeted hyper-focused muscle isolation.
          </p>
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
          <p class="feature-desc">
            Dedicated private coaching zones where our certified trainers assess posture, prescribe dynamic warm-ups, and supervise high-intensity strength programs.
          </p>
        </div>
      </div>

      <!-- 5 -->
      <div class="facility-card">
        <div class="facility-img-box">
          <img src="<?= $base_url ?>/assets/images/facility-crossfit.jpg" alt="Cross Training Zone">
          <div class="facility-img-overlay"></div>
        </div>
        <div class="facility-body">
          <h3 class="feature-title"><i class="fa-solid fa-bolt" style="color: var(--cyan); margin-right: 8px;"></i> Cross Training</h3>
          <p class="feature-desc">
            40-meter artificial turf strip for prowler sled pushes, heavy battle ropes, slam balls, plyometric wooden boxes, and gymnastic pull-up rigs.
          </p>
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
          <p class="feature-desc">
            Electronic key-coded lockers, private rain showers, hot steam sauna, hair dryers, and grooming stations designed for total post-workout relaxation.
          </p>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
