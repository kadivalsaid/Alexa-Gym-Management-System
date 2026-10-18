<?php
/**
 * Member Portal Sidebar — Alexa Gym
 * Matches Alexa Gym existing dark navy/blue/cyan visual identity
 */
$current_page = basename($_SERVER['PHP_SELF'] ?? '');
$base_url = get_base_url();
$u = get_logged_in_user();

// Fetch unread notification count & member status
$unread_count = 0;
$m_status = 'pending'; // default
if ($u && isset($pdo)) {
    try {
        $n_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $n_stmt->execute([$u['id']]);
        $unread_count = (int)$n_stmt->fetchColumn();

        $st_stmt = $pdo->prepare("SELECT status, plan_id, expiry_date FROM members WHERE user_id = ? LIMIT 1");
        $st_stmt->execute([$u['id']]);
        $st_row = $st_stmt->fetch();
        if ($st_row) {
            $is_exp = (!empty($st_row['expiry_date']) && strtotime($st_row['expiry_date']) < time());
            if ($is_exp) {
                $m_status = 'expired';
            } elseif (strtolower($st_row['status'] ?? '') === 'active') {
                $m_status = 'active'; // Approved by admin
            } elseif (strtolower($st_row['status'] ?? '') === 'pending') {
                $m_status = 'in_progress';
            } else {
                $m_status = strtolower($st_row['status'] ?? 'pending');
            }
        } else {
            // Check for pending request in membership_requests
            $req_stmt = $pdo->prepare("SELECT id FROM membership_requests WHERE user_id = ? AND status = 'pending' LIMIT 1");
            $req_stmt->execute([$u['id']]);
            if ($req_stmt->fetch()) {
                $m_status = 'in_progress';
            } else {
                $m_status = 'pending';
            }
        }
    } catch (Exception $e) {
        $unread_count = 0;
    }
}
$is_active_member = ($m_status === 'active');
?>
<aside class="app-sidebar">
  <div class="sidebar-brand" style="display: flex; align-items: center; justify-content: space-between;">
    <div style="display: flex; align-items: center; gap: 12px;">
      <div class="sidebar-logo-box"><i class="fa-solid fa-dumbbell"></i></div>
      <div class="sidebar-brand-text">
        <span class="title">Alexa Gym</span>
        <span class="sub">MEMBER PORTAL</span>
      </div>
    </div>
    <button type="button" class="sidebar-close-btn" onclick="toggleSidebar()" aria-label="Close Sidebar">
      <i class="fa-solid fa-xmark"></i>
    </button>
  </div>

  <nav class="sidebar-nav">
    <!-- 1. Dashboard -->
    <a href="<?= $base_url ?>/user/dashboard.php" class="<?= ($current_page === 'dashboard.php') ? 'active' : '' ?>">
      <i class="fa-solid fa-gauge-high"></i> Dashboard
      <?php if ($m_status === 'in_progress'): ?>
        <span style="margin-left: auto; font-size: 10px; font-weight: 700; background: rgba(0, 242, 254, 0.15); color: var(--cyan); padding: 2px 7px; border-radius: 6px;">IN PROGRESS</span>
      <?php elseif ($m_status === 'active'): ?>
        <span style="margin-left: auto; font-size: 10px; font-weight: 700; background: rgba(16, 185, 129, 0.2); color: #34d399; padding: 2px 7px; border-radius: 6px;">ACTIVE</span>
      <?php elseif ($m_status === 'expired'): ?>
        <span style="margin-left: auto; font-size: 10px; font-weight: 700; background: rgba(239, 68, 68, 0.2); color: #f87171; padding: 2px 7px; border-radius: 6px;">EXPIRED</span>
      <?php endif; ?>
    </a>

    <!-- 3. Membership -->
    <?php if ($is_active_member): ?>
      <a href="<?= $base_url ?>/user/membership.php" class="<?= ($current_page === 'membership.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-id-card"></i> Membership
      </a>
    <?php else: ?>
      <a href="<?= $base_url ?>/user/dashboard.php<?= ($m_status === 'pending') ? '#membership-plans' : '' ?>" style="opacity: 0.55;" title="<?= ($m_status === 'in_progress') ? 'Locked until Admin Approval' : 'Choose a plan first' ?>">
        <i class="fa-solid fa-id-card"></i> Membership
        <span style="margin-left: auto; font-size: 11px;"><i class="fa-solid fa-lock"></i></span>
      </a>
    <?php endif; ?>

    <!-- 4. Membership Plans (Always accessible) -->
    <a href="<?= $base_url ?>/user/plans.php" class="<?= ($current_page === 'plans.php') ? 'active' : '' ?>">
      <i class="fa-solid fa-tag"></i> Membership Plans
    </a>

    <!-- 5. Payments -->
    <a href="<?= $base_url ?>/user/payments.php" class="<?= ($current_page === 'payments.php') ? 'active' : '' ?>">
      <i class="fa-solid fa-credit-card"></i> Payments
    </a>

    <!-- 6. Attendance -->
    <?php if ($is_active_member): ?>
      <a href="<?= $base_url ?>/user/attendance.php" class="<?= ($current_page === 'attendance.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-calendar-check"></i> Attendance
      </a>
    <?php else: ?>
      <a href="<?= $base_url ?>/user/dashboard.php<?= ($m_status === 'pending') ? '#membership-plans' : '' ?>" style="opacity: 0.55;" title="<?= ($m_status === 'in_progress') ? 'Locked until Admin Approval' : 'Choose a plan first' ?>">
        <i class="fa-solid fa-calendar-check"></i> Attendance
        <span style="margin-left: auto; font-size: 11px;"><i class="fa-solid fa-lock"></i></span>
      </a>
    <?php endif; ?>

    <!-- 7. My Workout -->
    <?php if ($is_active_member): ?>
      <a href="<?= $base_url ?>/user/workout.php" class="<?= ($current_page === 'workout.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-dumbbell"></i> My Workout
      </a>
    <?php else: ?>
      <a href="<?= $base_url ?>/user/dashboard.php<?= ($m_status === 'pending') ? '#membership-plans' : '' ?>" style="opacity: 0.55;" title="<?= ($m_status === 'in_progress') ? 'Locked until Admin Approval' : 'Choose a plan first' ?>">
        <i class="fa-solid fa-dumbbell"></i> My Workout
        <span style="margin-left: auto; font-size: 11px;"><i class="fa-solid fa-lock"></i></span>
      </a>
    <?php endif; ?>

    <!-- 8. My Trainer -->
    <?php if ($is_active_member): ?>
      <a href="<?= $base_url ?>/user/trainer.php" class="<?= ($current_page === 'trainer.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-user-ninja"></i> My Trainer
      </a>
    <?php else: ?>
      <a href="<?= $base_url ?>/user/dashboard.php<?= ($m_status === 'pending') ? '#membership-plans' : '' ?>" style="opacity: 0.55;" title="<?= ($m_status === 'in_progress') ? 'Locked until Admin Approval' : 'Choose a plan first' ?>">
        <i class="fa-solid fa-user-ninja"></i> My Trainer
        <span style="margin-left: auto; font-size: 11px;"><i class="fa-solid fa-lock"></i></span>
      </a>
    <?php endif; ?>

    <!-- 9. My Tickets (Always accessible) -->
    <a href="<?= $base_url ?>/user/tickets.php" class="<?= ($current_page === 'tickets.php') ? 'active' : '' ?>">
      <i class="fa-solid fa-headset"></i> My Tickets
    </a>

    <!-- 10. Feedback (Always accessible) -->
    <a href="<?= $base_url ?>/user/feedback.php" class="<?= ($current_page === 'feedback.php') ? 'active' : '' ?>">
      <i class="fa-solid fa-star"></i> Feedback
    </a>

    <!-- 11. Download App -->
    <a href="<?= $base_url ?>/app.php" style="color: #10B981; margin-top: 10px; border: 1px dashed rgba(16, 185, 129, 0.4); border-radius: 8px;">
      <i class="fa-brands fa-android"></i> Android App (.apk)
    </a>

  </nav>
</aside>
