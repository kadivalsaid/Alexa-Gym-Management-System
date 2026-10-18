<?php
/**
 * Alexa Gym — Official Tax Invoice / Payment Receipt
 * Authentic A4 Bill Page with PDF Download & Print capability
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$authUserId = (int)($_GET['user_id'] ?? 0);
$authMemberId = (int)($_GET['member_id'] ?? 0);
$u = null;

if (!is_logged_in()) {
    if ($authUserId > 0) {
        $uStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $uStmt->execute([$authUserId]);
        $u = $uStmt->fetch();
    } elseif ($authMemberId > 0) {
        $mUStmt = $pdo->prepare("SELECT u.* FROM users u JOIN members m ON m.user_id = u.id WHERE m.id = ? LIMIT 1");
        $mUStmt->execute([$authMemberId]);
        $u = $mUStmt->fetch();
    }
    if (!$u) {
        require_login();
    }
} else {
    $u = get_logged_in_user();
}

$payment_id = (int)($_GET['id'] ?? 0);

if ($payment_id <= 0) {
    die("Invalid invoice or receipt ID requested.");
}

// Fetch payment details with member and plan info
$stmt = $pdo->prepare("
    SELECT p.*, 
           m.full_name AS member_name, 
           m.email AS member_email, 
           m.phone AS member_phone, 
           m.address AS member_address,
           m.id AS member_code,
           m.trainer_id AS member_trainer_id,
           pl.name AS plan_name,
           pl.duration_months,
           pl.description AS plan_desc,
           t.full_name AS trainer_name,
           t.specialization AS trainer_specialization
    FROM payments p
    JOIN members m ON p.member_id = m.id
    LEFT JOIN plans pl ON p.plan_id = pl.id
    LEFT JOIN trainers t ON m.trainer_id = t.id
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$payment_id]);
$receipt = $stmt->fetch();

if (!$receipt) {
    die("Payment record not found. Please check your payment history.");
}

// Authorization check: if member, can only view own receipt
if ($u['role'] === 'member') {
    $current_member = get_member_by_user_id($pdo, $u['id']);
    $user_member_id = $current_member['id'] ?? ($_SESSION['member_id'] ?? ($authMemberId > 0 ? $authMemberId : null));
    if (!$user_member_id) {
        $chk_m = $pdo->prepare("SELECT id FROM members WHERE user_id = ? OR email = ? LIMIT 1");
        $chk_m->execute([$u['id'], $u['email']]);
        $user_member_id = $chk_m->fetchColumn();
    }
    if ($user_member_id && (int)$user_member_id !== (int)$receipt['member_id']) {
        die("Unauthorized access to this payment receipt.");
    }
}

// Calculations
$total_amount = (float)$receipt['amount'];
$taxable_value = round($total_amount / 1.18, 2);
$total_gst = round($total_amount - $taxable_value, 2);
$cgst = round($total_gst / 2, 2);
$sgst = round($total_gst - $cgst, 2);
$amount_in_words = number_to_words_inr($total_amount);

$invoice_no = 'INV-' . date('Y', strtotime($receipt['paid_on'])) . '-' . str_pad($receipt['id'], 5, '0', STR_PAD_LEFT);
$rec_no = (int)$receipt['id'];
$pdf_filename = 'AlexaGym_Receipt_' . $rec_no . '.pdf';

// Assigned Trainer Details for Signature
$trainer_display_name = !empty($receipt['trainer_name']) ? $receipt['trainer_name'] : 'Vikram Sharma';
$trainer_spec         = !empty($receipt['trainer_specialization']) ? $receipt['trainer_specialization'] : 'Certified Fitness Coach & Trainer';

$back_url = ($u['role'] === 'admin') ? '../admin/payments.php' : 'payments.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tax Invoice #<?= e($rec_no) ?> — Alexa Gym</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@600;700;800;900&family=Great+Vibes&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  
  <style>
    :root {
      --primary: #0284c7;
      --primary-dark: #0369a1;
      --text-dark: #0f172a;
      --text-muted: #64748b;
      --border-color: #cbd5e1;
      --bg-page: #0f111a;
      --stamp-color: #059669;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      background-color: var(--bg-page);
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      color: var(--text-dark);
      min-height: 100vh;
      padding-bottom: 60px;
    }

    /* Top Action Bar (Hidden on Print & PDF) */
    .top-action-bar {
      background: #181b2a;
      border-bottom: 1px solid #2a2f45;
      padding: 14px 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
    }

    .action-bar-left {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .action-bar-right {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .btn-action {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
      font-weight: 700;
      padding: 9px 18px;
      border-radius: 9px;
      border: 1px solid transparent;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.2s ease;
      line-height: 1.3;
    }

    .btn-action-primary {
      background: linear-gradient(135deg, #00f2fe, #4facfe);
      color: #070913;
      box-shadow: 0 4px 14px rgba(0, 242, 254, 0.3);
      border: none;
    }

    .btn-action-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0, 242, 254, 0.45);
      color: #000;
    }

    .btn-action-outline {
      background: #23273c;
      border-color: #3b4263;
      color: #e2e8f0;
    }

    .btn-action-outline:hover {
      background: #2e3450;
      border-color: #00f2fe;
      color: #00f2fe;
      transform: translateY(-1px);
    }

    /* Page container */
    .invoice-wrapper {
      max-width: 860px;
      margin: 32px auto;
      padding: 0 16px;
    }

    /* The Authentic A4 Paper Sheet */
    .invoice-sheet {
      background: #ffffff;
      color: #1e293b;
      border-radius: 4px;
      box-shadow: 0 15px 45px rgba(0, 0, 0, 0.5);
      padding: 44px 48px;
      position: relative;
      overflow: hidden;
      border: 1px solid #e2e8f0;
    }

    /* Decorative top header stripe */
    .invoice-sheet::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 6px;
      background: linear-gradient(90deg, #00f2fe, #0284c7, #6366f1);
    }

    /* Header */
    .invoice-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding-bottom: 24px;
      border-bottom: 2px solid #e2e8f0;
      margin-bottom: 24px;
    }

    .gym-brand {
      display: flex;
      gap: 14px;
      align-items: center;
    }

    .brand-logo {
      width: 50px;
      height: 50px;
      border-radius: 12px;
      background: linear-gradient(135deg, #00f2fe, #4facfe);
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Outfit', sans-serif;
      font-weight: 900;
      font-size: 22px;
      color: #070913;
      box-shadow: 0 4px 12px rgba(0, 242, 254, 0.25);
    }

    .brand-name {
      font-family: 'Outfit', sans-serif;
      font-size: 24px;
      font-weight: 900;
      color: #0f172a;
      letter-spacing: -0.5px;
      line-height: 1.1;
    }

    .brand-tagline {
      font-size: 11.5px;
      font-weight: 700;
      color: #0284c7;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-top: 2px;
    }

    .gym-legal-meta {
      font-size: 12px;
      color: #64748b;
      line-height: 1.5;
      margin-top: 6px;
    }

    .invoice-badge-box {
      text-align: right;
    }

    .invoice-badge-title {
      font-family: 'Outfit', sans-serif;
      font-size: 24px;
      font-weight: 900;
      color: #0f172a;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      line-height: 1.1;
    }

    .invoice-badge-sub {
      font-size: 11px;
      font-weight: 700;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 1.2px;
      margin-top: 3px;
    }

    .invoice-num-highlight {
      font-family: 'Outfit', sans-serif;
      font-size: 17px;
      font-weight: 800;
      color: #0284c7;
      margin-top: 8px;
    }

    .invoice-date-highlight {
      font-size: 12.5px;
      color: #475569;
      margin-top: 2px;
    }

    /* Details Grid */
    .details-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 20px 24px;
      margin-bottom: 28px;
    }

    .details-title {
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: #64748b;
      margin-bottom: 8px;
      border-bottom: 1px solid #cbd5e1;
      padding-bottom: 4px;
    }

    .details-name {
      font-size: 16px;
      font-weight: 800;
      color: #0f172a;
      margin-bottom: 4px;
    }

    .details-row {
      font-size: 13px;
      color: #334155;
      line-height: 1.6;
    }

    .details-row strong {
      color: #0f172a;
    }

    /* Paid Stamp Badge */
    .paid-stamp-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #ecfdf5;
      border: 1.5px solid #10b981;
      color: #065f46;
      font-size: 12px;
      font-weight: 800;
      padding: 3px 10px;
      border-radius: 6px;
      text-transform: uppercase;
      letter-spacing: 0.8px;
    }

    /* Itemized Table */
    .bill-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 24px;
    }

    .bill-table th {
      background: #0f172a;
      color: #ffffff;
      padding: 12px 14px;
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      border: 1px solid #0f172a;
    }

    .bill-table td {
      padding: 14px 14px;
      font-size: 13.5px;
      color: #1e293b;
      border: 1px solid #e2e8f0;
      vertical-align: middle;
    }

    .bill-table tr:nth-child(even) td {
      background: #f8fafc;
    }

    .col-right {
      text-align: right;
    }

    .col-center {
      text-align: center;
    }

    /* Summary & Totals */
    .summary-section {
      display: grid;
      grid-template-columns: 1.2fr 0.8fr;
      gap: 28px;
      margin-bottom: 28px;
    }

    .amount-in-words-box {
      background: #f1f5f9;
      border-radius: 8px;
      padding: 14px 16px;
      margin-bottom: 16px;
      border-left: 4px solid #0284c7;
    }

    .words-label {
      font-size: 11px;
      font-weight: 800;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      margin-bottom: 3px;
    }

    .words-text {
      font-size: 13px;
      font-weight: 700;
      color: #0f172a;
      font-style: italic;
    }

    .terms-box {
      font-size: 11.5px;
      color: #64748b;
      line-height: 1.6;
    }

    .terms-title {
      font-weight: 800;
      color: #334155;
      text-transform: uppercase;
      margin-bottom: 4px;
      font-size: 11px;
    }

    .totals-table {
      width: 100%;
      border-collapse: collapse;
    }

    .totals-table td {
      padding: 8px 12px;
      font-size: 13px;
      color: #334155;
      border-bottom: 1px solid #e2e8f0;
    }

    .totals-table tr.total-row td {
      background: #0f172a;
      color: #ffffff;
      font-size: 15px;
      font-weight: 900;
      padding: 12px 14px;
      border: none;
    }

    /* Signatory & Stamp */
    .invoice-footer {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      padding-top: 24px;
      border-top: 2px solid #e2e8f0;
      margin-top: 10px;
    }

    .official-stamp {
      width: 110px;
      height: 110px;
      border: 3px dashed #059669;
      border-radius: 50%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 8px;
      color: #059669;
      font-family: 'Outfit', sans-serif;
      transform: rotate(-10deg);
      opacity: 0.9;
    }

    .stamp-text-1 {
      font-size: 9px;
      font-weight: 900;
      letter-spacing: 1.5px;
    }

    .stamp-text-center {
      font-size: 13px;
      font-weight: 900;
      margin: 2px 0;
    }

    .stamp-text-2 {
      font-size: 8.5px;
      font-weight: 700;
      letter-spacing: 1px;
    }

    .signatory-box {
      text-align: right;
    }

    .sign-title {
      font-size: 12px;
      color: #475569;
      margin-bottom: 6px;
    }

    .signature-graphic {
      font-family: 'Great Vibes', cursive;
      font-size: 34px;
      color: #0f172a;
      line-height: 1;
      margin-bottom: 4px;
      margin-right: 10px;
    }

    .sign-name {
      font-size: 13px;
      font-weight: 800;
      color: #0f172a;
    }

    .sign-role {
      font-size: 11.5px;
      color: #64748b;
    }

    .invoice-notice {
      text-align: center;
      font-size: 11px;
      color: #94a3b8;
      margin-top: 24px;
      padding-top: 12px;
      border-top: 1px dashed #e2e8f0;
    }

    /* Print Specific Rules */
    @media print {
      body {
        background: #ffffff !important;
        padding: 0 !important;
      }
      .top-action-bar {
        display: none !important;
      }
      .invoice-wrapper {
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
      }
      .invoice-sheet {
        box-shadow: none !important;
        border: none !important;
        padding: 20px 24px !important;
      }
      * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
    }
  </style>
</head>
<body>

<!-- Top Action Bar (Download & Print Actions) -->
<div class="top-action-bar" id="action-toolbar">
  <div class="action-bar-left">
    <a href="<?= e($back_url) ?>" class="btn-action btn-action-outline" id="receipt-back-btn" onclick="return handleReceiptBack(event, '<?= e($back_url) ?>')">
      <i class="fa-solid fa-arrow-left"></i> Back
    </a>
  </div>
  <div class="action-bar-right">
    <button type="button" class="btn-action btn-action-outline" id="btn-print" onclick="window.print()" style="margin-right: 8px;">
      <i class="fa-solid fa-print"></i> Print / PDF
    </button>
    <button type="button" class="btn-action btn-action-primary" id="btn-download" onclick="downloadInvoicePDF()">
      <i class="fa-solid fa-download"></i> Download Bill
    </button>
  </div>
</div>

<div class="invoice-wrapper">
  <!-- The Printable Official Document Paper -->
  <div class="invoice-sheet" id="invoice-sheet">
    
    <!-- Header -->
    <div class="invoice-header">
      <div class="gym-brand">
        <div class="brand-logo">AG</div>
        <div>
          <h1 class="brand-name">ALEXA GYM</h1>
          <div class="brand-tagline">Fit For Life • Premium Fitness Club</div>
          <div class="gym-legal-meta">
            Ground Floor, Shop No. 39, Shukan Plaza, Palanpur Highway, Vadgam, Banaskantha, Gujarat – 385410<br>
            <strong>GSTIN:</strong> 24AABCA1234F1Z5 &bull; <strong>State:</strong> Gujarat (24)<br>
            <strong>Email:</strong> support@alexagym.com &bull; <strong>Phone:</strong> +91 98765 43210
          </div>
        </div>
      </div>

      <div class="invoice-badge-box">
        <div class="invoice-badge-title">TAX INVOICE</div>
        <div class="invoice-badge-sub">Official Payment Receipt</div>
        <div class="invoice-num-highlight">#<?= e($rec_no) ?></div>
        <div class="invoice-date-highlight">Date: <strong><?= format_date($receipt['paid_on'], 'd M Y') ?></strong></div>
        <div style="margin-top: 8px;">
          <span class="paid-stamp-badge"><i class="fa-solid fa-circle-check"></i> PAID &amp; CONFIRMED</span>
        </div>
      </div>
    </div>

    <!-- Customer & Transaction Information Grid -->
    <div class="details-grid">
      <div>
        <div class="details-title">Billed To (Member Details)</div>
        <div class="details-name"><?= e($receipt['member_name']) ?></div>
        <div class="details-row"><strong>Member Code:</strong> AG-MEM-<?= str_pad($receipt['member_code'], 4, '0', STR_PAD_LEFT) ?></div>
        <div class="details-row"><strong>Phone:</strong> <?= e($receipt['member_phone'] ?: 'N/A') ?></div>
        <div class="details-row"><strong>Email:</strong> <?= e($receipt['member_email'] ?: 'N/A') ?></div>
        <div class="details-row"><strong>Address:</strong> <?= e($receipt['member_address'] ?: 'Not Provided') ?></div>
      </div>

      <div>
        <div class="details-title">Payment &amp; Invoice Reference</div>
        <div class="details-row"><strong>Invoice Number:</strong> <?= e($invoice_no) ?></div>
        <div class="details-row"><strong>Receipt Number:</strong> <?= e($rec_no) ?></div>
        <div class="details-row"><strong>Payment Method:</strong> <?= strtoupper(e($receipt['method'])) ?></div>
        <div class="details-row"><strong>Transaction / UTR:</strong> <?= e($receipt['transaction_id'] ?: 'N/A') ?></div>
        <div class="details-row"><strong>Payment Date:</strong> <?= format_date($receipt['paid_on'], 'd M Y') ?></div>
      </div>
    </div>

    <!-- Itemized Services Table -->
    <table class="bill-table">
      <thead>
        <tr>
          <th class="col-center" style="width: 45px;">#</th>
          <th>Description of Services</th>
          <th class="col-center" style="width: 100px;">SAC Code</th>
          <th class="col-center" style="width: 90px;">Duration</th>
          <th class="col-right" style="width: 110px;">Taxable Amt</th>
          <th class="col-right" style="width: 95px;">CGST (9%)</th>
          <th class="col-right" style="width: 95px;">SGST (9%)</th>
          <th class="col-right" style="width: 110px;">Total (INR)</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="col-center">1</td>
          <td>
            <strong style="color: #0f172a; font-size: 14px;"><?= e($receipt['plan_name'] ?: 'Gym Membership Subscription') ?></strong>
            <div style="color: #64748b; font-size: 12px; margin-top: 2px;">
              <?= e($receipt['plan_desc'] ?: 'Unlimited access to gym facilities, equipment and locker rooms.') ?>
            </div>
            <?php if (!empty($receipt['trainer_name'])): ?>
              <div style="color: #0284c7; font-size: 11.5px; margin-top: 5px; font-weight: 600;">
                <i class="fa-solid fa-user-ninja" style="margin-right: 4px;"></i> Assigned Trainer: <?= e($receipt['trainer_name']) ?> (<?= e($trainer_spec) ?>)
              </div>
            <?php endif; ?>
          </td>
          <td class="col-center" style="font-family: monospace; font-size: 12px; font-weight: 600;">999723</td>
          <td class="col-center"><?= (int)($receipt['duration_months'] ?: 1) ?> Month<?= (int)($receipt['duration_months'] ?: 1) > 1 ? 's' : '' ?></td>
          <td class="col-right">₹<?= number_format($taxable_value, 2) ?></td>
          <td class="col-right">₹<?= number_format($cgst, 2) ?></td>
          <td class="col-right">₹<?= number_format($sgst, 2) ?></td>
          <td class="col-right"><strong style="color: #0284c7;">₹<?= number_format($total_amount, 2) ?></strong></td>
        </tr>
      </tbody>
    </table>

    <!-- Totals and Terms Breakdown -->
    <div class="summary-section">
      <div>
        <div class="amount-in-words-box">
          <div class="words-label">Amount in Words</div>
          <div class="words-text"><?= e($amount_in_words) ?></div>
        </div>

        <div class="terms-box">
          <div class="terms-title">Terms &amp; General Conditions</div>
          <ol style="padding-left: 14px;">
            <li>All membership fees and subscriptions are non-refundable and non-transferable.</li>
            <li>Members must carry their digital ID for gym turnstile attendance scanning.</li>
            <li>Tax payable under Reverse Charge Mechanism (RCM): <strong>No</strong>.</li>
            <li>Subject to Banaskantha (Gujarat) Jurisdiction only.</li>
          </ol>
        </div>
      </div>

      <div>
        <table class="totals-table">
          <tr>
            <td>Total Taxable Value</td>
            <td class="col-right"><strong>₹<?= number_format($taxable_value, 2) ?></strong></td>
          </tr>
          <tr>
            <td>CGST @ 9.00%</td>
            <td class="col-right">₹<?= number_format($cgst, 2) ?></td>
          </tr>
          <tr>
            <td>SGST @ 9.00%</td>
            <td class="col-right">₹<?= number_format($sgst, 2) ?></td>
          </tr>
          <tr class="total-row">
            <td>Grand Total (INR)</td>
            <td class="col-right">₹<?= number_format($total_amount, 2) ?></td>
          </tr>
        </table>
      </div>
    </div>

    <!-- Signatures & Stamp -->
    <div class="invoice-footer">
      <!-- Circular Authentic Paid Stamp -->
      <div class="official-stamp">
        <div class="stamp-text-1">★ ALEXA GYM ★</div>
        <div class="stamp-text-center">PAID</div>
        <div class="stamp-text-2">OFFICIAL RECEIPT</div>
      </div>

      <!-- Authorized Trainer Signatory Box -->
      <div class="signatory-box">
        <div class="sign-title">For <strong>ALEXA GYM FITNESS CLUB</strong></div>
        <div class="signature-graphic"><?= e($trainer_display_name) ?></div>
        <div class="sign-name"><?= e($trainer_display_name) ?></div>
        <div class="sign-role">Assigned Personal Trainer &bull; <?= e($trainer_spec) ?></div>
      </div>
    </div>

    <div class="invoice-notice">
      This is an authentic computer-generated official tax invoice issued by Alexa Gym Management System.
    </div>

  </div>
</div>

<!-- html2pdf Library for Instant Clean Client-side PDF Generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadInvoicePDF() {
    const btn = document.getElementById('btn-download');
    const origHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating PDF...';
    btn.disabled = true;

    const element = document.getElementById('invoice-sheet');
    const opt = {
        margin:       [6, 6, 6, 6],
        filename:     '<?= $pdf_filename ?>',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, letterRendering: true, logging: false },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    // If running inside Android WebView with Native AndroidBridge
    if (window.AndroidBridge && typeof window.AndroidBridge.downloadPdfBase64 === 'function') {
        html2pdf().set(opt).from(element).outputPdf('datauristring').then(function(pdfDataUri) {
            const base64Data = pdfDataUri.split(',')[1];
            window.AndroidBridge.downloadPdfBase64(base64Data, '<?= $pdf_filename ?>');
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Saved to Downloads!';
            setTimeout(() => {
                btn.innerHTML = origHtml;
                btn.disabled = false;
            }, 2500);
        }).catch(function(err) {
            console.error('Android bridge error:', err);
            html2pdf().set(opt).from(element).save();
            btn.innerHTML = origHtml;
            btn.disabled = false;
        });
        return;
    }

    html2pdf().set(opt).from(element).save().then(() => {
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Downloaded!';
        setTimeout(() => {
            btn.innerHTML = origHtml;
            btn.disabled = false;
        }, 2000);
    }).catch(err => {
        console.error('PDF generation error:', err);
        btn.innerHTML = origHtml;
        btn.disabled = false;
        // Fallback to native print
        window.print();
    });
}

// Smart back navigation: Close tab if opened in new tab, or return to referrer/history
function handleReceiptBack(e, fallbackUrl) {
    if (e && e.preventDefault) e.preventDefault();
    try {
        if (window.opener && !window.opener.closed) {
            window.close();
        } else {
            window.close();
        }
    } catch (err) {}

    setTimeout(function() {
        if (document.referrer && document.referrer !== window.location.href && document.referrer.indexOf(window.location.host) !== -1) {
            window.location.href = document.referrer;
        } else if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = fallbackUrl;
        }
    }, 120);

    return false;
}

// Auto-trigger download if ?download=1 is in the URL
window.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('download') === '1') {
        setTimeout(downloadInvoicePDF, 600);
    }
});
</script>
</body>
</html>
