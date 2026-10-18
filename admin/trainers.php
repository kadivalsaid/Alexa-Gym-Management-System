<?php
/**
 * Alexa Gym — Admin Portal: Trainers Management
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$msg_success = '';
$msg_error   = '';

// Delete Trainer
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    try {
        $pdo->prepare("DELETE FROM trainers WHERE id = ?")->execute([$del_id]);
        $msg_success = "Trainer #$del_id removed.";
    } catch (Exception $e) {
        $msg_error = "Could not delete trainer: " . $e->getMessage();
    }
}

// Add Trainer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_trainer'])) {
    $name   = trim($_POST['full_name'] ?? '');
    $phone  = trim($_POST['phone'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $spec   = trim($_POST['specialization'] ?? '');
    $exp    = trim($_POST['experience'] ?? '3+ Years Experience');
    $salary = (float)($_POST['salary'] ?? 0);
    $bio    = trim($_POST['bio'] ?? '');

    $clean_phone = preg_replace('/[^0-9]/', '', $phone);

    if (empty($name) || empty($phone)) {
        $msg_error = 'Name and Phone number are required.';
    } elseif (strlen($clean_phone) !== 10 || !preg_match('/^[6-9]\d{9}$/', $clean_phone)) {
        $msg_error = 'Invalid phone number: Please provide a valid 10-digit mobile number starting with 6, 7, 8, or 9.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO trainers (full_name, email, phone, specialization, experience, bio, salary, joined_on, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 'active', NOW())
            ");
            $stmt->execute([$name, $email, $clean_phone, $spec, $exp, $bio, $salary]);
            $msg_success = "Trainer $name successfully added!";
        } catch (Exception $e) {
            $msg_error = "Failed to add trainer: " . $e->getMessage();
        }
    }
}

$trainers = $pdo->query("
    SELECT t.*, (SELECT COUNT(*) FROM members m WHERE m.trainer_id = t.id AND m.status = 'active') AS assigned_count 
    FROM trainers t 
    ORDER BY t.id DESC
")->fetchAll();

$page_title   = 'Manage Trainers — Alexa Gym Admin';
$topbar_title = 'Trainers Management';
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
          <h2 style="font-size: 26px; font-weight: 800; margin-bottom: 4px;">Certified Trainers Staff</h2>
          <p style="color: var(--muted); font-size: 14.5px;">Manage coaching profiles, payroll, and member assignment rosters.</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="openModal('add-trainer-modal')">
          <i class="fa-solid fa-user-plus"></i> Add New Trainer
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

      <div class="card">
        <div class="custom-table-wrap">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Trainer</th>
                <th>Specialization</th>
                <th>Experience</th>
                <th>Salary</th>
                <th>Clients</th>
                <th style="text-align: right; width: 100px;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($trainers)): ?>
                <tr>
                  <td colspan="6" style="text-align: center; color: var(--muted); padding: 32px;">No trainers found.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($trainers as $tr): ?>
                  <tr>
                    <td>
                      <strong style="color: var(--text); font-size: 15px;"><?= e($tr['full_name']) ?></strong>
                      <small style="display: block; color: var(--muted); font-size: 12px; margin-top: 2px;">
                        <i class="fa-solid fa-phone" style="font-size: 10px; color: var(--cyan); margin-right: 3px;"></i> <?= e($tr['phone'] ?: '—') ?>
                      </small>
                    </td>
                    <td><span class="badge-status active"><?= e($tr['specialization'] ?: 'General Fitness') ?></span></td>
                    <td><?= e(str_ireplace(' Experience', '', $tr['experience'] ?? '')) ?></td>
                    <td><strong style="color: var(--cyan); font-size: 14.5px;"><?= format_currency($tr['salary']) ?></strong></td>
                    <td>
                      <span class="badge-status" style="background: var(--panel-2); color: var(--cyan); display: inline-flex; align-items: center; gap: 5px;">
                        <i class="fa-solid fa-user-group"></i> <?= (int)$tr['assigned_count'] ?> Member<?= (int)$tr['assigned_count'] != 1 ? 's' : '' ?>
                      </span>
                    </td>
                    <td style="text-align: right;">
                      <a href="trainers.php?delete_id=<?= $tr['id'] ?>" class="btn btn-outline btn-sm" style="color: var(--bad); border-color: rgba(239, 68, 68, 0.3); padding: 6px 10px;" title="Delete Trainer" onclick="return confirm('Remove trainer <?= e(addslashes($tr['full_name'])) ?>?')">
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

<!-- Add Trainer Modal -->
<div class="modal-backdrop" id="add-trainer-modal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 18px; font-weight: 800;"><i class="fa-solid fa-user-ninja" style="color: var(--cyan); margin-right: 8px;"></i> Add New Trainer</h3>
      <button type="button" class="action-btn-circle" onclick="closeModal('add-trainer-modal')">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form method="POST" action="trainers.php">
      <div class="modal-body">
        <div style="margin-bottom: 14px;">
          <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Full Name *</label>
          <input type="text" name="full_name" class="form-control" placeholder="Coach's Name" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div>
            <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Phone Number *</label>
            <input type="tel" name="phone" class="form-control" placeholder="98XXXXXXXX" maxlength="10" pattern="[6-9][0-9]{9}" title="10-digit mobile number starting with 6, 7, 8, or 9" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);" required>
          </div>
          <div>
            <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="coach@gym.com">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div>
            <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Specialization</label>
            <input type="text" name="specialization" class="form-control" placeholder="e.g. Strength &amp; Conditioning">
          </div>
          <div>
            <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Experience</label>
            <input type="text" name="experience" class="form-control" placeholder="e.g. 5+ Years Experience">
          </div>
        </div>

        <div style="margin-bottom: 14px;">
          <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Monthly Salary (₹)</label>
          <input type="number" step="0.01" name="salary" class="form-control" placeholder="35000">
        </div>

        <div style="margin-bottom: 14px;">
          <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Short Bio / Certifications</label>
          <textarea name="bio" rows="3" class="form-control" placeholder="IFBB certified coach specialized in..."></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('add-trainer-modal')">Cancel</button>
        <button type="submit" name="add_trainer" class="btn btn-primary btn-sm">Save Trainer</button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/js/user.js?v=2.0"></script>
</body>
</html>
