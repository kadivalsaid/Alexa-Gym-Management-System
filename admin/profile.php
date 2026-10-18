<?php
/**
 * Alexa Gym — Admin Portal: My Profile
 * Clean Profile Overview with only 2 Options under avatar:
 * 1. Edit Profile
 * 2. Change Password
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$u = get_logged_in_user();

// Fetch fresh admin user details from users table
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
$stmt->execute([$u['id']]);
$admin = $stmt->fetch();

if (!$admin) {
    // Fallback if not found by role restriction
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$u['id']]);
    $admin = $stmt->fetch();
}

$profile_success = '';
$profile_error   = '';
$pass_success    = '';
$pass_error      = '';

// Handle Profile Info Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');

    if (empty($full_name)) {
        $profile_error = 'Full Name is required.';
    } else {
        try {
            $pdo->prepare("UPDATE users SET name = ? WHERE id = ?")->execute([$full_name, $u['id']]);
            $_SESSION['user_name'] = $full_name;

            $profile_success = 'Admin profile updated successfully!';
            
            // Refresh admin object
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$u['id']]);
            $admin = $stmt->fetch();
            $u = get_logged_in_user();
        } catch (Exception $e) {
            $profile_error = 'Error updating profile: ' . $e->getMessage();
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_p = $_POST['current_password'] ?? '';
    $new_p     = $_POST['new_password'] ?? '';
    $confirm_p = $_POST['confirm_password'] ?? '';

    if (empty($current_p) || empty($new_p) || empty($confirm_p)) {
        $pass_error = 'Please fill in all password fields.';
    } elseif (strlen($new_p) < 6) {
        $pass_error = 'New password must be at least 6 characters long.';
    } elseif ($new_p !== $confirm_p) {
        $pass_error = 'New passwords do not match.';
    } else {
        $u_stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $u_stmt->execute([$u['id']]);
        $curr_hash = $u_stmt->fetchColumn();

        if ($curr_hash && ($current_p === $curr_hash || password_verify($current_p, $curr_hash))) {
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_p, $u['id']]);
            $pass_success = 'Password updated successfully!';
        } else {
            $pass_error = 'Current password is incorrect.';
        }
    }
}

// Determine View Mode: overview (default), edit, or password
$view_mode = 'overview';
if (!empty($profile_error)) {
    $view_mode = 'edit';
} elseif (!empty($pass_error)) {
    $view_mode = 'password';
} elseif (isset($_GET['tab']) && in_array($_GET['tab'], ['edit', 'password'])) {
    $view_mode = $_GET['tab'];
}

$page_title   = 'My Profile — Alexa Gym Admin';
$topbar_title = 'Admin Profile';
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
  <style>
    /* Profile Hero Card */
    .profile-hero-card {
      background: linear-gradient(135deg, rgba(28, 24, 45, 0.95) 0%, rgba(19, 17, 30, 0.95) 100%);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      padding: 28px 30px;
      margin-bottom: 24px;
      position: relative;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
    }
    .profile-hero-card::before {
      content: '';
      position: absolute;
      top: -60px;
      right: -60px;
      width: 240px;
      height: 240px;
      background: radial-gradient(circle, rgba(168, 85, 247, 0.16) 0%, transparent 70%);
      pointer-events: none;
    }
    [data-theme="light"] .profile-hero-card {
      background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%) !important;
      border-color: #e2e8f0;
      box-shadow: 0 8px 25px rgba(15, 23, 42, 0.06);
    }
    [data-theme="light"] .profile-hero-card::before {
      background: radial-gradient(circle, rgba(2, 132, 199, 0.08) 0%, transparent 70%);
    }
    [data-theme="light"] .profile-hero-card h2 {
      color: #0f172a;
    }
    .hero-main-layout {
      display: flex;
      align-items: flex-start;
      gap: 24px;
      flex-wrap: wrap;
    }
    .hero-avatar {
      width: 86px;
      height: 86px;
      border-radius: 24px;
      background: var(--purple-grad);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 38px;
      font-weight: 900;
      color: #fff;
      box-shadow: 0 0 25px rgba(168, 85, 247, 0.35);
      flex-shrink: 0;
    }
    .hero-info {
      flex: 1;
      min-width: 250px;
    }
    .hero-info h2 {
      font-size: 26px;
      font-weight: 800;
      margin-bottom: 6px;
      letter-spacing: -0.5px;
      color: var(--text);
    }
    .hero-badges {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 18px;
    }
    .badge-status-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 11.5px;
      font-weight: 700;
      padding: 4px 12px;
      border-radius: 999px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .badge-status-active {
      background: rgba(16, 185, 129, 0.15);
      color: #10b981;
      border: 1px solid rgba(16, 185, 129, 0.3);
    }
    .badge-role-pill {
      background: rgba(168, 85, 247, 0.15);
      color: #c084fc;
      border: 1px solid rgba(168, 85, 247, 0.35);
    }

    /* 2 Options Under Avatar */
    .under-avatar-actions {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }

    /* Info Grid */
    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 16px;
    }
    .info-tile {
      background: var(--panel-2);
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 16px 18px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      transition: border-color 0.2s;
    }
    .info-tile:hover {
      border-color: rgba(0, 242, 254, 0.35);
    }
    .tile-label {
      font-size: 12px;
      font-weight: 600;
      color: var(--muted);
      display: flex;
      align-items: center;
      gap: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .tile-label i {
      color: var(--cyan);
      font-size: 13px;
    }
    .tile-value {
      font-size: 15px;
      font-weight: 700;
      color: var(--text);
      word-break: break-word;
    }

    /* Password field with toggle button */
    .password-input-wrap {
      position: relative;
      display: flex;
      align-items: center;
    }
    .password-input-wrap .form-control {
      padding-right: 42px;
    }
    /* Hide browser default password reveal and clear buttons (Microsoft Edge / WebKit) */
    .password-input-wrap input::-ms-reveal,
    .password-input-wrap input::-ms-clear,
    input::-ms-reveal,
    input::-ms-clear {
      display: none !important;
      width: 0 !important;
      height: 0 !important;
      pointer-events: none !important;
    }
    .password-toggle-btn {
      position: absolute;
      right: 12px;
      background: none;
      border: none;
      color: var(--muted);
      cursor: pointer;
      font-size: 15px;
      padding: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: color 0.2s;
      z-index: 2;
    }
    .password-toggle-btn:hover {
      color: var(--cyan);
    }
  </style>
</head>
<body>

<div class="app-layout">
  <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

  <main class="app-main">
    <?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>

    <div class="dashboard-content">

      <!-- Success Alerts -->
      <?php if (!empty($profile_success)): ?>
        <div class="alert alert-success" style="margin-bottom: 20px;">
          <i class="fa-solid fa-circle-check" style="font-size: 18px;"></i>
          <div><?= e($profile_success) ?></div>
        </div>
      <?php endif; ?>

      <?php if (!empty($pass_success)): ?>
        <div class="alert alert-success" style="margin-bottom: 20px;">
          <i class="fa-solid fa-circle-check" style="font-size: 18px;"></i>
          <div><?= e($pass_success) ?></div>
        </div>
      <?php endif; ?>

      <!-- =========================================================
           1. PROFILE OVERVIEW SECTION (DEFAULT VIEW)
           ========================================================= -->
      <div id="section-overview" style="display: <?= ($view_mode === 'overview') ? 'block' : 'none' ?>;">
        
        <!-- Hero Header: Avatar + Info + ONLY 2 OPTIONS UNDER AVATAR -->
        <div class="profile-hero-card">
          <div class="hero-main-layout">
            <div class="hero-avatar">
              <?= strtoupper(substr($admin['name'] ?? $u['name'] ?? 'A', 0, 1)) ?>
            </div>
            
            <div class="hero-info">
              <h2><?= e($admin['name'] ?? $u['name'] ?? 'Admin Officer') ?></h2>
              
              <div class="hero-badges">
                <span class="badge-status-pill badge-status-active">
                  <i class="fa-solid fa-circle-check"></i> Active
                </span>
                <span class="badge-status-pill badge-role-pill">
                  <i class="fa-solid fa-shield-halved"></i> Administrator
                </span>
                <span class="badge-status-pill" style="background: rgba(0, 242, 254, 0.08); color: var(--text); border: 1px solid rgba(0, 242, 254, 0.25);">
                  <i class="fa-solid fa-id-badge" style="color: var(--cyan);"></i> Admin ID: <strong style="color: var(--cyan);">#<?= (int)($admin['id'] ?? $u['id']) ?></strong>
                </span>
              </div>

              <!-- SIRF YAHI 2 OPTIONS CHAHIYE: Edit Profile & Change Password -->
              <div class="under-avatar-actions">
                <button type="button" class="btn btn-primary" onclick="showSection('edit')">
                  <i class="fa-solid fa-user-pen"></i> Edit Profile
                </button>
                <button type="button" class="btn btn-outline" onclick="showSection('password')">
                  <i class="fa-solid fa-key"></i> Change Password
                </button>
              </div>

            </div>
          </div>
        </div>

        <!-- Details Cards: Clean data view without extra clutter -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; align-items: start;">
          
          <!-- Card 1: Administrator Information -->
          <div class="card">
            <h3 class="card-title" style="margin-bottom: 20px;">
              <i class="fa-solid fa-user-shield" style="color: var(--cyan); margin-right: 8px;"></i> Administrator Details
            </h3>

            <div class="info-grid">
              <div class="info-tile">
                <span class="tile-label"><i class="fa-solid fa-id-badge"></i> Admin ID</span>
                <span class="tile-value" style="color: var(--cyan);">#<?= (int)($admin['id'] ?? $u['id']) ?></span>
              </div>
              <div class="info-tile">
                <span class="tile-label"><i class="fa-solid fa-signature"></i> Full Name</span>
                <span class="tile-value"><?= e($admin['name'] ?? $u['name'] ?? 'Admin Officer') ?></span>
              </div>
              <div class="info-tile">
                <span class="tile-label"><i class="fa-solid fa-envelope"></i> Email Address</span>
                <span class="tile-value"><?= e($admin['email'] ?? $u['email']) ?></span>
              </div>
              <div class="info-tile">
                <span class="tile-label"><i class="fa-solid fa-shield-halved"></i> Access Role</span>
                <span class="tile-value" style="color: #c084fc; text-transform: uppercase;"><?= e($admin['role'] ?? 'admin') ?> (Super Admin)</span>
              </div>
              <div class="info-tile">
                <span class="tile-label"><i class="fa-solid fa-circle-dot"></i> Account Status</span>
                <span class="tile-value" style="color: #10b981;"><?= ucfirst(e($admin['status'] ?? 'active')) ?></span>
              </div>
              <div class="info-tile">
                <span class="tile-label"><i class="fa-solid fa-calendar-check"></i> Registered Since</span>
                <span class="tile-value"><?= !empty($admin['created_at']) ? format_date($admin['created_at']) : 'System Initialization' ?></span>
              </div>
            </div>
          </div>

          <!-- Card 2: System Permissions & Management -->
          <div class="card">
            <h3 class="card-title" style="margin-bottom: 20px;">
              <i class="fa-solid fa-sliders" style="color: var(--cyan); margin-right: 8px;"></i> Privileges &amp; Controls
            </h3>

            <div class="info-grid">
              <div class="info-tile">
                <span class="tile-label"><i class="fa-solid fa-lock-open"></i> Privilege Level</span>
                <span class="tile-value" style="color: var(--cyan);">Full Master Control</span>
              </div>
              <div class="info-tile">
                <span class="tile-label"><i class="fa-solid fa-users"></i> Member Management</span>
                <span class="tile-value" style="color: #10b981;">Read / Write / Delete</span>
              </div>
              <div class="info-tile">
                <span class="tile-label"><i class="fa-solid fa-credit-card"></i> Billing &amp; Finance</span>
                <span class="tile-value" style="color: #10b981;">Full Access</span>
              </div>
              <div class="info-tile">
                <span class="tile-label"><i class="fa-solid fa-server"></i> System Security</span>
                <span class="tile-value">Bcrypt Protected</span>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- =========================================================
           2. EDIT PROFILE SECTION
           ========================================================= -->
      <div id="section-edit" style="display: <?= ($view_mode === 'edit') ? 'block' : 'none' ?>;">
        <div class="card" style="max-width: 720px; margin: 0 auto;">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; border-bottom: 1px solid var(--line); padding-bottom: 16px;">
            <div>
              <h3 class="card-title" style="margin-bottom: 4px;">
                <i class="fa-solid fa-user-pen" style="color: var(--cyan); margin-right: 8px;"></i> Edit Admin Profile
              </h3>
              <p style="color: var(--muted); font-size: 13.5px; margin-bottom: 0;">
                Update your administrative display name and details.
              </p>
            </div>
            <button type="button" class="btn btn-outline btn-sm" onclick="showSection('overview')">
              <i class="fa-solid fa-arrow-left"></i> Back to Profile
            </button>
          </div>

          <?php if (!empty($profile_error)): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px;">
              <i class="fa-solid fa-triangle-exclamation" style="font-size: 18px;"></i>
              <div><?= e($profile_error) ?></div>
            </div>
          <?php endif; ?>

          <form method="POST" action="profile.php?tab=edit">
            <div style="display: grid; grid-template-columns: 140px 1fr; gap: 16px; margin-bottom: 18px;">
              <div>
                <label class="form-label">Admin ID</label>
                <input type="text" class="form-control" value="#<?= (int)($admin['id'] ?? $u['id']) ?>" readonly style="background: rgba(255, 255, 255, 0.03); cursor: not-allowed; color: var(--cyan); font-weight: 700;">
              </div>
              <div>
                <label class="form-label">Full Name <span style="color: var(--bad);">*</span></label>
                <input type="text" name="full_name" class="form-control" value="<?= e($admin['name'] ?? $u['name']) ?>" required placeholder="Enter your full name">
              </div>
            </div>

            <div class="form-group" style="margin-bottom: 18px;">
              <label class="form-label">Email Address (Login ID)</label>
              <input type="email" class="form-control" value="<?= e($admin['email'] ?? $u['email']) ?>" readonly style="background: rgba(255, 255, 255, 0.03); cursor: not-allowed; color: var(--muted);" title="Primary admin email cannot be changed from profile">
              <small style="color: var(--muted); font-size: 11.5px; margin-top: 4px; display: block;">Registered login email ID is locked for system security.</small>
            </div>

            <div class="form-group" style="margin-bottom: 18px;">
              <label class="form-label">Role</label>
              <input type="text" class="form-control" value="Administrator" readonly style="background: rgba(255, 255, 255, 0.03); cursor: not-allowed; color: #c084fc; font-weight: 600;">
            </div>

            <div style="display: flex; align-items: center; gap: 12px; margin-top: 26px;">
              <button type="submit" name="update_profile" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Save Profile Details
              </button>
              <button type="button" class="btn btn-outline" onclick="showSection('overview')">
                <i class="fa-solid fa-arrow-left"></i> Cancel
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- =========================================================
           3. CHANGE PASSWORD SECTION
           ========================================================= -->
      <div id="section-password" style="display: <?= ($view_mode === 'password') ? 'block' : 'none' ?>;">
        <div class="card" style="max-width: 620px; margin: 0 auto;">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; border-bottom: 1px solid var(--line); padding-bottom: 16px;">
            <div>
              <h3 class="card-title" style="margin-bottom: 4px;">
                <i class="fa-solid fa-key" style="color: var(--cyan); margin-right: 8px;"></i> Change Password
              </h3>
              <p style="color: var(--muted); font-size: 13.5px; margin-bottom: 0;">
                Update your login password to keep your administrator account secure.
              </p>
            </div>
            <button type="button" class="btn btn-outline btn-sm" onclick="showSection('overview')">
              <i class="fa-solid fa-arrow-left"></i> Back to Profile
            </button>
          </div>

          <?php if (!empty($pass_error)): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px;">
              <i class="fa-solid fa-triangle-exclamation" style="font-size: 18px;"></i>
              <div><?= e($pass_error) ?></div>
            </div>
          <?php endif; ?>

          <form method="POST" action="profile.php?tab=password">
            <div class="form-group" style="margin-bottom: 18px;">
              <label class="form-label">Current Password <span style="color: var(--bad);">*</span></label>
              <div class="password-input-wrap">
                <input type="password" name="current_password" id="curr_pass" class="form-control" required placeholder="Enter current password">
                <button type="button" class="password-toggle-btn" onclick="togglePassVisibility('curr_pass', this)" title="Show/Hide Password">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </div>
            </div>

            <div class="form-group" style="margin-bottom: 18px;">
              <label class="form-label">New Password <span style="color: var(--bad);">*</span></label>
              <div class="password-input-wrap">
                <input type="password" name="new_password" id="new_pass" class="form-control" required minlength="6" placeholder="Enter new password (min 6 characters)">
                <button type="button" class="password-toggle-btn" onclick="togglePassVisibility('new_pass', this)" title="Show/Hide Password">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </div>
              <!-- Real-time Password Strength: 1 continuous line + Weak / Medium / Strong -->
              <div id="admin_pass_strength_container" style="display: none; margin-top: 8px;">
                <div style="height: 5px; width: 100%; background: rgba(128,128,128,0.22); border-radius: 999px; overflow: hidden; margin-bottom: 5px;">
                  <div id="admin_pass_strength_bar" style="height: 100%; width: 0%; border-radius: 999px; transition: width 0.35s ease, background 0.35s ease;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; font-weight: 600; padding: 0 2px;">
                  <span id="admin_lbl_weak" style="color: var(--muted); transition: all 0.25s ease;">Weak</span>
                  <span id="admin_lbl_medium" style="color: var(--muted); transition: all 0.25s ease;">Medium</span>
                  <span id="admin_lbl_strong" style="color: var(--muted); transition: all 0.25s ease;">Strong</span>
                </div>
              </div>
            </div>

            <div class="form-group" style="margin-bottom: 18px;">
              <label class="form-label">Confirm New Password <span style="color: var(--bad);">*</span></label>
              <div class="password-input-wrap">
                <input type="password" name="confirm_password" id="conf_pass" class="form-control" required minlength="6" placeholder="Re-enter new password to confirm">
                <button type="button" class="password-toggle-btn" onclick="togglePassVisibility('conf_pass', this)" title="Show/Hide Password">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </div>
              <small id="admin_conf_hint" style="display: none; font-size: 11.5px; margin-top: 6px; font-weight: 600;"></small>
            </div>

            <div style="display: flex; align-items: center; gap: 12px; margin-top: 26px;">
              <button type="submit" name="change_password" class="btn btn-primary">
                <i class="fa-solid fa-shield-halved"></i> Update Password
              </button>
              <button type="button" class="btn btn-outline" onclick="showSection('overview')">
                <i class="fa-solid fa-arrow-left"></i> Cancel
              </button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </main>
</div>

<script src="../assets/js/user.js?v=2.0"></script>
<script>
function showSection(sectionName) {
  const overview = document.getElementById('section-overview');
  const edit = document.getElementById('section-edit');
  const password = document.getElementById('section-password');

  if (overview) overview.style.display = (sectionName === 'overview') ? 'block' : 'none';
  if (edit) edit.style.display = (sectionName === 'edit') ? 'block' : 'none';
  if (password) password.style.display = (sectionName === 'password') ? 'block' : 'none';

  // Update browser URL query string without reloading page
  const newUrl = new URL(window.location.href);
  if (sectionName === 'overview') {
    newUrl.searchParams.delete('tab');
  } else {
    newUrl.searchParams.set('tab', sectionName);
  }
  window.history.replaceState({}, '', newUrl);

  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function togglePassVisibility(inputId, btn) {
  const input = document.getElementById(inputId);
  if (!input) return;
  const icon = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    if (icon) {
      icon.classList.remove('fa-eye');
      icon.classList.add('fa-eye-slash');
    }
  } else {
    input.type = 'password';
    if (icon) {
      icon.classList.remove('fa-eye-slash');
      icon.classList.add('fa-eye');
    }
  }
}

// Live Validation: Password Strength & Match in Admin Profile
(function() {
  const passInput     = document.getElementById('new_pass');
  const passContainer = document.getElementById('admin_pass_strength_container');
  const strBar        = document.getElementById('admin_pass_strength_bar');
  const lblWeak       = document.getElementById('admin_lbl_weak');
  const lblMedium     = document.getElementById('admin_lbl_medium');
  const lblStrong     = document.getElementById('admin_lbl_strong');
  const confInput     = document.getElementById('conf_pass');
  const confHint      = document.getElementById('admin_conf_hint');

  function evaluateStrength(pwd) {
    if (!pwd) return { level: 'none' };
    const hasLower = /[a-z]/.test(pwd);
    const hasUpper = /[A-Z]/.test(pwd);
    const hasNum   = /[0-9]/.test(pwd);
    const hasSpec  = /[^a-zA-Z0-9]/.test(pwd);
    const variety  = (hasLower ? 1 : 0) + (hasUpper ? 1 : 0) + (hasNum ? 1 : 0) + (hasSpec ? 1 : 0);

    if (pwd.length < 6 || variety <= 1) return { level: 'weak' };
    if (pwd.length >= 8 && variety >= 3) return { level: 'strong' };
    return { level: 'medium' };
  }

  function updateStrength() {
    if (!passInput || !passContainer) return;
    const pwd = passInput.value;
    if (!pwd) {
      passContainer.style.display = 'none';
      checkMatch();
      return;
    }
    passContainer.style.display = 'block';
    const res = evaluateStrength(pwd);

    const mutedColor = 'var(--muted, #94a3b8)';
    lblWeak.style.color        = mutedColor;
    lblWeak.style.fontWeight   = '500';
    lblMedium.style.color      = mutedColor;
    lblMedium.style.fontWeight = '500';
    lblStrong.style.color      = mutedColor;
    lblStrong.style.fontWeight = '500';

    if (res.level === 'weak') {
      strBar.style.width       = '33%';
      strBar.style.background  = '#ef4444';
      lblWeak.style.color      = '#ef4444';
      lblWeak.style.fontWeight = '700';
    } else if (res.level === 'medium') {
      strBar.style.width       = '66%';
      strBar.style.background  = '#f59e0b';
      lblWeak.style.color      = '#f59e0b';
      lblMedium.style.color    = '#f59e0b';
      lblMedium.style.fontWeight = '700';
    } else if (res.level === 'strong') {
      strBar.style.width       = '100%';
      strBar.style.background  = '#10b981';
      lblWeak.style.color      = '#10b981';
      lblMedium.style.color    = '#10b981';
      lblStrong.style.color    = '#10b981';
      lblStrong.style.fontWeight = '700';
    }
    checkMatch();
  }

  function checkMatch() {
    if (!confInput || !confHint) return;
    const cVal = confInput.value;
    const pVal = passInput ? passInput.value : '';
    if (!cVal) {
      confHint.style.display = 'none';
      return;
    }
    confHint.style.display = 'block';
    if (cVal === pVal) {
      confHint.style.color = '#34d399';
      confHint.innerHTML = '<i class="fa-solid fa-circle-check"></i> Passwords match';
    } else {
      confHint.style.color = '#ef4444';
      confHint.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Passwords do not match';
    }
  }

  if (passInput) {
    passInput.addEventListener('input', updateStrength);
    passInput.addEventListener('blur', updateStrength);
  }
  if (confInput) {
    confInput.addEventListener('input', checkMatch);
    confInput.addEventListener('blur', checkMatch);
  }
})();
</script>
</body>
</html>
