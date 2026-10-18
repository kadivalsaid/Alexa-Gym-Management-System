<?php
/**
 * Public Navigation Bar
 * Alexa Gym Management System
 */
$current_page = basename($_SERVER['PHP_SELF'] ?? '');
$base_url = get_base_url();
$is_user = is_logged_in();
$user_info = get_logged_in_user();
?>
<header class="site-navbar">
  <div class="container navbar-container">
    <!-- Brand Logo -->
    <a href="<?= $base_url ?>/index.php" class="navbar-brand">
      <div class="brand-logo-badge"><i class="fa-solid fa-dumbbell"></i></div>
      <div class="brand-info">
        <span class="brand-name">Alexa Gym</span>
        <span class="brand-sub">Build Your Better Self</span>
      </div>
    </a>

    <!-- Navigation Links -->
    <nav>
      <ul class="navbar-nav" id="navbar-nav">
        <li>
          <a href="<?= $base_url ?>/index.php" class="nav-link <?= ($current_page === 'index.php') ? 'active' : '' ?>">Home</a>
        </li>
        <li>
          <a href="<?= $base_url ?>/facilities.php" class="nav-link <?= ($current_page === 'facilities.php') ? 'active' : '' ?>">Facilities</a>
        </li>
        <?php
        $nav_plans_link = $base_url . '/plans.php';
        if ($is_user) {
            if (($user_info['role'] ?? '') === 'admin') {
                $nav_plans_link = $base_url . '/admin/plans.php';
            } elseif (($user_info['role'] ?? '') === 'member') {
                $nav_plans_link = $base_url . '/user/plans.php';
            }
        }
        ?>
        <li>
          <a href="<?= $nav_plans_link ?>" class="nav-link <?= ($current_page === 'plans.php') ? 'active' : '' ?>">Membership Plans</a>
        </li>
        <li>
          <a href="<?= $base_url ?>/trainers.php" class="nav-link <?= ($current_page === 'trainers.php') ? 'active' : '' ?>">Trainers</a>
        </li>
        <li>
          <a href="<?= $base_url ?>/gallery.php" class="nav-link <?= ($current_page === 'gallery.php') ? 'active' : '' ?>">Gallery</a>
        </li>
        <li>
          <a href="<?= $base_url ?>/contact.php" class="nav-link <?= ($current_page === 'contact.php') ? 'active' : '' ?>">Contact</a>
        </li>
        <li>
          <a href="<?= $base_url ?>/app.php" class="nav-link <?= ($current_page === 'app.php') ? 'active' : '' ?>" style="color: #10B981; font-weight: 700;">
            <i class="fa-brands fa-android" style="margin-right: 5px;"></i> Android App
          </a>
        </li>

        <?php if ($is_user): ?>
          <li>
            <a href="<?= ($user_info['role'] === 'admin') ? $base_url . '/admin/dashboard.php' : $base_url . '/user/dashboard.php' ?>" class="btn btn-sm btn-primary">
              <i class="fa-solid fa-gauge-high"></i> Dashboard
            </a>
          </li>
        <?php else: ?>
          <li>
            <a href="<?= $base_url ?>/auth/login.php" class="nav-link <?= ($current_page === 'login.php') ? 'active' : '' ?>">Login</a>
          </li>
          <li style="margin-right: 4px;">
            <a href="<?= $base_url ?>/auth/admin-login.php" class="btn btn-sm btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
              <i class="fa-solid fa-shield-halved"></i> Admin Login
            </a>
          </li>
        <?php endif; ?>
      </ul>
    </nav>

    <!-- Right Actions: Theme Toggle & Mobile Menu -->
    <div class="navbar-actions" style="margin-left: 24px; display: flex; align-items: center; gap: 12px; flex-shrink: 0;">
      <button type="button" class="theme-toggle" onclick="toggleSiteTheme()" title="Toggle Dark/Light Mode" aria-label="Toggle Theme">
        <i class="fa-regular fa-moon" id="theme-toggle-icon"></i>
      </button>
      <button type="button" class="hamburger-btn" id="hamburger-btn" aria-label="Toggle navigation menu">
        <i class="fa-solid fa-bars"></i>
      </button>
    </div>
  </div>
</header>

<script>
  (function() {
    const theme = localStorage.getItem('gym_theme') || 'dark';
    const icon = document.getElementById('theme-toggle-icon');
    if (icon) {
      icon.className = theme === 'dark' ? 'fa-regular fa-moon' : 'fa-regular fa-sun';
    }
  })();

  function toggleSiteTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'dark';
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('gym_theme', next);
    const icon = document.getElementById('theme-toggle-icon');
    if (icon) {
      icon.className = next === 'dark' ? 'fa-regular fa-moon' : 'fa-regular fa-sun';
    }
  }
</script>
