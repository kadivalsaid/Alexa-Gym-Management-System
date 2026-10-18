<?php
/**
 * Alexa Gym — Admin Portal: Reports & Analytics
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

// Analytics calculations
$total_revenue = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed'")->fetchColumn();
$month_revenue = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE MONTH(paid_on) = MONTH(CURDATE()) AND YEAR(paid_on) = YEAR(CURDATE()) AND status = 'completed'")->fetchColumn();
$total_payments_count = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'completed'")->fetchColumn();
$avg_ticket = $total_payments_count > 0 ? round($total_revenue / $total_payments_count, 2) : 0;

// Members by plan distribution (Active members only)
$plan_dist = $pdo->query("
    SELECT p.name AS plan_name, p.price, COUNT(m.id) AS member_count, COALESCE(SUM(p.price), 0) AS total_val 
    FROM plans p 
    LEFT JOIN members m ON m.plan_id = p.id AND m.status = 'active'
    GROUP BY p.id 
    ORDER BY member_count DESC
")->fetchAll();

$total_members = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active' AND plan_id IS NOT NULL")->fetchColumn();

$page_title   = 'Analytics & Reports — Alexa Gym Admin';
$topbar_title = 'Financial & Membership Analytics';
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
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; flex-wrap: wrap; gap: 16px;">
        <div>
          <h2 style="font-size: 26px; font-weight: 800; margin-bottom: 4px;">Business &amp; Revenue Reports</h2>
          <p style="color: var(--muted); font-size: 14.5px;">Comprehensive financial performance and plan enrollment analytics.</p>
        </div>
        <a href="report_view.php" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700;">
          <i class="fa-solid fa-file-invoice"></i> View Executive Report
        </a>
      </div>

      <!-- Financial Metrics -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon-wrapper">
            <i class="fa-solid fa-sack-dollar"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Total Lifetime Revenue</div>
            <div class="stat-val"><?= format_currency($total_revenue) ?></div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon-wrapper" style="color: var(--cyan); background: rgba(0, 242, 254, 0.1); border-color: rgba(0, 242, 254, 0.25);">
            <i class="fa-solid fa-chart-line"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Current Month Gross</div>
            <div class="stat-val"><?= format_currency($month_revenue) ?></div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon-wrapper" style="color: #10b981; background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.25);">
            <i class="fa-solid fa-receipt"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Average Order Value</div>
            <div class="stat-val"><?= format_currency($avg_ticket) ?></div>
          </div>
        </div>
      </div>

      <!-- Plan Enrollment Distribution -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><i class="fa-solid fa-pie-chart" style="color: var(--cyan); margin-right: 8px;"></i> Membership Plan Distribution</h3>
        </div>

        <div class="custom-table-wrap">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Plan Name</th>
                <th>Unit Price</th>
                <th>Enrolled Members</th>
                <th>Share of Total</th>
                <th>Total Potential Yield</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($plan_dist as $pd): 
                  $share_pct = $total_members > 0 ? round(((int)$pd['member_count'] / $total_members) * 100) : 0;
              ?>
                <tr>
                  <td><strong><?= e($pd['plan_name']) ?></strong></td>
                  <td><?= format_currency($pd['price']) ?></td>
                  <td>
                    <strong style="color: var(--cyan);"><?= (int)$pd['member_count'] ?> Members</strong>
                  </td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 12px;">
                      <div class="progress-box" style="width: 140px; margin: 0;">
                        <div class="progress-fill" style="width: <?= $share_pct ?>%;"></div>
                      </div>
                      <span style="font-size: 13px; font-weight: 700;"><?= $share_pct ?>%</span>
                    </div>
                  </td>
                  <td><strong><?= format_currency($pd['total_val']) ?></strong></td>
                </tr>
              <?php endforeach; ?>
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
