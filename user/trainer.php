<?php
/**
 * Alexa Gym — Member Portal: My Trainer
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_member();

$u = get_logged_in_user();
$member = get_member_by_user_id($pdo, $u['id']);

if (!$member || empty($member['plan_id']) || ($member['status'] ?? '') !== 'active') {
    header('Location: dashboard.php');
    exit;
}

$trainer = null;
if (!empty($member['trainer_id'])) {
    $t_stmt = $pdo->prepare("SELECT * FROM trainers WHERE id = ?");
    $t_stmt->execute([$member['trainer_id']]);
    $trainer = $t_stmt->fetch();
}

// Fallback to default lead coach if none explicitly assigned
if (!$trainer) {
    $trainer = $pdo->query("SELECT * FROM trainers ORDER BY id ASC LIMIT 1")->fetch();
}

$page_title   = 'My Trainer — Alexa Gym';
$topbar_title = 'Assigned Fitness Coach';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/admin.css?v=2.3">
</head>
<body>

<div class="app-layout">
  <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

  <main class="app-main">
    <?php require_once __DIR__ . '/../includes/user_topbar.php'; ?>

    <div class="dashboard-content">
      <div style="margin-bottom: 28px;">
        <h2 style="font-size: 26px; font-weight: 800; margin-bottom: 4px;">My Assigned Personal Trainer</h2>
        <p style="color: var(--muted); font-size: 14.5px;">Your coach oversees your training splits, nutritional goals, and biomechanics.</p>
      </div>

      <div style="display: grid; grid-template-columns: 0.9fr 1.1fr; gap: 32px; align-items: start;">
        <!-- Trainer Bio Card -->
        <div class="card" style="padding: 36px; text-align: center;">
          <div style="width: 140px; height: 140px; border-radius: 30px; overflow: hidden; margin: 0 auto 20px auto; border: 2px solid var(--cyan); box-shadow: 0 0 25px var(--cyan-glow);">
            <img src="<?= e($trainer['photo'] ?: '../assets/images/trainer-1.jpg') ?>" alt="<?= e($trainer['full_name'] ?? 'Coach') ?>" style="width: 100%; height: 100%; object-fit: cover;">
          </div>
          <span class="badge-status active" style="margin-bottom: 12px;"><?= e($trainer['specialization'] ?? 'Head Strength Coach') ?></span>
          <h3 style="font-size: 24px; font-weight: 800; margin-bottom: 6px;"><?= e($trainer['full_name'] ?? 'Vikram Sharma') ?></h3>
          <p style="color: var(--muted); font-size: 14px; margin-bottom: 24px;">
            <?= e($trainer['experience'] ?? '6+ Years Experience') ?>
          </p>

          <p style="color: var(--text-muted); font-size: 14.5px; line-height: 1.6; text-align: left; margin-bottom: 24px;">
            <?= e($trainer['bio'] ?? 'Certified IFBB Pro coach specialized in progressive overload, hypertrophy mechanics, and injury-free conditioning.') ?>
          </p>

          <div style="display: flex; flex-direction: column; gap: 12px; text-align: left; border-top: 1px solid var(--line); padding-top: 20px;">
            <div style="display: flex; justify-content: space-between; font-size: 14px;">
              <span style="color: var(--muted);"><i class="fa-solid fa-phone" style="color: var(--cyan); margin-right: 6px;"></i> Direct Phone:</span>
              <strong><?= e($trainer['phone'] ?? '+91 98765 43210') ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 14px;">
              <span style="color: var(--muted);"><i class="fa-solid fa-envelope" style="color: var(--cyan); margin-right: 6px;"></i> Coach Email:</span>
              <strong><?= e($trainer['email'] ?? 'trainer@alexagym.com') ?></strong>
            </div>
          </div>
        </div>

        <!-- Training Schedule & Booking -->
        <div>
          <div class="card">
            <h3 class="card-title" style="margin-bottom: 18px;">
              <i class="fa-solid fa-calendar-days" style="color: var(--cyan); margin-right: 8px;"></i> Coach Availability Schedule
            </h3>
            
            <div style="display: flex; flex-direction: column; gap: 12px;">
              <div style="display: flex; justify-content: space-between; padding: 12px 16px; background: var(--panel-2); border-radius: 10px;">
                <span><strong>Monday – Wednesday:</strong> Morning Strength Sessions</span>
                <span style="color: var(--cyan); font-weight: 700;">06:00 AM – 11:00 AM</span>
              </div>
              <div style="display: flex; justify-content: space-between; padding: 12px 16px; background: var(--panel-2); border-radius: 10px;">
                <span><strong>Thursday – Saturday:</strong> Evening Hypertrophy</span>
                <span style="color: var(--cyan); font-weight: 700;">05:00 PM – 09:30 PM</span>
              </div>
              <div style="display: flex; justify-content: space-between; padding: 12px 16px; background: var(--panel-2); border-radius: 10px;">
                <span><strong>Sunday:</strong> Mobility &amp; Diet Consultation</span>
                <span style="color: #f59e0b; font-weight: 700;">By Appointment Only</span>
              </div>
            </div>
          </div>

          <!-- Coach Consultation Ticket -->
          <div class="card">
            <h3 class="card-title" style="margin-bottom: 14px;">
              <i class="fa-solid fa-message" style="color: var(--cyan); margin-right: 8px;"></i> Need Diet or Routine Adjustments?
            </h3>
            <p style="color: var(--muted); font-size: 14px; margin-bottom: 18px;">
              Have your coach update your caloric targets or change your training split by submitting a request ticket.
            </p>
            <a href="tickets.php?category=Trainer" class="btn btn-primary btn-sm">
              <i class="fa-solid fa-headset"></i> Message Coach via Support Ticket
            </a>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<script src="../assets/js/user.js?v=2.0"></script>
</body>
</html>
