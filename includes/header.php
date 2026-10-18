<?php
/**
 * Shared HTML Head Include
 * Alexa Gym Management System
 */
if (!defined('APP_INIT')) {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/auth.php';
    require_once __DIR__ . '/functions.php';
    define('APP_INIT', true);
}

$page_title = $page_title ?? 'Alexa Gym — Build Your Better Self';
$base_url   = get_base_url();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title) ?></title>
  
  <!-- SEO Meta Tags -->
  <meta name="description" content="Alexa Gym — Premium fitness center, certified personal trainers, modern equipment, and flexible membership plans. Build Your Better Self.">
  <meta name="keywords" content="Alexa Gym, Gym Management System, Fitness, Workout, Personal Training, Gym Membership, Vadgam, Banaskantha, Gujarat">
  <meta name="author" content="Alexa Gym Management">

  <!-- Google Fonts: Outfit & Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

  <!-- Font Awesome 6 Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

  <!-- Core Stylesheets -->
  <?php 
  $css_path = __DIR__ . '/../assets/css/style.css';
  $css_ver = file_exists($css_path) ? filemtime($css_path) : '4.5';
  ?>
  <link rel="stylesheet" href="<?= $base_url ?>/assets/css/style.css?v=<?= $css_ver ?>">
  <?php if (isset($extra_css)): ?>
    <link rel="stylesheet" href="<?= $base_url ?>/<?= ltrim($extra_css, '/') ?>?v=3.0">
  <?php endif; ?>

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
</head>
<body>
