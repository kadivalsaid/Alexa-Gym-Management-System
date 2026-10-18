<?php
/**
 * Member Portal Topbar
 * Alexa Gym Management System
 */
$topbar_title = $topbar_title ?? 'Dashboard';
$base_url = get_base_url();
$u = get_logged_in_user();

if (!isset($unread_count) && $u && isset($pdo)) {
    try {
        $n_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $n_stmt->execute([$u['id']]);
        $unread_count = (int)$n_stmt->fetchColumn();
    } catch (Exception $e) {
        $unread_count = 0;
    }
}
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
    <!-- Public Site Link -->
    <a href="<?= $base_url ?>/index.php" class="action-btn-circle" title="View Public Website">
      <i class="fa-solid fa-globe"></i>
    </a>

    <!-- Theme Toggle -->
    <button type="button" class="action-btn-circle" onclick="toggleTheme()" title="Toggle Dark / Light Theme" aria-label="Toggle Theme">
      <i class="fa-regular fa-moon"></i>
    </button>

    <!-- Notifications Bell -->
    <a href="<?= $base_url ?>/user/notifications.php" class="action-btn-circle" title="Notifications">
      <i class="fa-regular fa-bell"></i>
      <?php if (!empty($unread_count) && $unread_count > 0): ?>
        <span class="notif-badge-dot"></span>
      <?php endif; ?>
    </a>

    <!-- User Profile Dropdown -->
    <div class="user-profile-dropdown-wrap" id="user-profile-dropdown-wrap">
      <button type="button" class="user-profile-btn" id="user-profile-trigger" aria-expanded="false" aria-haspopup="true" title="Account Menu">
        <div class="user-avatar-squircle">
          <?= strtoupper(substr($u['name'] ?? 'M', 0, 1)) ?>
        </div>
        <span class="user-profile-name">
          <?= e($u['name'] ?? 'Member') ?>
        </span>
        <i class="fa-solid fa-chevron-down dropdown-arrow" id="dropdown-arrow-icon"></i>
      </button>

      <!-- Dropdown Menu -->
      <div class="user-profile-menu" id="user-profile-menu">
        <div class="user-menu-header">
          <div class="user-avatar-squircle" style="width: 36px; height: 36px; font-size: 16px; flex-shrink: 0;">
            <?= strtoupper(substr($u['name'] ?? 'M', 0, 1)) ?>
          </div>
          <div class="user-menu-header-info">
            <div class="menu-user-name"><?= e($u['name'] ?? 'Member') ?></div>
            <div class="menu-user-role">MEMBER ACCOUNT</div>
          </div>
        </div>
        <div class="user-menu-divider"></div>
        <a href="<?= $base_url ?>/user/profile.php" class="user-menu-item">
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
  function initUserDropdown() {
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
    document.addEventListener('DOMContentLoaded', initUserDropdown);
  } else {
    initUserDropdown();
  }
})();
</script>
