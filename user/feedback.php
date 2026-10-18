<?php
/**
 * Alexa Gym — Member Portal: Feedback & Reviews
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_member();

$u = get_logged_in_user();
$member = get_member_by_user_id($pdo, $u['id']);

$fb_success = '';
$fb_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $rating  = (int)($_POST['rating'] ?? 5);
    $comment = trim($_POST['comment'] ?? '');

    if (empty($comment)) {
        $fb_error = 'Please share a few words in your review comment.';
    } elseif ($rating < 1 || $rating > 5) {
        $fb_error = 'Please select a star rating between 1 and 5.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO feedback (member_id, rating, comment, is_approved, created_at) 
                VALUES (?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$member['id'], $rating, $comment]);
            $fb_success = 'Thank you for your feedback! Your review has been saved.';
        } catch (Exception $e) {
            $fb_error = 'Error saving feedback: ' . $e->getMessage();
        }
    }
}

// Fetch member's past feedback
$my_feedback = [];
if ($member) {
    $stmt = $pdo->prepare("SELECT * FROM feedback WHERE member_id = ? ORDER BY id DESC");
    $stmt->execute([$member['id']]);
    $my_feedback = $stmt->fetchAll();
}

$page_title   = 'Feedback & Reviews — Alexa Gym';
$topbar_title = 'Member Reviews & Ratings';
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
        <h2 style="font-size: 26px; font-weight: 800; margin-bottom: 4px;">Share Your Gym Experience</h2>
        <p style="color: var(--muted); font-size: 14.5px;">Your reviews help us continuously enhance the facility, training equipment, and member services.</p>
      </div>

      <div style="display: grid; grid-template-columns: 0.95fr 1.05fr; gap: 30px; align-items: start;">
        <!-- Submit Feedback Card -->
        <div class="card">
          <h3 class="card-title" style="margin-bottom: 16px;">
            <i class="fa-solid fa-star" style="color: #f59e0b; margin-right: 8px;"></i> Write A Review
          </h3>

          <?php if (!empty($fb_success)): ?>
            <div class="alert alert-success" style="margin-bottom: 18px;">
              <i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i> <?= e($fb_success) ?>
            </div>
          <?php elseif (!empty($fb_error)): ?>
            <div class="alert alert-danger" style="margin-bottom: 18px;">
              <i class="fa-solid fa-triangle-exclamation" style="margin-right: 8px;"></i> <?= e($fb_error) ?>
            </div>
          <?php endif; ?>

          <form method="POST" action="feedback.php">
            <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Select Rating (1 to 5 Stars)</label>
            <div class="star-rating-select" id="star-rating-widget">
              <i class="fa-solid fa-star active" data-val="1"></i>
              <i class="fa-solid fa-star active" data-val="2"></i>
              <i class="fa-solid fa-star active" data-val="3"></i>
              <i class="fa-solid fa-star active" data-val="4"></i>
              <i class="fa-solid fa-star active" data-val="5"></i>
            </div>
            <input type="hidden" name="rating" id="rating-input" value="5">

            <div style="margin-bottom: 20px;">
              <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Your Experience / Feedback *</label>
              <textarea name="comment" rows="5" class="form-control" placeholder="Share your experience about the trainers, equipment, hygiene, or gym vibe..." required></textarea>
            </div>

            <button type="submit" name="submit_feedback" class="btn btn-primary btn-block">
              <i class="fa-solid fa-paper-plane"></i> Submit Feedback
            </button>
          </form>
        </div>

        <!-- Previously Submitted Feedback -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-clock-rotate-left" style="color: var(--cyan); margin-right: 8px;"></i> My Past Reviews</h3>
          </div>

          <div style="display: flex; flex-direction: column; gap: 16px;">
            <?php if (!empty($my_feedback)): ?>
              <?php foreach ($my_feedback as $fb): ?>
                <div style="background: var(--panel-2); border-radius: 12px; padding: 18px; border: 1px solid var(--line);">
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <div style="color: #f59e0b; font-size: 14px;">
                      <?php for ($s = 1; $s <= 5; $s++): ?>
                        <i class="fa-solid fa-star" style="color: <?= $s <= (int)$fb['rating'] ? '#f59e0b' : 'var(--muted)' ?>;"></i>
                      <?php endfor; ?>
                    </div>
                    <span style="font-size: 12px; color: var(--muted);"><?= format_date($fb['created_at']) ?></span>
                  </div>
                  <p style="font-size: 14px; color: var(--text); line-height: 1.5; font-style: italic;">
                    "<?= e($fb['comment']) ?>"
                  </p>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p style="color: var(--muted); font-size: 14px; text-align: center; padding: 24px;">
                You haven't submitted any feedback yet. Share your rating using the form!
              </p>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<script src="../assets/js/user.js?v=2.0"></script>
</body>
</html>
