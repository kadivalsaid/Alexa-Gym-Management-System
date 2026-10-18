<?php
/**
 * Alexa Gym — Authentication: Reset Password
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$raw_token = trim($_GET['token'] ?? '');
$email     = trim($_GET['email'] ?? '');
$error_msg = '';
$invalid_token = false;

if (empty($raw_token) || empty($email)) {
    $invalid_token = true;
    $error_msg = 'Invalid password reset request. Missing token or email parameter.';
} else {
    // Verify token
    $token_hash = hash('sha256', $raw_token);
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$email, $token_hash]);
    $reset_req = $stmt->fetch();

    if (!$reset_req) {
        $invalid_token = true;
        $error_msg = 'This password reset link has expired or has already been used. Please request a new one.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$invalid_token) {
    $password  = $_POST['password'] ?? '';
    $confirm_p = $_POST['confirm_password'] ?? '';

    if (empty($password) || strlen($password) < 6) {
        $error_msg = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_p) {
        $error_msg = 'Passwords do not match. Please re-enter.';
    } else {
        // Save plain text password
        $up_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $up_stmt->execute([$password, $email]);

        // Add notification for user
        try {
            $uid_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $uid_stmt->execute([$email]);
            $user_id = $uid_stmt->fetchColumn();
            if ($user_id) {
                $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, 'Password Updated Successfully ✅', 'Your account password has been updated. If you did not make this change, contact gym management immediately.', 'success', 0, NOW())")->execute([$user_id]);
            }
        } catch (Exception $e) {}

        // Invalidate token
        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

        header('Location: login.php?reset_done=1');
        exit;
    }
}

$page_title = 'Reset Password — Alexa Gym';
$extra_css  = 'assets/css/auth.css?v=2.8';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrapper">
  <!-- Left Side Banner -->
  <div class="auth-banner-side">
    <div class="auth-banner-content auth-banner-brand">
      <a href="../index.php" style="display: flex; align-items: center; gap: 12px;">
        <div class="brand-logo-badge"><i class="fa-solid fa-dumbbell"></i></div>
        <div class="brand-info">
          <span class="brand-name" style="font-size: 22px;">Alexa Gym</span>
          <span class="brand-sub">Build Your Better Self</span>
        </div>
      </a>
    </div>

    <div class="auth-banner-content auth-banner-middle">
      <div class="auth-banner-tag"><i class="fa-solid fa-shield-check"></i> Security Confirmation</div>
      <h2 class="auth-banner-title">
        Set Your <span>New Password</span>
      </h2>
      <p class="auth-banner-desc">
        Choose a strong, unique password to secure your gym profile, workouts, and payment history.
      </p>
    </div>

    <div class="auth-banner-content auth-banner-footer">
      &copy; <?= date('Y') ?> Alexa Gym &bull; Member Account Security
    </div>
  </div>

  <!-- Right Side Form -->
  <div class="auth-form-side">
    <!-- Theme Toggle -->
    <button type="button" class="auth-theme-toggle" onclick="toggleTheme()" title="Toggle Dark / Light Theme" aria-label="Toggle Theme">
      <i class="fa-regular fa-moon"></i>
    </button>
    <div class="auth-card-inner">
      <div class="auth-header">
        <h1 class="auth-title">Create New Password</h1>
        <p class="auth-subtitle"><?= $invalid_token ? 'Security Verification Notice' : 'Resetting credentials for: <strong style="color: var(--cyan);">' . e($email) . '</strong>' ?></p>
      </div>

      <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger" style="margin-bottom: 20px;">
          <i class="fa-solid fa-triangle-exclamation" style="margin-right: 8px;"></i>
          <?= e($error_msg) ?>
        </div>
      <?php endif; ?>

      <?php if ($invalid_token): ?>
        <div style="text-align: center; margin-top: 10px;">
          <p style="font-size: 13.5px; color: var(--text-muted); margin-bottom: 20px; line-height: 1.6;">
            Password reset links expire after 1 hour for security purposes. You can request a fresh reset link right away.
          </p>
          <a href="forgot-password.php" class="btn btn-primary btn-block" style="padding: 12px; font-size: 14.5px;">
            <i class="fa-solid fa-arrow-rotate-left"></i> Request New Reset Link
          </a>
        </div>
      <?php else: ?>
        <form method="POST" action="reset-password.php?token=<?= urlencode($raw_token) ?>&email=<?= urlencode($email) ?>">
          <div class="auth-form-box">
            <div class="auth-input-group">
              <label class="auth-label">New Password (Min 6 chars)</label>
              <div class="auth-input-wrap has-toggle">
                <i class="fa-solid fa-lock auth-input-icon"></i>
                <input type="password" id="reset_pass" name="password" class="auth-input" placeholder="••••••••" required>
                <button type="button" class="auth-pass-toggle" onclick="toggleAuthPass('reset_pass', this)" title="Show/Hide Password" aria-label="Toggle Password Visibility">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </div>
              <div id="reset_pass_strength_container" style="display: none; margin-top: 8px;">
                <div style="height: 5px; width: 100%; background: rgba(128,128,128,0.22); border-radius: 999px; overflow: hidden; margin-bottom: 5px;">
                  <div id="reset_pass_strength_bar" style="height: 100%; width: 0%; border-radius: 999px; transition: width 0.35s ease, background 0.35s ease;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; font-weight: 600; padding: 0 2px;">
                  <span id="reset_lbl_weak" style="color: var(--text-muted); transition: all 0.25s ease;">Weak</span>
                  <span id="reset_lbl_medium" style="color: var(--text-muted); transition: all 0.25s ease;">Medium</span>
                  <span id="reset_lbl_strong" style="color: var(--text-muted); transition: all 0.25s ease;">Strong</span>
                </div>
              </div>
            </div>

            <div class="auth-input-group">
              <label class="auth-label">Confirm New Password</label>
              <div class="auth-input-wrap has-toggle">
                <i class="fa-solid fa-lock auth-input-icon"></i>
                <input type="password" id="reset_conf_pass" name="confirm_password" class="auth-input" placeholder="••••••••" required>
                <button type="button" class="auth-pass-toggle" onclick="toggleAuthPass('reset_conf_pass', this)" title="Show/Hide Password" aria-label="Toggle Password Visibility">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </div>
              <small id="reset_confirm_hint" style="display: none; font-size: 11.5px; margin-top: 6px; font-weight: 600;"></small>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding: 12px; font-size: 14.5px;">
              <i class="fa-solid fa-check"></i> Update Password &amp; Login
            </button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
(function() {
  const passInput      = document.getElementById('reset_pass');
  const passContainer  = document.getElementById('reset_pass_strength_container');
  const strBar         = document.getElementById('reset_pass_strength_bar');
  const lblWeak        = document.getElementById('reset_lbl_weak');
  const lblMedium      = document.getElementById('reset_lbl_medium');
  const lblStrong      = document.getElementById('reset_lbl_strong');
  const confirmInput   = document.getElementById('reset_conf_pass');
  const confirmHint    = document.getElementById('reset_confirm_hint');

  function evaluateStrength(pwd) {
    if (!pwd) return { level: 'none' };
    const hasLower = /[a-z]/.test(pwd);
    const hasUpper = /[A-Z]/.test(pwd);
    const hasNum   = /[0-9]/.test(pwd);
    const hasSpec  = /[^a-zA-Z0-9]/.test(pwd);
    const variety  = (hasLower ? 1 : 0) + (hasUpper ? 1 : 0) + (hasNum ? 1 : 0) + (hasSpec ? 1 : 0);

    if (pwd.length < 6 || variety <= 1) {
      return { level: 'weak' };
    }
    if (pwd.length >= 8 && variety >= 3) {
      return { level: 'strong' };
    }
    return { level: 'medium' };
  }

  function updateStrength() {
    if (!passInput || !passContainer) return;
    const pwd = passInput.value;
    if (!pwd) {
      passContainer.style.display = 'none';
      checkConfirm();
      return;
    }
    passContainer.style.display = 'block';
    const res = evaluateStrength(pwd);

    const mutedColor = 'var(--text-muted, #94a3b8)';
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
    checkConfirm();
  }

  function checkConfirm() {
    if (!confirmInput || !confirmHint) return;
    const cVal = confirmInput.value;
    const pVal = passInput ? passInput.value : '';
    if (!cVal) {
      confirmHint.style.display = 'none';
      return;
    }
    confirmHint.style.display = 'block';
    if (cVal === pVal) {
      confirmHint.style.color = '#34d399';
      confirmHint.innerHTML = '<i class="fa-solid fa-circle-check"></i> Passwords match';
    } else {
      confirmHint.style.color = '#ef4444';
      confirmHint.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Passwords do not match';
    }
  }

  if (passInput) {
    passInput.addEventListener('input', updateStrength);
    passInput.addEventListener('blur', updateStrength);
  }
  if (confirmInput) {
    confirmInput.addEventListener('input', checkConfirm);
    confirmInput.addEventListener('blur', checkConfirm);
  }
})();
</script>
</body>
</html>
