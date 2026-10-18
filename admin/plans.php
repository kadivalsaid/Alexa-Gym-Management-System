<?php
/**
 * Alexa Gym — Admin Portal: Plans Management
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$msg_success = '';
$msg_error   = '';

// Delete Plan
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    try {
        $pdo->prepare("DELETE FROM plans WHERE id = ?")->execute([$del_id]);
        $msg_success = "Plan #$del_id removed.";
    } catch (Exception $e) {
        $msg_error = "Could not delete plan: " . $e->getMessage();
    }
}

// Add New Plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_plan'])) {
    $name     = trim($_POST['name'] ?? '');
    $duration = (int)($_POST['duration_months'] ?? 1);
    $price    = (float)($_POST['price'] ?? 0);
    $desc     = trim($_POST['description'] ?? '');
    $features = trim($_POST['features'] ?? '');
    $popular  = isset($_POST['is_popular']) ? 1 : 0;

    if (empty($name) || $price <= 0) {
        $msg_error = 'Plan name and a positive price are required.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO plans (name, duration_months, price, description, features, is_popular, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([$name, $duration, $price, $desc, $features, $popular]);
            $msg_success = "Plan '$name' added successfully!";
        } catch (Exception $e) {
            $msg_error = "Failed to add plan: " . $e->getMessage();
        }
    }
}

// Fetch all plans with member count (Only approved active members)
$plans = $pdo->query("
    SELECT p.*, (SELECT COUNT(*) FROM members m WHERE m.plan_id = p.id AND m.status = 'active') AS member_count 
    FROM plans p 
    ORDER BY p.duration_months ASC
")->fetchAll();

$page_title   = 'Manage Plans — Alexa Gym Admin';
$topbar_title = 'Membership Plans Management';
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
          <h2 style="font-size: 26px; font-weight: 800; margin-bottom: 4px;">Membership Packages</h2>
          <p style="color: var(--muted); font-size: 14.5px;">Configure pricing, subscription duration, and included facilities.</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="openModal('add-plan-modal')">
          <i class="fa-solid fa-plus"></i> Create New Plan
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

      <!-- Plans Table -->
      <div class="card">
        <div class="custom-table-wrap">
          <table class="custom-table">
            <thead>
              <tr>
                <th>Plan Name</th>
                <th>Duration</th>
                <th>Price</th>
                <th>Active Enrollees</th>
                <th style="text-align: right; width: 100px;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($plans)): ?>
                <tr>
                  <td colspan="5" style="text-align: center; color: var(--muted); padding: 32px;">No plans created yet.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($plans as $p): ?>
                  <tr>
                    <td>
                      <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <strong style="color: var(--text); font-size: 15px;"><?= e($p['name']) ?></strong>
                        <?php if (!empty($p['is_popular'])): ?>
                          <span style="background: rgba(0, 242, 254, 0.15); color: var(--cyan); border: 1px solid rgba(0, 242, 254, 0.35); font-size: 10.5px; font-weight: 800; padding: 2px 7px; border-radius: 6px; letter-spacing: 0.5px;">POPULAR</span>
                        <?php endif; ?>
                      </div>
                      <div style="color: var(--muted); font-size: 12px; margin-top: 2px;"><?= e(substr($p['description'] ?? '', 0, 60)) ?><?= strlen($p['description'] ?? '') > 60 ? '...' : '' ?></div>
                    </td>
                    <td><strong><?= (int)$p['duration_months'] ?> Month<?= (int)$p['duration_months'] > 1 ? 's' : '' ?></strong></td>
                    <td><strong style="color: var(--cyan); font-size: 15px;"><?= format_currency($p['price']) ?></strong></td>
                    <td>
                      <span class="badge-status active" style="display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-users"></i> <?= (int)$p['member_count'] ?> Member<?= (int)$p['member_count'] != 1 ? 's' : '' ?>
                      </span>
                    </td>
                    <td style="text-align: right;">
                      <a href="plans.php?delete_id=<?= $p['id'] ?>" class="btn btn-outline btn-sm" style="color: var(--bad); border-color: rgba(239, 68, 68, 0.3); padding: 6px 10px;" title="Delete Plan" onclick="return confirm('Delete plan <?= e(addslashes($p['name'])) ?>?')">
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

<!-- Add Plan Modal -->
<div class="modal-backdrop" id="add-plan-modal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 18px; font-weight: 800;"><i class="fa-solid fa-tag" style="color: var(--cyan); margin-right: 8px;"></i> Create Membership Plan</h3>
      <button type="button" class="action-btn-circle" onclick="closeModal('add-plan-modal')">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form method="POST" action="plans.php">
      <div class="modal-body">
        <div style="margin-bottom: 14px;">
          <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Plan Title *</label>
          <input type="text" name="name" class="form-control" placeholder="e.g. Quarterly Pro" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div>
            <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Duration (Months) *</label>
            <input type="number" name="duration_months" min="1" max="36" class="form-control" value="3" required>
          </div>
          <div>
            <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Price (₹ INR) *</label>
            <input type="number" step="0.01" name="price" class="form-control" placeholder="2499" required>
          </div>
        </div>

        <div style="margin-bottom: 14px;">
          <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Description</label>
          <input type="text" name="description" class="form-control" placeholder="Brief tagline or description">
        </div>

        <div style="margin-bottom: 14px;">
          <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Features (One per line)</label>
          <textarea name="features" rows="4" class="form-control" placeholder="Full Gym Floor Access&#10;Cardio &amp; Strength Zone&#10;Locker Room Privileges"></textarea>
        </div>

        <div style="margin-bottom: 14px;">
          <label class="checkbox-wrap">
            <input type="checkbox" name="is_popular" value="1">
            <span>Mark as Popular / Recommended Plan</span>
          </label>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('add-plan-modal')">Cancel</button>
        <button type="submit" name="add_plan" class="btn btn-primary btn-sm">Create Plan</button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/js/user.js?v=2.0"></script>
</body>
</html>
