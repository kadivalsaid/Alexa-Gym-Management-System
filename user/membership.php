<?php
/**
 * Alexa Gym — Member Portal: My Membership
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

$days_remaining = 0;
if (!empty($member['expiry_date'])) {
    $days_remaining = max(0, (int)ceil((strtotime($member['expiry_date']) - time()) / 86400));
}

// Fetch historical memberships
$history = [];
if ($member) {
    $h_stmt = $pdo->prepare("
        SELECT ms.*, p.name AS plan_name, p.duration_months 
        FROM memberships ms 
        LEFT JOIN plans p ON ms.plan_id = p.id 
        WHERE ms.member_id = ? 
        ORDER BY ms.id DESC
    ");
    $h_stmt->execute([$member['id']]);
    $history = $h_stmt->fetchAll();
}

$page_title   = 'My Membership — Alexa Gym';
$topbar_title = 'Membership Plan & Status';
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
      
      <!-- Current Plan Active Card -->
      <div class="card status-card-hero" style="border-color: rgba(0, 242, 254, 0.3); padding: 36px; margin-bottom: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px;">
          <div>
            <span class="section-tag" style="margin-bottom: 12px;"><i class="fa-solid fa-crown"></i> Active Package</span>
            <h2 style="font-size: 32px; font-weight: 800; margin-bottom: 6px;">
              <?= e($member['plan_name'] ?? 'Standard Pro') ?>
            </h2>
            <div style="font-size: 24px; font-weight: 800; color: var(--cyan); margin-bottom: 18px;">
              <?= format_currency($member['plan_price'] ?? 2499) ?>
              <span style="font-size: 14px; font-weight: 500; color: var(--muted);">/ <?= (int)($member['duration_months'] ?? 3) ?> Months</span>
            </div>
            <div style="display: flex; gap: 24px; flex-wrap: wrap; font-size: 14.5px;">
              <div>
                <span style="color: var(--muted); display: block;">Start Date:</span>
                <strong><?= format_date($member['join_date'] ?? date('Y-m-d')) ?></strong>
              </div>
              <div>
                <span style="color: var(--muted); display: block;">Expiry Date:</span>
                <strong style="color: #f59e0b;"><?= format_date($member['expiry_date'] ?? date('Y-m-d', strtotime('+60 days'))) ?></strong>
              </div>
              <div>
                <span style="color: var(--muted); display: block;">Remaining:</span>
                <strong style="color: var(--cyan);"><?= $days_remaining ?> Days Left</strong>
              </div>
              <div>
                <span style="color: var(--muted); display: block;">Status:</span>
                <span class="badge-status active">ACTIVE</span>
              </div>
            </div>
          </div>

          <div>
            <a href="plans.php" class="btn btn-primary" style="padding: 14px 28px;">
              <i class="fa-solid fa-arrows-rotate"></i> Renew Membership
            </a>
          </div>
        </div>
      </div>

      <!-- Membership History -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><i class="fa-solid fa-history" style="color: var(--cyan); margin-right: 8px;"></i> Membership History</h3>
          <span style="color: var(--muted); font-size: 13.5px;">All past renewals and plan records</span>
        </div>

        <div class="custom-table-wrap">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Plan Name</th>
                <th>Duration</th>
                <th>Price</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($history)): ?>
                <?php foreach ($history as $h): ?>
                  <tr>
                    <td><strong><?= e($h['plan_name'] ?: 'Custom Plan') ?></strong></td>
                    <td><?= (int)$h['duration_months'] ?> Months</td>
                    <td><?= format_currency($h['price']) ?></td>
                    <td><?= format_date($h['start_date']) ?></td>
                    <td><?= format_date($h['end_date']) ?></td>
                    <td>
                      <span class="badge-status <?= $h['status'] === 'active' ? 'active' : 'expired' ?>">
                        <?= strtoupper($h['status']) ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td><strong><?= e($member['plan_name'] ?? 'Standard Pro') ?></strong></td>
                  <td><?= (int)($member['duration_months'] ?? 3) ?> Months</td>
                  <td><?= format_currency($member['plan_price'] ?? 2499) ?></td>
                  <td><?= format_date($member['join_date'] ?? date('Y-m-d')) ?></td>
                  <td><?= format_date($member['expiry_date'] ?? date('Y-m-d', strtotime('+60 days'))) ?></td>
                  <td><span class="badge-status active">ACTIVE</span></td>
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
