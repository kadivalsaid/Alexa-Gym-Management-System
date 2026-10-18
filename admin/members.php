<?php
/**
 * Alexa Gym — Admin Portal: Members Management
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$msg_success = '';
$msg_error   = '';

// Handle Approve / Accept Member Request
if (isset($_GET['approve_id'])) {
    $approve_id = (int)$_GET['approve_id'];
    try {
        $pdo->beginTransaction();

        // 1. Look up request in membership_requests
        $req_stmt = $pdo->prepare("
            SELECT mr.*, p.duration_months, p.name as plan_name, p.price as plan_price 
            FROM membership_requests mr 
            LEFT JOIN plans p ON mr.plan_id = p.id 
            WHERE mr.id = ?
        ");
        $req_stmt->execute([$approve_id]);
        $target_req = $req_stmt->fetch();

        if ($target_req) {
            $months = (int)($target_req['duration_months'] ?? 1);
            if ($months <= 0) $months = 1;
            $start_date = date('Y-m-d');
            $expiry_date = date('Y-m-d', strtotime("+$months months"));

            // Check if member already exists in members table
            $exist_stmt = $pdo->prepare("SELECT id FROM members WHERE user_id = ? OR (email = ? AND email != '') LIMIT 1");
            $exist_stmt->execute([$target_req['user_id'], $target_req['email']]);
            $existing_m = $exist_stmt->fetch();

            if (!$existing_m) {
                // Insert brand new member into members table ONLY ON ADMIN ACCEPTANCE
                $ins_m = $pdo->prepare("
                    INSERT INTO members (user_id, full_name, email, phone, gender, dob, address, plan_id, trainer_id, join_date, expiry_date, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ");
                $ins_m->execute([
                    $target_req['user_id'],
                    $target_req['full_name'],
                    $target_req['email'],
                    $target_req['phone'],
                    $target_req['gender'] ?? 'male',
                    !empty($target_req['dob']) ? $target_req['dob'] : null,
                    $target_req['address'] ?? '',
                    $target_req['plan_id'],
                    $target_req['trainer_id'] ?: 1,
                    $start_date,
                    $expiry_date
                ]);
                $real_member_id = $pdo->lastInsertId();
            } else {
                // Update existing member record
                $real_member_id = $existing_m['id'];
                $up_m = $pdo->prepare("
                    UPDATE members 
                    SET user_id = ?, full_name = ?, email = ?, phone = ?, plan_id = ?, trainer_id = ?, join_date = ?, expiry_date = ?, status = 'active'
                    WHERE id = ?
                ");
                $up_m->execute([
                    $target_req['user_id'],
                    $target_req['full_name'],
                    $target_req['email'],
                    $target_req['phone'],
                    $target_req['plan_id'],
                    $target_req['trainer_id'] ?: 1,
                    $start_date,
                    $expiry_date,
                    $real_member_id
                ]);
            }

            // Ensure user account is active in users table and phone is synced
            if (!empty($target_req['user_id'])) {
                if (!empty($target_req['phone'])) {
                    $pdo->prepare("UPDATE users SET phone = ?, status = 'active' WHERE id = ?")->execute([$target_req['phone'], $target_req['user_id']]);
                } else {
                    $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$target_req['user_id']]);
                }

                // Send congratulatory notification to member
                $pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                    VALUES (?, 'Membership Request Accepted! 🎉', ?, 'success', 0, NOW())
                ")->execute([
                    $target_req['user_id'],
                    "Your request for " . ($target_req['plan_name'] ?: 'Gym Membership') . " has been approved by the Admin! Your member access is now active until " . format_date($expiry_date) . "."
                ]);
            }

            // Record completed payment
            $pdo->prepare("
                INSERT INTO payments (member_id, plan_id, amount, method, transaction_id, paid_on, status, note, created_at) 
                VALUES (?, ?, ?, ?, ?, CURDATE(), 'completed', ?, NOW())
            ")->execute([
                $real_member_id,
                $target_req['plan_id'],
                $target_req['amount'] ?? ($target_req['plan_price'] ?? 0),
                $target_req['payment_method'] ?? 'upi',
                $target_req['transaction_id'] ?? ('PAY-' . strtoupper(bin2hex(random_bytes(3)))),
                'Approved Plan: ' . ($target_req['plan_name'] ?? 'Membership')
            ]);

            // Log in memberships table
            if (!empty($target_req['plan_id'])) {
                $pdo->prepare("
                    INSERT INTO memberships (member_id, plan_id, start_date, end_date, price, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'active', NOW())
                ")->execute([
                    $real_member_id,
                    $target_req['plan_id'],
                    $start_date,
                    $expiry_date,
                    $target_req['amount'] ?? ($target_req['plan_price'] ?? 0.00)
                ]);
            }

            // Update membership_requests status to approved
            $pdo->prepare("UPDATE membership_requests SET status = 'approved' WHERE id = ?")->execute([$approve_id]);

            $pdo->commit();
            $msg_success = "Member " . e($target_req['full_name']) . " has been approved, activated and added to the Members directory!";
        } else {
            // Fallback: check members table directly if legacy record
            $m_stmt = $pdo->prepare("SELECT m.*, p.duration_months, p.name as plan_name, p.price as plan_price FROM members m LEFT JOIN plans p ON m.plan_id = p.id WHERE m.id = ?");
            $m_stmt->execute([$approve_id]);
            $target_m = $m_stmt->fetch();
            if ($target_m) {
                $months = (int)($target_m['duration_months'] ?? 1);
                if ($months <= 0) $months = 1;
                $start_date = date('Y-m-d');
                $expiry_date = date('Y-m-d', strtotime("+$months months"));
                $pdo->prepare("UPDATE members SET status = 'active', join_date = ?, expiry_date = ? WHERE id = ?")->execute([$start_date, $expiry_date, $approve_id]);
                if (!empty($target_m['user_id'])) {
                    $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$target_m['user_id']]);
                }
                $pdo->commit();
                $msg_success = "Member " . e($target_m['full_name']) . " has been approved and activated!";
            } else {
                $pdo->rollBack();
                $msg_error = "Membership request #$approve_id not found.";
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg_error = "Could not approve member: " . $e->getMessage();
    }
}

// Handle Reject Member Request
if (isset($_GET['reject_id'])) {
    $reject_id = (int)$_GET['reject_id'];
    try {
        $pdo->beginTransaction();
        $req_stmt = $pdo->prepare("
            SELECT mr.*, p.name as plan_name 
            FROM membership_requests mr 
            LEFT JOIN plans p ON mr.plan_id = p.id 
            WHERE mr.id = ?
        ");
        $req_stmt->execute([$reject_id]);
        $target_req = $req_stmt->fetch();

        if ($target_req) {
            // Mark request as rejected in membership_requests table (User is NEVER added to members table!)
            $pdo->prepare("UPDATE membership_requests SET status = 'rejected' WHERE id = ?")->execute([$reject_id]);

            // Send notification to user explaining rejection
            if (!empty($target_req['user_id'])) {
                $plan_txt = !empty($target_req['plan_name']) ? "for {$target_req['plan_name']}" : "";
                $pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                    VALUES (?, 'Membership Request Update ⚠️', ?, 'warning', 0, NOW())
                ")->execute([
                    $target_req['user_id'],
                    "Due to a technical / verification issue, your plan request {$plan_txt} could not be approved. Please review your details and choose a plan again from your dashboard."
                ]);
            }
            $pdo->commit();
            $msg_success = "Plan request #$reject_id for " . e($target_req['full_name']) . " has been rejected. They were NOT added to the members directory.";
        } else {
            // Fallback for legacy members row if any
            $pdo->prepare("UPDATE members SET status = 'inactive' WHERE id = ?")->execute([$reject_id]);
            $pdo->commit();
            $msg_success = "Record #$reject_id has been rejected.";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg_error = "Could not reject member request: " . $e->getMessage();
    }
}

// Handle Delete Member
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    try {
        $pdo->prepare("DELETE FROM members WHERE id = ?")->execute([$del_id]);
        $msg_success = "Member #$del_id has been removed.";
    } catch (Exception $e) {
        $msg_error = "Could not delete member: " . $e->getMessage();
    }
}

// Handle Delete Request
if (isset($_GET['delete_req_id'])) {
    $del_req_id = (int)$_GET['delete_req_id'];
    try {
        $pdo->prepare("DELETE FROM membership_requests WHERE id = ?")->execute([$del_req_id]);
        $msg_success = "Plan request #$del_req_id has been removed.";
    } catch (Exception $e) {
        $msg_error = "Could not delete request: " . $e->getMessage();
    }
}

// Handle Add Member Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {
    $name    = trim($_POST['full_name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $gender  = trim($_POST['gender'] ?? 'male');
    $dob     = trim($_POST['dob'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $plan_id = (int)($_POST['plan_id'] ?? 0);
    $email_regex = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';

    $clean_phone = preg_replace('/[^0-9]/', '', $phone);

    if (empty($name) || empty($phone)) {
        $msg_error = 'Name and Phone number are required.';
    } elseif (strlen($clean_phone) !== 10 || !preg_match('/^[6-9]\d{9}$/', $clean_phone)) {
        $msg_error = 'Invalid phone number: Please provide a valid 10-digit mobile number starting with 6, 7, 8, or 9.';
    } elseif (!empty($email) && (strpos($email, '@') === false || !preg_match('/\.[a-zA-Z]{2,}$/', $email) || !preg_match($email_regex, $email) || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
        $msg_error = 'Invalid email: Email must contain "@" and a valid domain extension like .com (e.g. user@gmail.com).';
    } else {
        try {
            // Compute expiry if plan selected
            $expiry_date = null;
            if ($plan_id > 0) {
                $p_row = $pdo->prepare("SELECT duration_months FROM plans WHERE id = ?");
                $p_row->execute([$plan_id]);
                $months = (int)$p_row->fetchColumn();
                if ($months > 0) $expiry_date = date('Y-m-d', strtotime("+$months months"));
            }

            // Create user account if email provided
            $user_id = null;
            if (!empty($email)) {
                $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $chk->execute([$email]);
                $user_id = $chk->fetchColumn();
                if (!$user_id) {
                    $default_pass = 'Member786';
                    $ins_u = $pdo->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, 'member', 'active', NOW())");
                    $ins_u->execute([$name, $email, $default_pass]);
                    $user_id = $pdo->lastInsertId();
                }
            }

            $ins_m = $pdo->prepare("
                INSERT INTO members (user_id, full_name, email, phone, gender, dob, address, plan_id, join_date, expiry_date, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, NOW())
            ");
            $ins_m->execute([
                $user_id ?: null,
                $name,
                $email ?: null,
                $clean_phone,
                $gender,
                !empty($dob) ? $dob : null,
                $address ?: null,
                $plan_id > 0 ? $plan_id : null,
                $expiry_date,
                $status
            ]);
            $msg_success = "Member $name registered successfully!";
        } catch (Exception $e) {
            $msg_error = "Error adding member: " . $e->getMessage();
        }
    }
}

// Search & Filter
$search = trim($_GET['q'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$params = [];

if ($filter_status === 'pending') {
    // Show requests from membership_requests table awaiting admin acceptance
    $sql = "
        SELECT mr.id, mr.user_id, mr.full_name, mr.email, mr.phone, mr.gender, mr.dob, mr.address,
               mr.plan_id, mr.trainer_id, mr.created_at AS join_date, NULL AS expiry_date,
               'pending' AS status, p.name AS plan_name, p.price AS plan_price,
               t.full_name AS trainer_name, mr.payment_method AS pay_method,
               mr.transaction_id AS pay_txn_id, mr.amount AS pay_amount, 'pending' AS pay_status,
               1 AS is_request
        FROM membership_requests mr
        LEFT JOIN plans p ON mr.plan_id = p.id
        LEFT JOIN trainers t ON mr.trainer_id = t.id
        WHERE mr.status = 'pending'
    ";
    if (!empty($search)) {
        $sql .= " AND (mr.full_name LIKE ? OR mr.phone LIKE ? OR mr.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $sql .= " ORDER BY mr.id DESC";
} elseif ($filter_status === 'inactive') {
    // Inactive members & rejected requests
    $sql = "
        SELECT m.id, m.user_id, m.full_name, m.email, m.phone, m.gender, m.dob, m.address,
               m.plan_id, m.trainer_id, m.join_date, m.expiry_date, m.status,
               p.name AS plan_name, p.price AS plan_price, t.full_name AS trainer_name,
               pay.method AS pay_method, pay.transaction_id AS pay_txn_id, pay.amount AS pay_amount, pay.status AS pay_status,
               0 AS is_request
        FROM members m 
        LEFT JOIN plans p ON m.plan_id = p.id 
        LEFT JOIN trainers t ON m.trainer_id = t.id 
        LEFT JOIN (
            SELECT p1.* FROM payments p1
            INNER JOIN (SELECT member_id, MAX(id) as max_id FROM payments GROUP BY member_id) p2
            ON p1.id = p2.max_id
        ) pay ON m.id = pay.member_id 
        WHERE m.status = 'inactive'
    ";
    if (!empty($search)) {
        $sql .= " AND (m.full_name LIKE ? OR m.phone LIKE ? OR m.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $sql .= " ORDER BY m.id DESC";
} else {
    // Active members (default)
    $sql = "
        SELECT m.id, m.user_id, m.full_name, m.email, m.phone, m.gender, m.dob, m.address,
               m.plan_id, m.trainer_id, m.join_date, m.expiry_date, m.status,
               p.name AS plan_name, p.price AS plan_price, t.full_name AS trainer_name,
               pay.method AS pay_method, pay.transaction_id AS pay_txn_id, pay.amount AS pay_amount, pay.status AS pay_status,
               0 AS is_request
        FROM members m 
        LEFT JOIN plans p ON m.plan_id = p.id 
        LEFT JOIN trainers t ON m.trainer_id = t.id 
        LEFT JOIN (
            SELECT p1.* FROM payments p1
            INNER JOIN (SELECT member_id, MAX(id) as max_id FROM payments GROUP BY member_id) p2
            ON p1.id = p2.max_id
        ) pay ON m.id = pay.member_id 
        WHERE m.status = 'active'
    ";
    if (!empty($search)) {
        $sql .= " AND (m.full_name LIKE ? OR m.phone LIKE ? OR m.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $sql .= " ORDER BY m.id DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members_list = $stmt->fetchAll();

// Plans list for dropdowns
$all_plans = $pdo->query("SELECT id, name, duration_months, price FROM plans WHERE status = 'active'")->fetchAll();

// Quick Filter Counts
$cnt_active   = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active'")->fetchColumn();
$cnt_pending  = (int)$pdo->query("SELECT COUNT(*) FROM membership_requests WHERE status = 'pending'")->fetchColumn();
$cnt_inactive = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status = 'inactive'")->fetchColumn()
              + (int)$pdo->query("SELECT COUNT(*) FROM membership_requests WHERE status = 'rejected'")->fetchColumn();
$cnt_all      = $cnt_active + $cnt_pending + $cnt_inactive;

$page_title   = 'Manage Members — Alexa Gym Admin';
$topbar_title = 'Members Management';
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
          <h2 style="font-size: 26px; font-weight: 800; margin-bottom: 4px;">Members Database</h2>
          <p style="color: var(--muted); font-size: 14.5px;">Search, edit, assign plans, and register new gym admissions.</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="openModal('add-member-modal')">
          <i class="fa-solid fa-user-plus"></i> Add New Member
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

      <!-- Quick Filter Buttons -->
      <div style="display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; align-items: center;">
        <a href="members.php" class="btn btn-sm <?= (empty($filter_status) || $filter_status === 'active') ? 'btn-primary' : 'btn-outline' ?>">
          <i class="fa-solid fa-circle-check"></i> Active Members (<?= $cnt_active ?>)
        </a>
        <a href="members.php?status=pending" class="btn btn-sm <?= $filter_status === 'pending' ? 'btn-primary' : 'btn-outline' ?>" style="<?= $cnt_pending > 0 ? 'border-color: #fbbf24; color: #fbbf24;' : '' ?>">
          <i class="fa-solid fa-hourglass-half"></i> Pending Requests
          <?php if ($cnt_pending > 0): ?>
            <span style="background: #fbbf24; color: #070913; padding: 1px 7px; border-radius: 999px; font-weight: 800; font-size: 11px; margin-left: 4px;">
              <?= $cnt_pending ?>
            </span>
          <?php endif; ?>
        </a>
        <a href="members.php?status=inactive" class="btn btn-sm <?= $filter_status === 'inactive' ? 'btn-primary' : 'btn-outline' ?>">
          <i class="fa-solid fa-ban"></i> Inactive / Rejected (<?= $cnt_inactive ?>)
        </a>
      </div>

      <!-- Search & Filter Bar -->
      <div class="card" style="padding: 18px 24px; margin-bottom: 24px;">
        <form method="GET" action="members.php" style="display: flex; gap: 16px; flex-wrap: wrap; align-items: center;">
          <div style="flex-grow: 1; min-width: 260px; position: relative;">
            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 16px; top: 14px; color: var(--muted);"></i>
            <input type="text" name="q" class="form-control" placeholder="Search by member name, phone or email..." value="<?= e($search) ?>" style="padding-left: 44px;">
          </div>
          <div>
            <select name="status" class="form-control" onchange="this.form.submit()" style="width: 190px;">
              <option value="active" <?= (empty($filter_status) || $filter_status === 'active') ? 'selected' : '' ?>>Active Members (<?= $cnt_active ?>)</option>
              <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending Requests (<?= $cnt_pending ?>)</option>
              <option value="inactive" <?= $filter_status === 'inactive' ? 'selected' : '' ?>>Inactive / Rejected (<?= $cnt_inactive ?>)</option>
            </select>
          </div>
          <button type="submit" class="btn btn-outline btn-sm">Filter</button>
          <?php if (!empty($search) || !empty($filter_status)): ?>
            <a href="members.php" class="btn btn-outline btn-sm" style="color: var(--muted);">Clear</a>
          <?php endif; ?>
        </form>
      </div>

      <!-- Members Table -->
      <div class="card">
        <div class="custom-table-wrap">
          <table class="custom-table">
            <thead>
              <tr>
                <th style="width: 60px;">ID</th>
                <th>Member Name</th>
                <th>Phone</th>
                <th>Plan &amp; Details</th>
                <th>Expiry Date</th>
                <th>Status</th>
                <th style="text-align: right; min-width: 140px;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($members_list)): ?>
                <?php foreach ($members_list as $m): ?>
                  <tr>
                    <td><strong style="color: var(--muted);"><?= $m['id'] ?></strong></td>
                    <td>
                      <div style="font-weight: 700; color: var(--text);"><?= e($m['full_name']) ?></div>
                      <small style="color: var(--muted);"><?= ($m['status'] === 'pending') ? 'Requested on ' : 'Joined ' ?><?= format_date($m['join_date']) ?></small>
                    </td>
                    <td>
                      <strong style="color: var(--text);"><?= e($m['phone'] ?: '—') ?></strong>
                    </td>
                    <td>
                      <strong style="color: var(--cyan);"><?= e($m['plan_name'] ?: 'No Plan Selected') ?></strong>
                      <?php if (!empty($m['trainer_name'])): ?>
                        <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">
                          <i class="fa-solid fa-user-ninja" style="margin-right: 4px;"></i> <?= e($m['trainer_name']) ?>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($m['pay_txn_id'])): ?>
                        <div style="font-size: 11.5px; color: <?= ($m['pay_status'] === 'completed') ? '#34d399' : '#fbbf24' ?>; margin-top: 3px; font-weight: 600;">
                          <i class="fa-solid fa-credit-card" style="margin-right: 3px;"></i>
                          <?= strtoupper($m['pay_method'] ?? 'UPI') ?>: <?= e($m['pay_txn_id']) ?>
                          <?php if (!empty($m['pay_amount'])): ?>
                            (₹<?= number_format((float)$m['pay_amount'], 0) ?>)
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if (!empty($m['expiry_date']) && $m['status'] !== 'pending'): ?>
                        <span style="color: <?= (strtotime($m['expiry_date']) < time()) ? 'var(--bad)' : 'var(--text)' ?>;">
                          <?= format_date($m['expiry_date']) ?>
                        </span>
                      <?php else: ?>
                        <span style="color: var(--muted);">Upon Approval</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($m['status'] === 'pending'): ?>
                        <?php if (!empty($m['plan_id'])): ?>
                          <span class="badge" style="background: rgba(0, 242, 254, 0.15); color: var(--cyan); border: 1px solid rgba(0, 242, 254, 0.4); padding: 4px 10px; border-radius: 999px; font-weight: 800; font-size: 11px; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 5px;">
                            <i class="fa-solid fa-hourglass-half"></i> IN PROGRESS
                          </span>
                        <?php else: ?>
                          <span class="badge" style="background: rgba(148, 163, 184, 0.15); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.4); padding: 4px 10px; border-radius: 999px; font-weight: 700; font-size: 11px; letter-spacing: 0.5px;">
                            <i class="fa-solid fa-user"></i> NO PLAN
                          </span>
                        <?php endif; ?>
                      <?php elseif ($m['status'] === 'active'): ?>
                        <span class="badge-status active">ACTIVE</span>
                      <?php else: ?>
                        <span class="badge-status expired"><?= strtoupper($m['status']) ?></span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align: right; white-space: nowrap;">
                      <?php if ($m['status'] === 'pending'): ?>
                        <a href="members.php?approve_id=<?= $m['id'] ?>&status=pending" class="btn btn-sm" style="background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); margin-right: 6px; text-decoration: none; padding: 6px 12px; font-weight: 700;" title="Approve & Activate" onclick="return confirm('Accept and activate member <?= e(addslashes($m['full_name'])) ?>?')">
                          <i class="fa-solid fa-check"></i> Approve
                        </a>
                        <a href="members.php?reject_id=<?= $m['id'] ?>&status=pending" class="btn btn-outline btn-sm" style="color: #f87171; border-color: rgba(239, 68, 68, 0.3); margin-right: 6px; text-decoration: none; padding: 6px 10px;" title="Reject Request" onclick="return confirm('Reject request for <?= e(addslashes($m['full_name'])) ?>?')">
                          <i class="fa-solid fa-xmark"></i> Reject
                        </a>
                      <?php endif; ?>
                      <?php if (!empty($m['is_request'])): ?>
                        <a href="members.php?delete_req_id=<?= $m['id'] ?>&status=pending" class="btn btn-outline btn-sm" style="color: var(--bad); border-color: rgba(239, 68, 68, 0.3); text-decoration: none; padding: 6px 10px;" title="Delete Request" onclick="return confirm('Are you sure you want to remove request #<?= $m['id'] ?>?')">
                          <i class="fa-solid fa-trash"></i>
                        </a>
                      <?php else: ?>
                        <a href="members.php?delete_id=<?= $m['id'] ?>" class="btn btn-outline btn-sm" style="color: var(--bad); border-color: rgba(239, 68, 68, 0.3); text-decoration: none; padding: 6px 10px;" title="Delete Member" onclick="return confirm('Are you sure you want to remove member <?= e(addslashes($m['full_name'])) ?>?')">
                          <i class="fa-solid fa-trash"></i>
                        </a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" style="text-align: center; color: var(--muted); padding: 32px;">No members matching criteria.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- Add Member Modal -->
<div class="modal-backdrop" id="add-member-modal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 18px; font-weight: 800;"><i class="fa-solid fa-user-plus" style="color: var(--cyan); margin-right: 8px;"></i> Add New Member</h3>
      <button type="button" class="action-btn-circle" onclick="closeModal('add-member-modal')">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form method="POST" action="members.php">
      <div class="modal-body">
        <div style="margin-bottom: 14px;">
          <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Full Name *</label>
          <input type="text" name="full_name" class="form-control" placeholder="Member's full name" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div>
            <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Phone Number *</label>
            <input type="tel" name="phone" class="form-control" placeholder="98XXXXXXXX" maxlength="10" pattern="[6-9][0-9]{9}" title="10-digit mobile number starting with 6, 7, 8, or 9" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);" required>
          </div>
          <div>
            <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="member@example.com">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div>
            <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Gender</label>
            <select name="gender" class="form-control">
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div>
            <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Date of Birth</label>
            <input type="date" name="dob" class="form-control">
          </div>
        </div>

        <div style="margin-bottom: 14px;">
          <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Assign Membership Plan</label>
          <select name="plan_id" class="form-control">
            <option value="0">-- No Initial Plan --</option>
            <?php foreach ($all_plans as $p): ?>
              <option value="<?= $p['id'] ?>">
                <?= e($p['name']) ?> (<?= format_currency($p['price']) ?> &bull; <?= (int)$p['duration_months'] ?> Mo)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="margin-bottom: 14px;">
          <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Residential Address</label>
          <textarea name="address" rows="2" class="form-control" placeholder="Address..."></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('add-member-modal')">Cancel</button>
        <button type="submit" name="add_member" class="btn btn-primary btn-sm">Save Member Admission</button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/js/user.js?v=2.0"></script>
</body>
</html>
