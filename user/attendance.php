<?php
/**
 * Alexa Gym — Member Portal: Attendance
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

$attendance_records = [];
$total_days = 0;
$current_month_days = 0;
$percentage = 0;

if ($member) {
    // 1. Fetch all attendance
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE member_id = ? ORDER BY attend_date DESC");
    $stmt->execute([$member['id']]);
    $attendance_records = $stmt->fetchAll();
    $total_days = count($attendance_records);

    // 2. Current Month count
    $m_stmt = $pdo->prepare("
        SELECT COUNT(*) FROM attendance 
        WHERE member_id = ? 
        AND MONTH(attend_date) = MONTH(CURRENT_DATE()) 
        AND YEAR(attend_date) = YEAR(CURRENT_DATE())
    ");
    $m_stmt->execute([$member['id']]);
    $current_month_days = (int)$m_stmt->fetchColumn();

    // 3. Attendance Percentage (relative to elapsed days this month or gym open days: say 26 working days/month)
    $working_days_in_month = max(1, date('j')); // days elapsed this month
    $percentage = min(100, round(($current_month_days / $working_days_in_month) * 100));
}

$page_title   = 'My Attendance — Alexa Gym';
$topbar_title = 'Attendance Records';
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
        <h2 style="font-size: 26px; font-weight: 800; margin-bottom: 4px;">My Workout Attendance</h2>
        <p style="color: var(--muted); font-size: 14.5px;">Consistent check-ins lead to championship results. Track your daily gym visits.</p>
      </div>

      <!-- Attendance Metric Cards -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon-wrapper">
            <i class="fa-solid fa-calendar-check"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Total Gym Days</div>
            <div class="stat-val"><?= $total_days ?> <span style="font-size: 14px; color: var(--muted);">Sessions</span></div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon-wrapper" style="color: #10b981; background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.25);">
            <i class="fa-solid fa-fire-flame-curved"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Current Month Check-ins</div>
            <div class="stat-val"><?= $current_month_days ?> <span style="font-size: 14px; color: var(--muted);">Days</span></div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon-wrapper" style="color: var(--cyan); background: rgba(0, 242, 254, 0.1); border-color: rgba(0, 242, 254, 0.25);">
            <i class="fa-solid fa-chart-pie"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Attendance Rate</div>
            <div class="stat-val"><?= $percentage ?>%</div>
          </div>
        </div>
      </div>

      <!-- Attendance History Table -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><i class="fa-solid fa-list-check" style="color: var(--cyan); margin-right: 8px;"></i> Daily Check-in Log</h3>
          <span style="color: var(--muted); font-size: 13.5px;">Records captured at front entrance scanner</span>
        </div>

        <div class="custom-table-wrap">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Check-In Time</th>
                <th>Check-Out Time</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($attendance_records)): ?>
                <?php foreach ($attendance_records as $att): ?>
                  <tr>
                    <td><strong><?= format_date($att['attend_date']) ?></strong></td>
                    <td>
                      <i class="fa-regular fa-clock" style="color: var(--cyan); margin-right: 6px;"></i>
                      <?= !empty($att['check_in']) ? date('h:i A', strtotime($att['check_in'])) : '06:30 AM' ?>
                    </td>
                    <td>
                      <i class="fa-regular fa-clock" style="color: var(--muted); margin-right: 6px;"></i>
                      <?= !empty($att['check_out']) ? date('h:i A', strtotime($att['check_out'])) : '08:00 AM' ?>
                    </td>
                    <td>
                      <span class="badge-status present">
                        <?= strtoupper($att['status'] ?? 'PRESENT') ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="4" style="text-align: center; color: var(--muted); padding: 32px;">No attendance check-ins recorded yet.</td>
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
