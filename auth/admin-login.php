<?php
/**
 * Alexa Gym — Authentication: Admin Login Portal
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

if (isset($_GET['reset_done'])) {
    $success_msg = 'Your administrator password has been successfully reset! Please login.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_or_user = trim($_POST['email'] ?? '');
    $password      = $_POST['password'] ?? '';

    if (empty($email_or_user) || empty($password)) {
        $error_msg = 'Please enter both your admin email/username and password.';
    } else {
        // Query users table by email or name
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR name = ? LIMIT 1");
        $stmt->execute([$email_or_user, $email_or_user]);
        $user = $stmt->fetch();

        $password_valid = false;
        if ($user) {
            // Plain text password verification (with auto-convert for previous hash)
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

            // Strict Role Check: ONLY ADMIN ALLOWED
            if ($db_role !== 'admin') {
                $error_msg = 'Access denied! This portal is restricted to Gym Administrators. Members please login via Member Login.';
            } elseif ($user['status'] !== 'active') {
                $error_msg = 'Your administrator account is inactive or restricted. Please contact system management.';
            } else {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);

                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role']  = 'admin';

                // Handle Remember Me token
                if (!empty($_POST['remember_me'])) {
                    $rem_token = bin2hex(random_bytes(32));
                    $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?")->execute([$rem_token, $user['id']]);
                    setcookie('gym_remember', $rem_token, time() + (30 * 86400), '/', '', false, true);
                }

                header('Location: ../admin/dashboard.php');
                exit;
            }
        } else {
            $error_msg = 'Invalid administrator credentials. Please check your email and password.';
        }
    }
}

$page_title = 'Admin Login Portal — Alexa Gym';
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
      <div class="auth-banner-tag" style="background: rgba(245, 158, 11, 0.15); border-color: rgba(245, 158, 11, 0.35); color: #fbbf24;">
        <i class="fa-solid fa-shield-halved"></i> Executive Portal
      </div>
      <h2 class="auth-banner-title">
        Management &amp; <span>Administrative</span> Control
      </h2>
      <p class="auth-banner-desc">
        Secure administration portal for managing members, processing billing &amp; receipts, tracking live attendance, organizing trainer assignments, and facility analytics.
      </p>
    </div>

    <div class="auth-banner-content auth-banner-footer">
      &copy; <?= date('Y') ?> Alexa Gym &bull; Authorized Personnel Only
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
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
          <span style="background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.35); font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 20px; letter-spacing: 0.5px; text-transform: uppercase;">
            <i class="fa-solid fa-shield-halved"></i> Admin Access
          </span>
        </div>
        <h1 class="auth-title">Admin Login</h1>
        <p class="auth-subtitle">Restricted area. Enter your administrator credentials.</p>
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

      <form method="POST" action="admin-login.php" id="admin-login-form">
        <div class="auth-form-box">
          <div class="auth-input-group">
            <label class="auth-label" for="email">Admin Email or Username</label>
            <div class="auth-input-wrap">
              <i class="fa-solid fa-user-shield auth-input-icon" style="color: #fbbf24;"></i>
              <input type="text" id="email" name="email" class="auth-input" placeholder="admin@gym.com or admin" required value="<?= e($_POST['email'] ?? '') ?>">
            </div>
          </div>

          <div class="auth-input-group">
            <label class="auth-label" for="password">Admin Password</label>
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
            <a href="admin-forgot-password.php" class="auth-link">Forgot Password?</a>
          </div>

          <button type="submit" class="btn btn-primary btn-block" style="padding: 12px; font-size: 14.5px;">
            <i class="fa-solid fa-shield-halved"></i> Login to Admin Portal
          </button>

          <div class="auth-footer-prompt">
            Are you a gym member?
            <a href="login.php" class="auth-link">Member Login</a>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>
