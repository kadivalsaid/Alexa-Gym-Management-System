<?php
/**
 * Alexa Gym — Member Portal: Main Dashboard
 * Dynamically adapts according to member's actual MySQL membership status:
 * - Pending: No active membership / Choose a plan
 * - Payment Pending: Payment under review / pending
 * - In Progress: Plan chosen, awaiting admin review
 * - Active: Full access with 6 metric cards, workout, attendance, and payments
 * - Expired: Membership expired / prompt to renew
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_member();

$u = get_logged_in_user();

// Fetch or link Member details
$member = get_member_by_user_id($pdo, $u['id']);

if (!$member) {
    // Attempt lookup by email
    $stmt = $pdo->prepare("SELECT m.*, p.name AS plan_name, p.duration_months, p.price AS plan_price, 
                                  t.full_name AS trainer_name, t.phone AS trainer_phone, t.specialization AS trainer_specialization 
                           FROM members m 
                           LEFT JOIN plans p ON m.plan_id = p.id 
                           LEFT JOIN trainers t ON m.trainer_id = t.id 
                           WHERE m.email = ? LIMIT 1");
    $stmt->execute([$u['email']]);
    $member = $stmt->fetch();
    if ($member && empty($member['user_id'])) {
        $pdo->prepare("UPDATE members SET user_id = ? WHERE id = ?")->execute([$u['id'], $member['id']]);
        $member['user_id'] = $u['id'];
    }
}

$u_phone_stmt = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
$u_phone_stmt->execute([$u['id']]);
$u_phone = (string)($u_phone_stmt->fetchColumn() ?: '');
$u['phone'] = $u_phone;
$_SESSION['user_phone'] = $u_phone;

// If member record doesn't exist, user has not chosen a plan yet (virtual in-memory fallback)
if (!$member) {
    $member = [
        'id' => 0,
        'user_id' => $u['id'],
        'full_name' => $u['name'],
        'email' => $u['email'],
        'phone' => $u_phone,
        'gender' => 'male',
        'dob' => null,
        'address' => '',
        'plan_id' => null,
        'trainer_id' => null,
        'status' => 'pending',
        'expiry_date' => null
    ];
} elseif (empty($member['phone']) || $member['phone'] === 'Not Provided') {
    $member['phone'] = $u_phone;
}

// Handle Flash Messages
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Read any pre-selected plan from public website registration flow
$preselected_plan_id = (int)($_SESSION['chosen_plan_id'] ?? 0);
unset($_SESSION['chosen_plan_id']);

// Handle Quick Save Contact Phone Number
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_contact_phone') {
    $p_in = trim($_POST['phone_number'] ?? '');
    $p_digits = preg_replace('/[^0-9]/', '', $p_in);
    if (strlen($p_digits) !== 10 || !preg_match('/^[6-9]\d{9}$/', $p_digits)) {
        $_SESSION['flash_error'] = 'Please enter a valid 10-digit mobile phone number starting with 6, 7, 8, or 9.';
    } else {
        $pdo->prepare("UPDATE users SET phone = ? WHERE id = ?")->execute([$p_digits, $u['id']]);
        $_SESSION['user_phone'] = $p_digits;
        $u['phone'] = $p_digits;
        if (!empty($member['id'])) {
            $pdo->prepare("UPDATE members SET phone = ? WHERE id = ?")->execute([$p_digits, $member['id']]);
        }
        $member['phone'] = $p_digits;
        $_SESSION['flash_success'] = 'Phone number saved successfully to your profile!';
    }
    header('Location: dashboard.php');
    exit;
}

// Handle Choose Plan & Request Submission from Dashboard
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'choose_plan_pay') {
    $plan_id    = (int)($_POST['plan_id'] ?? 0);
    $method     = trim($_POST['payment_method'] ?? 'upi');
    $trainer_id = (int)($_POST['trainer_id'] ?? 0);
    $txn_id     = trim($_POST['transaction_id'] ?? '');
    if (empty($txn_id)) {
        $txn_id = strtoupper($method) . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    // Phone number submitted in modal (mandatory permanent field)
    $sub_phone = trim($_POST['phone_number'] ?? '');
    if (empty($sub_phone) && !empty($u['phone'])) {
        $sub_phone = $u['phone'];
    }
    $sub_digits = preg_replace('/[^0-9]/', '', $sub_phone);
    if (strlen($sub_digits) !== 10 || !preg_match('/^[6-9]\d{9}$/', $sub_digits)) {
        $flash_error = 'Please provide a valid 10-digit contact mobile number (starting with 6, 7, 8, or 9).';
    } else {
        // Permanently save to users table
        $pdo->prepare("UPDATE users SET phone = ? WHERE id = ?")->execute([$sub_digits, $u['id']]);
        $_SESSION['user_phone'] = $sub_digits;
        $u['phone'] = $sub_digits;

        // If member record already exists in members table, update it too
        if (!empty($member['id'])) {
            $pdo->prepare("UPDATE members SET phone = ? WHERE id = ?")->execute([$sub_digits, $member['id']]);
        }
        $member['phone'] = $sub_digits;
    }

    $p_stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ? LIMIT 1");
    $p_stmt->execute([$plan_id]);
    $chosen_plan = $p_stmt->fetch();

    if (!empty($flash_error)) {
        // Halt if phone validation failed
    } elseif (!$chosen_plan) {
        $flash_error = 'Selected plan is currently not available. Please choose another.';
    } else {
        try {
            $pdo->beginTransaction();
            $amount = (float)$chosen_plan['price'];
            $target_trainer = ($trainer_id > 0) ? $trainer_id : ($member['trainer_id'] ?? 1);
            $contact_phone = !empty($sub_digits) ? $sub_digits : ($u['phone'] ?? $member['phone'] ?? '');

            // 1. Insert Plan Request into membership_requests table ONLY (DO NOT touch members or payments table!)
            $req_ins = $pdo->prepare("
                INSERT INTO membership_requests (user_id, full_name, email, phone, gender, dob, address, plan_id, trainer_id, payment_method, transaction_id, amount, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $req_ins->execute([
                $u['id'],
                $u['name'],
                $u['email'],
                $contact_phone,
                $member['gender'] ?? 'male',
                !empty($member['dob']) ? $member['dob'] : null,
                $member['address'] ?? '',
                $chosen_plan['id'],
                $target_trainer,
                $method,
                $txn_id,
                $amount
            ]);

            // 2. Send Notification to User
            $notif = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                VALUES (?, 'Membership Request Submitted ⏳', ?, 'info', 0, NOW())
            ");
            $notif->execute([
                $u['id'],
                "Your membership request for {$chosen_plan['name']} (₹" . number_format($amount, 2) . ") with payment reference {$txn_id} has been submitted! Our admin team is reviewing your request."
            ]);

            // 3. Send Notification to Admin
            $adm_title = 'New Plan Request: ' . $chosen_plan['name'];
            $adm_msg   = ($u['name'] ?? 'A member') . " has requested the {$chosen_plan['name']} plan (Ref: {$txn_id}). Please review in Admin Panel.";
            $adm_stmt  = $pdo->query("SELECT id FROM users WHERE role = 'admin'");
            $ins_adm   = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                VALUES (?, ?, ?, 'info', 0, NOW())
            ");
            while ($adm = $adm_stmt->fetch()) {
                $ins_adm->execute([
                    $adm['id'],
                    $adm_title,
                    $adm_msg
                ]);
            }

            $pdo->commit();
            $_SESSION['flash_success'] = "Your membership request for {$chosen_plan['name']} has been submitted! It is now under review by our Admin team.";
            header('Location: dashboard.php');
            exit;
        } catch (Exception $ex) {
            $pdo->rollBack();
            $flash_error = 'Payment request could not be processed: ' . $ex->getMessage();
        }
    }
}

// Check for pending membership request
$pending_request = get_pending_request_by_user_id($pdo, $u['id']);

// Fetch latest payment for member (for active/expired members)
$latest_payment = null;
if ($member && !empty($member['id'])) {
    try {
        $lp_stmt = $pdo->prepare("SELECT * FROM payments WHERE member_id = ? ORDER BY id DESC LIMIT 1");
        $lp_stmt->execute([$member['id']]);
        $latest_payment = $lp_stmt->fetch();
    } catch (Exception $e) {
        $latest_payment = null;
    }
}

// Check for any pending payment in MySQL
$has_pending_payment = ($latest_payment && $latest_payment['status'] === 'pending');

// Determine Dynamic Membership Status from MySQL
$raw_status      = strtolower($member['status'] ?? 'pending');
$expiry_date_str = $member['expiry_date'] ?? null;
$has_plan        = !empty($member['plan_id']);
$is_expired      = (!empty($expiry_date_str) && strtotime($expiry_date_str) < time());

if ($is_expired && !empty($member['id'])) {
    $status_type        = 'expired';
    $status_title       = 'Expired';
    $status_badge_text  = 'EXPIRED';
    $status_badge_class = 'badge-danger';
    $status_message     = 'Your membership has expired. Renew your membership to regain full gym access.';
} elseif ($member && !empty($member['id']) && $raw_status === 'active' && !$is_expired) {
    $status_type        = 'active';
    $status_title       = 'Active';
    $status_badge_text  = 'ACTIVE';
    $status_badge_class = 'badge-active';
    $plan_name_display  = $member['plan_name'] ?? 'Premium';
    $status_message     = "Your {$plan_name_display} membership is active.";
} elseif ($pending_request) {
    // User has submitted a plan request awaiting Admin verification (NOT yet in members table)
    $status_type        = 'in_progress';
    $status_title       = 'In Progress';
    $status_badge_text  = 'IN PROGRESS';
    $status_badge_class = 'badge-info';
    $status_message     = 'Your membership request has been submitted and is awaiting Admin review and approval.';
    // Populate member array with requested plan info for in_progress display
    $member['plan_name'] = $pending_request['plan_name'];
    $member['duration_months'] = $pending_request['duration_months'];
    $member['plan_price'] = $pending_request['plan_price'];
    $member['trainer_name'] = $pending_request['trainer_name'];
    $member['trainer_specialization'] = $pending_request['trainer_specialization'];
    if (!empty($pending_request['phone'])) {
        $member['phone'] = $pending_request['phone'];
    }
    $latest_payment = [
        'amount' => $pending_request['amount'],
        'method' => $pending_request['payment_method'],
        'transaction_id' => $pending_request['transaction_id'],
        'paid_on' => $pending_request['created_at'],
        'status' => 'pending'
    ];
} else {
    // New member or no active plan
    $status_type        = 'pending';
    $status_title       = 'Pending';
    $status_badge_text  = 'PENDING';
    $status_badge_class = 'badge-pending';
    $status_message     = 'Choose a membership plan to start your fitness journey.';
}

$is_active_member = ($status_type === 'active');

// Check if contact phone number is missing (e.g. from Google Login)
$clean_phone_digits = preg_replace('/[^0-9]/', '', (string)($member['phone'] ?? ''));
$is_phone_missing = empty($member['phone']) || $member['phone'] === 'Not Provided' || strlen($clean_phone_digits) < 10;

// Calculate validity remaining & percentage for active members
$days_remaining  = 0;
$days_elapsed    = 0;
$total_plan_days = 0;
$progress_pct    = 0;
if ($expiry_date_str && $is_active_member) {
    $now_ts    = time();
    $exp_ts    = strtotime($expiry_date_str);
    $join_ts   = !empty($member['join_date']) ? strtotime($member['join_date']) : strtotime('-30 days');
    $total_sec = max(1, $exp_ts - $join_ts);
    $rem_sec   = max(0, $exp_ts - $now_ts);
    
    $days_remaining  = max(0, (int)ceil(($exp_ts - $now_ts) / 86400));
    $days_elapsed    = max(0, (int)floor(($now_ts - $join_ts) / 86400));
    $total_plan_days = max(1, (int)ceil(($exp_ts - $join_ts) / 86400));
    $progress_pct    = min(100, max(0, round(($rem_sec / $total_sec) * 100)));
}

// Attendance Count and Recent Logs
$att_count = 0;
$recent_attendance = [];
if ($member && !empty($member['id'])) {
    try {
        $att_stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE member_id = ?");
        $att_stmt->execute([$member['id']]);
        $att_count = (int)$att_stmt->fetchColumn();

        $att_recent_stmt = $pdo->prepare("SELECT * FROM attendance WHERE member_id = ? ORDER BY attend_date DESC LIMIT 4");
        $att_recent_stmt->execute([$member['id']]);
        $recent_attendance = $att_recent_stmt->fetchAll();
    } catch (Exception $e) {
        $att_count = 0;
    }
}

// Recent Payments for this member
$recent_payments = [];
if ($member && !empty($member['id'])) {
    try {
        $p_list_stmt = $pdo->prepare("
            SELECT p.*, pl.name AS plan_name 
            FROM payments p 
            LEFT JOIN plans pl ON p.plan_id = pl.id 
            WHERE p.member_id = ? 
            ORDER BY p.id DESC 
            LIMIT 5
        ");
        $p_list_stmt->execute([$member['id']]);
        $recent_payments = $p_list_stmt->fetchAll();
    } catch (Exception $e) {
        $recent_payments = [];
    }
}

// Today's Workouts preview
$today_day = date('l');
$workouts_preview = [];
if ($member && !empty($member['id'])) {
    try {
        $w_stmt = $pdo->prepare("SELECT * FROM workout_assignments WHERE member_id = ? ORDER BY id ASC LIMIT 3");
        $w_stmt->execute([$member['id']]);
        $workouts_preview = $w_stmt->fetchAll();
        
        if (empty($workouts_preview)) {
            $w_stmt2 = $pdo->query("SELECT * FROM workout_assignments ORDER BY id ASC LIMIT 3");
            $workouts_preview = $w_stmt2->fetchAll();
        }
    } catch (Exception $e) {
        $workouts_preview = [];
    }
}

// Available Trainers list for modal checkout
$trainers_list = [];
try {
    $t_stmt = $pdo->query("SELECT id, full_name, specialization FROM trainers WHERE status = 'active' ORDER BY full_name ASC");
    $trainers_list = $t_stmt->fetchAll();
} catch (Exception $e) {
    $trainers_list = [];
}

$page_title   = 'Member Dashboard — Alexa Gym';
$topbar_title = $is_active_member ? 'Member Dashboard' : ($status_type === 'in_progress' ? 'Membership In Progress' : 'Member Dashboard');
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
  <style>
    /* Status Badge Pulsing & Card Styles */
    .status-badge-custom {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 14px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 800;
      letter-spacing: 0.8px;
      text-transform: uppercase;
      margin-bottom: 12px;
    }
    .status-badge-custom.badge-pending {
      background: rgba(245, 158, 11, 0.15);
      border: 1px solid rgba(245, 158, 11, 0.4);
      color: #fbbf24;
    }
    .status-badge-custom.badge-warning {
      background: rgba(249, 115, 22, 0.15);
      border: 1px solid rgba(249, 115, 22, 0.4);
      color: #fb923c;
    }
    .status-badge-custom.badge-info {
      background: rgba(0, 242, 254, 0.15);
      border: 1px solid rgba(0, 242, 254, 0.4);
      color: var(--cyan);
    }
    .status-badge-custom.badge-active {
      background: rgba(16, 185, 129, 0.15);
      border: 1px solid rgba(16, 185, 129, 0.4);
      color: #34d399;
    }
    .status-badge-custom.badge-danger {
      background: rgba(239, 68, 68, 0.15);
      border: 1px solid rgba(239, 68, 68, 0.4);
      color: #f87171;
    }
    .pulse-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: currentColor;
      box-shadow: 0 0 10px currentColor;
      animation: pulseAnim 1.6s infinite;
    }
    @keyframes pulseAnim {
      0% { transform: scale(0.9); opacity: 0.7; }
      50% { transform: scale(1.3); opacity: 1; }
      100% { transform: scale(0.9); opacity: 0.7; }
    }

    /* Status Card */
    .status-card-hero {
      background: linear-gradient(135deg, rgba(28, 24, 45, 0.95) 0%, rgba(19, 17, 30, 0.95) 100%);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      padding: 28px 26px;
      position: relative;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .status-card-hero::before {
      content: '';
      position: absolute;
      top: -40px;
      right: -40px;
      width: 180px;
      height: 180px;
      background: radial-gradient(circle, rgba(0, 242, 254, 0.1) 0%, transparent 70%);
      pointer-events: none;
    }
    [data-theme="light"] .status-card-hero {
      background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%) !important;
      border-color: #e2e8f0;
      box-shadow: 0 8px 25px rgba(15, 23, 42, 0.06);
    }
    [data-theme="light"] .status-card-hero::before {
      background: radial-gradient(circle, rgba(2, 132, 199, 0.08) 0%, transparent 70%);
    }
    [data-theme="light"] .status-card-hero h2,
    [data-theme="light"] .status-card-hero h3 {
      color: #0f172a;
    }
    [data-theme="light"] .profile-card-hero {
      background: #ffffff;
      border-color: #e2e8f0;
      box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05);
    }
    [data-theme="light"] .profile-avatar-box {
      color: #ffffff !important;
      box-shadow: 0 4px 14px rgba(2, 132, 199, 0.25);
    }
    [data-theme="light"] .profile-item {
      background: #f8fafc;
      border-color: #e2e8f0;
    }
    [data-theme="light"] .plan-card {
      background: #ffffff;
      border-color: #e2e8f0;
      box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05);
    }
    [data-theme="light"] .plan-card.featured {
      background: linear-gradient(180deg, #ffffff 0%, #f0f9ff 100%) !important;
      border: 2px solid #0284c7;
      box-shadow: 0 12px 35px rgba(2, 132, 199, 0.14);
    }
    [data-theme="light"] .plan-card.featured .plan-features-list li {
      color: #0f172a !important;
    }
    [data-theme="light"] .plan-badge-top {
      color: #ffffff !important;
    }
    [data-theme="light"] .stepper-container {
      background: #ffffff;
      border-color: #e2e8f0;
      box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05);
    }
    [data-theme="light"] .step-circle {
      background: #f1f5f9;
      border-color: #cbd5e1;
      color: #64748b;
    }

    /* Profile Card */
    .profile-card-hero {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      padding: 28px 26px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
    }
    .profile-avatar-box {
      width: 76px;
      height: 76px;
      border-radius: 20px;
      background: var(--accent-grad);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 32px;
      font-weight: 900;
      color: #070913;
      box-shadow: 0 0 20px var(--cyan-glow);
      flex-shrink: 0;
    }
    .profile-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 12px;
      margin-top: 18px;
    }
    .profile-item {
      background: var(--panel-2);
      border: 1px solid var(--line);
      border-radius: 10px;
      padding: 10px 14px;
    }
    .profile-item-label {
      font-size: 11px;
      font-weight: 700;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 2px;
    }
    .profile-item-val {
      font-size: 13.5px;
      color: var(--text);
      font-weight: 600;
      word-break: break-word;
    }

    /* Plans Grid */
    .plans-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(310px, 1fr));
      gap: 24px;
      margin-top: 20px;
    }
    .plan-card {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      padding: 32px 28px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
      transition: all 0.3s ease;
    }
    .plan-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 14px 35px rgba(0, 0, 0, 0.45);
      border-color: rgba(0, 242, 254, 0.4);
    }
    .plan-card.featured {
      background: linear-gradient(180deg, rgba(20, 27, 45, 0.95) 0%, rgba(13, 17, 30, 0.95) 100%);
      border: 2px solid var(--cyan);
      box-shadow: 0 0 25px rgba(0, 242, 254, 0.2);
    }
    .plan-badge-top {
      position: absolute;
      top: -12px;
      left: 50%;
      transform: translateX(-50%);
      background: var(--accent-grad);
      color: #070913;
      font-size: 11px;
      font-weight: 900;
      padding: 4px 14px;
      border-radius: 999px;
      text-transform: uppercase;
      letter-spacing: 1px;
      box-shadow: 0 4px 12px rgba(0, 242, 254, 0.3);
    }
    .plan-features-list {
      list-style: none;
      padding: 0;
      margin: 24px 0 28px 0;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .plan-features-list li {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 14px;
      color: var(--text);
    }
    .plan-features-list li i {
      color: var(--cyan);
      font-size: 13px;
    }

    /* Modal Overlay & Card */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(7, 9, 19, 0.85);
      backdrop-filter: blur(8px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.25s ease;
      padding: 20px 16px;
      overflow-y: auto;
    }
    .modal-overlay.active {
      opacity: 1;
      pointer-events: auto;
    }
    .modal-card {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      width: 100%;
      max-width: 530px;
      max-height: calc(100vh - 40px);
      display: flex;
      flex-direction: column;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
      overflow: hidden;
      transform: translateY(20px);
      transition: transform 0.25s ease;
      margin: auto;
    }
    .modal-overlay.active .modal-card {
      transform: translateY(0);
    }
    .modal-header {
      padding: 18px 24px;
      border-bottom: 1px solid var(--line);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
    }
    .modal-card form {
      display: flex;
      flex-direction: column;
      flex: 1;
      overflow: hidden;
      min-height: 0;
    }
    .modal-body {
      padding: 20px 24px;
      overflow-y: auto;
      flex: 1;
      min-height: 0;
    }
    .modal-body::-webkit-scrollbar {
      width: 6px;
    }
    .modal-body::-webkit-scrollbar-track {
      background: var(--panel-2);
      border-radius: 999px;
    }
    .modal-body::-webkit-scrollbar-thumb {
      background: rgba(0, 242, 254, 0.4);
      border-radius: 999px;
    }
    .modal-body::-webkit-scrollbar-thumb:hover {
      background: var(--cyan);
    }
    .modal-footer {
      padding: 16px 24px;
      border-top: 1px solid var(--line);
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      background: var(--panel);
      flex-shrink: 0;
    }
    .pay-methods-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;
      margin: 16px 0;
    }
    .pay-method-choice {
      border: 1.5px solid var(--line);
      background: var(--panel-2);
      border-radius: 12px;
      padding: 14px 10px;
      text-align: center;
      cursor: pointer;
      transition: all 0.25s ease;
      position: relative;
    }
    .pay-method-choice:hover {
      border-color: rgba(0, 242, 254, 0.45);
      background: rgba(0, 242, 254, 0.05);
    }
    .pay-method-choice.selected {
      border-color: var(--cyan);
      background: rgba(0, 242, 254, 0.12);
      box-shadow: 0 0 16px rgba(0, 242, 254, 0.2);
    }
    .pay-method-choice i {
      font-size: 24px;
      margin-bottom: 5px;
      display: block;
      color: var(--cyan);
    }
    .pay-method-choice span {
      font-size: 13px;
      font-weight: 800;
      color: var(--text);
      display: block;
    }
    .pay-method-choice small {
      font-size: 10.5px;
      color: var(--muted);
      display: block;
      margin-top: 2px;
    }

    /* Stepper Styling */
    .stepper-container {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      padding: 24px 26px;
      margin-bottom: 28px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
    }
    .stepper-header-title {
      font-size: 13px;
      font-weight: 700;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.8px;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .stepper-track {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      position: relative;
    }
    .stepper-track::before {
      content: '';
      position: absolute;
      top: 20px;
      left: 40px;
      right: 40px;
      height: 3px;
      background: var(--line);
      z-index: 1;
    }
    .stepper-track-fill {
      position: absolute;
      top: 20px;
      left: 40px;
      width: 66%;
      height: 3px;
      background: var(--accent-grad);
      z-index: 2;
      box-shadow: 0 0 12px var(--cyan-glow);
    }
    .step-node {
      position: relative;
      z-index: 3;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      flex: 1;
    }
    .step-circle {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      background: var(--panel-2);
      border: 2px solid var(--line);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 15px;
      font-weight: 800;
      color: var(--muted);
      transition: all 0.3s;
    }
    .step-node.completed .step-circle {
      background: rgba(16, 185, 129, 0.15);
      border-color: #10b981;
      color: #34d399;
      box-shadow: 0 0 12px rgba(16, 185, 129, 0.25);
    }
    .step-node.in-progress .step-circle {
      background: rgba(0, 242, 254, 0.15);
      border-color: var(--cyan);
      color: var(--cyan);
      box-shadow: 0 0 18px var(--cyan-glow);
      animation: pulseAnim 1.6s infinite;
    }
    .step-node.locked .step-circle {
      background: var(--panel-2);
      border-color: var(--line);
      color: var(--muted);
      opacity: 0.6;
    }
    .step-node-title {
      font-size: 13px;
      font-weight: 700;
      color: var(--text);
      margin-top: 10px;
    }
    .step-node-desc {
      font-size: 11.5px;
      color: var(--muted);
      margin-top: 2px;
    }
  </style>
</head>
<body>

<div class="app-layout">
  <!-- Sidebar -->
  <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

  <!-- Main View -->
  <main class="app-main">
    <?php require_once __DIR__ . '/../includes/user_topbar.php'; ?>

    <div class="dashboard-content">

      <!-- Flash Messages -->
      <?php if (!empty($flash_success)): ?>
        <div class="alert alert-success" style="margin-bottom: 24px;">
          <i class="fa-solid fa-circle-check"></i>
          <span><?= e($flash_success) ?></span>
        </div>
      <?php endif; ?>
      <?php if (!empty($flash_error)): ?>
        <div class="alert alert-danger" style="margin-bottom: 24px;">
          <i class="fa-solid fa-triangle-exclamation"></i>
          <span><?= e($flash_error) ?></span>
        </div>
      <?php endif; ?>


      <?php if ($status_type === 'in_progress'): ?>
        <!-- =========================================================
             STATE 1: IN PROGRESS (PLAN CHOSEN & PAYMENT SUBMITTED, AWAITING ADMIN REVIEW)
             ========================================================= -->

        <!-- Welcome Banner -->
        <div style="margin-bottom: 24px;">
          <h2 style="font-size: 28px; font-weight: 800; margin-bottom: 4px; letter-spacing: -0.5px;">
            Welcome, <?= e($u['name']) ?> 👋
          </h2>
          <p style="color: var(--muted); font-size: 15px; margin-bottom: 0;">
            Your membership request has been submitted. Our gym administration is reviewing your details to activate your account.
          </p>
        </div>

        <!-- In Progress Hero Banner -->
        <div class="status-card-hero" style="margin-bottom: 26px;">
          <div>
            <div class="status-badge-custom badge-info">
              <span class="pulse-dot"></span>
              <span>Status: IN PROGRESS • Awaiting Admin Acceptance</span>
            </div>

            <h3 style="font-size: 24px; font-weight: 800; color: var(--text); margin-bottom: 8px;">
              Membership Request In Progress ⏳
            </h3>

            <p style="color: var(--muted); font-size: 14.5px; line-height: 1.6; max-width: 840px; margin-bottom: 20px;">
              Thank you for choosing Alexa Gym! Your request for the <strong style="color: var(--cyan);"><?= e($member['plan_name'] ?? 'Membership') ?></strong> package (<?= (int)($member['duration_months'] ?? 1) ?> Month(s)) with coach <strong style="color: var(--text);"><?= e($member['trainer_name'] ?? 'General Gym Coach') ?></strong> has been received along with your payment submission. Once our administrative team verifies your details, your full membership dashboard and all gym facilities will unlock automatically.
            </p>
          </div>

          <div style="border-top: 1px solid var(--line); padding-top: 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
              <i class="fa-solid fa-bell" style="color: var(--cyan); font-size: 16px;"></i>
              <span style="font-size: 13.5px; color: var(--text); font-weight: 600;">
                You will receive an automatic system notification as soon as the Admin approves your admission.
              </span>
            </div>
            <a href="tickets.php?action=new" class="btn btn-outline btn-sm">
              <i class="fa-solid fa-headset"></i> Contact Support
            </a>
          </div>
        </div>

        <!-- 4-Step Stepper -->
        <div class="stepper-container">
          <div class="stepper-header-title">
            <i class="fa-solid fa-route" style="color: var(--cyan);"></i> Membership Onboarding Status
          </div>
          <div class="stepper-track">
            <div class="stepper-track-fill"></div>
            
            <!-- Step 1: Registration -->
            <div class="step-node completed">
              <div class="step-circle">
                <i class="fa-solid fa-check"></i>
              </div>
              <div class="step-node-title">1. Registration</div>
              <div class="step-node-desc">Account Created</div>
            </div>

            <!-- Step 2: Plan & Payment -->
            <div class="step-node completed">
              <div class="step-circle">
                <i class="fa-solid fa-check"></i>
              </div>
              <div class="step-node-title">2. Plan &amp; Payment</div>
              <div class="step-node-desc"><?= e($member['plan_name'] ?? 'Plan') ?> Submitted</div>
            </div>

            <!-- Step 3: Admin Review -->
            <div class="step-node in-progress">
              <div class="step-circle">
                <i class="fa-solid fa-hourglass-half"></i>
              </div>
              <div class="step-node-title" style="color: var(--cyan);">3. Admin Review</div>
              <div class="step-node-desc" style="color: var(--cyan);">In Progress ⏳</div>
            </div>

            <!-- Step 4: Full Access -->
            <div class="step-node locked">
              <div class="step-circle">
                <i class="fa-solid fa-lock"></i>
              </div>
              <div class="step-node-title">4. Full Access</div>
              <div class="step-node-desc">Unlocks Upon Approval</div>
            </div>
          </div>
        </div>

        <!-- 2 Column Section: Selected Plan Details + Profile Card -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 28px;">
          
          <!-- Card 1: Submitted Request Details -->
          <div class="card" style="padding: 26px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
              <h3 class="card-title" style="margin-bottom: 0;">
                <i class="fa-solid fa-receipt" style="color: var(--cyan); margin-right: 8px;"></i>
                Submitted Request Details
              </h3>
              <span class="badge" style="background: rgba(0, 242, 254, 0.15); color: var(--cyan); border: 1px solid rgba(0, 242, 254, 0.3); padding: 4px 10px; border-radius: 999px; font-weight: 700; font-size: 11px;">
                IN PROGRESS
              </span>
            </div>

            <div style="display: flex; flex-direction: column; gap: 14px;">
              <div style="background: var(--panel-2); border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; display: flex; align-items: center; justify-content: space-between;">
                <div>
                  <div style="font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase;">Selected Plan</div>
                  <strong style="font-size: 16px; color: var(--text);"><?= e($member['plan_name'] ?? 'Standard Pro') ?></strong>
                </div>
                <div style="text-align: right;">
                  <div style="font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase;">Duration</div>
                  <span style="font-size: 14px; font-weight: 700; color: var(--cyan);"><?= (int)($member['duration_months'] ?? 1) ?> Month(s)</span>
                </div>
              </div>

              <div style="background: var(--panel-2); border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; display: flex; align-items: center; justify-content: space-between;">
                <div>
                  <div style="font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase;">Amount Submitted</div>
                  <strong style="font-size: 17px; color: var(--cyan);"><?= format_currency($latest_payment['amount'] ?? ($member['plan_price'] ?? 0)) ?></strong>
                </div>
                <div style="text-align: right;">
                  <div style="font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase;">Payment Method</div>
                  <span style="font-size: 13px; font-weight: 700; color: var(--text); text-transform: uppercase;"><?= e($latest_payment['method'] ?? 'UPI') ?></span>
                </div>
              </div>

              <div style="background: var(--panel-2); border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; display: flex; align-items: center; justify-content: space-between;">
                <div>
                  <div style="font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase;">Transaction Reference / UTR</div>
                  <span class="code-badge" style="font-size: 12.5px;"><?= e($latest_payment['transaction_id'] ?? 'REF-SUBMITTED') ?></span>
                </div>
                <div style="text-align: right;">
                  <div style="font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase;">Submitted Date</div>
                  <span style="font-size: 13px; color: var(--muted); font-weight: 600;"><?= format_date($latest_payment['paid_on'] ?? date('Y-m-d')) ?></span>
                </div>
              </div>

              <div style="background: var(--panel-2); border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; display: flex; align-items: center; justify-content: space-between;">
                <div>
                  <div style="font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase;">Assigned Coach</div>
                  <strong style="font-size: 14px; color: var(--text);"><i class="fa-solid fa-user-ninja" style="color: var(--cyan); margin-right: 6px;"></i><?= e($member['trainer_name'] ?? 'General Gym Coach') ?></strong>
                </div>
                <span style="font-size: 12px; color: var(--muted);"><?= e($member['trainer_specialization'] ?? 'Certified Trainer') ?></span>
              </div>
            </div>

            <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--line); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
              <span style="font-size: 12px; color: var(--muted);">Want to switch your package or coach?</span>
              <a href="plans.php" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-arrows-rotate"></i> Change Plan
              </a>
            </div>
          </div>

          <!-- Card 2: Member Profile Card -->
          <div class="profile-card-hero">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
              <h3 class="card-title" style="margin-bottom: 0;">
                <i class="fa-solid fa-user-circle" style="color: var(--cyan); margin-right: 8px;"></i>
                My Profile
              </h3>
              <a href="profile.php?tab=edit" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-user-pen"></i> Edit Profile
              </a>
            </div>

            <div style="display: flex; align-items: center; gap: 18px; margin-bottom: 14px;">
              <div class="profile-avatar-box">
                <?= strtoupper(substr($u['name'] ?? 'M', 0, 1)) ?>
              </div>
              <div>
                <h4 style="font-size: 19px; font-weight: 800; color: var(--text); margin-bottom: 2px;">
                  <?= e($u['name']) ?>
                </h4>
                <div style="font-size: 13.5px; color: var(--cyan); font-weight: 600;">
                  <i class="fa-solid fa-envelope" style="margin-right: 4px; font-size: 12px;"></i> <?= e($u['email']) ?>
                </div>
                <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                  Joined: <?= !empty($member['join_date']) ? format_date($member['join_date']) : date('d M Y') ?>
                </div>
              </div>
            </div>

            <!-- Profile Info Grid -->
            <div class="profile-grid">
              <div class="profile-item">
                <div class="profile-item-label"><i class="fa-solid fa-phone" style="color: var(--cyan); margin-right: 4px;"></i> Phone</div>
                <div class="profile-item-val"><?= !empty($member['phone']) ? e($member['phone']) : '<span style="color: var(--muted);">Not provided</span>' ?></div>
              </div>

              <div class="profile-item">
                <div class="profile-item-label"><i class="fa-solid fa-venus-mars" style="color: var(--cyan); margin-right: 4px;"></i> Gender</div>
                <div class="profile-item-val"><?= ucfirst(e($member['gender'] ?? 'male')) ?></div>
              </div>

              <div class="profile-item">
                <div class="profile-item-label"><i class="fa-solid fa-cake-candles" style="color: var(--cyan); margin-right: 4px;"></i> Date of Birth</div>
                <div class="profile-item-val"><?= !empty($member['dob']) ? format_date($member['dob']) : '<span style="color: var(--muted);">Not provided</span>' ?></div>
              </div>

              <div class="profile-item">
                <div class="profile-item-label"><i class="fa-solid fa-location-dot" style="color: var(--cyan); margin-right: 4px;"></i> Address</div>
                <div class="profile-item-val"><?= !empty($member['address']) ? e($member['address']) : '<span style="color: var(--muted);">Not provided</span>' ?></div>
              </div>
            </div>
          </div>

        </div>

        <!-- Card 3: Day 1 Gym Guidelines & Operating Hours -->
        <div class="card" style="padding: 26px; margin-bottom: 24px;">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; flex-wrap: wrap; gap: 12px;">
            <h3 class="card-title" style="margin-bottom: 0;">
              <i class="fa-solid fa-circle-info" style="color: var(--cyan); margin-right: 8px;"></i>
              Gym Guidelines &amp; Operating Hours
            </h3>
            <span style="font-size: 12px; color: #34d399; font-weight: 700; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); padding: 4px 10px; border-radius: 999px;">
              <i class="fa-solid fa-door-open"></i> Front Desk Open
            </span>
          </div>

          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
            <div style="background: var(--panel-2); border: 1px solid var(--line); border-radius: 12px; padding: 18px;">
              <strong style="font-size: 14.5px; color: var(--text); display: block; margin-bottom: 8px;">
                <i class="fa-solid fa-clock" style="color: var(--cyan); margin-right: 6px;"></i> Facility Timings
              </strong>
              <div style="font-size: 13px; color: var(--muted); line-height: 1.7;">
                <div><strong>Mon – Sat:</strong> 06:00 AM – 11:00 AM &amp; 04:00 PM – 10:00 PM</div>
                <div><strong>Sunday:</strong> 07:00 AM – 02:00 PM (Morning Special)</div>
                <div style="color: var(--cyan); margin-top: 4px;"><i class="fa-solid fa-shield-halved"></i> 24/7 Monitored Secure Gym Floor</div>
              </div>
            </div>

            <div style="background: var(--panel-2); border: 1px solid var(--line); border-radius: 12px; padding: 18px;">
              <strong style="font-size: 14.5px; color: var(--text); display: block; margin-bottom: 8px;">
                <i class="fa-solid fa-id-badge" style="color: var(--cyan); margin-right: 6px;"></i> First Day Onboarding Checklist
              </strong>
              <ul style="list-style: none; padding: 0; margin: 0; font-size: 13px; color: var(--muted); display: flex; flex-direction: column; gap: 6px;">
                <li><i class="fa-solid fa-check" style="color: #34d399; margin-right: 6px;"></i> Carry clean indoor sports shoes &amp; gym towel</li>
                <li><i class="fa-solid fa-check" style="color: #34d399; margin-right: 6px;"></i> Biometric fingerprint setup at front desk</li>
                <li><i class="fa-solid fa-check" style="color: #34d399; margin-right: 6px;"></i> Intro fitness consultation with Coach <?= e($member['trainer_name'] ?? 'Vikram Sharma') ?></li>
              </ul>
            </div>
          </div>
        </div>

      <?php elseif ($status_type === 'pending' || $status_type === 'expired'): ?>
        <!-- =========================================================
             STATE 2: NEW / UNENROLLED MEMBER (CHOOSE A PLAN) OR EXPIRED
             ========================================================= -->

        <!-- Welcome Banner -->
        <div style="margin-bottom: 26px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
          <div>
            <h2 style="font-size: 28px; font-weight: 800; margin-bottom: 4px; letter-spacing: -0.5px;">
              Welcome, <?= e($u['name']) ?> 👋
            </h2>
            <p style="color: var(--muted); font-size: 15px; margin-bottom: 0;">
              <?php if ($status_type === 'expired'): ?>
                Your membership has expired. Please select a plan below to renew and continue your training.
              <?php else: ?>
                Please choose a membership package below to get started and unlock your gym experience.
              <?php endif; ?>
            </p>
          </div>
          <a href="profile.php" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-user-circle"></i> My Profile
          </a>
        </div>

        <?php if ($status_type === 'expired'): ?>
          <div class="alert alert-danger" style="margin-bottom: 24px;">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span><strong>Membership Expired:</strong> Your gym membership has ended. Choose a package below to renew instantly.</span>
          </div>
        <?php endif; ?>

        <!-- =========================================================
             SECTION: CHOOSE YOUR MEMBERSHIP (3 PREMIUM PLAN CARDS DIRECTLY VISIBLE)
             ========================================================= -->
        <div id="membership-plans" style="margin-bottom: 36px;">
          <div style="text-align: center; max-width: 640px; margin: 0 auto 26px auto;">
            <span style="font-size: 12px; font-weight: 800; color: var(--cyan); letter-spacing: 1.2px; text-transform: uppercase;">
              MEMBERSHIP TIERS
            </span>
            <h2 style="font-size: 28px; font-weight: 800; margin-top: 4px; margin-bottom: 8px;">
              Choose Your Membership
            </h2>
            <p style="color: var(--muted); font-size: 14.5px;">
              Select a flexible plan suited to your fitness goals. Get instant access to certified coaches, cross-fit machines, and nutrition counseling.
            </p>
          </div>

          <div class="plans-container">
            
            <!-- Plan 1: Basic -->
            <div class="plan-card">
              <div>
                <div style="font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.8px;">
                  STARTER
                </div>
                <h3 style="font-size: 24px; font-weight: 800; color: var(--text); margin-top: 4px; margin-bottom: 12px;">
                  Basic
                </h3>
                <div style="display: flex; align-items: baseline; gap: 6px; margin-bottom: 8px;">
                  <span style="font-size: 34px; font-weight: 900; color: var(--cyan);">₹999</span>
                  <span style="color: var(--muted); font-size: 14px; font-weight: 600;">/ Month</span>
                </div>
                <div style="font-size: 13px; color: var(--muted); padding-bottom: 14px; border-bottom: 1px solid var(--line);">
                  Ideal for beginners starting their journey with general gym equipment.
                </div>

                <ul class="plan-features-list">
                  <li><i class="fa-solid fa-check"></i> Full Gym Floor Access</li>
                  <li><i class="fa-solid fa-check"></i> Standard Cardio Equipment</li>
                  <li><i class="fa-solid fa-check"></i> Locker Room & Shower Access</li>
                  <li><i class="fa-solid fa-check"></i> Free Fitness Assessment</li>
                </ul>
              </div>

              <button type="button" class="btn btn-outline" style="width: 100%;" onclick="openPlanModal(1, 'Basic', 999, '1 Month')">
                <i class="fa-solid fa-bolt"></i> Choose Plan
              </button>
            </div>

            <!-- Plan 2: Standard (Most Popular) -->
            <div class="plan-card featured">
              <span class="plan-badge-top">MOST POPULAR</span>
              <div>
                <div style="font-size: 13px; font-weight: 700; color: var(--cyan); text-transform: uppercase; letter-spacing: 0.8px;">
                  RECOMMENDED
                </div>
                <h3 style="font-size: 24px; font-weight: 800; color: var(--text); margin-top: 4px; margin-bottom: 12px;">
                  Standard Pro
                </h3>
                <div style="display: flex; align-items: baseline; gap: 6px; margin-bottom: 8px;">
                  <span style="font-size: 34px; font-weight: 900; color: var(--cyan);">₹2499</span>
                  <span style="color: var(--muted); font-size: 14px; font-weight: 600;">/ 3 Months</span>
                </div>
                <div style="font-size: 13px; color: var(--muted); padding-bottom: 14px; border-bottom: 1px solid var(--line);">
                  Our most popular quarterly plan for sustained gains and guided training.
                </div>

                <ul class="plan-features-list">
                  <li><i class="fa-solid fa-check" style="color: #34d399;"></i> All Basic Features Included</li>
                  <li><i class="fa-solid fa-check" style="color: #34d399;"></i> Cardio & Strength Training Zone</li>
                  <li><i class="fa-solid fa-check" style="color: #34d399;"></i> 1 Personal Trainer Consultation</li>
                  <li><i class="fa-solid fa-check" style="color: #34d399;"></i> Customized Nutrition Guidelines</li>
                  <li><i class="fa-solid fa-check" style="color: #34d399;"></i> Free Steam Bath (2x/month)</li>
                </ul>
              </div>

              <button type="button" class="btn btn-primary" style="width: 100%;" onclick="openPlanModal(2, 'Standard Pro', 2499, '3 Months')">
                <i class="fa-solid fa-crown"></i> Choose Plan
              </button>
            </div>

            <!-- Plan 3: Premium (VIP Elite) -->
            <div class="plan-card" style="border-color: rgba(245, 158, 11, 0.4);">
              <span class="plan-badge-top" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff;">VIP ELITE</span>
              <div>
                <div style="font-size: 13px; font-weight: 700; color: #fbbf24; text-transform: uppercase; letter-spacing: 0.8px;">
                  ALL-INCLUSIVE
                </div>
                <h3 style="font-size: 24px; font-weight: 800; color: var(--text); margin-top: 4px; margin-bottom: 12px;">
                  Premium VIP
                </h3>
                <div style="display: flex; align-items: baseline; gap: 6px; margin-bottom: 8px;">
                  <span style="font-size: 34px; font-weight: 900; color: #fbbf24;">₹7999</span>
                  <span style="color: var(--muted); font-size: 14px; font-weight: 600;">/ Year</span>
                </div>
                <div style="font-size: 13px; color: var(--muted); padding-bottom: 14px; border-bottom: 1px solid var(--line);">
                  Ultimate 365-day all-access membership with dedicated personal mentor.
                </div>

                <ul class="plan-features-list">
                  <li><i class="fa-solid fa-check" style="color: #fbbf24;"></i> All Gym Facilities & Group Classes</li>
                  <li><i class="fa-solid fa-check" style="color: #fbbf24;"></i> Dedicated Personal Coach Support</li>
                  <li><i class="fa-solid fa-check" style="color: #fbbf24;"></i> Personalized Workout & Diet Chart</li>
                  <li><i class="fa-solid fa-check" style="color: #fbbf24;"></i> Unlimited Sauna & Steam Bath</li>
                  <li><i class="fa-solid fa-check" style="color: #fbbf24;"></i> Complimentary Gym Kit & Shaker</li>
                  <li><i class="fa-solid fa-check" style="color: #fbbf24;"></i> Priority Support & 2 Guest Passes</li>
                </ul>
              </div>

              <button type="button" class="btn btn-outline" style="width: 100%; border-color: rgba(245, 158, 11, 0.5); color: #fbbf24;" onclick="openPlanModal(3, 'Premium VIP', 7999, '12 Months')">
                <i class="fa-solid fa-star"></i> Choose Plan
              </button>
            </div>

          </div>
        </div>

        <!-- 2 Column Section Below Plans: Member Profile + Gym Amenities -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 28px;">
          
          <!-- Card 1: Member Profile Card -->
          <div class="profile-card-hero">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
              <h3 class="card-title" style="margin-bottom: 0;">
                <i class="fa-solid fa-user-circle" style="color: var(--cyan); margin-right: 8px;"></i>
                My Profile Details
              </h3>
              <a href="profile.php?tab=edit" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-user-pen"></i> Edit Profile
              </a>
            </div>

            <div style="display: flex; align-items: center; gap: 18px; margin-bottom: 14px;">
              <div class="profile-avatar-box">
                <?= strtoupper(substr($u['name'] ?? 'M', 0, 1)) ?>
              </div>
              <div>
                <h4 style="font-size: 19px; font-weight: 800; color: var(--text); margin-bottom: 2px;">
                  <?= e($u['name']) ?>
                </h4>
                <div style="font-size: 13.5px; color: var(--cyan); font-weight: 600;">
                  <i class="fa-solid fa-envelope" style="margin-right: 4px; font-size: 12px;"></i> <?= e($u['email']) ?>
                </div>
                <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                  Registered: <?= !empty($member['join_date']) ? format_date($member['join_date']) : date('d M Y') ?>
                </div>
              </div>
            </div>

            <!-- Profile Info Grid -->
            <div class="profile-grid">
              <div class="profile-item">
                <div class="profile-item-label"><i class="fa-solid fa-phone" style="color: var(--cyan); margin-right: 4px;"></i> Phone</div>
                <div class="profile-item-val"><?= !empty($member['phone']) ? e($member['phone']) : '<span style="color: var(--muted);">Not provided</span>' ?></div>
              </div>

              <div class="profile-item">
                <div class="profile-item-label"><i class="fa-solid fa-venus-mars" style="color: var(--cyan); margin-right: 4px;"></i> Gender</div>
                <div class="profile-item-val"><?= ucfirst(e($member['gender'] ?? 'male')) ?></div>
              </div>

              <div class="profile-item">
                <div class="profile-item-label"><i class="fa-solid fa-cake-candles" style="color: var(--cyan); margin-right: 4px;"></i> Date of Birth</div>
                <div class="profile-item-val"><?= !empty($member['dob']) ? format_date($member['dob']) : '<span style="color: var(--muted);">Not provided</span>' ?></div>
              </div>

              <div class="profile-item">
                <div class="profile-item-label"><i class="fa-solid fa-location-dot" style="color: var(--cyan); margin-right: 4px;"></i> Address</div>
                <div class="profile-item-val"><?= !empty($member['address']) ? e($member['address']) : '<span style="color: var(--muted);">Not provided</span>' ?></div>
              </div>
            </div>
          </div>

          <!-- Card 2: Gym Benefits & Privileges -->
          <div class="card" style="padding: 26px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
              <h3 class="card-title" style="margin-bottom: 0;">
                <i class="fa-solid fa-crown" style="color: var(--cyan); margin-right: 8px;"></i>
                Why Alexa Gym?
              </h3>
              <span style="font-size: 11px; font-weight: 800; color: var(--cyan); text-transform: uppercase; letter-spacing: 0.8px;">
                PREMIUM CLUB
              </span>
            </div>

            <div style="display: flex; flex-direction: column; gap: 14px;">
              <div style="background: var(--panel-2); border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; display: flex; align-items: center; gap: 14px;">
                <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(0, 242, 254, 0.12); color: var(--cyan); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                  <i class="fa-solid fa-dumbbell"></i>
                </div>
                <div>
                  <strong style="font-size: 14px; color: var(--text);">Imported Biomechanical Equipment</strong>
                  <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">Cross-fit zones, free weights, heavy squat racks, and dedicated cardio section.</div>
                </div>
              </div>

              <div style="background: var(--panel-2); border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; display: flex; align-items: center; gap: 14px;">
                <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(16, 185, 129, 0.12); color: #34d399; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                  <i class="fa-solid fa-user-ninja"></i>
                </div>
                <div>
                  <strong style="font-size: 14px; color: var(--text);">Certified Personal Coaches</strong>
                  <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">Guidance from state-level champions and certified nutritionists.</div>
                </div>
              </div>

              <div style="background: var(--panel-2); border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; display: flex; align-items: center; gap: 14px;">
                <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(245, 158, 11, 0.12); color: #fbbf24; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;">
                  <i class="fa-solid fa-shower"></i>
                </div>
                <div>
                  <strong style="font-size: 14px; color: var(--text);">Luxury Steam &amp; Locker Facilities</strong>
                  <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">Hygienic private showers, sanitised changing rooms, and steam baths.</div>
                </div>
              </div>
            </div>
          </div>

        </div>
        </div>

      <?php else: ?>
        <!-- =========================================================
             STATE 2: ACTIVE MEMBER DASHBOARD (FULL ACCESS)
             ========================================================= -->

        <!-- Welcome Header -->
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 26px;">
          <div>
            <h2 style="font-size: 26px; font-weight: 800; margin-bottom: 4px; letter-spacing: -0.5px;">
              Welcome, <?= e($u['name']) ?> 👋
            </h2>
            <p style="color: var(--muted); font-size: 14.5px; margin-bottom: 0;">
              Your <?= e($member['plan_name'] ?? 'Premium') ?> membership is active. Track your progression and strength routines.
            </p>
          </div>
          <div>
            <a href="workout.php" class="btn btn-primary btn-sm">
              <i class="fa-solid fa-dumbbell"></i> View Today's Workout
            </a>
          </div>
        </div>

        <!-- 6 Key Metric Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-bottom: 24px;">
          
          <!-- Card 1: Current Plan -->
          <div class="stat-card">
            <div class="stat-icon-wrapper" style="color: var(--cyan); background: rgba(0, 242, 254, 0.1); border-color: rgba(0, 242, 254, 0.25);">
              <i class="fa-solid fa-id-card"></i>
            </div>
            <div class="stat-info">
              <div class="stat-label">Current Plan</div>
              <div class="stat-val" style="font-size: 18px; color: var(--cyan);">
                <?= e($member['plan_name'] ?? 'Premium VIP') ?>
              </div>
              <span style="font-size: 12px; color: var(--muted);"><?= (int)($member['duration_months'] ?? 1) ?> Months Package</span>
            </div>
          </div>

          <!-- Card 2: Membership Status -->
          <div class="stat-card">
            <div class="stat-icon-wrapper" style="color: #10b981; background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.25);">
              <i class="fa-solid fa-shield-halved"></i>
            </div>
            <div class="stat-info">
              <div class="stat-label">Membership Status</div>
              <div class="stat-val" style="font-size: 18px; color: #34d399; display: flex; align-items: center; gap: 6px;">
                <span class="pulse-dot" style="background: #34d399; box-shadow: 0 0 10px #34d399;"></span> ACTIVE
              </div>
              <span style="font-size: 12px; color: var(--muted);">Full Access Granted</span>
            </div>
          </div>

          <!-- Card 3: Expiry Date -->
          <div class="stat-card">
            <div class="stat-icon-wrapper" style="color: #a855f7; background: rgba(168, 85, 247, 0.1); border-color: rgba(168, 85, 247, 0.25);">
              <i class="fa-solid fa-calendar-day"></i>
            </div>
            <div class="stat-info">
              <div class="stat-label">Expiry Date</div>
              <div class="stat-val" style="font-size: 17px;">
                <?= !empty($expiry_date_str) ? format_date($expiry_date_str) : 'N/A' ?>
              </div>
              <span style="font-size: 12px; color: var(--muted);">Renewal deadline</span>
            </div>
          </div>

          <!-- Card 4: Remaining Days -->
          <div class="stat-card">
            <div class="stat-icon-wrapper" style="color: #f59e0b; background: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.25);">
              <i class="fa-solid fa-hourglass-half"></i>
            </div>
            <div class="stat-info">
              <div class="stat-label">Remaining Days</div>
              <div class="stat-val" style="font-size: 20px; color: #fbbf24;">
                <?= $days_remaining ?> <span style="font-size: 13px; font-weight: 500; color: var(--muted);">Days</span>
              </div>
              <span style="font-size: 12px; color: var(--muted);"><?= $progress_pct ?>% validity left</span>
            </div>
          </div>

          <!-- Card 5: Attendance -->
          <div class="stat-card">
            <div class="stat-icon-wrapper" style="color: #06b6d4; background: rgba(6, 182, 212, 0.1); border-color: rgba(6, 182, 212, 0.25);">
              <i class="fa-solid fa-calendar-check"></i>
            </div>
            <div class="stat-info">
              <div class="stat-label">Attendance</div>
              <div class="stat-val" style="font-size: 20px;">
                <?= $att_count ?> <span style="font-size: 13px; font-weight: 500; color: var(--muted);">Days</span>
              </div>
              <span style="font-size: 12px; color: var(--muted);">Lifetime check-ins</span>
            </div>
          </div>

          <!-- Card 6: Assigned Trainer -->
          <div class="stat-card">
            <div class="stat-icon-wrapper" style="color: #ec4899; background: rgba(236, 72, 153, 0.1); border-color: rgba(236, 72, 153, 0.25);">
              <i class="fa-solid fa-user-ninja"></i>
            </div>
            <div class="stat-info">
              <div class="stat-label">Trainer</div>
              <div class="stat-val" style="font-size: 17px;">
                <?= e($member['trainer_name'] ?? 'Vikram Sharma') ?>
              </div>
              <span style="font-size: 12px; color: var(--muted);"><?= e($member['trainer_specialization'] ?? 'Personal Coach') ?></span>
            </div>
          </div>

        </div>

        <!-- Membership Validity Circular Days Chart Card -->
        <div class="card validity-circle-card">
          <div class="validity-chart-flex">
            
            <!-- Left: Donut Ring Chart with Center Days Badge -->
            <div class="validity-circle-wrap">
              <div class="validity-canvas-container">
                <canvas id="memberDaysCircleChart"></canvas>
                <div class="validity-circle-center">
                  <span class="v-days-number" style="color: <?= $days_remaining <= 7 ? 'var(--bad)' : ($days_remaining <= 15 ? '#fbbf24' : 'var(--cyan)') ?>;">
                    <?= $days_remaining ?>
                  </span>
                  <span class="v-days-label">DAYS LEFT</span>
                  <span class="v-days-badge"><?= $progress_pct ?>% Valid</span>
                </div>
              </div>
            </div>

            <!-- Right: Validity Details, Milestone Pills & Actions -->
            <div class="validity-details-wrap">
              <div class="validity-header-row">
                <div class="validity-header-info">
                  <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                    <span class="badge-status active" style="font-size: 11px; padding: 2.5px 8px;">ACTIVE PLAN</span>
                    <span style="color: var(--cyan); font-weight: 700; font-size: 14px;"><?= e($member['plan_name'] ?? 'Membership Plan') ?></span>
                  </div>
                  <h3 style="font-size: 18px; font-weight: 800; color: var(--text); margin-bottom: 3px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-clock-rotate-left" style="color: var(--cyan);"></i> Membership Days & Validity
                  </h3>
                  <p style="font-size: 13px; color: var(--muted); margin: 0;">
                    Active from <strong style="color: var(--text);"><?= format_date($member['join_date'] ?? date('Y-m-d')) ?></strong> until <strong style="color: var(--cyan);"><?= format_date($expiry_date_str) ?></strong>.
                  </p>
                </div>

                <div class="validity-header-actions">
                  <?php if ($days_remaining <= 15): ?>
                    <a href="plans.php" class="btn btn-warning btn-sm" style="font-weight: 700; text-decoration: none; padding: 7px 14px;">
                      <i class="fa-solid fa-arrows-rotate"></i> Renew Plan
                    </a>
                  <?php endif; ?>
                  <a href="receipt.php" class="btn btn-outline btn-sm" style="font-size: 12.5px; font-weight: 600; text-decoration: none;">
                    <i class="fa-solid fa-receipt"></i> Receipts
                  </a>
                </div>
              </div>

              <!-- 3 Milestone Info Cards -->
              <div class="validity-milestone-grid">
                <div class="milestone-box">
                  <div class="m-icon" style="color: var(--cyan); background: rgba(0, 242, 254, 0.1);"><i class="fa-solid fa-calendar-check"></i></div>
                  <div style="min-width: 0;">
                    <div class="m-label">Started On</div>
                    <div class="m-val"><?= format_date($member['join_date'] ?? date('Y-m-d')) ?></div>
                  </div>
                </div>

                <div class="milestone-box">
                  <div class="m-icon" style="color: #a855f7; background: rgba(168, 85, 247, 0.1);"><i class="fa-solid fa-calendar-xmark"></i></div>
                  <div style="min-width: 0;">
                    <div class="m-label">Expires On</div>
                    <div class="m-val"><?= !empty($expiry_date_str) ? format_date($expiry_date_str) : 'N/A' ?></div>
                  </div>
                </div>

                <div class="milestone-box">
                  <div class="m-icon" style="color: #10b981; background: rgba(16, 185, 129, 0.1);"><i class="fa-solid fa-hourglass-end"></i></div>
                  <div style="min-width: 0;">
                    <div class="m-label">Total Duration</div>
                    <div class="m-val"><?= $total_plan_days ?> Total Days</div>
                  </div>
                </div>
              </div>

              <!-- Dual progress bar legend -->
              <div class="validity-progress-bar-legend">
                <div class="validity-progress-legend-row">
                  <span style="display: flex; align-items: center; gap: 6px; color: var(--muted);">
                    <span style="width: 8px; height: 8px; border-radius: 50%; background: #6366f1; flex-shrink: 0;"></span>
                    Completed: <strong style="color: var(--text); margin-left: 2px;"><?= $days_elapsed ?> Days</strong>
                  </span>
                  <span style="display: flex; align-items: center; gap: 6px; color: var(--muted);">
                    <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--cyan); flex-shrink: 0;"></span>
                    Remaining: <strong style="color: var(--cyan); margin-left: 2px;"><?= $days_remaining ?> Days (<?= $progress_pct ?>%)</strong>
                  </span>
                </div>
                <div class="validity-dual-bar">
                  <div style="width: <?= 100 - $progress_pct ?>%; background: #6366f1;"></div>
                  <div style="width: <?= $progress_pct ?>%; background: var(--accent-grad);"></div>
                </div>
              </div>

            </div>

          </div>
        </div>

        <!-- 2 Column Section: Workouts & Attendance Summary -->
        <div class="user-dashboard-two-col">
          
          <!-- Column 1: Today's Workout Plan -->
          <div class="card">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
              <h3 class="card-title" style="margin-bottom: 0;">
                <i class="fa-solid fa-dumbbell" style="color: var(--cyan); margin-right: 8px;"></i>
                Workout Plan Preview
              </h3>
              <span style="font-size: 12px; color: var(--cyan); font-weight: 700; background: rgba(0, 242, 254, 0.1); padding: 4px 10px; border-radius: 8px;">
                <?= $today_day ?>
              </span>
            </div>

            <?php if (!empty($workouts_preview)): ?>
              <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($workouts_preview as $w): ?>
                  <div style="background: var(--panel-2); border: 1px solid var(--line); border-radius: 12px; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                      <strong style="font-size: 15px; color: var(--text);"><?= e($w['exercise_name'] ?? 'Bench Press') ?></strong>
                      <div style="font-size: 12.5px; color: var(--muted); margin-top: 3px;">
                        <?= (int)($w['sets'] ?? 3) ?> Sets × <?= e($w['reps'] ?? '12') ?> Reps
                        <?php if (!empty($w['duration'])): ?>
                          • <?= e($w['duration']) ?>
                        <?php endif; ?>
                      </div>
                    </div>
                    <span style="font-size: 12px; font-weight: 700; color: var(--cyan); background: rgba(0, 242, 254, 0.1); border: 1px solid rgba(0, 242, 254, 0.2); padding: 4px 10px; border-radius: 999px;">
                      <?= e($w['category'] ?? 'Chest') ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
              <div style="text-align: right; margin-top: 18px;">
                <a href="workout.php" class="btn btn-outline btn-sm">
                  Full Workout Schedule <i class="fa-solid fa-arrow-right" style="margin-left: 6px;"></i>
                </a>
              </div>
            <?php else: ?>
              <div style="text-align: center; color: var(--muted); padding: 30px;">
                No assigned exercises for today.
              </div>
            <?php endif; ?>
          </div>

          <!-- Column 2: Attendance Summary -->
          <div class="card">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
              <h3 class="card-title" style="margin-bottom: 0;">
                <i class="fa-solid fa-calendar-check" style="color: var(--cyan); margin-right: 8px;"></i>
                Attendance Summary
              </h3>
              <span style="font-size: 12.5px; color: var(--muted); font-weight: 600;">
                <?= $att_count ?> Total
              </span>
            </div>

            <?php if (!empty($recent_attendance)): ?>
              <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach ($recent_attendance as $att): ?>
                  <div style="background: var(--panel-2); border: 1px solid var(--line); border-radius: 12px; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                      <strong style="font-size: 14px; color: var(--text);"><?= format_date($att['attend_date']) ?></strong>
                      <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">
                        In: <?= date('h:i A', strtotime($att['check_in'])) ?>
                        <?php if (!empty($att['check_out'])): ?>
                          • Out: <?= date('h:i A', strtotime($att['check_out'])) ?>
                        <?php endif; ?>
                      </div>
                    </div>
                    <span class="badge-status active" style="font-size: 11px;">
                      <?= strtoupper($att['status'] ?? 'PRESENT') ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
              <div style="text-align: right; margin-top: 18px;">
                <a href="attendance.php" class="btn btn-outline btn-sm">
                  View Full Attendance <i class="fa-solid fa-arrow-right" style="margin-left: 6px;"></i>
                </a>
              </div>
            <?php else: ?>
              <div style="text-align: center; color: var(--muted); padding: 30px;">
                No check-in records logged yet. Check in at reception!
              </div>
            <?php endif; ?>
          </div>

        </div>

        <!-- Full Width: Recent Payments -->
        <div class="card">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; flex-wrap: wrap; gap: 12px;">
            <h3 class="card-title" style="margin-bottom: 0;">
              <i class="fa-solid fa-receipt" style="color: var(--cyan); margin-right: 8px;"></i>
              Recent Payments &amp; Bills
            </h3>
            <a href="payments.php" class="btn btn-outline btn-sm">
              All Payments History <i class="fa-solid fa-arrow-right" style="margin-left: 6px;"></i>
            </a>
          </div>

          <div class="custom-table-wrap">
            <table class="custom-table">
              <thead>
                <tr>
                  <th style="width: 140px;">Receipt No.</th>
                  <th>Plan Name</th>
                  <th>Amount</th>
                  <th>Payment Method</th>
                  <th>Paid Date</th>
                  <th>Status</th>
                  <th style="text-align: right;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($recent_payments)): ?>
                  <?php foreach ($recent_payments as $pay): ?>
                    <tr>
                      <td><strong class="code-badge"><?= (int)$pay['id'] ?></strong></td>
                      <td><?= e($pay['plan_name'] ?: 'Membership Fee') ?></td>
                      <td><strong style="color: var(--cyan);"><?= format_currency($pay['amount']) ?></strong></td>
                      <td><span style="text-transform: uppercase; font-size: 12.5px;"><?= e($pay['method']) ?></span></td>
                      <td><?= format_date($pay['paid_on']) ?></td>
                      <td>
                        <span class="badge-status <?= $pay['status'] === 'completed' ? 'completed' : 'pending' ?>">
                          <?= strtoupper($pay['status']) ?>
                        </span>
                      </td>
                      <td style="text-align: right;">
                        <a href="receipt.php?id=<?= $pay['id'] ?>" target="_blank" rel="opener" class="btn btn-primary btn-sm">
                          <i class="fa-solid fa-download"></i> View Bill
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="7" style="text-align: center; color: var(--muted); padding: 28px;">No payment records found.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php endif; ?>

    </div>
  </main>
</div>

<!-- =========================================================
     MODAL: CHOOSE PLAN & INSTANT CHECKOUT / PAYMENT
     ========================================================= -->
<div class="modal-overlay" id="planModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 0; color: var(--text);">
        <i class="fa-solid fa-credit-card" style="color: var(--cyan); margin-right: 8px;"></i>
        Confirm Plan &amp; Checkout
      </h3>
      <button type="button" style="background: none; border: none; color: var(--muted); font-size: 20px; cursor: pointer;" onclick="closePlanModal()">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form method="POST" action="dashboard.php" id="planCheckoutForm">
      <input type="hidden" name="action" value="choose_plan_pay">
      <input type="hidden" name="plan_id" id="modalPlanId" value="1">
      <input type="hidden" name="payment_method" id="modalPayMethod" value="upi">

      <div class="modal-body">
        <!-- Selected Plan Banner -->
        <div style="background: var(--panel-2); border: 1px solid var(--line); border-radius: 12px; padding: 16px; margin-bottom: 18px; display: flex; align-items: center; justify-content: space-between;">
          <div>
            <div style="font-size: 12px; font-weight: 700; color: var(--cyan); text-transform: uppercase;" id="modalPlanDuration">1 Month</div>
            <div style="font-size: 18px; font-weight: 800; color: var(--text);" id="modalPlanName">Basic Membership</div>
          </div>
          <div style="font-size: 24px; font-weight: 900; color: var(--cyan);" id="modalPlanPrice">
            ₹999
          </div>
        </div>

        <!-- Mobile Phone Number (Permanent & Required) -->
        <div style="background: rgba(0, 242, 254, 0.05); border: 1.5px solid rgba(0, 242, 254, 0.25); border-radius: 12px; padding: 14px 16px; margin-bottom: 18px;">
          <label class="form-label" style="color: var(--text); font-weight: 700; font-size: 13px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
            <span style="display: flex; align-items: center; gap: 8px;">
              <i class="fa-solid fa-phone" style="color: var(--cyan);"></i> Mobile Phone Number *
            </span>
            <span style="font-size: 11px; font-weight: 700; color: var(--cyan); background: rgba(0, 242, 254, 0.15); padding: 2px 8px; border-radius: 6px;">Permanent Profile Detail</span>
          </label>
          <input type="tel" name="phone_number" id="modalPhoneInput" class="form-control" 
                 placeholder="10-Digit Mobile Number (e.g. 9876543210)" 
                 value="<?= e($u['phone'] ?? $member['phone'] ?? '') ?>" 
                 pattern="[6-9][0-9]{9}" maxlength="10" required 
                 style="background: var(--panel); font-size: 14.5px; font-weight: 600; letter-spacing: 0.5px;">
          <small style="color: var(--muted); font-size: 11.5px; margin-top: 5px; display: block; line-height: 1.4;">
            This number will be permanently saved to your profile and used by gym administration for onboarding &amp; locker access.
          </small>
        </div>

        <!-- Payment Method Selector (2 Options: UPI/QR and Cash) -->
        <label style="font-size: 13px; font-weight: 700; color: var(--text); display: block; margin-bottom: 6px;">
          Select Payment Method
        </label>
        <div class="pay-methods-grid">
          <div class="pay-method-choice selected" id="method-btn-upi" data-method="upi" onclick="selectMethod('upi')">
            <i class="fa-solid fa-qrcode"></i>
            <span>UPI / QR Code</span>
            <small>GPay, PhonePe, Paytm</small>
          </div>
          <div class="pay-method-choice" id="method-btn-cash" data-method="cash" onclick="selectMethod('cash')">
            <i class="fa-solid fa-money-bill-wave"></i>
            <span>Cash Payment</span>
            <small>Pay at Gym Reception</small>
          </div>
        </div>

        <!-- 1. UPI / QR Section (Active by default) -->
        <div id="upi-details-section" class="upi-qr-box">
          <div class="upi-badge-row">
            <span class="upi-app-badge"><i class="fa-brands fa-google-pay" style="color: #4285F4; margin-right: 4px;"></i> GPay</span>
            <span class="upi-app-badge"><i class="fa-solid fa-mobile-screen" style="color: #6739B7; margin-right: 4px;"></i> PhonePe</span>
            <span class="upi-app-badge"><i class="fa-solid fa-wallet" style="color: #00BAF2; margin-right: 4px;"></i> Paytm</span>
            <span class="upi-app-badge"><i class="fa-solid fa-building-columns" style="color: #34d399; margin-right: 4px;"></i> Any UPI App</span>
          </div>

          <p style="font-size: 13px; color: var(--muted); margin-bottom: 8px;">
            Scan the official Alexa Gym QR code to pay <strong id="modalUpiAmountDisplay" style="color: var(--cyan); font-size: 15px;">₹999</strong>:
          </p>

          <div class="qr-image-wrapper">
            <img src="../assets/images/Qr.jpeg" alt="Alexa Gym UPI QR Code">
          </div>

          <div>
            <div style="background: var(--panel); border: 1px solid var(--line); border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 8px;">
              <span style="font-size: 12px; color: var(--muted);">Gym UPI ID:</span>
              <code id="gymUpiIdText" style="font-size: 13px; color: var(--cyan); font-weight: 700; font-family: monospace;">alexagym@upi</code>
              <button type="button" onclick="copyGymUpi()" class="btn btn-sm" style="padding: 2px 8px; font-size: 11px; background: rgba(0, 242, 254, 0.15); color: var(--cyan); border: 1px solid rgba(0, 242, 254, 0.35); border-radius: 5px;" title="Copy UPI ID">
                <i class="fa-regular fa-copy" id="upiCopyIcon"></i> <span id="upiCopyBtnText">Copy</span>
              </button>
            </div>
          </div>
        </div>

        <!-- 2. Cash Section (Active when Cash chosen) -->
        <div id="cash-details-section" class="cash-info-box" style="display: none;">
          <div style="display: flex; align-items: flex-start; gap: 14px;">
            <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(16, 185, 129, 0.18); color: #34d399; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
              <i class="fa-solid fa-hand-holding-dollar"></i>
            </div>
            <div>
              <strong style="font-size: 15px; color: #34d399; display: block; margin-bottom: 4px;">
                Pay Cash at Gym Reception Desk
              </strong>
              <p style="font-size: 13px; color: var(--muted); margin: 0; line-height: 1.6;">
                Please deposit the membership amount of <strong id="modalCashAmountDisplay" style="color: var(--text);">₹999</strong> at the Alexa Gym front desk counter. The administrator will verify cash payment and activate your portal.
              </p>
            </div>
          </div>
        </div>

        <!-- Transaction Reference / UTR Number -->
        <div class="form-group" style="margin-top: 14px;">
          <label class="form-label" id="txnLabel" style="font-size: 12.5px;">UPI Transaction / UTR Reference ID *</label>
          <input type="text" name="transaction_id" id="modalTxnId" class="form-control" placeholder="e.g. 12-digit UTR No. (428109823491)" value="" required>
          <small id="txnHelp" style="color: var(--muted); font-size: 11.5px; margin-top: 3px; display: block;">
            Enter the 12-digit UTR number from your payment app receipt (or keep auto-generated reference).
          </small>
        </div>

        <!-- Optional Trainer Preference -->
        <div class="form-group" style="margin-top: 14px;">
          <label class="form-label" style="font-size: 12.5px;">Preferred Personal Coach (Optional)</label>
          <select name="trainer_id" class="form-control">
            <option value="0">General Gym Coach</option>
            <?php foreach ($trainers_list as $t): ?>
              <option value="<?= $t['id'] ?>" <?= ($member['trainer_id'] == $t['id']) ? 'selected' : '' ?>>
                <?= e($t['full_name']) ?> (<?= e($t['specialization']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline btn-sm" onclick="closePlanModal()">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-paper-plane"></i> Submit Request &amp; Pay
        </button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/js/chart.min.js"></script>
<script>
if (typeof Chart === 'undefined') {
  document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js"><\/script>');
}
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const ctxDays = document.getElementById('memberDaysCircleChart');
  if (ctxDays && typeof Chart !== 'undefined') {
    const isLight = document.documentElement.getAttribute('data-theme') === 'light';
    const daysRemaining = <?= (int)$days_remaining ?>;
    const daysElapsed = <?= (int)$days_elapsed ?>;
    const remainingColor = daysRemaining <= 7 ? '#ef4444' : (daysRemaining <= 15 ? '#fbbf24' : '#00f2fe');
    const elapsedColor = isLight ? '#cbd5e1' : '#2b2542';

    const chartData = (daysRemaining === 0 && daysElapsed === 0) 
      ? [0, 1] 
      : [Math.max(0, daysRemaining), Math.max(0, daysElapsed)];

    const memberDaysChart = new Chart(ctxDays, {
      type: 'doughnut',
      data: {
        labels: ['Days Remaining', 'Days Completed'],
        datasets: [{
          data: chartData,
          backgroundColor: [remainingColor, elapsedColor],
          borderWidth: 2,
          borderColor: isLight ? '#ffffff' : '#13111e',
          hoverBorderColor: '#ffffff',
          hoverOffset: 3
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        layout: {
          padding: 3
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(11, 10, 18, 0.95)',
            titleColor: remainingColor,
            titleFont: { family: 'Outfit', size: 12, weight: '700' },
            bodyColor: '#f0edfd',
            bodyFont: { family: 'Inter', size: 11 },
            borderColor: 'rgba(0, 242, 254, 0.3)',
            borderWidth: 1,
            padding: 8,
            boxPadding: 4,
            callbacks: {
              label: function(c) {
                return ' ' + c.label + ': ' + c.raw + ' Days';
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

    const themeObs = new MutationObserver(() => {
      const light = document.documentElement.getAttribute('data-theme') === 'light';
      if (memberDaysChart && memberDaysChart.data && memberDaysChart.data.datasets[0]) {
        memberDaysChart.data.datasets[0].borderColor = light ? '#ffffff' : '#13111e';
        memberDaysChart.data.datasets[0].backgroundColor[1] = light ? '#cbd5e1' : '#2b2542';
        memberDaysChart.update();
      }
    });
    themeObs.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
  }
});
</script>
<script src="../assets/js/user.js?v=2.0"></script>
<script>
function openPlanModal(planId, planName, price, duration) {
  document.getElementById('modalPlanId').value = planId;
  document.getElementById('modalPlanName').innerText = planName;
  document.getElementById('modalPlanPrice').innerText = '₹' + price;
  document.getElementById('modalPlanDuration').innerText = duration;

  if (document.getElementById('modalUpiAmountDisplay')) {
    document.getElementById('modalUpiAmountDisplay').innerText = '₹' + price;
  }
  if (document.getElementById('modalCashAmountDisplay')) {
    document.getElementById('modalCashAmountDisplay').innerText = '₹' + price;
  }

  selectMethod('upi');
  document.getElementById('planModal').classList.add('active');
}

function closePlanModal() {
  document.getElementById('planModal').classList.remove('active');
}

function selectMethod(method) {
  document.getElementById('modalPayMethod').value = method;
  
  const btnUpi = document.getElementById('method-btn-upi');
  const btnCash = document.getElementById('method-btn-cash');
  if (btnUpi) btnUpi.classList.toggle('selected', method === 'upi');
  if (btnCash) btnCash.classList.toggle('selected', method === 'cash');

  const upiSec = document.getElementById('upi-details-section');
  const cashSec = document.getElementById('cash-details-section');
  const txnLabel = document.getElementById('txnLabel');
  const txnHelp = document.getElementById('txnHelp');
  const txnInput = document.getElementById('modalTxnId');

  if (method === 'upi') {
    if (upiSec) upiSec.style.display = 'block';
    if (cashSec) cashSec.style.display = 'none';
    if (txnLabel) txnLabel.innerText = 'UPI Transaction / UTR Reference ID *';
    if (txnInput) {
      txnInput.placeholder = 'e.g. 12-digit UTR (428109823491)';
      txnInput.value = 'UPI-' + Math.floor(1000000000 + Math.random() * 9000000000);
    }
    if (txnHelp) txnHelp.innerText = 'Enter the 12-digit UTR number from your payment app receipt (or keep auto-generated reference).';
  } else {
    if (upiSec) upiSec.style.display = 'none';
    if (cashSec) cashSec.style.display = 'block';
    if (txnLabel) txnLabel.innerText = 'Desk Cash Receipt Reference';
    if (txnInput) {
      txnInput.placeholder = 'e.g. CASH-REC-4819';
      txnInput.value = 'CASH-REC-' + Math.floor(1000 + Math.random() * 9000);
    }
    if (txnHelp) txnHelp.innerText = 'Desk receipt reference. Present this receipt ID at the gym front desk.';
  }
}

function copyGymUpi() {
  const upiId = document.getElementById('gymUpiIdText').innerText.trim();
  navigator.clipboard.writeText(upiId).then(() => {
    const icon = document.getElementById('upiCopyIcon');
    const text = document.getElementById('upiCopyBtnText');
    if (icon) icon.className = 'fa-solid fa-check';
    if (text) text.innerText = 'Copied!';
    setTimeout(() => {
      if (icon) icon.className = 'fa-regular fa-copy';
      if (text) text.innerText = 'Copy';
    }, 2000);
  }).catch(() => {
    prompt('Copy Gym UPI ID:', upiId);
  });
}

// Close modal on click outside
window.addEventListener('click', function(e) {
  const modal = document.getElementById('planModal');
  if (e.target === modal) {
    closePlanModal();
  }
});

<?php if (!empty($preselected_plan_id) && !$is_active_member): ?>
window.addEventListener('DOMContentLoaded', function() {
  <?php if ($preselected_plan_id == 1): ?>
    openPlanModal(1, 'Basic', 999, '1 Month');
  <?php elseif ($preselected_plan_id == 2): ?>
    openPlanModal(2, 'Standard Pro', 2499, '3 Months');
  <?php elseif ($preselected_plan_id == 3): ?>
    openPlanModal(3, 'Premium VIP', 7999, '12 Months');
  <?php endif; ?>
});
<?php endif; ?>
</script>
</body>
</html>

