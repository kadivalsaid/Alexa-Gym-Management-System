<?php
/**
 * Alexa Gym — Photo Gallery
 */
$page_title = 'Photo Gallery — Inside Alexa Gym Facility';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

$gallery_images = [
    ['src' => $base_url . '/assets/images/facility-interior.jpg', 'title' => 'Main Training Bay'],
    ['src' => $base_url . '/assets/images/facility-strength.jpg', 'title' => 'Free Weight Arena'],
    ['src' => $base_url . '/assets/images/gallery-dumbbells.jpg', 'title' => 'Calibrated Dumbbell Section'],
    ['src' => $base_url . '/assets/images/facility-cardio.jpg', 'title' => 'Cardio & Treadmills'],
    ['src' => $base_url . '/assets/images/facility-strength.jpg', 'title' => 'Powerlifting Platforms'],
    ['src' => $base_url . '/assets/images/facility-crossfit.jpg', 'title' => 'Functional Turf Strip'],
    ['src' => $base_url . '/assets/images/gallery-conditioning.jpg', 'title' => 'Assault Conditioning'],
    ['src' => $base_url . '/assets/images/facility-lockers.jpg', 'title' => 'Locker & Shower Amenities'],
    ['src' => $base_url . '/assets/images/gallery-deadlift.jpg', 'title' => 'Olympic Deadlift Stalls']
];
?>

<section style="padding: 130px 0 60px 0; background: radial-gradient(circle at top center, rgba(0, 242, 254, 0.12), transparent 45%), var(--bg); border-bottom: 1px solid var(--line);">
  <div class="container text-center" style="text-align: center;">
    <div class="section-tag"><i class="fa-solid fa-camera"></i> Visual Tour</div>
    <h1 class="section-title">Alexa Gym Gallery</h1>
    <p class="section-desc" style="margin: 0 auto; max-width: 650px;">
      Take an interactive look at our cutting-edge training bays, heavy lifting equipment, and premium amenities.
    </p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="gallery-grid">
      <?php foreach ($gallery_images as $img): ?>
        <div class="gallery-item">
          <img src="<?= e($img['src']) ?>" alt="<?= e($img['title']) ?>">
          <div class="gallery-hover-overlay">
            <div style="text-align: center; color: #fff;">
              <i class="fa-solid fa-magnifying-glass-plus" style="font-size: 26px; color: var(--cyan); margin-bottom: 8px;"></i>
              <strong style="display: block; font-size: 15px;"><?= e($img['title']) ?></strong>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
