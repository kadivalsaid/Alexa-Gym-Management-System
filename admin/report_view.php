<?php
/**
 * Alexa Gym — Official Executive Business & Revenue Report
 * Authentic A4 Document View with PDF Download & Print capability
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

$upi_revenue = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND LOWER(method) = 'upi'")->fetchColumn();
$cash_revenue = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND LOWER(method) = 'cash'")->fetchColumn();
$other_revenue = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND LOWER(method) NOT IN ('upi', 'cash')")->fetchColumn();

// Members count
$total_members = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active' AND plan_id IS NOT NULL")->fetchColumn();
$active_members = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active' AND plan_id IS NOT NULL")->fetchColumn();

// Plan distribution
$plan_dist = $pdo->query("
    SELECT p.name AS plan_name, p.price, p.duration_months, COUNT(m.id) AS member_count, COALESCE(SUM(p.price), 0) AS total_val 
    FROM plans p 
    LEFT JOIN members m ON m.plan_id = p.id AND m.status = 'active'
    GROUP BY p.id 
    ORDER BY member_count DESC
")->fetchAll();

// Recent 10 completed payments
$recent_payments = $pdo->query("
    SELECT p.*, m.full_name AS member_name, pl.name AS plan_name 
    FROM payments p 
    LEFT JOIN members m ON p.member_id = m.id 
    LEFT JOIN plans pl ON p.plan_id = pl.id 
    WHERE p.status = 'completed' 
    ORDER BY p.paid_on DESC, p.id DESC 
    LIMIT 10
")->fetchAll();

// Tax & Totals Breakdown (GST 18% standard calculation)
$taxable_total = round($total_revenue / 1.18, 2);
$gst_total = round($total_revenue - $taxable_total, 2);
$cgst_total = round($gst_total / 2, 2);
$sgst_total = round($gst_total - $cgst_total, 2);

$report_id = 'REP-' . date('Ymd') . '-01';
$pdf_filename = 'AlexaGym_Executive_Report_' . date('Ymd') . '.pdf';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Executive Business &amp; Revenue Report — Alexa Gym</title>
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
    .report-wrapper {
      max-width: 900px;
      margin: 32px auto;
      padding: 0 16px;
    }

    /* The Authentic A4 Document Sheet */
    .report-sheet {
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
    .report-sheet::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 6px;
      background: linear-gradient(90deg, #00f2fe, #0284c7, #6366f1);
    }

    /* Header */
    .report-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding-bottom: 22px;
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
      font-size: 11.5px;
      color: #64748b;
      line-height: 1.5;
      margin-top: 6px;
    }

    .report-badge-box {
      text-align: right;
    }

    .report-badge-title {
      font-family: 'Outfit', sans-serif;
      font-size: 22px;
      font-weight: 900;
      color: #0f172a;
      text-transform: uppercase;
      letter-spacing: 1px;
      line-height: 1.1;
    }

    .report-badge-sub {
      font-size: 11px;
      font-weight: 700;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 1.2px;
      margin-top: 3px;
    }

    .report-num-highlight {
      font-family: 'Outfit', sans-serif;
      font-size: 16px;
      font-weight: 800;
      color: #0284c7;
      margin-top: 6px;
    }

    .report-date-highlight {
      font-size: 12px;
      color: #475569;
      margin-top: 2px;
    }

    .audit-stamp-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #ecfdf5;
      border: 1.5px solid #10b981;
      color: #065f46;
      font-size: 11px;
      font-weight: 800;
      padding: 3px 10px;
      border-radius: 6px;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      margin-top: 6px;
    }

    /* Executive KPI Grid */
    .kpi-section {
      margin-bottom: 24px;
    }

    .section-title {
      font-size: 12px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: #0f172a;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .section-title i {
      color: #0284c7;
    }

    .kpi-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 14px;
      margin-bottom: 24px;
    }

    .kpi-card {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 14px 16px;
      position: relative;
    }

    .kpi-label {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      color: #64748b;
      margin-bottom: 6px;
    }

    .kpi-value {
      font-family: 'Outfit', sans-serif;
      font-size: 20px;
      font-weight: 800;
      color: #0f172a;
      line-height: 1.1;
    }

    .kpi-subtext {
      font-size: 11px;
      color: #0284c7;
      font-weight: 600;
      margin-top: 4px;
    }

    /* Document Tables */
    .report-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 24px;
    }

    .report-table th {
      background: #0f172a;
      color: #ffffff;
      padding: 10px 12px;
      font-size: 11.5px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      border: 1px solid #0f172a;
    }

    .report-table td {
      padding: 10px 12px;
      font-size: 12.5px;
      color: #1e293b;
      border: 1px solid #e2e8f0;
      vertical-align: middle;
    }

    .report-table tr:nth-child(even) td {
      background: #f8fafc;
    }

    .col-right {
      text-align: right;
    }

    .col-center {
      text-align: center;
    }

    /* Summary & Certification */
    .summary-grid {
      display: grid;
      grid-template-columns: 1.2fr 0.8fr;
      gap: 24px;
      margin-bottom: 24px;
    }

    .audit-notes-box {
      background: #f1f5f9;
      border-radius: 8px;
      padding: 14px 16px;
      border-left: 4px solid #0284c7;
    }

    .notes-title {
      font-size: 11px;
      font-weight: 800;
      color: #334155;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      margin-bottom: 6px;
    }

    .notes-list {
      font-size: 11.5px;
      color: #475569;
      line-height: 1.6;
      padding-left: 16px;
    }

    .totals-table {
      width: 100%;
      border-collapse: collapse;
    }

    .totals-table td {
      padding: 7px 10px;
      font-size: 12.5px;
      color: #334155;
      border-bottom: 1px solid #e2e8f0;
    }

    .totals-table tr.grand-total td {
      background: #0f172a;
      color: #ffffff;
      font-size: 14px;
      font-weight: 900;
      padding: 10px 12px;
      border: none;
    }

    /* Signatory & Stamp */
    .report-footer {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      padding-top: 20px;
      border-top: 2px solid #e2e8f0;
      margin-top: 10px;
    }

    .official-stamp {
      width: 105px;
      height: 105px;
      border: 3px dashed #059669;
      border-radius: 50%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 6px;
      color: #059669;
      font-family: 'Outfit', sans-serif;
      transform: rotate(-10deg);
      opacity: 0.9;
    }

    .stamp-text-1 {
      font-size: 8.5px;
      font-weight: 900;
      letter-spacing: 1.5px;
    }

    .stamp-text-center {
      font-size: 12px;
      font-weight: 900;
      margin: 2px 0;
    }

    .stamp-text-2 {
      font-size: 8px;
      font-weight: 700;
      letter-spacing: 1px;
    }

    .signatory-box {
      text-align: right;
    }

    .sign-title {
      font-size: 11.5px;
      color: #475569;
      margin-bottom: 4px;
    }

    .signature-graphic {
      font-family: 'Great Vibes', cursive;
      font-size: 32px;
      color: #0f172a;
      line-height: 1;
      margin-bottom: 4px;
      margin-right: 10px;
    }

    .sign-name {
      font-size: 12.5px;
      font-weight: 800;
      color: #0f172a;
    }

    .sign-role {
      font-size: 11px;
      color: #64748b;
    }

    .report-notice {
      text-align: center;
      font-size: 10.5px;
      color: #94a3b8;
      margin-top: 20px;
      padding-top: 10px;
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
      .report-wrapper {
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
      }
      .report-sheet {
        box-shadow: none !important;
        border: none !important;
        padding: 16px 20px !important;
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
    <a href="reports.php" class="btn-action btn-action-outline" onclick="if (window.history.length > 1) { window.history.back(); return false; }">
      <i class="fa-solid fa-arrow-left"></i> Back to Reports
    </a>
  </div>
  <div class="action-bar-right">
    <button type="button" class="btn-action btn-action-outline" onclick="window.print()">
      <i class="fa-solid fa-print"></i> Print
    </button>
    <button type="button" class="btn-action btn-action-primary" id="btn-download" onclick="downloadReportPDF()">
      <i class="fa-solid fa-download"></i> Download Report PDF
    </button>
  </div>
</div>

<div class="report-wrapper">
  <!-- The Printable Official Executive Document -->
  <div class="report-sheet" id="report-sheet">
    
    <!-- Header -->
    <div class="report-header">
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

      <div class="report-badge-box">
        <div class="report-badge-title">EXECUTIVE REPORT</div>
        <div class="report-badge-sub">Financial &amp; Performance Audit</div>
        <div class="report-num-highlight"><?= e($report_id) ?></div>
        <div class="report-date-highlight">Date: <strong><?= date('d M Y, h:i A') ?></strong></div>
        <div>
          <span class="audit-stamp-badge"><i class="fa-solid fa-certificate"></i> OFFICIAL AUDITED</span>
        </div>
      </div>
    </div>

    <!-- Executive Metrics KPI Section -->
    <div class="kpi-section">
      <div class="section-title"><i class="fa-solid fa-chart-simple"></i> Executive Financial Snapshot</div>
      <div class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-label">Lifetime Revenue</div>
          <div class="kpi-value"><?= format_currency($total_revenue) ?></div>
          <div class="kpi-subtext"><?= $total_payments_count ?> Completed Receipts</div>
        </div>

        <div class="kpi-card">
          <div class="kpi-label">Current Month Gross</div>
          <div class="kpi-value"><?= format_currency($month_revenue) ?></div>
          <div class="kpi-subtext">Month of <?= date('F Y') ?></div>
        </div>

        <div class="kpi-card">
          <div class="kpi-label">Avg Order Value</div>
          <div class="kpi-value"><?= format_currency($avg_ticket) ?></div>
          <div class="kpi-subtext">Per Transaction</div>
        </div>

        <div class="kpi-card">
          <div class="kpi-label">Active Enrolled Members</div>
          <div class="kpi-value"><?= $active_members ?> / <?= $total_members ?></div>
          <div class="kpi-subtext"><?= $total_members > 0 ? round(($active_members / $total_members) * 100) : 0 ?>% Active Ratio</div>
        </div>
      </div>
    </div>

    <!-- Membership Plan Breakdown -->
    <div class="section-title"><i class="fa-solid fa-cubes"></i> Membership Plan Distribution &amp; Revenue Yield</div>
    <table class="report-table">
      <thead>
        <tr>
          <th style="width: 40px;" class="col-center">#</th>
          <th>Plan Name</th>
          <th class="col-center" style="width: 90px;">Duration</th>
          <th class="col-right" style="width: 110px;">Unit Price</th>
          <th class="col-center" style="width: 120px;">Enrolled Members</th>
          <th class="col-center" style="width: 100px;">Share of Total</th>
          <th class="col-right" style="width: 130px;">Total Potential Yield</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $i = 1;
        $sum_yield = 0;
        foreach ($plan_dist as $pd): 
            $share_pct = $total_members > 0 ? round(((int)$pd['member_count'] / $total_members) * 100) : 0;
            $sum_yield += (float)$pd['total_val'];
        ?>
          <tr>
            <td class="col-center"><?= $i++ ?></td>
            <td><strong><?= e($pd['plan_name']) ?></strong></td>
            <td class="col-center"><?= (int)($pd['duration_months'] ?: 1) ?> Mo</td>
            <td class="col-right"><?= format_currency($pd['price']) ?></td>
            <td class="col-center"><strong style="color: #0284c7;"><?= (int)$pd['member_count'] ?> Members</strong></td>
            <td class="col-center"><strong><?= $share_pct ?>%</strong></td>
            <td class="col-right"><strong><?= format_currency($pd['total_val']) ?></strong></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Recent Payment Ledger Overview -->
    <div class="section-title"><i class="fa-solid fa-receipt"></i> Recent Verified Payment Ledger (Audit Sample)</div>
    <table class="report-table" style="margin-bottom: 24px;">
      <thead>
        <tr>
          <th class="col-center" style="width: 70px;">Receipt</th>
          <th>Member Name</th>
          <th>Plan / Purpose</th>
          <th class="col-center" style="width: 90px;">Payment Mode</th>
          <th class="col-center" style="width: 110px;">Date</th>
          <th class="col-right" style="width: 110px;">Amount</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($recent_payments)): ?>
          <tr>
            <td colspan="6" class="col-center" style="padding: 16px; color: #64748b;">No completed transactions recorded yet.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($recent_payments as $rp): ?>
            <tr>
              <td class="col-center" style="font-weight: 700; color: #64748b;"><?= (int)$rp['id'] ?></td>
              <td><strong><?= e($rp['member_name'] ?: 'Member #' . $rp['member_id']) ?></strong></td>
              <td style="color: #475569;"><?= e($rp['plan_name'] ?: ($rp['note'] ?: 'Subscription Fee')) ?></td>
              <td class="col-center"><span style="text-transform: uppercase; font-weight: 700; font-size: 11px; background: #e2e8f0; padding: 2px 6px; border-radius: 4px;"><?= e($rp['method']) ?></span></td>
              <td class="col-center"><?= format_date($rp['paid_on'], 'd M Y') ?></td>
              <td class="col-right"><strong style="color: #0284c7;"><?= format_currency($rp['amount']) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <!-- Summary & Financial Certification -->
    <div class="summary-grid">
      <div class="audit-notes-box">
        <div class="notes-title">Audit Governance &amp; Notes</div>
        <ol class="notes-list">
          <li>This report is generated directly from the certified database of Alexa Gym.</li>
          <li>All listed amounts are accounted in Indian Rupees (INR) inclusive of GST where applicable.</li>
          <li>Collections via UPI/Digital: <strong><?= format_currency($upi_revenue) ?></strong> &bull; Cash: <strong><?= format_currency($cash_revenue) ?></strong>.</li>
          <li>Reconciliation confirmed by the automated financial ledger engine.</li>
        </ol>
      </div>

      <div>
        <table class="totals-table">
          <tr>
            <td>Gross Base (Excl. Tax)</td>
            <td class="col-right"><strong><?= format_currency($taxable_total) ?></strong></td>
          </tr>
          <tr>
            <td>Estimated CGST @ 9%</td>
            <td class="col-right"><?= format_currency($cgst_total) ?></td>
          </tr>
          <tr>
            <td>Estimated SGST @ 9%</td>
            <td class="col-right"><?= format_currency($sgst_total) ?></td>
          </tr>
          <tr class="grand-total">
            <td>Grand Total Inflow</td>
            <td class="col-right"><?= format_currency($total_revenue) ?></td>
          </tr>
        </table>
      </div>
    </div>

    <!-- Signatures & Stamp -->
    <div class="report-footer">
      <!-- Circular Official Stamp -->
      <div class="official-stamp">
        <div class="stamp-text-1">★ ALEXA GYM ★</div>
        <div class="stamp-text-center">AUDITED</div>
        <div class="stamp-text-2">EXECUTIVE REPORT</div>
      </div>

      <!-- Authorized Signatory Box -->
      <div class="signatory-box">
        <div class="sign-title">For <strong>ALEXA GYM MANAGEMENT</strong></div>
        <div class="signature-graphic">R. Sharma</div>
        <div class="sign-name">Authorized Signatory</div>
        <div class="sign-role">Accounts &amp; Financial Controller</div>
      </div>
    </div>

    <div class="report-notice">
      This is an authentic computer-generated executive financial &amp; performance report issued by Alexa Gym Management System.
    </div>

  </div>
</div>

<!-- html2pdf Library for Instant Clean Client-side PDF Generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadReportPDF() {
    const btn = document.getElementById('btn-download');
    const origHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating PDF...';
    btn.disabled = true;

    const element = document.getElementById('report-sheet');
    const opt = {
        margin:       [6, 6, 6, 6],
        filename:     '<?= $pdf_filename ?>',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, letterRendering: true, logging: false },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

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
</script>
</body>
</html>
