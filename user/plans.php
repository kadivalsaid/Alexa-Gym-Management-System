<?php
/**
 * Alexa Gym — Member Portal: Choose & Renew Membership Plans
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_member();

$u = get_logged_in_user();
$member = get_member_by_user_id($pdo, $u['id']);
$u_phone_stmt = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
$u_phone_stmt->execute([$u['id']]);
$u_phone = (string)($u_phone_stmt->fetchColumn() ?: '');
$u['phone'] = $u_phone;
$_SESSION['user_phone'] = $u_phone;

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

// Check if user has a pending membership request
$pending_request = get_pending_request_by_user_id($pdo, $u['id']);

$payment_success = '';
$payment_error   = '';

// Handle Quick Save Contact Phone Number
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_contact_phone') {
    $p_in = trim($_POST['phone_number'] ?? '');
    $p_digits = preg_replace('/[^0-9]/', '', $p_in);
    if (strlen($p_digits) !== 10 || !preg_match('/^[6-9]\d{9}$/', $p_digits)) {
        $payment_error = 'Please enter a valid 10-digit mobile phone number starting with 6, 7, 8, or 9.';
    } else {
        $pdo->prepare("UPDATE users SET phone = ? WHERE id = ?")->execute([$p_digits, $u['id']]);
        $_SESSION['user_phone'] = $p_digits;
        $u['phone'] = $p_digits;
        if (!empty($member['id'])) {
            $pdo->prepare("UPDATE members SET phone = ? WHERE id = ?")->execute([$p_digits, $member['id']]);
        }
        $member['phone'] = $p_digits;
        $payment_success = 'Phone number saved successfully to your profile!';
    }
}

// Process Plan Selection & Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $plan_id        = (int)($_POST['plan_id'] ?? 0);
    $payment_method = trim($_POST['payment_method'] ?? 'upi');
    $txn_id         = trim($_POST['txn_id'] ?? 'UPI-' . strtoupper(bin2hex(random_bytes(4))));
    $trainer_id     = (int)($_POST['trainer_id'] ?? 0);

    // Phone number submitted in checkout modal (permanent required field)
    $sub_phone = trim($_POST['phone_number'] ?? '');
    if (empty($sub_phone) && !empty($u['phone'])) {
        $sub_phone = $u['phone'];
    }
    $sub_digits = preg_replace('/[^0-9]/', '', $sub_phone);
    if (strlen($sub_digits) !== 10 || !preg_match('/^[6-9]\d{9}$/', $sub_digits)) {
        $payment_error = 'Please provide a valid 10-digit contact mobile number (starting with 6, 7, 8, or 9).';
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

    $p_stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ? AND status = 'active' LIMIT 1");
    $p_stmt->execute([$plan_id]);
    $chosen_plan = $p_stmt->fetch();

    if (!empty($payment_error)) {
        // Stop execution if phone validation failed
    } elseif (!$chosen_plan) {
        $payment_error = 'Selected plan is not available. Please choose another.';
    } else {
        try {
            $pdo->beginTransaction();

            $amount = (float)$chosen_plan['price'];
            $target_trainer = $trainer_id > 0 ? $trainer_id : ($member['trainer_id'] ?? 1);
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
                $payment_method,
                $txn_id,
                $amount
            ]);

            // 2. Send Notification to Member
            $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                VALUES (?, 'Membership Request Submitted ⏳', ?, 'info', 0, NOW())
            ")->execute([
                $u['id'], 
                "Your request for {$chosen_plan['name']} (₹" . number_format($amount, 2) . ") with Ref: {$txn_id} has been submitted! It is awaiting Admin approval."
            ]);

            // 3. Send Notification to Admin
            $adm_title = 'New Plan Request: ' . $chosen_plan['name'];
            $adm_msg   = ($u['name'] ?? 'A member') . " has requested the {$chosen_plan['name']} plan (Ref: {$txn_id}). Please review and approve.";
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
            if (session_status() === PHP_SESSION_NONE) session_start();
            $_SESSION['flash_success'] = "Your request for {$chosen_plan['name']} has been submitted! Your membership is now in progress awaiting Admin approval.";
            header('Location: dashboard.php');
            exit;
        } catch (Exception $ex) {
            $pdo->rollBack();
            $payment_error = 'Payment request could not be processed: ' . $ex->getMessage();
        }
    }
}

// Fetch all plans and trainers
try {
    $plans_stmt = $pdo->query("SELECT * FROM plans WHERE status = 'active' ORDER BY price ASC");
    $plans_list = $plans_stmt->fetchAll();

    $trainers_stmt = $pdo->query("SELECT id, full_name, specialization FROM trainers WHERE status = 'active' ORDER BY full_name ASC");
    $available_trainers = $trainers_stmt->fetchAll();
} catch (Exception $e) {
    $plans_list = [];
    $available_trainers = [];
}

$page_title   = 'Membership Plans — Alexa Gym';
$topbar_title = 'Available Plans & Renewal';
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
        <h2 style="font-size: 26px; font-weight: 800; margin-bottom: 4px;">Choose A Membership Plan</h2>
        <p style="color: var(--muted); font-size: 14.5px;">Select any plan below to renew or upgrade your gym membership instantly.</p>
      </div>

      <?php if (!empty($payment_success)): ?>
        <div class="alert alert-success" style="margin-bottom: 24px;">
          <i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i>
          <?= e($payment_success) ?>
          <a href="payments.php" style="color: #fff; text-decoration: underline; margin-left: 12px; font-weight: bold;">View Payment Receipt &rarr;</a>
        </div>
      <?php elseif (!empty($payment_error)): ?>
        <div class="alert alert-danger" style="margin-bottom: 24px;">
          <i class="fa-solid fa-triangle-exclamation" style="margin-right: 8px;"></i>
          <?= e($payment_error) ?>
        </div>
      <?php endif; ?>

      <!-- Pending Membership Request Notice -->
      <?php if ($pending_request): ?>
        <div style="background: rgba(0, 242, 254, 0.08); border: 1.5px solid rgba(0, 242, 254, 0.35); border-radius: var(--radius); padding: 20px 24px; margin-bottom: 26px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
          <div style="display: flex; align-items: center; gap: 14px;">
            <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(0, 242, 254, 0.15); color: var(--cyan); display: flex; align-items: center; justify-content: center; font-size: 20px;">
              <i class="fa-solid fa-hourglass-half"></i>
            </div>
            <div>
              <strong style="font-size: 15.5px; color: var(--cyan); display: block;">Membership Request In Progress: <?= e($pending_request['plan_name'] ?? 'Selected Plan') ?> (Ref: <?= e($pending_request['transaction_id'] ?? 'REF-SUBMITTED') ?>)</strong>
              <span style="font-size: 13px; color: var(--muted);">Your request has been submitted to the Admin team. Once approved, your membership will be activated. You can select another plan below to change your request.</span>
            </div>
          </div>
          <a href="dashboard.php" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-gauge-high"></i> View Dashboard Status
          </a>
        </div>
      <?php endif; ?>


      <!-- Plans Cards Grid -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 26px;">
        <?php foreach ($plans_list as $plan): 
            $features = !empty($plan['features']) ? explode("\n", trim($plan['features'])) : [
                'Full Gym Floor Access',
                'Cardio & Strength Training Zone',
                'Locker Room & Shower Access'
            ];
            $is_current = ((int)($member['plan_id'] ?? 0) === (int)$plan['id']);
        ?>
          <div class="card" style="display: flex; flex-direction: column; position: relative; <?= $plan['is_popular'] ? 'border-color: var(--cyan); box-shadow: 0 10px 30px rgba(0, 242, 254, 0.15);' : '' ?>">
            <?php if ($plan['is_popular']): ?>
              <span class="badge-count" style="position: absolute; top: 18px; right: 20px;">RECOMMENDED</span>
            <?php endif; ?>

            <h3 style="font-size: 22px; font-weight: 800; margin-bottom: 8px;"><?= e($plan['name']) ?></h3>
            <p style="color: var(--muted); font-size: 13.5px; margin-bottom: 20px; min-height: 38px;">
              <?= e($plan['description'] ?: 'Complete training facility access.') ?>
            </p>

            <div style="font-size: 38px; font-weight: 900; color: var(--text); margin-bottom: 22px; padding-bottom: 18px; border-bottom: 1px solid var(--line);">
              <span style="font-size: 22px; color: var(--cyan);">₹</span><?= number_format((float)$plan['price'], 0) ?>
              <span style="font-size: 13.5px; color: var(--muted); font-weight: 500;">/ <?= (int)$plan['duration_months'] === 1 ? 'Month' : (int)$plan['duration_months'] . ' Months' ?></span>
            </div>

            <ul style="list-style: none; margin-bottom: 30px; flex-grow: 1; display: flex; flex-direction: column; gap: 12px;">
              <?php foreach ($features as $feat): ?>
                <?php if (!empty(trim($feat))): ?>
                  <li style="display: flex; align-items: center; gap: 10px; font-size: 14px;">
                    <i class="fa-solid fa-circle-check" style="color: var(--cyan); font-size: 14px;"></i>
                    <?= e(trim($feat)) ?>
                  </li>
                <?php endif; ?>
              <?php endforeach; ?>
            </ul>

            <button type="button" class="btn <?= $plan['is_popular'] ? 'btn-primary' : 'btn-outline' ?> btn-block" 
                    onclick="openCheckoutModal(<?= (int)$plan['id'] ?>, '<?= e(addslashes($plan['name'])) ?>', <?= (float)$plan['price'] ?>, <?= (int)$plan['duration_months'] ?>)">
              <?= $is_current ? '<i class="fa-solid fa-arrows-rotate"></i> Renew This Plan' : '<i class="fa-solid fa-bolt"></i> Choose Plan' ?>
            </button>
          </div>
        <?php endforeach; ?>
      </div>

    </div>
  </main>
</div>

<!-- Payment Checkout Modal -->
<div class="modal-backdrop" id="checkout-modal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 18px; font-weight: 800;"><i class="fa-solid fa-credit-card" style="color: var(--cyan); margin-right: 8px;"></i> Checkout &amp; Payment</h3>
      <button type="button" class="action-btn-circle" onclick="closeModal('checkout-modal')">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form method="POST" action="plans.php">
      <input type="hidden" name="plan_id" id="modal-plan-id" value="0">
      
      <div class="modal-body">
        <div style="background: var(--panel-2); padding: 18px; border-radius: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
          <div>
            <span style="font-size: 12px; color: var(--muted); text-transform: uppercase;">Selected Plan</span>
            <h4 id="modal-plan-name" style="font-size: 18px; font-weight: 800; color: var(--text);">Standard Pro</h4>
            <span id="modal-plan-duration" style="font-size: 13px; color: var(--cyan);">3 Months Access</span>
          </div>
          <div style="text-align: right;">
            <span style="font-size: 12px; color: var(--muted); text-transform: uppercase;">Amount Due</span>
            <div id="modal-plan-price" style="font-size: 24px; font-weight: 900; color: var(--cyan);">₹2,499.00</div>
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
          <input type="tel" name="phone_number" id="plansModalPhoneInput" class="form-control" 
                 placeholder="10-Digit Mobile Number (e.g. 9876543210)" 
                 value="<?= e($u['phone'] ?? $member['phone'] ?? '') ?>" 
                 pattern="[6-9][0-9]{9}" maxlength="10" required 
                 style="background: var(--panel); font-size: 14.5px; font-weight: 600; letter-spacing: 0.5px;">
          <small style="color: var(--muted); font-size: 11.5px; margin-top: 5px; display: block; line-height: 1.4;">
            This number will be permanently saved to your profile and used by gym administration for onboarding &amp; locker access.
          </small>
        </div>

        <div style="margin-bottom: 18px;">
          <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 8px;">Assigned Coach / Trainer</label>
          <select name="trainer_id" class="form-control">
            <option value="0">-- General Gym Coach (Default) --</option>
            <?php foreach ($available_trainers as $t): 
              $sel = (($member['trainer_id'] ?? 0) == $t['id']) ? 'selected' : '';
            ?>
              <option value="<?= $t['id'] ?>" <?= $sel ?>><?= e($t['full_name']) ?> (<?= e($t['specialization'] ?? 'Fitness') ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Payment Method Selector (2 Options: UPI/QR and Cash) -->
        <input type="hidden" name="payment_method" id="modal-pay-method" value="upi">
        <label style="display: block; font-size: 13.5px; font-weight: 700; margin-bottom: 6px;">Select Payment Method</label>
        <div class="pay-methods-grid">
          <div class="pay-method-choice selected" id="plans-btn-upi" onclick="selectPlansMethod('upi')">
            <i class="fa-solid fa-qrcode"></i>
            <span>UPI / QR Code</span>
            <small>GPay, PhonePe, Paytm</small>
          </div>
          <div class="pay-method-choice" id="plans-btn-cash" onclick="selectPlansMethod('cash')">
            <i class="fa-solid fa-money-bill-wave"></i>
            <span>Cash Payment</span>
            <small>Pay at Gym Reception</small>
          </div>
        </div>

        <!-- 1. UPI QR Display Section (Active by default) -->
        <div id="upi-instructions-box" class="upi-qr-box">
          <div class="upi-badge-row">
            <span class="upi-app-badge"><i class="fa-brands fa-google-pay" style="color: #4285F4; margin-right: 4px;"></i> GPay</span>
            <span class="upi-app-badge"><i class="fa-solid fa-mobile-screen" style="color: #6739B7; margin-right: 4px;"></i> PhonePe</span>
            <span class="upi-app-badge"><i class="fa-solid fa-wallet" style="color: #00BAF2; margin-right: 4px;"></i> Paytm</span>
            <span class="upi-app-badge"><i class="fa-solid fa-building-columns" style="color: #34d399; margin-right: 4px;"></i> Any UPI App</span>
          </div>

          <p style="font-size: 13px; color: var(--muted); margin-bottom: 8px;">
            Scan the official Alexa Gym QR code to pay <strong id="modal-upi-price-display" style="color: var(--cyan); font-size: 15px;">₹2,499.00</strong>:
          </p>

          <div class="qr-image-wrapper">
            <img src="../assets/images/Qr.jpeg" alt="Alexa Gym UPI QR">
          </div>

          <div>
            <div style="background: var(--panel); border: 1px solid var(--line); border-radius: 8px; padding: 7px 12px; display: inline-flex; align-items: center; gap: 8px;">
              <span style="font-size: 12px; color: var(--muted);">Gym UPI ID:</span>
              <code id="plansUpiIdText" style="font-size: 13px; color: var(--cyan); font-weight: 700; font-family: monospace;">alexagym@upi</code>
              <button type="button" onclick="copyPlansUpi()" class="btn btn-sm" style="padding: 2px 8px; font-size: 11px; background: rgba(0, 242, 254, 0.15); color: var(--cyan); border: 1px solid rgba(0, 242, 254, 0.35); border-radius: 5px;" title="Copy UPI ID">
                <i class="fa-regular fa-copy" id="plansUpiCopyIcon"></i> <span id="plansUpiCopyBtnText">Copy</span>
              </button>
            </div>
          </div>
        </div>

        <!-- 2. Cash Section (Active when Cash chosen) -->
        <div id="cash-instructions-box" class="cash-info-box" style="display: none;">
          <div style="display: flex; align-items: flex-start; gap: 14px;">
            <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(16, 185, 129, 0.18); color: #34d399; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
              <i class="fa-solid fa-hand-holding-dollar"></i>
            </div>
            <div>
              <strong style="font-size: 15px; color: #34d399; display: block; margin-bottom: 4px;">
                Pay Cash at Gym Reception Desk
              </strong>
              <p style="font-size: 13px; color: var(--muted); margin: 0; line-height: 1.6;">
                Please deposit the membership amount of <strong id="modal-cash-price-display" style="color: var(--text);">₹2,499.00</strong> at the Alexa Gym front desk counter. The administrator will verify cash payment and activate your portal.
              </p>
            </div>
          </div>
        </div>

        <div style="margin-bottom: 14px;">
          <label id="plansTxnLabel" style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">UPI Transaction / UTR Reference ID *</label>
          <input type="text" name="txn_id" id="modal-txn-id" class="form-control" placeholder="e.g. 12-digit UTR (428109823491)" value="UPI-<?= strtoupper(bin2hex(random_bytes(4))) ?>" required>
          <small id="plansTxnHelp" style="color: var(--muted); font-size: 12px; margin-top: 3px; display: block;">Enter the 12-digit UTR number from your payment app receipt (or keep auto-generated reference).</small>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('checkout-modal')">Cancel</button>
        <button type="submit" name="process_payment" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-paper-plane"></i> Submit Request &amp; Pay
        </button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/js/user.js?v=2.0"></script>
<script>
function openCheckoutModal(id, name, price, duration) {
    document.getElementById('modal-plan-id').value = id;
    document.getElementById('modal-plan-name').innerText = name;
    document.getElementById('modal-plan-duration').innerText = duration + ' Months Access';
    
    const formattedPrice = '₹' + price.toLocaleString('en-IN', {minimumFractionDigits: 2});
    document.getElementById('modal-plan-price').innerText = formattedPrice;
    
    if (document.getElementById('modal-upi-price-display')) {
        document.getElementById('modal-upi-price-display').innerText = formattedPrice;
    }
    if (document.getElementById('modal-cash-price-display')) {
        document.getElementById('modal-cash-price-display').innerText = formattedPrice;
    }

    selectPlansMethod('upi');
    openModal('checkout-modal');
}

function selectPlansMethod(method) {
    document.getElementById('modal-pay-method').value = method;
    
    const btnUpi = document.getElementById('plans-btn-upi');
    const btnCash = document.getElementById('plans-btn-cash');
    if (btnUpi) btnUpi.classList.toggle('selected', method === 'upi');
    if (btnCash) btnCash.classList.toggle('selected', method === 'cash');

    const upiBox = document.getElementById('upi-instructions-box');
    const cashBox = document.getElementById('cash-instructions-box');
    const txnLabel = document.getElementById('plansTxnLabel');
    const txnHelp = document.getElementById('plansTxnHelp');
    const txnInput = document.getElementById('modal-txn-id');

    if (method === 'upi') {
        if (upiBox) upiBox.style.display = 'block';
        if (cashBox) cashBox.style.display = 'none';
        if (txnLabel) txnLabel.innerText = 'UPI Transaction / UTR Reference ID *';
        if (txnInput) {
            txnInput.placeholder = 'e.g. 12-digit UTR (428109823491)';
            txnInput.value = 'UPI-' + Math.floor(1000000000 + Math.random() * 9000000000);
        }
        if (txnHelp) txnHelp.innerText = 'Enter the 12-digit UTR number from your payment app receipt (or keep auto-generated reference).';
    } else {
        if (upiBox) upiBox.style.display = 'none';
        if (cashBox) cashBox.style.display = 'block';
        if (txnLabel) txnLabel.innerText = 'Desk Cash Receipt Reference';
        if (txnInput) {
            txnInput.placeholder = 'e.g. CASH-REC-4819';
            txnInput.value = 'CASH-REC-' + Math.floor(1000 + Math.random() * 9000);
        }
        if (txnHelp) txnHelp.innerText = 'Desk receipt reference. Present this receipt ID at the gym front desk.';
    }
}

function copyPlansUpi() {
    const upiId = document.getElementById('plansUpiIdText').innerText.trim();
    navigator.clipboard.writeText(upiId).then(() => {
        const icon = document.getElementById('plansUpiCopyIcon');
        const text = document.getElementById('plansUpiCopyBtnText');
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
</script>
</body>
</html>
