<?php
/**
 * Alexa Gym — Authentication: Member Registration
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    $user = get_logged_in_user();
    if (($user['role'] ?? '') === 'admin') {
        header('Location: ../admin/dashboard.php');
    } else {
        header('Location: ../user/dashboard.php');
    }
    exit;
}

$error_msg = '';

// If a plan was selected on the public website, save it in session to open on dashboard
if (isset($_GET['plan_id']) || isset($_GET['plan'])) {
    $plan_val = (int)($_GET['plan_id'] ?? $_GET['plan'] ?? 0);
    if ($plan_val > 0) {
        $_SESSION['chosen_plan_id'] = $plan_val;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name  = trim($_POST['full_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $dob        = trim($_POST['dob'] ?? '');
    $gender     = trim($_POST['gender'] ?? 'male');
    $address    = trim($_POST['address'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm_p  = $_POST['confirm_password'] ?? '';
    $terms      = isset($_POST['terms']);

    // Validation
    $email_regex = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);

    if (empty($full_name) || empty($email) || empty($phone) || empty($password)) {
        $error_msg = 'Please fill in all mandatory fields marked with an asterisk (*).';
    } elseif (strpos($email, '@') === false) {
        $error_msg = 'Invalid email: Email address must contain "@" (e.g. user@gmail.com).';
    } elseif (!preg_match('/\.[a-zA-Z]{2,}$/', $email) || !preg_match($email_regex, $email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = 'Invalid email: Email must contain a valid domain extension like .com (e.g. user@gmail.com).';
    } elseif (strlen($clean_phone) !== 10 || !preg_match('/^[6-9]\d{9}$/', $clean_phone)) {
        $error_msg = 'Invalid phone number: Please provide a valid 10-digit mobile number starting with 6, 7, 8, or 9.';
    } elseif (strlen($password) < 6) {
        $error_msg = 'Password is too weak: Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_p) {
        $error_msg = 'Passwords do not match. Please verify and re-type.';
    } elseif (!$terms) {
        $error_msg = 'You must agree to the Terms & Conditions to register.';
    } else {
        // Check for duplicate email in users table
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check_stmt->execute([$email]);
        if ($check_stmt->fetch()) {
            $error_msg = 'An account with this email address already exists. Please Login.';
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Create User (plain text password)
                $u_insert = $pdo->prepare("
                    INSERT INTO users (name, email, password, role, status, created_at) 
                    VALUES (?, ?, ?, 'member', 'active', NOW())
                ");
                $u_insert->execute([$full_name, $email, $password]);
                $new_user_id = $pdo->lastInsertId();

                // 2. Create Initial Welcome Notification
                $notif = $pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                    VALUES (?, 'Welcome to Alexa Gym! 🏋️', 'Your account has been registered successfully. Choose your membership plan from your dashboard to start training!', 'info', 0, NOW())
                ");
                $notif->execute([$new_user_id]);

                $pdo->commit();

                // Auto login new registered user and redirect directly to dashboard
                session_regenerate_id(true);
                $_SESSION['user_id']    = $new_user_id;
                $_SESSION['user_name']  = $full_name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_phone'] = '';
                $_SESSION['user_role']  = 'member';
                $_SESSION['member_id']  = null;

                header('Location: ../user/dashboard.php');
                exit;

            } catch (Exception $ex) {
                $pdo->rollBack();
                $error_msg = 'Registration could not be completed: ' . $ex->getMessage();
            }
        }
    }
}

$page_title = 'Create Account — Alexa Gym';
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
      <div class="auth-banner-tag"><i class="fa-solid fa-bolt"></i> Join The Movement</div>
      <h2 class="auth-banner-title">
        Start Your <span>Transformation</span> Today
      </h2>
      <p class="auth-banner-desc">
        Join over 500+ motivated members. Sign up to unlock access to certified trainers, personalized workout splits, nutrition tips, and seamless digital gym passes.
      </p>
    </div>

    <div class="auth-banner-content auth-banner-footer">
      &copy; <?= date('Y') ?> Alexa Gym &bull; State-of-the-Art Training Facility
    </div>
  </div>

  <!-- Right Side Form -->
  <div class="auth-form-side" style="height: 100vh; overflow-y: auto; padding: 24px 20px;">
    <!-- Theme Toggle -->
    <button type="button" class="auth-theme-toggle" onclick="toggleTheme()" title="Toggle Dark / Light Theme" aria-label="Toggle Theme">
      <i class="fa-regular fa-moon"></i>
    </button>
    <div class="auth-card-inner" style="max-width: 520px; margin: auto;">
      <div class="auth-header">
        <a href="../index.php" style="color: var(--cyan); font-size: 13.5px; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 16px;">
          <i class="fa-solid fa-arrow-left"></i> Back to Homepage
        </a>
        <h1 class="auth-title">Create Account</h1>
        <p class="auth-subtitle">Join Alexa Gym in just 60 seconds.</p>
      </div>

      <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger" style="margin-bottom: 20px;">
          <i class="fa-solid fa-triangle-exclamation" style="margin-right: 8px;"></i>
          <?= e($error_msg) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="register.php">
        <div class="auth-form-box">
          <div class="auth-input-group">
            <label class="auth-label">Full Name *</label>
            <div class="auth-input-wrap">
              <i class="fa-solid fa-user auth-input-icon"></i>
              <input type="text" name="full_name" class="auth-input" placeholder="e.g. Rahul Deshmukh" required value="<?= e($_POST['full_name'] ?? '') ?>">
            </div>
          </div>

          <div class="auth-input-grid">
            <div class="auth-input-group">
              <label class="auth-label">Email Address *</label>
              <div class="auth-input-wrap">
                <i class="fa-solid fa-envelope auth-input-icon"></i>
                <input type="email" id="reg_email" name="email" class="auth-input" placeholder="rahul@gmail.com" required 
                       pattern="^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$" 
                       title="Email must contain @ and a valid domain extension like .com (e.g. rahul@gmail.com)" 
                       value="<?= e($_POST['email'] ?? '') ?>">
              </div>
              <small id="email_hint" style="display: none; color: #fbbf24; font-size: 11.5px; margin-top: 5px; font-weight: 600;"></small>
            </div>
            <div class="auth-input-group">
              <label class="auth-label">Phone Number *</label>
              <div class="auth-input-wrap">
                <i class="fa-solid fa-phone auth-input-icon"></i>
                <input type="tel" id="reg_phone" name="phone" class="auth-input" placeholder="98XXXXXXXX" maxlength="10" pattern="[6-9][0-9]{9}" title="10-digit mobile number starting with 6, 7, 8, or 9" required value="<?= e($_POST['phone'] ?? '') ?>">
              </div>
              <small id="phone_hint" style="display: none; font-size: 11.5px; margin-top: 5px; font-weight: 600;"></small>
            </div>
          </div>

          <div class="auth-input-grid">
            <div class="auth-input-group">
              <label class="auth-label">Date of Birth</label>
              <div class="auth-input-wrap">
                <i class="fa-solid fa-calendar auth-input-icon"></i>
                <input type="date" name="dob" class="auth-input" value="<?= e($_POST['dob'] ?? '') ?>">
              </div>
            </div>
            <div class="auth-input-group">
              <label class="auth-label">Gender</label>
              <div class="auth-input-wrap">
                <i class="fa-solid fa-venus-mars auth-input-icon"></i>
                <select name="gender" class="auth-input" style="appearance: auto;">
                  <option value="male" <?= (($_POST['gender'] ?? '') === 'male') ? 'selected' : '' ?>>Male</option>
                  <option value="female" <?= (($_POST['gender'] ?? '') === 'female') ? 'selected' : '' ?>>Female</option>
                  <option value="other" <?= (($_POST['gender'] ?? '') === 'other') ? 'selected' : '' ?>>Other</option>
                </select>
              </div>
            </div>
          </div>

          <div class="auth-input-group">
            <label class="auth-label">Residential Address</label>
            <div class="auth-input-wrap">
              <i class="fa-solid fa-location-dot auth-input-icon"></i>
              <input type="text" name="address" class="auth-input" placeholder="Flat No, Street, City" value="<?= e($_POST['address'] ?? '') ?>">
            </div>
          </div>

          <div class="auth-input-grid">
            <div class="auth-input-group">
              <label class="auth-label">Password *</label>
              <div class="auth-input-wrap has-toggle">
                <i class="fa-solid fa-lock auth-input-icon"></i>
                <input type="password" id="reg_pass" name="password" class="auth-input" placeholder="••••••••" required>
                <button type="button" class="auth-pass-toggle" onclick="toggleAuthPass('reg_pass', this)" title="Show/Hide Password" aria-label="Toggle Password Visibility">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </div>
              <!-- Real-time Password Strength: 1 continuous line + Weak / Medium / Strong -->
              <div id="pass_strength_container" style="display: none; margin-top: 8px;">
                <div style="height: 5px; width: 100%; background: rgba(128,128,128,0.22); border-radius: 999px; overflow: hidden; margin-bottom: 5px;">
                  <div id="pass_strength_bar" style="height: 100%; width: 0%; border-radius: 999px; transition: width 0.35s ease, background 0.35s ease;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; font-weight: 600; padding: 0 2px;">
                  <span id="lbl_weak" style="color: var(--text-muted); transition: all 0.25s ease;">Weak</span>
                  <span id="lbl_medium" style="color: var(--text-muted); transition: all 0.25s ease;">Medium</span>
                  <span id="lbl_strong" style="color: var(--text-muted); transition: all 0.25s ease;">Strong</span>
                </div>
              </div>
            </div>
            <div class="auth-input-group">
              <label class="auth-label">Confirm Password *</label>
              <div class="auth-input-wrap has-toggle">
                <i class="fa-solid fa-lock auth-input-icon"></i>
                <input type="password" id="reg_confirm_pass" name="confirm_password" class="auth-input" placeholder="••••••••" required>
                <button type="button" class="auth-pass-toggle" onclick="toggleAuthPass('reg_confirm_pass', this)" title="Show/Hide Password" aria-label="Toggle Password Visibility">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </div>
              <small id="confirm_pass_hint" style="display: none; font-size: 11.5px; margin-top: 6px; font-weight: 600;"></small>
            </div>
          </div>

          <div style="margin-bottom: 18px;">
            <label class="checkbox-wrap">
              <input type="checkbox" name="terms" value="1" required <?= isset($_POST['terms']) ? 'checked' : '' ?>>
              <span>I agree to Alexa Gym <a href="#" class="auth-link">Terms &amp; Conditions</a> and Privacy Policy</span>
            </label>
          </div>

          <button type="submit" class="btn btn-primary btn-block" style="padding: 12px; font-size: 14.5px;">
            <i class="fa-solid fa-user-plus"></i> Create Account
          </button>

          <div class="auth-footer-prompt">
            Already have an account?
            <a href="login.php" class="auth-link">Login</a>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
(function() {
  // 1. Live Email Validation
  const emailInput = document.getElementById('reg_email');
  const emailHint  = document.getElementById('email_hint');
  if (emailInput && emailHint) {
    function validateEmailInput() {
      const val = emailInput.value.trim();
      if (!val) {
        emailHint.style.display = 'none';
        return;
      }
      const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
      if (!val.includes('@')) {
        emailHint.style.display = 'block';
        emailHint.style.color = '#fbbf24';
        emailHint.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Email must include "@" (e.g. name@gmail.com)';
      } else if (!val.includes('.') || !val.split('@')[1] || !val.split('@')[1].includes('.')) {
        emailHint.style.display = 'block';
        emailHint.style.color = '#fbbf24';
        emailHint.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Email must have domain extension like ".com" (e.g. name@gmail.com)';
      } else if (!emailPattern.test(val)) {
        emailHint.style.display = 'block';
        emailHint.style.color = '#fbbf24';
        emailHint.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Please enter a complete valid email (e.g. name@gmail.com)';
      } else {
        emailHint.style.display = 'block';
        emailHint.style.color = '#34d399';
        emailHint.innerHTML = '<i class="fa-solid fa-circle-check"></i> Valid email format';
      }
    }
    emailInput.addEventListener('input', validateEmailInput);
    emailInput.addEventListener('blur', validateEmailInput);
  }

  // 2. Live Phone Number Validation
  const phoneInput = document.getElementById('reg_phone');
  const phoneHint  = document.getElementById('phone_hint');
  if (phoneInput && phoneHint) {
    function validatePhoneInput() {
      // Auto-strip non-digits & limit to 10
      phoneInput.value = phoneInput.value.replace(/[^0-9]/g, '').slice(0, 10);
      const val = phoneInput.value;
      if (!val) {
        phoneHint.style.display = 'none';
        return;
      }
      phoneHint.style.display = 'block';
      if (!['6', '7', '8', '9'].includes(val[0])) {
        phoneHint.style.color = '#ef4444';
        phoneHint.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Mobile number must start with 6, 7, 8, or 9';
      } else if (val.length < 10) {
        phoneHint.style.color = '#fbbf24';
        phoneHint.innerHTML = `<i class="fa-solid fa-circle-info"></i> 10 digits required (${val.length}/10 entered)`;
      } else {
        phoneHint.style.color = '#34d399';
        phoneHint.innerHTML = '<i class="fa-solid fa-circle-check"></i> Valid 10-digit mobile number';
      }
    }
    phoneInput.addEventListener('input', validatePhoneInput);
    phoneInput.addEventListener('blur', validatePhoneInput);
  }

  // 3. Password Strength Evaluation (Single Line: Pehle Weak, Bich Me Medium, End Me Strong)
  const passInput      = document.getElementById('reg_pass');
  const passContainer  = document.getElementById('pass_strength_container');
  const strBar         = document.getElementById('pass_strength_bar');
  const lblWeak        = document.getElementById('lbl_weak');
  const lblMedium      = document.getElementById('lbl_medium');
  const lblStrong      = document.getElementById('lbl_strong');

  function evaluatePasswordStrength(pwd) {
    if (!pwd) return { level: 'none' };
    const hasLower = /[a-z]/.test(pwd);
    const hasUpper = /[A-Z]/.test(pwd);
    const hasNum   = /[0-9]/.test(pwd);
    const hasSpec  = /[^a-zA-Z0-9]/.test(pwd);
    const variety  = (hasLower ? 1 : 0) + (hasUpper ? 1 : 0) + (hasNum ? 1 : 0) + (hasSpec ? 1 : 0);

    // Weak: under 6 chars or single character category
    if (pwd.length < 6 || variety <= 1) {
      return { level: 'weak' };
    }

    // Strong: 8+ chars and at least 3 categories (e.g. upper + lower + numbers/symbols)
    if (pwd.length >= 8 && variety >= 3) {
      return { level: 'strong' };
    }

    // Medium: 6+ chars with mix of letters & numbers/symbols
    return { level: 'medium' };
  }

  function updatePasswordStrength() {
    if (!passInput || !passContainer) return;
    const pwd = passInput.value;
    if (!pwd) {
      passContainer.style.display = 'none';
      checkConfirmMatch();
      return;
    }
    passContainer.style.display = 'block';
    const res = evaluatePasswordStrength(pwd);

    // Default neutral state
    const mutedColor = 'var(--text-muted, #94a3b8)';
    lblWeak.style.color       = mutedColor;
    lblWeak.style.fontWeight  = '500';
    lblMedium.style.color     = mutedColor;
    lblMedium.style.fontWeight= '500';
    lblStrong.style.color     = mutedColor;
    lblStrong.style.fontWeight= '500';

    if (res.level === 'weak') {
      strBar.style.width      = '33%';
      strBar.style.background = '#ef4444';
      lblWeak.style.color     = '#ef4444';
      lblWeak.style.fontWeight= '700';
    } else if (res.level === 'medium') {
      strBar.style.width      = '66%';
      strBar.style.background = '#f59e0b';
      lblWeak.style.color     = '#f59e0b';
      lblMedium.style.color   = '#f59e0b';
      lblMedium.style.fontWeight = '700';
    } else if (res.level === 'strong') {
      strBar.style.width      = '100%';
      strBar.style.background = '#10b981';
      lblWeak.style.color     = '#10b981';
      lblMedium.style.color   = '#10b981';
      lblStrong.style.color   = '#10b981';
      lblStrong.style.fontWeight = '700';
    }
    checkConfirmMatch();
  }

  // 4. Confirm Password Match Check
  const confirmInput = document.getElementById('reg_confirm_pass');
  const confirmHint  = document.getElementById('confirm_pass_hint');

  function checkConfirmMatch() {
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
    passInput.addEventListener('input', updatePasswordStrength);
    passInput.addEventListener('blur', updatePasswordStrength);
  }
  if (confirmInput) {
    confirmInput.addEventListener('input', checkConfirmMatch);
    confirmInput.addEventListener('blur', checkConfirmMatch);
  }
})();
</script>
</body>
</html>
