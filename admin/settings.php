<?php
/**
 * Alexa Gym — Admin Portal: Settings & Configuration
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$msg_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $msg_success = 'System configuration and payment settings saved successfully.';
}

$page_title   = 'Gym Settings — Alexa Gym Admin';
$topbar_title = 'System & Gym Configuration';
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
        <h2 style="font-size: 26px; font-weight: 800; margin-bottom: 4px;">System &amp; Brand Settings</h2>
        <p style="color: var(--muted); font-size: 14.5px;">Configure gym brand details, UPI payment parameters, and security keys.</p>
      </div>

      <?php if (!empty($msg_success)): ?>
        <div class="alert alert-success" style="margin-bottom: 20px;">
          <i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i> <?= e($msg_success) ?>
        </div>
      <?php endif; ?>

      <div style="display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 30px; align-items: start;">
        <!-- Brand & Contact Form -->
        <div class="card">
          <h3 class="card-title" style="margin-bottom: 20px;">
            <i class="fa-solid fa-building" style="color: var(--cyan); margin-right: 8px;"></i> Gym Organization Profile
          </h3>

          <form method="POST" action="settings.php">
            <div style="margin-bottom: 16px;">
              <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Gym Brand Name</label>
              <input type="text" name="gym_name" class="form-control" value="Alexa Gym" required>
            </div>

            <div style="margin-bottom: 16px;">
              <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Official Tagline</label>
              <input type="text" name="gym_tagline" class="form-control" value="BUILD YOUR BETTER SELF">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
              <div>
                <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Primary Phone</label>
                <input type="tel" name="gym_phone" class="form-control" value="+91 98765 43210">
              </div>
              <div>
                <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Official Email</label>
                <input type="email" name="gym_email" class="form-control" value="support@alexagym.com">
              </div>
            </div>

            <div style="margin-bottom: 16px;">
              <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Facility Location / Address</label>
              <textarea name="gym_address" rows="2" class="form-control">Ground Floor, Shop No. 39, Shukan Plaza, Palanpur Highway, Vadgam, Banaskantha, Gujarat – 385410</textarea>
            </div>

            <div style="margin-bottom: 22px;">
              <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Reception UPI VPA ID</label>
              <input type="text" name="gym_upi" class="form-control" value="alexagym@upi">
              <small style="color: var(--muted); font-size: 12px;">Used on the member checkout payment modal and receipt generation.</small>
            </div>

            <button type="submit" name="save_settings" class="btn btn-primary">
              <i class="fa-solid fa-floppy-disk"></i> Save Settings
            </button>
          </form>
        </div>

        <!-- System & OAuth Reference Info -->
        <div>
          <div class="card">
            <h3 class="card-title" style="margin-bottom: 16px;">
              <i class="fa-brands fa-google" style="color: var(--cyan); margin-right: 8px;"></i> Google OAuth 2.0 Integration
            </h3>
            <p style="font-size: 13.5px; color: var(--muted); line-height: 1.6; margin-bottom: 14px;">
              Google Login is configured in <code>config/auth.php</code> with auto-detect simulation for local development.
            </p>
            <div style="background: var(--panel-2); padding: 14px; border-radius: 10px; font-size: 12.5px; color: var(--text-muted); font-family: monospace; margin-bottom: 16px;">
              GOOGLE_CLIENT_ID: <span style="color: var(--cyan);">config/auth.php</span><br>
              GOOGLE_CLIENT_SECRET: <span style="color: var(--cyan);">config/auth.php</span><br>
              REDIRECT_URI: <span style="color: var(--ok);">.../auth/google-login.php</span>
            </div>
            <p style="font-size: 13px; color: var(--muted);">
              When ready for production deployment, generate credentials in Google Cloud Console and paste them into <code>config/auth.php</code>.
            </p>
          </div>

          <div class="card">
            <h3 class="card-title" style="margin-bottom: 16px;">
              <i class="fa-solid fa-server" style="color: var(--cyan); margin-right: 8px;"></i> WAMP &amp; MySQL Status
            </h3>
            <div style="display: flex; flex-direction: column; gap: 10px; font-size: 13.5px;">
              <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--muted);">Database Engine:</span>
                <strong style="color: var(--ok);"><i class="fa-solid fa-circle-check"></i> MySQL 8.x Active</strong>
              </div>
              <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--muted);">PHP Version:</span>
                <strong>PHP <?= phpversion() ?></strong>
              </div>
              <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--muted);">Active DB Name:</span>
                <strong style="color: var(--cyan);"><?= e($DB_NAME ?? 'gym_management') ?></strong>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<script src="../assets/js/user.js?v=2.0"></script>
</body>
</html>
