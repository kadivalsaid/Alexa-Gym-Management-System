<?php
/**
 * Alexa Gym — Member Authentication: 4-Digit OTP Forgot Password
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../includes/functions.php';

$success_msg   = '';
$error_msg     = '';
$step          = 1;
$email         = trim($_GET['email'] ?? $_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'send_otp';

    // -------------------------------------------------------------
    // STEP 1: SEND 4-DIGIT OTP TO REGISTERED MEMBER EMAIL
    // -------------------------------------------------------------
    if ($action === 'send_otp') {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_msg = 'Kripya valid email address enter karein.';
        } else {
            // Strictly check that user is an active registered MEMBER
            $stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $error_msg = 'Yeh email address kisi registered Gym Member account se match nahi karta. Kripya check karke sahi email dalein.';
            } elseif ($user['role'] !== 'member') {
                $error_msg = 'Yeh account Admin account hai. Admin password reset ke liye Admin Login portal use karein.';
            } else {
                // Generate 4-digit numeric OTP
                $otp = (string)random_int(1000, 9999);

                // Clear previous reset requests for this email
                $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

                // Save 4-Digit OTP with 10-minute validity
                $ins = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW())");
                $ins->execute([$email, $otp]);

                // In-app notification for member
                try {
                    $notif_title = 'Password Reset OTP Requested 🔐';
                    $notif_msg   = "A 4-digit verification code was requested for your account. If this wasn't you, please secure your account immediately.";
                    $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'warning', 0, NOW())")->execute([$user['id'], $notif_title, $notif_msg]);
                } catch (Exception $ex) {}

                // Prepare and send OTP email
                $subject   = 'Your 4-Digit Reset OTP: ' . $otp . ' — Alexa Gym';
                $user_name = e($user['name'] ?? 'Member');
                $body = "
                    <div style=\"font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; max-width: 540px; margin: 0 auto; background: #0b0d14; border: 1px solid #1e293b; border-radius: 14px; overflow: hidden; color: #f8fafc; box-shadow: 0 10px 30px rgba(0,0,0,0.5);\">
                        <div style=\"background: linear-gradient(135deg, #0f172a, #1e1b4b); padding: 28px 24px; text-align: center; border-bottom: 2px solid #00f2fe;\">
                            <h1 style=\"color: #00f2fe; margin: 0; font-size: 24px; font-weight: 900; letter-spacing: 2px;\">ALEXA GYM</h1>
                            <p style=\"color: #94a3b8; margin: 6px 0 0 0; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;\">Member Password Recovery</p>
                        </div>
                        <div style=\"padding: 30px 24px; text-align: center;\">
                            <h2 style=\"color: #ffffff; font-size: 20px; margin: 0 0 12px 0;\">Your 4-Digit Verification Code</h2>
                            <p style=\"color: #cbd5e1; font-size: 14.5px; line-height: 1.6; margin: 0 0 18px 0;\">
                                Hello <strong style=\"color: #00f2fe;\">{$user_name}</strong>,<br>
                                Please enter this 4-digit verification code to reset your Alexa Gym account password:
                            </p>
                            
                            <div style=\"background: rgba(0, 242, 254, 0.08); border: 2px dashed #00f2fe; border-radius: 12px; padding: 18px 16px; margin: 20px auto; max-width: 260px;\">
                                <span style=\"font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; display: block; margin-bottom: 6px;\">4-Digit OTP</span>
                                <div style=\"font-size: 38px; font-weight: 900; letter-spacing: 12px; color: #00f2fe; font-family: monospace; padding-left: 12px;\">
                                    {$otp}
                                </div>
                            </div>

                            <p style=\"color: #94a3b8; font-size: 13px; line-height: 1.5; margin: 0;\">
                                ⏳ This code is valid for the next <strong style=\"color: #f59e0b;\">10 minutes</strong>.
                            </p>
                            <p style=\"color: #64748b; font-size: 12px; margin-top: 16px;\">
                                Sent from: <strong>" . e(get_admin_sender_email()) . "</strong><br>
                                If you did not request this, you can safely ignore this email.
                            </p>
                        </div>
                    </div>
                ";

                send_app_mail($email, $subject, $body);

                $step = 2;
                $success_msg = '4-Digit OTP aapki registered email par bhej diya gaya hai. Kripya niche code daal kar naya password banayein.';
            }
        }
    }

    // -------------------------------------------------------------
    // STEP 2: VERIFY 4-DIGIT OTP AND UPDATE PASSWORD
    // -------------------------------------------------------------
    elseif ($action === 'verify_otp') {
        $step = 2;
        $otp_input  = trim($_POST['otp'] ?? '');
        $password   = $_POST['password'] ?? '';
        $confirm_p  = $_POST['confirm_password'] ?? '';

        if (empty($email) || empty($otp_input)) {
            $error_msg = 'Kripya 4-digit OTP enter karein.';
        } elseif (strlen($password) < 6) {
            $error_msg = 'Naya password kam se kam 6 characters ka hona chahiye.';
        } elseif ($password !== $confirm_p) {
            $error_msg = 'Dono passwords match nahi ho rahe. Kripya dubara check karein.';
        } else {
            // Verify OTP
            $chk = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
            $chk->execute([$email, $otp_input]);
            $reset_req = $chk->fetch();

            if (!$reset_req) {
                $error_msg = 'Galat ya expired 4-digit OTP. Kripya sahi OTP dalein ya naya OTP mangwayein.';
            } else {
                // Update password
                $up = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                $up->execute([$password, $email]);

                // Delete used OTP
                $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

                // In-app success notification
                try {
                    $u_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                    $u_stmt->execute([$email]);
                    $uid = $u_stmt->fetchColumn();
                    if ($uid) {
                        $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, 'Password Reset Successful ✅', 'Your account password has been updated successfully. You can now log in using your new password.', 'success', 0, NOW())")->execute([$uid]);
                    }
                } catch (Exception $e) {}

                header('Location: login.php?reset_done=1');
                exit;
            }
        }
    }
}

// Check if user came with step 2 query param
if (isset($_GET['step']) && $_GET['step'] == '2' && !empty($email)) {
    $step = 2;
}

$page_title = 'Forgot Password — Alexa Gym';
$extra_css  = 'assets/css/auth.css?v=2.9';
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
      <div class="auth-banner-tag"><i class="fa-solid fa-shield-halved"></i> 4-Digit OTP Recovery</div>
      <h2 class="auth-banner-title">
        Password <span>Recovery</span>
      </h2>
      <p class="auth-banner-desc">
        Quick 4-digit code sent directly to your registered email inbox. Reset your credentials safely in 30 seconds.
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
        <a href="login.php" style="color: var(--cyan); font-size: 13.5px; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 16px;">
          <i class="fa-solid fa-arrow-left"></i> Back to Login
        </a>
        <h1 class="auth-title">Forgot Password</h1>
        <p class="auth-subtitle">
          <?= ($step === 1) ? 'Enter your registered email to receive a 4-digit OTP.' : 'Enter the 4-digit OTP sent to: <strong style="color: var(--cyan);">' . e($email) . '</strong>' ?>
        </p>
      </div>

      <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success" style="margin-bottom: 20px;">
          <i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i>
          <?= e($success_msg) ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger" style="margin-bottom: 20px;">
          <i class="fa-solid fa-triangle-exclamation" style="margin-right: 8px;"></i>
          <?= e($error_msg) ?>
        </div>
      <?php endif; ?>

      <?php if ($step === 1): ?>
        <!-- ================= STEP 1: ENTER EMAIL ================= -->
        <form method="POST" action="forgot-password.php">
          <input type="hidden" name="action" value="send_otp">
          <div class="auth-form-box">
            <div class="auth-input-group">
              <label class="auth-label" for="email">Registered Member Email</label>
              <div class="auth-input-wrap">
                <i class="fa-solid fa-envelope auth-input-icon"></i>
                <input type="email" id="email" name="email" class="auth-input" placeholder="member@gmail.com" required value="<?= e($email) ?>">
              </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding: 12px; font-size: 14.5px;">
              <i class="fa-solid fa-paper-plane"></i> Send 4-Digit OTP
            </button>

            <div class="auth-footer-prompt">
              Remembered your password?
              <a href="login.php" class="auth-link">Login</a>
            </div>
          </div>
        </form>

      <?php else: ?>
        <!-- ================= STEP 2: ENTER 4-DIGIT OTP & NEW PASSWORD ================= -->
        <form method="POST" action="forgot-password.php">
          <input type="hidden" name="action" value="verify_otp">
          <input type="hidden" name="email" value="<?= e($email) ?>">

          <div class="auth-form-box">
            <div class="auth-input-group">
              <label class="auth-label" style="text-align: center; display: block;">Enter 4-Digit OTP Code</label>
              <div class="auth-input-wrap">
                <i class="fa-solid fa-key auth-input-icon"></i>
                <input type="text" name="otp" class="auth-input" placeholder="••••" required maxlength="4" pattern="[0-9]{4}" inputmode="numeric" style="text-align: center; font-size: 22px; font-weight: 800; letter-spacing: 12px; font-family: monospace;" autofocus>
              </div>
              <small style="display: block; text-align: center; color: var(--text-muted); font-size: 12px; margin-top: 5px;">
                Code valid for 10 minutes &bull; <a href="forgot-password.php" style="color: var(--cyan); text-decoration: underline;">Change Email</a>
              </small>
            </div>

            <div class="auth-input-group">
              <label class="auth-label">New Password (Min 6 chars)</label>
              <div class="auth-input-wrap has-toggle">
                <i class="fa-solid fa-lock auth-input-icon"></i>
                <input type="password" id="otp_new_pass" name="password" class="auth-input" placeholder="••••••••" required minlength="6">
                <button type="button" class="auth-pass-toggle" onclick="toggleAuthPass('otp_new_pass', this)" title="Show/Hide Password" aria-label="Toggle Password Visibility">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </div>
              <!-- Real-time Password Strength: 1 continuous line + Weak / Medium / Strong -->
              <div id="otp_pass_strength_container" style="display: none; margin-top: 8px;">
                <div style="height: 5px; width: 100%; background: rgba(128,128,128,0.22); border-radius: 999px; overflow: hidden; margin-bottom: 5px;">
                  <div id="otp_pass_strength_bar" style="height: 100%; width: 0%; border-radius: 999px; transition: width 0.35s ease, background 0.35s ease;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; font-weight: 600; padding: 0 2px;">
                  <span id="otp_lbl_weak" style="color: var(--text-muted); transition: all 0.25s ease;">Weak</span>
                  <span id="otp_lbl_medium" style="color: var(--text-muted); transition: all 0.25s ease;">Medium</span>
                  <span id="otp_lbl_strong" style="color: var(--text-muted); transition: all 0.25s ease;">Strong</span>
                </div>
              </div>
            </div>

            <div class="auth-input-group">
              <label class="auth-label">Confirm New Password</label>
              <div class="auth-input-wrap has-toggle">
                <i class="fa-solid fa-lock auth-input-icon"></i>
                <input type="password" id="otp_conf_pass" name="confirm_password" class="auth-input" placeholder="••••••••" required minlength="6">
                <button type="button" class="auth-pass-toggle" onclick="toggleAuthPass('otp_conf_pass', this)" title="Show/Hide Password" aria-label="Toggle Password Visibility">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </div>
              <small id="otp_conf_hint" style="display: none; font-size: 11.5px; margin-top: 6px; font-weight: 600;"></small>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding: 12px; font-size: 14.5px;">
              <i class="fa-solid fa-check"></i> Verify OTP &amp; Update Password
            </button>

            <div style="text-align: center; margin-top: 14px;">
              <a href="forgot-password.php?email=<?= urlencode($email) ?>" style="color: var(--text-muted); font-size: 13px; text-decoration: none;">
                <i class="fa-solid fa-rotate-right" style="margin-right: 4px;"></i> Didn't get the code? <span style="color: var(--cyan);">Resend OTP</span>
              </a>
            </div>
          </div>
        </form>
      <?php endif; ?>

    </div>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
(function() {
  const passInput     = document.getElementById('otp_new_pass');
  const passContainer = document.getElementById('otp_pass_strength_container');
  const strBar        = document.getElementById('otp_pass_strength_bar');
  const lblWeak       = document.getElementById('otp_lbl_weak');
  const lblMedium     = document.getElementById('otp_lbl_medium');
  const lblStrong     = document.getElementById('otp_lbl_strong');
  const confInput     = document.getElementById('otp_conf_pass');
  const confHint      = document.getElementById('otp_conf_hint');

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
