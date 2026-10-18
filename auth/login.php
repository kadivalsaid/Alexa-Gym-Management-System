<?php
/**
 * Alexa Gym — Authentication: Login Page
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// If already logged in, redirect to respective dashboard
if (is_logged_in()) {
    $user = get_logged_in_user();
    if ($user['role'] === 'admin') {
        header('Location: ../admin/dashboard.php');
    } else {
        header('Location: ../user/dashboard.php');
    }
    exit;
}

$error_msg   = '';
$success_msg = '';

if (isset($_GET['registered'])) {
    $success_msg = 'Your account was created successfully! Please login below.';
}
if (isset($_GET['reset_done'])) {
    $success_msg = 'Your password has been successfully reset! Please login.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_or_user = trim($_POST['email'] ?? '');
    $password      = $_POST['password'] ?? '';
    $selected_role = trim($_POST['role'] ?? 'member'); // Role selector from UI

    if (empty($email_or_user) || empty($password)) {
        $error_msg = 'Please enter both your email/username and password.';
    } else {
        // Query users table by email or name
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR name = ? LIMIT 1");
        $stmt->execute([$email_or_user, $email_or_user]);
        $user = $stmt->fetch();

        $password_valid = false;
        if ($user) {
            // Normal plain text password comparison (with auto-convert for legacy hash)
            if ($password === $user['password']) {
                $password_valid = true;
            } elseif (password_verify($password, $user['password'])) {
                $password_valid = true;
                // Automatically convert previously hashed password to plain text in database
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$password, $user['id']]);
            }
        }

        if ($user && $password_valid) {
            $db_role = strtolower($user['role']);

            // Strict Role Check: ONLY MEMBER ALLOWED
            if ($db_role !== 'member') {
                $error_msg = 'Access denied! This login page is strictly for Gym Members. If you are an administrator, please use the Admin Login portal.';
            } elseif ($user['status'] !== 'active') {
                $error_msg = 'Your account is inactive or restricted. Please contact gym administration.';
            } else {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);

                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email']= $user['email'];
                $_SESSION['user_phone']= $user['phone'] ?? '';
                $_SESSION['user_role'] = 'member';

                // Find associated member_id
                $m_stmt = $pdo->prepare("SELECT id FROM members WHERE user_id = ? OR email = ? LIMIT 1");
                $m_stmt->execute([$user['id'], $user['email']]);
                $m_rec = $m_stmt->fetch();
                if ($m_rec) {
                    $_SESSION['member_id'] = $m_rec['id'];
                }

                // Handle Remember Me token
                if (!empty($_POST['remember_me'])) {
                    $rem_token = bin2hex(random_bytes(32));
                    $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?")->execute([$rem_token, $user['id']]);
                    setcookie('gym_remember', $rem_token, time() + (30 * 86400), '/', '', false, true);
                }

                header('Location: ../user/dashboard.php');
                exit;
            }
        } else {
            $error_msg = 'Invalid credentials. Please double-check your email and password.';
        }
    }
}

$page_title = 'Member Login — Alexa Gym';
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
      <div class="auth-banner-tag"><i class="fa-solid fa-lock"></i> Secure Member Portal</div>
      <h2 class="auth-banner-title">
        Unleash Your <span>Peak Potential</span>
      </h2>
      <p class="auth-banner-desc">
        Welcome back to Alexa Gym. Log in to track your personalized workouts, verify your attendance records, manage memberships, and connect with your coach.
      </p>
    </div>

    <div class="auth-banner-content auth-banner-footer">
      &copy; <?= date('Y') ?> Alexa Gym &bull; State-of-the-Art Training Facility
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
        <a href="../index.php" style="color: var(--cyan); font-size: 13.5px; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 14px;">
          <i class="fa-solid fa-arrow-left"></i> Back to Homepage
        </a>
        <h1 class="auth-title">Member Login</h1>
        <p class="auth-subtitle">Login to access your workouts, diet & gym passes.</p>
      </div>

      <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success" style="margin-bottom: 14px; padding: 10px 14px; font-size: 13.5px; border-radius: 10px;">
          <i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i>
          <?= e($success_msg) ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger" style="margin-bottom: 14px; padding: 10px 14px; font-size: 13.5px; border-radius: 10px;">
          <i class="fa-solid fa-triangle-exclamation" style="margin-right: 8px;"></i>
          <?= e($error_msg) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php" id="login-form">
        <!-- Box containing Member Login and Registration prompt -->
        <div class="auth-form-box">
          <div class="auth-input-group">
            <label class="auth-label" for="email">Email or Username</label>
            <div class="auth-input-wrap">
              <i class="fa-solid fa-envelope auth-input-icon"></i>
              <input type="text" id="email" name="email" class="auth-input" placeholder="member@gym.com or your username" required value="<?= e($_POST['email'] ?? '') ?>">
            </div>
          </div>

          <div class="auth-input-group">
            <label class="auth-label" for="password">Password</label>
            <div class="auth-input-wrap has-toggle">
              <i class="fa-solid fa-lock auth-input-icon"></i>
              <input type="password" id="password" name="password" class="auth-input" placeholder="••••••••" required>
              <button type="button" class="auth-pass-toggle" onclick="toggleAuthPass('password', this)" title="Show/Hide Password" aria-label="Toggle Password Visibility">
                <i class="fa-regular fa-eye"></i>
              </button>
            </div>
          </div>

          <div class="auth-options">
            <label class="checkbox-wrap">
              <input type="checkbox" name="remember_me" value="1">
              <span>Remember Me</span>
            </label>
            <a href="forgot-password.php" class="auth-link">Forgot Password?</a>
          </div>

          <button type="submit" class="btn btn-primary btn-block" style="padding: 11px; font-size: 14.5px;">
            <i class="fa-solid fa-arrow-right-to-bracket"></i> Login
          </button>

          <div class="auth-divider">
            <span>OR CONTINUE WITH</span>
          </div>

          <a href="google-login.php" class="btn-google">
            <svg class="google-icon-svg" viewBox="0 0 24 24">
              <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
              <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.26v3.15C3.29 21.43 7.36 24 12 24z"/>
              <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.26C.46 8.16 0 9.94 0 12s.46 3.84 1.26 5.42l4.02-3.15z"/>
              <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.36 0 3.29 2.57 1.26 6.58l4.02 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
            </svg>
            Continue with Google
          </a>

          <div class="auth-footer-prompt">
            Don't have an account?
            <a href="register.php" class="auth-link">Create Account</a>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>
