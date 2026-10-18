<?php
/**
 * Admin Portal Sidebar
 * Reuses the exact premium dark navy/blue/cyan visual style
 */
$current_page = basename($_SERVER['PHP_SELF'] ?? '');
$base_url = get_base_url();
$u = get_logged_in_user();

// Pending tickets and pending member requests count
$pending_tickets = 0;
$pending_members_count = 0;
if (isset($pdo)) {
    try {
        $p_t = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'Pending'");
        $pending_tickets = (int)$p_t->fetchColumn();

        $p_m = $pdo->query("SELECT COUNT(*) FROM membership_requests WHERE status = 'pending'");
        $pending_members_count = (int)$p_m->fetchColumn();
    } catch (Exception $e) {
        $pending_tickets = 0;
        $pending_members_count = 0;
    }
}
?>
<aside class="app-sidebar">
  <div class="sidebar-brand" style="padding: 18px 20px; display: flex; align-items: center; justify-content: space-between;">
    <div style="display: flex; align-items: center; gap: 12px;">
      <div class="sidebar-logo-box"><i class="fa-solid fa-dumbbell"></i></div>
      <div class="sidebar-brand-text">
        <span class="title">Alexa Gym</span>
        <span class="sub">GYM MANAGEMENT &bull; ADMIN</span>
      </div>
    </div>
    <button type="button" class="sidebar-close-btn" onclick="toggleSidebar()" aria-label="Close Sidebar">
      <i class="fa-solid fa-xmark"></i>
    </button>
  </div>

  <nav class="sidebar-nav" style="padding: 12px 10px; gap: 2px;">
    <a href="<?= $base_url ?>/admin/dashboard.php" class="<?= ($current_page === 'dashboard.php') ? 'active' : '' ?>">
      <i class="fa-solid fa-gauge-high"></i> Dashboard
    </a>
    <a href="<?= $base_url ?>/admin/members.php" class="<?= ($current_page === 'members.php') ? 'active' : '' ?>">
      <i class="fa-solid fa-users"></i> Members
      <?php if ($pending_members_count > 0): ?>
        <span class="badge-count" style="background: #fbbf24; color: #070913;" title="<?= $pending_members_count ?> Pending Requests"><?= $pending_members_count ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= $base_url ?>/admin/plans.php" class="<?= ($current_page === 'plans.php') ? 'active' : '' ?>">
      <i class="fa-solid fa-tag"></i> Plans
    </a>
    <a href="<?= $base_url ?>/admin/trainers.php" class="<?= ($current_page === 'trainers.php') ? 'active' : '' ?>">
      <i class="fa-solid fa-user-ninja"></i> Trainers
    </a>
    <a href="<?= $base_url ?>/admin/payments.php" class="<?= ($current_page === 'payments.php') ? 'active' : '' ?>">
      <i class="fa-solid fa-credit-card"></i> Payments
    </a>
    <a href="<?= $base_url ?>/admin/attendance.php" class="<?= ($current_page === 'attendance.php') ? 'active' : '' ?>">
      <i class="fa-solid fa-calendar-check"></i> Attendance
    </a>
    <a href="<?= $base_url ?>/admin/tickets.php" class="<?= ($current_page === 'tickets.php') ? 'active' : '' ?>">
      <i class="fa-solid fa-headset"></i> Support Tickets
      <?php if ($pending_tickets > 0): ?>
        <span class="badge-count" style="background: var(--bad); color: #fff;"><?= $pending_tickets ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= $base_url ?>/admin/feedback.php" class="<?= ($current_page === 'feedback.php') ? 'active' : '' ?>">
      <i class="fa-solid fa-star"></i> Member Feedback
    </a>
    <a href="<?= $base_url ?>/admin/reports.php" class="<?= ($current_page === 'reports.php') ? 'active' : '' ?>">
      <i class="fa-solid fa-chart-line"></i> Revenue &amp; Reports
    </a>
    <a href="<?= $base_url ?>/app.php" style="color: #10B981; margin-top: 10px; border: 1px dashed rgba(16, 185, 129, 0.4); border-radius: 8px;">
      <i class="fa-brands fa-android"></i> Android App (.apk)
    </a>
  </nav>
</aside>
