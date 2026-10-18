<?php
/**
 * Alexa Gym — Admin Portal: Dashboard
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

// Compute real-time analytics from MySQL (Only accepted/active members with an approved plan are counted)
$total_members = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active' AND plan_id IS NOT NULL")->fetchColumn();
$active_members = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active' AND plan_id IS NOT NULL")->fetchColumn();
$expiring_soon = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active' AND plan_id IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
$total_trainers = (int)$pdo->query("SELECT COUNT(*) FROM trainers WHERE status = 'active'")->fetchColumn();
$month_revenue = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE MONTH(paid_on) = MONTH(CURDATE()) AND YEAR(paid_on) = YEAR(CURDATE()) AND status = 'completed'")->fetchColumn();
$today_checkins = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE attend_date = CURDATE()")->fetchColumn();
$pending_tickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'Pending'")->fetchColumn();
$pending_m_cnt = (int)$pdo->query("SELECT COUNT(*) FROM membership_requests WHERE status = 'pending'")->fetchColumn();

// Recent members (Only accepted/active members)
$recent_members = $pdo->query("
    SELECT m.*, p.name AS plan_name 
    FROM members m 
    INNER JOIN plans p ON m.plan_id = p.id 
    WHERE m.status = 'active'
    ORDER BY m.id DESC LIMIT 5
")->fetchAll();

// Recent payments
$recent_payments = $pdo->query("
    SELECT p.*, m.full_name AS member_name 
    FROM payments p 
    LEFT JOIN members m ON p.member_id = m.id 
    ORDER BY p.id DESC LIMIT 5
")->fetchAll();

// --- 1. TRAINER CIRCULAR CHART DATA ---
$trainer_chart_data = $pdo->query("
    SELECT 
        t.id, 
        t.full_name, 
        t.specialization,
        COUNT(m.id) AS member_count
    FROM trainers t
    LEFT JOIN members m ON m.trainer_id = t.id AND m.status = 'active'
    WHERE t.status = 'active'
    GROUP BY t.id, t.full_name, t.specialization
    ORDER BY member_count DESC, t.full_name ASC
")->fetchAll();

$unassigned_trainers_count = (int)$pdo->query("
    SELECT COUNT(*) FROM members 
    WHERE status = 'active' AND (trainer_id IS NULL OR trainer_id = 0)
")->fetchColumn();

$total_assigned_members = array_sum(array_column($trainer_chart_data, 'member_count'));
$color_palette = ['#00f2fe', '#a855f7', '#10b981', '#f59e0b', '#f43f5e', '#38bdf8', '#ec4899', '#6366f1'];

$js_trainer_labels = [];
$js_trainer_data = [];
$js_trainer_colors = [];

if (($total_assigned_members + $unassigned_trainers_count) > 0) {
    $t_i = 0;
    foreach ($trainer_chart_data as $tc) {
        $cnt = (int)$tc['member_count'];
        if ($cnt > 0) {
            $js_trainer_labels[] = $tc['full_name'];
            $js_trainer_data[] = $cnt;
            $js_trainer_colors[] = $color_palette[$t_i % count($color_palette)];
        }
        $t_i++;
    }
    if ($unassigned_trainers_count > 0) {
        $js_trainer_labels[] = 'Unassigned';
        $js_trainer_data[] = $unassigned_trainers_count;
        $js_trainer_colors[] = '#64748b';
    }
}

if (empty($js_trainer_data)) {
    $t_i = 0;
    foreach ($trainer_chart_data as $tc) {
        $js_trainer_labels[] = $tc['full_name'];
        $js_trainer_data[] = 1;
        $js_trainer_colors[] = $color_palette[$t_i % count($color_palette)];
        $t_i++;
    }
    if (empty($js_trainer_data)) {
        $js_trainer_labels[] = 'No Trainers';
        $js_trainer_data[] = [1];
        $js_trainer_colors[] = ['#64748b'];
    }
}

// --- 2. PAYMENT CIRCULAR CHART DATA ---
$payment_methods_data = $pdo->query("
    SELECT 
        UPPER(method) AS method_name,
        COUNT(*) AS txn_count,
        COALESCE(SUM(amount), 0) AS total_amount
    FROM payments
    WHERE status = 'completed'
    GROUP BY method
    ORDER BY total_amount DESC
")->fetchAll();

$total_completed_revenue = (float)$pdo->query("
    SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed'
")->fetchColumn();

$total_completed_txns = (int)$pdo->query("
    SELECT COUNT(*) FROM payments WHERE status = 'completed'
")->fetchColumn();

// Fallback to all payments if completed is 0
if (empty($payment_methods_data)) {
    $payment_methods_data = $pdo->query("
        SELECT 
            UPPER(method) AS method_name,
            COUNT(*) AS txn_count,
            COALESCE(SUM(amount), 0) AS total_amount
        FROM payments
        GROUP BY method
        ORDER BY total_amount DESC
    ")->fetchAll();
    $total_completed_revenue = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments")->fetchColumn();
    $total_completed_txns = (int)$pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn();
}

$method_colors = [
    'UPI'           => '#00f2fe',
    'CASH'          => '#10b981',
    'CARD'          => '#a855f7',
    'NET BANKING'   => '#f59e0b',
    'BANK TRANSFER' => '#38bdf8',
    'CHEQUE'        => '#ec4899',
    'OTHER'         => '#94a3b8'
];

$js_payment_labels = [];
$js_payment_data = [];
$js_payment_counts = [];
$js_payment_colors = [];

if (!empty($payment_methods_data)) {
    foreach ($payment_methods_data as $pm) {
        $m_name = $pm['method_name'] ?: 'OTHER';
        $js_payment_labels[] = $m_name;
        $js_payment_data[] = (float)$pm['total_amount'];
        $js_payment_counts[] = (int)$pm['txn_count'];
        $js_payment_colors[] = $method_colors[$m_name] ?? '#94a3b8';
    }
} else {
    $js_payment_labels = ['No Payments'];
    $js_payment_data = [1];
    $js_payment_counts = [0];
    $js_payment_colors = ['#64748b'];
}

$page_title   = 'Admin Dashboard — Alexa Gym';
$topbar_title = 'Administrator Overview';
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
  <link rel="stylesheet" href="../assets/css/admin.css?v=2.5">
</head>
<body>

<div class="app-layout">
  <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

  <main class="app-main">
    <?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>

    <div class="dashboard-content">
      <?php if ($pending_m_cnt > 0): ?>
        <div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.4); border-radius: var(--radius); padding: 18px 24px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
          <div style="display: flex; align-items: center; gap: 14px;">
            <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(245, 158, 11, 0.2); color: #fbbf24; display: flex; align-items: center; justify-content: center; font-size: 20px;">
              <i class="fa-solid fa-hourglass-half"></i>
            </div>
            <div>
              <strong style="font-size: 15.5px; color: #fbbf24; display: block;">You have <?= $pending_m_cnt ?> Pending Membership Request(s)!</strong>
              <span style="font-size: 13px; color: var(--muted);">New members have registered and are awaiting your acceptance to activate their portal.</span>
            </div>
          </div>
          <a href="members.php?status=pending" class="btn btn-sm" style="background: #fbbf24; color: #070913; font-weight: 800; text-decoration: none;">
            Review &amp; Approve <i class="fa-solid fa-arrow-right" style="margin-left: 6px;"></i>
          </a>
        </div>
      <?php endif; ?>

      <div style="margin-bottom: 28px;">
        <h2 style="font-size: 26px; font-weight: 800; margin-bottom: 4px;">Gym Administration Panel</h2>
        <p style="color: var(--muted); font-size: 14.5px;">Live metrics synchronized across member mobile registrations, payments, and check-ins.</p>
      </div>

      <!-- 6 Metrics Grid (Compact & Sleek, Member Portal Style) -->
      <div class="stats-grid admin-stats-grid">
        <!-- 1. Total Members -->
        <div class="stat-card">
          <div class="stat-icon-wrapper" style="color: var(--cyan); background: rgba(0, 242, 254, 0.1); border-color: rgba(0, 242, 254, 0.25);">
            <i class="fa-solid fa-users"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Total Members</div>
            <div class="stat-val"><?= $total_members ?></div>
            <span style="font-size: 11.5px; color: var(--ok);"><i class="fa-solid fa-check"></i> Registered</span>
          </div>
        </div>

        <!-- 2. Active Members -->
        <div class="stat-card">
          <div class="stat-icon-wrapper" style="color: #10b981; background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.25);">
            <i class="fa-solid fa-user-check"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Active Members</div>
            <div class="stat-val" style="color: #34d399;"><?= $active_members ?></div>
            <span style="font-size: 11.5px; color: var(--muted);">Currently Training</span>
          </div>
        </div>

        <!-- 3. Expiring Soon -->
        <div class="stat-card">
          <div class="stat-icon-wrapper" style="color: #f59e0b; background: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.25);">
            <i class="fa-solid fa-triangle-exclamation"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Expiring Soon</div>
            <div class="stat-val" style="color: #fbbf24;"><?= $expiring_soon ?></div>
            <span style="font-size: 11.5px; color: #f59e0b;">Next 7 Days</span>
          </div>
        </div>

        <!-- 4. Monthly Revenue -->
        <div class="stat-card">
          <div class="stat-icon-wrapper" style="color: var(--cyan); background: rgba(0, 242, 254, 0.1); border-color: rgba(0, 242, 254, 0.25);">
            <i class="fa-solid fa-indian-rupee-sign"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Month's Revenue</div>
            <div class="stat-val" style="font-size: 19px; color: var(--cyan);"><?= format_currency($month_revenue) ?></div>
            <span style="font-size: 11.5px; color: var(--cyan);">This Month</span>
          </div>
        </div>

        <!-- 5. Today's Checkins -->
        <div class="stat-card">
          <div class="stat-icon-wrapper" style="color: #a855f7; background: rgba(168, 85, 247, 0.1); border-color: rgba(168, 85, 247, 0.25);">
            <i class="fa-solid fa-calendar-day"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Today's Check-ins</div>
            <div class="stat-val"><?= $today_checkins ?></div>
            <span style="font-size: 11.5px; color: var(--muted);"><?= date('d M Y') ?></span>
          </div>
        </div>

        <!-- 6. Open Tickets -->
        <div class="stat-card">
          <div class="stat-icon-wrapper" style="color: <?= $pending_tickets > 0 ? 'var(--bad)' : 'var(--ok)' ?>; background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.25);">
            <i class="fa-solid fa-headset"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Pending Tickets</div>
            <div class="stat-val" style="color: <?= $pending_tickets > 0 ? 'var(--bad)' : 'inherit' ?>;"><?= $pending_tickets ?></div>
            <span style="font-size: 11.5px; color: <?= $pending_tickets > 0 ? 'var(--bad)' : 'var(--ok)' ?>;">Requires Action</span>
          </div>
        </div>
      </div>

      <!-- Circular Charts Grid: Trainer Member Allocation & Payment Collection by Method -->
      <div class="dashboard-charts-grid">
        
        <!-- 1. Trainer Circular Chart -->
        <div class="card" style="margin-bottom: 0;">
          <div class="card-header chart-card-header">
            <h3 class="card-title">
              <i class="fa-solid fa-user-ninja" style="color: var(--cyan); margin-right: 8px;"></i> Trainer Member Allocation
            </h3>
            <a href="trainers.php" class="btn btn-outline btn-sm" style="font-size: 12.5px; font-weight: 600; text-decoration: none;">
              Trainers <i class="fa-solid fa-arrow-right" style="margin-left: 4px;"></i>
            </a>
          </div>

          <div class="chart-card-body">
            <!-- Donut Canvas & Center Summary -->
            <div class="chart-canvas-wrap">
              <canvas id="trainerCircleChart"></canvas>
              <div class="chart-center-badge">
                <span class="chart-center-val"><?= $total_trainers ?></span>
                <span class="chart-center-label"><?= ($total_assigned_members + $unassigned_trainers_count) > 0 ? ($total_assigned_members . ' Trainees') : 'Coaches' ?></span>
              </div>
            </div>

            <!-- Custom Legend Breakdown -->
            <div class="chart-legend-list">
              <?php 
              $t_idx = 0;
              $active_pool = $total_assigned_members + $unassigned_trainers_count;
              ?>
              <?php if (empty($trainer_chart_data)): ?>
                <div style="text-align: center; color: var(--muted); padding: 20px; font-size: 13px;">No active trainers found.</div>
              <?php else: ?>
                <?php foreach ($trainer_chart_data as $tc): 
                  $c_color = $color_palette[$t_idx % count($color_palette)];
                  $t_idx++;
                  $pct = $active_pool > 0 ? round(((int)$tc['member_count'] / $active_pool) * 100) : 0;
                ?>
                  <div class="chart-legend-item">
                    <div class="chart-legend-left">
                      <span class="chart-color-dot" style="background: <?= $c_color ?>; color: <?= $c_color ?>;"></span>
                      <div style="min-width: 0;">
                        <div class="chart-legend-name"><?= e($tc['full_name']) ?></div>
                        <div class="chart-legend-sub"><?= e($tc['specialization'] ?: 'General Fitness') ?></div>
                      </div>
                    </div>
                    <div class="chart-legend-right">
                      <span class="chart-legend-val"><?= (int)$tc['member_count'] ?> <small style="font-weight: 500; font-size: 11px; color: var(--muted);">trainee<?= (int)$tc['member_count'] === 1 ? '' : 's' ?></small></span>
                      <span class="chart-legend-pct" style="color: <?= $c_color ?>;"><?= $pct ?>%</span>
                    </div>
                  </div>
                <?php endforeach; ?>

                <?php if ($unassigned_trainers_count > 0): 
                  $u_pct = $active_pool > 0 ? round(($unassigned_trainers_count / $active_pool) * 100) : 0;
                ?>
                  <div class="chart-legend-item">
                    <div class="chart-legend-left">
                      <span class="chart-color-dot" style="background: #64748b; color: #64748b;"></span>
                      <div style="min-width: 0;">
                        <div class="chart-legend-name">Unassigned Members</div>
                        <div class="chart-legend-sub">Without dedicated coach</div>
                      </div>
                    </div>
                    <div class="chart-legend-right">
                      <span class="chart-legend-val"><?= $unassigned_trainers_count ?> <small style="font-weight: 500; font-size: 11px; color: var(--muted);">trainees</small></span>
                      <span class="chart-legend-pct" style="color: #64748b;"><?= $u_pct ?>%</span>
                    </div>
                  </div>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- 2. Payment Circular Chart -->
        <div class="card" style="margin-bottom: 0;">
          <div class="card-header chart-card-header">
            <h3 class="card-title">
              <i class="fa-solid fa-circle-dollar-to-slot" style="color: #10b981; margin-right: 8px;"></i> Payment Breakdown
            </h3>
            <a href="payments.php" class="btn btn-outline btn-sm" style="font-size: 12.5px; font-weight: 600; text-decoration: none;">
              Payments <i class="fa-solid fa-arrow-right" style="margin-left: 4px;"></i>
            </a>
          </div>

          <div class="chart-card-body">
            <!-- Donut Canvas & Center Summary -->
            <div class="chart-canvas-wrap">
              <canvas id="paymentCircleChart"></canvas>
              <div class="chart-center-badge">
                <span class="chart-center-val currency-val" style="color: var(--cyan);"><?= format_currency($total_completed_revenue) ?></span>
                <span class="chart-center-label"><?= $total_completed_txns ?> Txn<?= $total_completed_txns === 1 ? '' : 's' ?></span>
              </div>
            </div>

            <!-- Custom Legend Breakdown -->
            <div class="chart-legend-list">
              <?php 
              $method_icons = [
                'UPI' => 'fa-qrcode',
                'CASH' => 'fa-money-bill-wave',
                'CARD' => 'fa-credit-card',
                'NET BANKING' => 'fa-building-columns',
                'BANK TRANSFER' => 'fa-building-columns',
                'CHEQUE' => 'fa-money-check'
              ];
              ?>
              <?php if (empty($payment_methods_data)): ?>
                <div style="text-align: center; color: var(--muted); padding: 20px; font-size: 13px;">No payments recorded yet.</div>
              <?php else: ?>
                <?php foreach ($payment_methods_data as $pm): 
                  $m_name = $pm['method_name'] ?: 'OTHER';
                  $m_color = $method_colors[$m_name] ?? '#94a3b8';
                  $m_icon = $method_icons[$m_name] ?? 'fa-receipt';
                  $m_pct = $total_completed_revenue > 0 ? round(((float)$pm['total_amount'] / $total_completed_revenue) * 100) : 0;
                ?>
                  <div class="chart-legend-item">
                    <div class="chart-legend-left">
                      <span class="chart-color-dot" style="background: <?= $m_color ?>; color: <?= $m_color ?>;"></span>
                      <div style="min-width: 0;">
                        <div class="chart-legend-name" style="display: flex; align-items: center; gap: 6px;">
                          <i class="fa-solid <?= $m_icon ?>" style="font-size: 11px; color: <?= $m_color ?>;"></i>
                          <span><?= e($m_name) ?></span>
                        </div>
                        <div class="chart-legend-sub"><?= (int)$pm['txn_count'] ?> transaction<?= (int)$pm['txn_count'] === 1 ? '' : 's' ?></div>
                      </div>
                    </div>
                    <div class="chart-legend-right">
                      <span class="chart-legend-val" style="color: var(--cyan);"><?= format_currency($pm['total_amount']) ?></span>
                      <span class="chart-legend-pct" style="color: <?= $m_color ?>;"><?= $m_pct ?>%</span>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

      </div>

      <!-- Vertical Stack: Recent Members & Recent Transactions (Top-to-Bottom Flow, No Side Scroll) -->
      <div style="display: flex; flex-direction: column; gap: 26px;">
        <!-- Recent Members -->
        <div class="card" style="margin-bottom: 0;">
          <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-user-plus" style="color: var(--cyan); margin-right: 8px;"></i> Recent Member Registrations</h3>
            <a href="members.php" class="btn btn-outline btn-sm" style="font-size: 13px; font-weight: 600; text-decoration: none;">All Members <i class="fa-solid fa-arrow-right" style="margin-left: 4px;"></i></a>
          </div>
          <div class="custom-table-wrap">
            <table class="custom-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Phone</th>
                  <th>Plan</th>
                  <th>Expiry Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recent_members)): ?>
                  <tr><td colspan="5" style="text-align: center; color: var(--muted); padding: 24px;">No member registrations found.</td></tr>
                <?php else: ?>
                  <?php foreach ($recent_members as $m): ?>
                    <tr>
                      <td><strong><?= e($m['full_name']) ?></strong></td>
                      <td><?= e($m['phone'] ?: 'N/A') ?></td>
                      <td><span style="color: var(--cyan); font-weight: 600;"><?= e($m['plan_name'] ?: 'None') ?></span></td>
                      <td><?= !empty($m['expiry_date']) ? format_date($m['expiry_date']) : '<span style="color: var(--muted);">N/A</span>' ?></td>
                      <td><span class="badge-status <?= $m['status'] === 'active' ? 'active' : ($m['status'] === 'pending' ? 'pending' : 'expired') ?>"><?= strtoupper($m['status']) ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Recent Payments -->
        <div class="card" style="margin-bottom: 0;">
          <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-receipt" style="color: var(--cyan); margin-right: 8px;"></i> Recent Transactions</h3>
            <a href="payments.php" class="btn btn-outline btn-sm" style="font-size: 13px; font-weight: 600; text-decoration: none;">View All Payments <i class="fa-solid fa-arrow-right" style="margin-left: 4px;"></i></a>
          </div>
          <div class="custom-table-wrap">
            <table class="custom-table">
              <thead>
                <tr>
                  <th>Member</th>
                  <th>Amount</th>
                  <th>Method</th>
                  <th>Reference / Note</th>
                  <th>Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recent_payments)): ?>
                  <tr><td colspan="6" style="text-align: center; color: var(--muted); padding: 24px;">No transactions recorded yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($recent_payments as $p): ?>
                    <tr>
                      <td><strong><?= e($p['member_name'] ?: 'Member #' . $p['member_id']) ?></strong></td>
                      <td><strong style="color: var(--cyan); font-size: 15px;"><?= format_currency($p['amount']) ?></strong></td>
                      <td><span style="text-transform: uppercase; font-size: 12px; font-weight: 700;"><?= e($p['method']) ?></span></td>
                      <td style="color: var(--muted); font-size: 13px;"><?= e($p['note'] ?: ($p['transaction_id'] ?: '—')) ?></td>
                      <td><?= format_date($p['paid_on']) ?></td>
                      <td>
                        <span class="badge-status <?= $p['status'] === 'completed' ? 'active' : ($p['status'] === 'pending' ? 'pending' : 'expired') ?>">
                          <?= strtoupper($p['status']) ?>
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<script src="../assets/js/chart.min.js"></script>
<script>
if (typeof Chart === 'undefined') {
  document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js"><\/script>');
}
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    let trainerChartInstance = null;
    let paymentChartInstance = null;

    const getThemeBorder = () => {
        return document.documentElement.getAttribute('data-theme') === 'light' ? '#ffffff' : '#13111e';
    };

    // 1. Trainer Donut Chart
    const ctxTrainer = document.getElementById('trainerCircleChart');
    if (ctxTrainer && typeof Chart !== 'undefined') {
        trainerChartInstance = new Chart(ctxTrainer, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($js_trainer_labels) ?>,
                datasets: [{
                    data: <?= json_encode($js_trainer_data) ?>,
                    backgroundColor: <?= json_encode($js_trainer_colors) ?>,
                    borderWidth: 2,
                    borderColor: getThemeBorder(),
                    hoverBorderColor: '#ffffff',
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                layout: {
                    padding: 4
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(11, 10, 18, 0.95)',
                        titleColor: '#00f2fe',
                        titleFont: { family: 'Outfit', size: 13, weight: '700' },
                        bodyColor: '#f0edfd',
                        bodyFont: { family: 'Inter', size: 12 },
                        borderColor: 'rgba(0, 242, 254, 0.3)',
                        borderWidth: 1,
                        padding: 10,
                        boxPadding: 4,
                        callbacks: {
                            label: function(ctx) {
                                <?php if (($total_assigned_members + $unassigned_trainers_count) > 0): ?>
                                    return ' ' + ctx.label + ': ' + ctx.raw + ' Trainee(s)';
                                <?php else: ?>
                                    return ' ' + ctx.label + ' (Coach)';
                                <?php endif; ?>
                            }
                        }
                    }
                },
                animation: {
                    animateScale: true,
                    animateRotate: true,
                    duration: 900
                }
            }
        });
    }

    // 2. Payment Donut Chart
    const ctxPayment = document.getElementById('paymentCircleChart');
    if (ctxPayment && typeof Chart !== 'undefined') {
        const paymentCounts = <?= json_encode($js_payment_counts) ?>;
        paymentChartInstance = new Chart(ctxPayment, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($js_payment_labels) ?>,
                datasets: [{
                    data: <?= json_encode($js_payment_data) ?>,
                    backgroundColor: <?= json_encode($js_payment_colors) ?>,
                    borderWidth: 2,
                    borderColor: getThemeBorder(),
                    hoverBorderColor: '#ffffff',
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                layout: {
                    padding: 4
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(11, 10, 18, 0.95)',
                        titleColor: '#10b981',
                        titleFont: { family: 'Outfit', size: 13, weight: '700' },
                        bodyColor: '#f0edfd',
                        bodyFont: { family: 'Inter', size: 12 },
                        borderColor: 'rgba(16, 185, 129, 0.3)',
                        borderWidth: 1,
                        padding: 10,
                        boxPadding: 4,
                        callbacks: {
                            label: function(ctx) {
                                <?php if (!empty($payment_methods_data)): ?>
                                    const amountStr = '₹' + Number(ctx.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 });
                                    const countStr = paymentCounts[ctx.dataIndex] !== undefined ? ' (' + paymentCounts[ctx.dataIndex] + ' txns)' : '';
                                    return ' ' + ctx.label + ': ' + amountStr + countStr;
                                <?php else: ?>
                                    return ' No payments recorded';
                                <?php endif; ?>
                            }
                        }
                    }
                },
                animation: {
                    animateScale: true,
                    animateRotate: true,
                    duration: 900
                }
            }
        });
    }

    // Adapt to Light / Dark mode toggle automatically
    const themeObserver = new MutationObserver(() => {
        const newBorder = getThemeBorder();
        [trainerChartInstance, paymentChartInstance].forEach(inst => {
            if (inst && inst.data && inst.data.datasets && inst.data.datasets[0]) {
                inst.data.datasets[0].borderColor = newBorder;
                inst.update();
            }
        });
    });
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
});
</script>
<script src="../assets/js/user.js?v=2.0"></script>
</body>
</html>
