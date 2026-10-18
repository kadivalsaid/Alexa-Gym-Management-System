<?php
/**
 * Alexa Gym — Member Portal: Notifications
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_member();

$u = get_logged_in_user();

// Auto-cleanup: Automatically delete read notifications older than 30 days
try {
    $pdo->query("DELETE FROM notifications WHERE is_read = 1 AND created_at < NOW() - INTERVAL 30 DAY");
} catch (Exception $e) {}

// Handle Mark All Read
if (isset($_GET['mark_all_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$u['id']]);
    header('Location: notifications.php');
    exit;
}

// Handle Clear All Read Notifications
if (isset($_GET['clear_read'])) {
    $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 1")->execute([$u['id']]);
    header('Location: notifications.php');
    exit;
}

// Handle Single Notification Delete
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?")->execute([$del_id, $u['id']]);
    header('Location: notifications.php');
    exit;
}

// Fetch all notifications for this member
$notifs = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC");
    $stmt->execute([$u['id']]);
    $notifs = $stmt->fetchAll();
} catch (Exception $e) {
    $notifs = [];
}

$page_title   = 'Notifications — Alexa Gym';
$topbar_title = 'System & Gym Notifications';
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
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; flex-wrap: wrap; gap: 16px;">
        <div>
          <h2 style="font-size: 26px; font-weight: 800; margin-bottom: 4px;">Notifications &amp; Alerts</h2>
          <p style="color: var(--muted); font-size: 14.5px;">Stay up-to-date with membership renewals, payment confirmations, and trainer updates.</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
          <a href="notifications.php?mark_all_read=1" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-check-double"></i> Mark All As Read
          </a>
          <a href="notifications.php?clear_read=1" class="btn btn-outline btn-sm" style="color: var(--bad); border-color: rgba(239, 68, 68, 0.35);" onclick="return confirm('Are you sure you want to clear all read notifications?');">
            <i class="fa-regular fa-trash-can"></i> Clear Read
          </a>
        </div>
      </div>

      <div class="card">
        <div style="display: flex; flex-direction: column; gap: 14px;">
          <?php if (!empty($notifs)): ?>
            <?php foreach ($notifs as $n): 
                $border_col = 'var(--cyan)';
                if ($n['type'] === 'success') $border_col = 'var(--ok)';
                if ($n['type'] === 'warning') $border_col = 'var(--warning)';
                if ($n['type'] === 'danger')  $border_col = 'var(--bad)';
            ?>
              <div style="padding: 16px 20px; border-radius: 12px; background: var(--panel-2); border-left: 4px solid <?= $border_col ?>; display: flex; justify-content: space-between; align-items: flex-start; gap: 16px;">
                <div>
                  <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                    <strong style="font-size: 15px; color: var(--text);"><?= e($n['title']) ?></strong>
                    <?php if (!$n['is_read']): ?>
                      <span class="badge-count" style="font-size: 10px; padding: 2px 6px;">NEW</span>
                    <?php endif; ?>
                  </div>
                  <p style="color: var(--text-muted); font-size: 14px; line-height: 1.5; margin: 0;">
                    <?= e($n['message']) ?>
                  </p>
                </div>
                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px; flex-shrink: 0;">
                  <span style="font-size: 12px; color: var(--muted); white-space: nowrap;">
                    <?= format_date($n['created_at'], 'd M Y, h:i A') ?>
                  </span>
                  <a href="notifications.php?delete_id=<?= (int)$n['id'] ?>" 
                     title="Delete notification"
                     onclick="return confirm('Delete this notification?');"
                     style="color: var(--muted); font-size: 13px; padding: 4px 7px; border-radius: 6px; transition: all 0.2s;"
                     onmouseover="this.style.color='var(--bad)'; this.style.background='rgba(239, 68, 68, 0.12)';"
                     onmouseout="this.style.color='var(--muted)'; this.style.background='transparent';">
                    <i class="fa-regular fa-trash-can"></i>
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div style="text-align: center; color: var(--muted); padding: 40px;">
              <i class="fa-regular fa-bell-slash" style="font-size: 38px; color: var(--line); margin-bottom: 12px; display: block;"></i>
              No notifications yet.
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </main>
</div>

<script src="../assets/js/user.js?v=2.0"></script>
</body>
</html>
