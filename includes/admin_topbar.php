<?php
/**
 * Admin Portal Topbar
 * Alexa Gym Management System
 */
$topbar_title = $topbar_title ?? 'Admin Dashboard';
$base_url = get_base_url();
$u = get_logged_in_user();
?>
<script>
(function () {
  const urlParams = new URLSearchParams(window.location.search);
  const urlTheme = urlParams.get('theme');
  if (urlTheme === 'light' || urlTheme === 'dark') {
    localStorage.setItem('gym_theme', urlTheme);
  }
  const savedTheme = localStorage.getItem('gym_theme') || 'dark';
  document.documentElement.setAttribute('data-theme', savedTheme);
})();
</script>
<header class="app-topbar">
  <div class="topbar-left">
    <button type="button" class="action-btn-circle" onclick="toggleSidebar()" aria-label="Toggle Sidebar" style="display: none;" id="sidebar-toggle-btn">
      <i class="fa-solid fa-bars"></i>
    </button>
    <h1 class="topbar-title"><?= e($topbar_title) ?></h1>
  </div>

  <div class="topbar-right">
    <!-- View Public Site -->
    <a href="<?= $base_url ?>/index.php" class="action-btn-circle" title="View Public Website">
      <i class="fa-solid fa-globe"></i>
    </a>

    <!-- Theme Toggle -->
    <button type="button" class="action-btn-circle" onclick="toggleTheme()" title="Toggle Dark / Light Theme" aria-label="Toggle Theme">
      <i class="fa-regular fa-moon"></i>
    </button>

    <!-- Admin Profile Dropdown (Like Member Portal) -->
    <div class="user-profile-dropdown-wrap" id="user-profile-dropdown-wrap">
      <button type="button" class="user-profile-btn" id="user-profile-trigger" aria-expanded="false" aria-haspopup="true" title="Account Menu">
        <div class="user-avatar-squircle" style="background: var(--purple-grad); color: #fff; width: 34px; height: 34px; font-size: 14px;">
          <?= strtoupper(substr($u['name'] ?? 'A', 0, 1)) ?>
        </div>
        <span class="user-profile-name">
          <?= e($u['name'] ?? 'Admin Officer') ?>
        </span>
        <i class="fa-solid fa-chevron-down dropdown-arrow" id="dropdown-arrow-icon"></i>
      </button>

      <!-- Dropdown Menu -->
      <div class="user-profile-menu" id="user-profile-menu">
        <div class="user-menu-header">
          <div class="user-avatar-squircle" style="width: 36px; height: 36px; font-size: 15px; background: var(--purple-grad); color: #fff; flex-shrink: 0;">
            <?= strtoupper(substr($u['name'] ?? 'A', 0, 1)) ?>
          </div>
          <div class="user-menu-header-info">
            <div class="menu-user-name"><?= e($u['name'] ?? 'Admin Officer') ?></div>
            <div class="menu-user-role">ADMINISTRATOR</div>
          </div>
        </div>
        <div class="user-menu-divider"></div>
        <a href="<?= $base_url ?>/admin/profile.php" class="user-menu-item">
          <i class="fa-solid fa-user"></i>
          <span>My Profile</span>
        </a>
        <div class="user-menu-divider"></div>
        <a href="<?= $base_url ?>/auth/logout.php" class="user-menu-item logout-item">
          <i class="fa-solid fa-arrow-right-from-bracket"></i>
          <span>Logout</span>
        </a>
      </div>
    </div>
  </div>
</header>
<style>
@media (max-width: 900px) {
  #sidebar-toggle-btn { display: flex !important; }
}
</style>
<script>
(function() {
  function initAdminDropdown() {
    const trigger = document.getElementById('user-profile-trigger');
    const wrap = document.getElementById('user-profile-dropdown-wrap');
    if (!trigger || !wrap) return;

    trigger.addEventListener('click', function(e) {
      e.stopPropagation();
      wrap.classList.toggle('open');
      trigger.setAttribute('aria-expanded', wrap.classList.contains('open'));
    });

    document.addEventListener('click', function(e) {
      if (!wrap.contains(e.target)) {
        wrap.classList.remove('open');
        trigger.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        wrap.classList.remove('open');
        trigger.setAttribute('aria-expanded', 'false');
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdminDropdown);
  } else {
    initAdminDropdown();
  }
})();
</script>
