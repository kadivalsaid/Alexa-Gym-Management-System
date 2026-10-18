<?php
/**
 * Alexa Gym — Admin Portal: Attendance Management
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$msg_success = '';
$msg_error   = '';

// Delete attendance record
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    try {
        $pdo->prepare("DELETE FROM attendance WHERE id = ?")->execute([$del_id]);
        $msg_success = "Attendance record #$del_id removed.";
    } catch (Exception $e) {
        $msg_error = "Failed to remove entry: " . $e->getMessage();
    }
}

// Mark Check-in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_checkin'])) {
    $member_id   = (int)($_POST['member_id'] ?? 0);
    $attend_date = trim($_POST['attend_date'] ?? date('Y-m-d'));
    $check_in    = date('H:i:s');

    if ($member_id <= 0) {
        $msg_error = 'Please select a member.';
    } else {
        try {
            // Check if already checked in on this date
            $chk = $pdo->prepare("SELECT id FROM attendance WHERE member_id = ? AND attend_date = ?");
            $chk->execute([$member_id, $attend_date]);
            if ($chk->fetch()) {
                $msg_error = 'This member is already marked present for the selected date.';
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO attendance (member_id, attend_date, check_in, status, created_at) 
                    VALUES (?, ?, ?, 'present', NOW())
                ");
                $ins->execute([$member_id, $attend_date, $check_in]);
                $msg_success = "Attendance recorded successfully at " . date('h:i A') . "!";
            }
        } catch (Exception $e) {
            $msg_error = "Error recording attendance: " . $e->getMessage();
        }
    }
}

$filter_date = trim($_GET['date'] ?? date('Y-m-d'));

// Fetch entries for the date
$stmt = $pdo->prepare("
    SELECT a.*, m.full_name, m.phone, p.name AS plan_name 
    FROM attendance a 
    JOIN members m ON a.member_id = m.id 
    LEFT JOIN plans p ON m.plan_id = p.id 
    WHERE a.attend_date = ? 
    ORDER BY a.check_in DESC
");
$stmt->execute([$filter_date]);
$daily_attendance = $stmt->fetchAll();

// All active members for checkin dropdown
$members = $pdo->query("SELECT id, full_name, phone FROM members WHERE status = 'active' ORDER BY full_name ASC")->fetchAll();

$page_title   = 'Manage Attendance — Alexa Gym Admin';
$topbar_title = 'Attendance Tracker';
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
      <div style="margin-bottom: 28px;">
        <h2 style="font-size: 26px; font-weight: 800; margin-bottom: 4px;">Daily Attendance Check-ins</h2>
        <p style="color: var(--muted); font-size: 14.5px;">Mark member check-ins and verify date-wise gym entries.</p>
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

      <!-- Mark Attendance Card -->
      <div class="card" style="margin-bottom: 26px;">
        <h3 class="card-title" style="margin-bottom: 18px;">
          <i class="fa-solid fa-user-check" style="color: var(--cyan); margin-right: 8px;"></i> Mark Member Check-In
        </h3>

        <form method="POST" action="attendance.php?date=<?= urlencode($filter_date) ?>" style="display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end;">
          <div style="flex: 1.5; min-width: 240px;">
            <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Select Member</label>
            <select name="member_id" class="form-control" required>
              <option value="">-- Choose Member --</option>
              <?php foreach ($members as $m): ?>
                <option value="<?= $m['id'] ?>"><?= e($m['full_name']) ?> (<?= e($m['phone']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>

          <div style="flex: 1; min-width: 180px;">
            <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Date</label>
            <input type="date" name="attend_date" class="form-control" value="<?= e($filter_date) ?>">
          </div>

          <div>
            <button type="submit" name="mark_checkin" class="btn btn-primary" style="padding: 12px 24px;">
              <i class="fa-solid fa-check"></i> Record Check-In
            </button>
          </div>
        </form>
      </div>

      <!-- Entries for Date Card -->
      <div class="card">
        <div class="card-header">
          <div>
            <h3 class="card-title">
              <i class="fa-solid fa-calendar-day" style="color: var(--cyan); margin-right: 8px;"></i>
              Check-ins for <?= format_date($filter_date) ?> (<?= count($daily_attendance) ?> Members)
            </h3>
          </div>
          <div>
            <form method="GET" action="attendance.php" style="display: flex; gap: 10px; align-items: center;">
              <label style="font-size: 13px; color: var(--muted);">Select Date:</label>
              <input type="date" name="date" class="form-control" value="<?= e($filter_date) ?>" onchange="this.form.submit()" style="width: 160px; padding: 6px 12px;">
            </form>
          </div>
        </div>

        <div class="custom-table-wrap">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Member Name</th>
                <th>Phone</th>
                <th>Active Plan</th>
                <th>Check-in Time</th>
                <th>Status</th>
                <th style="text-align: right;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($daily_attendance)): ?>
                <?php foreach ($daily_attendance as $entry): ?>
                  <tr>
                    <td><strong><?= e($entry['full_name']) ?></strong></td>
                    <td><?= e($entry['phone']) ?></td>
                    <td><span style="color: var(--muted);"><?= e($entry['plan_name'] ?: 'Standard') ?></span></td>
                    <td>
                      <i class="fa-regular fa-clock" style="color: var(--cyan); margin-right: 6px;"></i>
                      <?= date('h:i A', strtotime($entry['check_in'])) ?>
                    </td>
                    <td><span class="badge-status present">PRESENT</span></td>
                    <td style="text-align: right;">
                      <a href="attendance.php?delete_id=<?= $entry['id'] ?>&date=<?= urlencode($filter_date) ?>" class="btn btn-outline btn-sm" style="color: var(--bad);" onclick="return confirm('Remove check-in entry for <?= e(addslashes($entry['full_name'])) ?>?')">
                        <i class="fa-solid fa-trash"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" style="text-align: center; color: var(--muted); padding: 32px;">No check-ins recorded for this date.</td>
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
