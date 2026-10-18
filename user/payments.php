<?php
/**
 * Alexa Gym — Member Portal: Payments & Receipts
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_member();

$u = get_logged_in_user();
$member = get_member_by_user_id($pdo, $u['id']);

$payments = [];
if ($member) {
    $stmt = $pdo->prepare("
        SELECT p.*, pl.name AS plan_name, pl.duration_months 
        FROM payments p 
        LEFT JOIN plans pl ON p.plan_id = pl.id 
        WHERE p.member_id = ? 
        ORDER BY p.id DESC
    ");
    $stmt->execute([$member['id']]);
    $payments = $stmt->fetchAll();
}

$page_title   = 'Payments & Receipts — Alexa Gym';
$topbar_title = 'Payment Records';
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
  <link rel="stylesheet" href="../assets/css/admin.css?v=2.4">
  <style>
    .custom-table-wrap {
      overflow-x: auto;
      scrollbar-width: none !important;
      -ms-overflow-style: none !important;
    }
    .custom-table-wrap::-webkit-scrollbar {
      display: none !important;
      width: 0 !important;
      height: 0 !important;
    }
    .custom-table th, .custom-table td {
      padding: 14px 12px;
    }
  </style>
</head>
<body>

<div class="app-layout">
  <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

  <main class="app-main">
    <?php require_once __DIR__ . '/../includes/user_topbar.php'; ?>

    <div class="dashboard-content">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; flex-wrap: wrap; gap: 16px;">
        <div>
          <h2 style="font-size: 26px; font-weight: 800; margin-bottom: 4px;">My Payment History</h2>
          <p style="color: var(--muted); font-size: 14.5px;">View transaction records and generate printable tax receipts.</p>
        </div>
        <a href="plans.php" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-plus"></i> Make New Payment
        </a>
      </div>

      <div class="card">
        <div class="custom-table-wrap">
          <table class="custom-table">
            <thead>
              <tr>
                <th style="width: 85px;">Receipt No.</th>
                <th>Plan / Service</th>
                <th>Amount</th>
                <th>Payment Method</th>
                <th>Paid Date</th>
                <th>Status</th>
                <th style="text-align: right;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($payments)): ?>
                <?php foreach ($payments as $pay): ?>
                  <tr>
                    <td><strong class="code-badge"><?= (int)$pay['id'] ?></strong></td>
                    <td><strong style="color: var(--text);"><?= e($pay['plan_name'] ?: 'Membership Fee') ?></strong></td>
                    <td><strong style="color: var(--cyan);"><?= format_currency($pay['amount']) ?></strong></td>
                    <td>
                      <span style="text-transform: uppercase; font-size: 12.5px; font-weight: 700;"><?= e($pay['method']) ?></span>
                      <?php if (!empty($pay['transaction_id']) && $pay['transaction_id'] !== 'N/A'): ?>
                        <div style="color: var(--muted); font-size: 11.5px; font-family: monospace; margin-top: 2px;">
                          <?= e($pay['transaction_id']) ?>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td><?= format_date($pay['paid_on']) ?></td>
                    <td>
                      <span class="badge-status <?= $pay['status'] === 'completed' ? 'completed' : 'pending' ?>">
                        <?= strtoupper($pay['status']) ?>
                      </span>
                    </td>
                    <td style="text-align: right; white-space: nowrap;">
                      <a href="receipt.php?id=<?= $pay['id'] ?>" target="_blank" rel="opener" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-file-invoice"></i> View Bill
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" style="text-align: center; color: var(--muted); padding: 32px;">No payment records found.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>

<script src="../assets/js/user.js?v=2.1"></script>
</body>
</html>
