<?php
/**
 * Alexa Gym — Admin Portal: Payments Management
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$msg_success = '';
$msg_error   = '';

// Delete payment
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    try {
        $pdo->prepare("DELETE FROM payments WHERE id = ?")->execute([$del_id]);
        $msg_success = "Payment entry #$del_id has been deleted.";
    } catch (Exception $e) {
        $msg_error = "Could not delete payment: " . $e->getMessage();
    }
}

// Record Manual Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $member_id = (int)($_POST['member_id'] ?? 0);
    $amount    = (float)($_POST['amount'] ?? 0);
    $method    = trim($_POST['method'] ?? 'cash');
    $paid_on   = trim($_POST['paid_on'] ?? date('Y-m-d'));
    $txn_id    = trim($_POST['txn_id'] ?? 'OFFLINE-' . strtoupper(bin2hex(random_bytes(3))));
    $note      = trim($_POST['note'] ?? '');

    if ($member_id <= 0 || $amount <= 0) {
        $msg_error = 'Please select a valid member and enter a positive payment amount.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO payments (member_id, amount, method, transaction_id, paid_on, status, note, created_at) 
                VALUES (?, ?, ?, ?, ?, 'completed', ?, NOW())
            ");
            $stmt->execute([$member_id, $amount, $method, $txn_id, $paid_on, $note]);
            $msg_success = "Payment of " . format_currency($amount) . " recorded successfully!";
        } catch (Exception $e) {
            $msg_error = "Error recording payment: " . $e->getMessage();
        }
    }
}

// Metrics
$total_collected = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed'")->fetchColumn();
$upi_collected   = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND method = 'upi'")->fetchColumn();
$cash_collected  = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND method = 'cash'")->fetchColumn();

// Fetch payments list
$payments = $pdo->query("
    SELECT p.*, m.full_name AS member_name, m.phone AS member_phone 
    FROM payments p 
    LEFT JOIN members m ON p.member_id = m.id 
    ORDER BY p.id DESC
")->fetchAll();

// Members list for dropdown
$members_list = $pdo->query("SELECT id, full_name, phone FROM members ORDER BY full_name ASC")->fetchAll();

$page_title   = 'Manage Payments — Alexa Gym Admin';
$topbar_title = 'Billing & Revenue Records';
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
          <h2 style="font-size: 26px; font-weight: 800; margin-bottom: 4px;">Payment Records &amp; Receipts</h2>
          <p style="color: var(--muted); font-size: 14.5px;">Track all incoming subscriptions from UPI, cash, and card collections.</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="openModal('record-payment-modal')">
          <i class="fa-solid fa-plus"></i> Record Payment
        </button>
      </div>

      <?php if (!empty($msg_success)): ?>
        <div class="alert alert-success" style="margin-bottom: 20px;">
          <i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i> <?= e($msg_success) ?>
        </div>
      <?php elseif (!empty($msg_error)): ?>
        <div class="alert alert-danger" style="margin-bottom: 20px;">
          <i class="fa-solid fa-triangle-exclamation" style="margin-right: 8px;"></i> <?= e($msg_error) ?>
        </div>
      <?php endif; ?>

      <!-- Totals -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon-wrapper">
            <i class="fa-solid fa-vault"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Total Revenue Collected</div>
            <div class="stat-val"><?= format_currency($total_collected) ?></div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon-wrapper" style="color: var(--cyan); background: rgba(0, 242, 254, 0.1); border-color: rgba(0, 242, 254, 0.25);">
            <i class="fa-solid fa-qrcode"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">UPI Digital Collections</div>
            <div class="stat-val"><?= format_currency($upi_collected) ?></div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon-wrapper" style="color: #10b981; background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.25);">
            <i class="fa-solid fa-money-bill-wave"></i>
          </div>
          <div class="stat-info">
            <div class="stat-label">Cash At Reception</div>
            <div class="stat-val"><?= format_currency($cash_collected) ?></div>
          </div>
        </div>
      </div>

      <!-- Payments Table -->
      <div class="card">
        <div class="custom-table-wrap">
          <table class="custom-table">
            <thead>
              <tr>
                <th style="width: 80px;">Receipt</th>
                <th>Member Name</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Paid Date</th>
                <th>Plan / Note</th>
                <th style="text-align: right; min-width: 140px;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($payments)): ?>
                <tr>
                  <td colspan="7" style="text-align: center; color: var(--muted); padding: 32px;">No payments recorded yet.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($payments as $p): ?>
                  <tr>
                    <td><strong style="color: var(--muted);"><?= (int)$p['id'] ?></strong></td>
                    <td>
                      <strong style="color: var(--text);"><?= e($p['member_name'] ?: 'Member #' . $p['member_id']) ?></strong>
                      <small style="display: block; color: var(--muted); font-size: 12px;"><?= e($p['member_phone'] ?: '') ?></small>
                    </td>
                    <td><strong style="color: var(--cyan); font-size: 15px;"><?= format_currency($p['amount']) ?></strong></td>
                    <td><span style="text-transform: uppercase; font-size: 12px; font-weight: 700;"><?= e($p['method']) ?></span></td>
                    <td><?= format_date($p['paid_on']) ?></td>
                    <td style="color: var(--muted); font-size: 13px;"><?= e($p['note'] ?: '—') ?></td>
                    <td style="text-align: right; white-space: nowrap;">
                      <a href="../user/receipt.php?id=<?= $p['id'] ?>" target="_blank" rel="opener" class="btn btn-primary btn-sm" title="View Full Page Invoice" style="margin-right: 6px; padding: 6px 12px; font-weight: 600;">
                        <i class="fa-solid fa-file-invoice"></i> View Bill
                      </a>
                      <a href="payments.php?delete_id=<?= $p['id'] ?>" class="btn btn-outline btn-sm" style="color: var(--bad); border-color: rgba(239, 68, 68, 0.3); padding: 6px 10px;" title="Delete Record" onclick="return confirm('Delete payment entry?')">
                        <i class="fa-solid fa-trash"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- Record Payment Modal -->
<div class="modal-backdrop" id="record-payment-modal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 18px; font-weight: 800;"><i class="fa-solid fa-cash-register" style="color: var(--cyan); margin-right: 8px;"></i> Record Member Payment</h3>
      <button type="button" class="action-btn-circle" onclick="closeModal('record-payment-modal')">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form method="POST" action="payments.php">
      <div class="modal-body">
        <div style="margin-bottom: 14px;">
          <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Select Member *</label>
          <select name="member_id" class="form-control" required>
            <option value="">-- Choose Member --</option>
            <?php foreach ($members_list as $m): ?>
              <option value="<?= $m['id'] ?>"><?= e($m['full_name']) ?> (<?= e($m['phone']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div>
            <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Amount Paid (₹) *</label>
            <input type="number" step="0.01" name="amount" class="form-control" placeholder="2499.00" required>
          </div>
          <div>
            <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Payment Method</label>
            <select name="method" class="form-control">
              <option value="upi">UPI / QR Code</option>
              <option value="cash">Cash</option>
              <option value="card">Card</option>
              <option value="bank">Bank Transfer</option>
            </select>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div>
            <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Payment Date</label>
            <input type="date" name="paid_on" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div>
            <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Transaction / Reference ID</label>
            <input type="text" name="txn_id" class="form-control" placeholder="e.g. UPI-98210398">
          </div>
        </div>

        <div style="margin-bottom: 14px;">
          <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Note / Description</label>
          <input type="text" name="note" class="form-control" placeholder="e.g. 3 Months Renewal / Joining Fee">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('record-payment-modal')">Cancel</button>
        <button type="submit" name="record_payment" class="btn btn-primary btn-sm">Save Payment Record</button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/js/user.js?v=2.0"></script>
</body>
</html>
