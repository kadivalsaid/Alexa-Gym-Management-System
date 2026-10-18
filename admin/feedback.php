<?php
/**
 * Alexa Gym — Admin Portal: Feedback & Reviews
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$msg_success = '';

// Toggle Approval
if (isset($_GET['toggle_approval'])) {
    $fb_id = (int)$_GET['toggle_approval'];
    $pdo->prepare("UPDATE feedback SET is_approved = NOT is_approved WHERE id = ?")->execute([$fb_id]);
    $msg_success = "Feedback visibility updated.";
}

// Delete
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    $pdo->prepare("DELETE FROM feedback WHERE id = ?")->execute([$del_id]);
    $msg_success = "Feedback #$del_id deleted.";
}

// Fetch all feedback
$feedback_list = $pdo->query("
    SELECT f.*, m.full_name AS member_name, m.phone AS member_phone 
    FROM feedback f 
    JOIN members m ON f.member_id = m.id 
    ORDER BY f.id DESC
")->fetchAll();

$page_title   = 'Member Feedback — Alexa Gym Admin';
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
  <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

  <main class="app-main">
    <?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>

    <div class="dashboard-content">
      <div style="margin-bottom: 28px;">
        <h2 style="font-size: 26px; font-weight: 800; margin-bottom: 4px;">Member Reviews &amp; Ratings</h2>
        <p style="color: var(--muted); font-size: 14.5px;">Monitor member satisfaction and approve reviews for public website testimonials.</p>
      </div>

      <?php if (!empty($msg_success)): ?>
        <div class="alert alert-success" style="margin-bottom: 20px;">
          <i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i> <?= e($msg_success) ?>
        </div>
      <?php endif; ?>

      <div class="card">
        <div class="custom-table-wrap">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Member</th>
                <th>Star Rating</th>
                <th>Review Comment</th>
                <th>Submitted Date</th>
                <th>Public Website</th>
                <th style="text-align: right;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($feedback_list)): ?>
                <?php foreach ($feedback_list as $fb): ?>
                  <tr>
                    <td>
                      <strong style="color: var(--text);"><?= e($fb['member_name']) ?></strong>
                      <small style="display: block; color: var(--muted);"><?= e($fb['member_phone']) ?></small>
                    </td>
                    <td>
                      <div style="color: #f59e0b; font-size: 13px;">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                          <i class="fa-solid fa-star" style="color: <?= $s <= (int)$fb['rating'] ? '#f59e0b' : 'var(--muted)' ?>;"></i>
                        <?php endfor; ?>
                      </div>
                    </td>
                    <td style="max-width: 360px; font-size: 14px; font-style: italic;">
                      "<?= e($fb['comment']) ?>"
                    </td>
                    <td><?= format_date($fb['created_at']) ?></td>
                    <td>
                      <?php if (!empty($fb['is_approved'])): ?>
                        <span class="badge-status active">Visible on Web</span>
                      <?php else: ?>
                        <span class="badge-status pending">Hidden</span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align: right; white-space: nowrap;">
                      <a href="feedback.php?toggle_approval=<?= $fb['id'] ?>" class="btn btn-outline btn-sm" style="margin-right: 6px; padding: 6px 12px; font-weight: 600; text-decoration: none;">
                        <?= !empty($fb['is_approved']) ? '<i class="fa-solid fa-eye-slash"></i> Hide' : '<i class="fa-solid fa-eye"></i> Show' ?>
                      </a>
                      <a href="feedback.php?delete_id=<?= $fb['id'] ?>" class="btn btn-outline btn-sm" style="color: var(--bad); border-color: rgba(239, 68, 68, 0.3); padding: 6px 10px; text-decoration: none;" title="Delete Review" onclick="return confirm('Delete review?')">
                        <i class="fa-solid fa-trash"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" style="text-align: center; color: var(--muted); padding: 32px;">No member reviews received yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>

<script src="../assets/js/user.js?v=2.0"></script>
</body>
</html>
