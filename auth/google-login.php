<?php
/**
 * Alexa Gym — Authentication: Google OAuth 2.0 Handler & Real Google Identity Connector
 * Compatible with InfinityFree & Standard Shared Hosting (Client-side token flow with MySQL sync)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (isset($_GET['from']) && $_GET['from'] === 'app') {
    $_SESSION['oauth_from'] = 'app';
}

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    $u_role = $_SESSION['user_role'] ?? 'member';
    if (!empty($_SESSION['oauth_from']) && $_SESSION['oauth_from'] === 'app') {
        unset($_SESSION['oauth_from']);
        header('Location: ../mobile/index.html#/?auth=success&user_id=' . ($_SESSION['user_id'] ?? 1) . '&role=' . $u_role . '&name=' . urlencode($_SESSION['user_name'] ?? '') . '&email=' . urlencode($_SESSION['user_email'] ?? ''));
        exit;
    }
    header($u_role === 'admin' ? 'Location: ../admin/dashboard.php' : 'Location: ../user/dashboard.php');
    exit;
}

// Construct Google OAuth URL using Token Flow (Implicit Flow) for 100% free-hosting compatibility
$oauth_params = [
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'token',
    'scope'         => 'openid email profile',
    'prompt'        => 'select_account',
];
if ((isset($_GET['from']) && $_GET['from'] === 'app') || (isset($_GET['state']) && $_GET['state'] === 'app')) {
    $oauth_params['state'] = 'app';
}
$auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($oauth_params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Signing in with Google — Alexa Gym</title>
  <link rel="icon" type="image/png" href="../mobile/favicon.png">
  <style>
    * { box-sizing: border-box; }
    body {
      background: #07080F;
      color: #f3f4fd;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      margin: 0;
      padding: 24px;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
    }
    .auth-card {
      background: #11121d;
      border: 1px solid #23253b;
      border-radius: 24px;
      padding: 36px 28px;
      max-width: 420px;
      width: 100%;
      box-shadow: 0 20px 40px rgba(0,0,0,0.5);
    }
    .logo-badge {
      width: 64px;
      height: 64px;
      border-radius: 20px;
      background: linear-gradient(135deg, #00F2FE 0%, #4FACFE 100%);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: #07080F;
      font-size: 28px;
      font-weight: 900;
      margin-bottom: 20px;
      box-shadow: 0 10px 25px rgba(0, 242, 254, 0.35);
    }
    .spinner {
      width: 40px;
      height: 40px;
      border: 3.5px solid rgba(0, 242, 254, 0.2);
      border-top: 3.5px solid #00F2FE;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
      margin: 20px auto 16px auto;
    }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    h2 { font-size: 20px; font-weight: 800; margin: 0 0 8px 0; color: #fff; }
    p { font-size: 14px; color: #94a3b8; line-height: 1.5; margin: 0 0 16px 0; }
    .btn-retry {
      display: inline-block;
      background: #00F2FE;
      color: #07080F;
      font-weight: 700;
      padding: 12px 24px;
      border-radius: 12px;
      text-decoration: none;
      font-size: 14px;
      margin-top: 12px;
    }
  </style>
</head>
<body>

<div class="auth-card">
  <div class="logo-badge">AG</div>
  <h2>Google Sign-In</h2>
  <div class="spinner" id="loader-spinner"></div>
  <p id="auth-status">Connecting to Google Account Chooser...</p>
  <a href="<?= htmlspecialchars($auth_url) ?>" id="retry-btn" class="btn-retry" style="display: none;">Continue with Google</a>
</div>

<script>
(async function() {
  const statusEl = document.getElementById('auth-status');
  const spinnerEl = document.getElementById('loader-spinner');
  const retryBtn = document.getElementById('retry-btn');
  const googleAuthUrl = <?= json_encode($auth_url) ?>;

  // 1. Check if returning from Google with access_token in the URL hash
  const hash = window.location.hash.substring(1);
  const hashParams = new URLSearchParams(hash);
  const accessToken = hashParams.get('access_token');
  const errorParam = hashParams.get('error') || new URLSearchParams(window.location.search).get('error');

  if (errorParam) {
    spinnerEl.style.display = 'none';
    statusEl.innerText = 'Google Sign-In cancelled or failed: ' + errorParam;
    retryBtn.style.display = 'inline-block';
    return;
  }

  if (accessToken) {
    try {
      statusEl.innerText = 'Verifying your Google profile...';

      // 2. Fetch user profile directly from Google UserInfo API (via client browser internet)
      const userRes = await fetch('https://www.googleapis.com/oauth2/v3/userinfo?access_token=' + encodeURIComponent(accessToken));
      if (!userRes.ok) {
        throw new Error('Google UserInfo verification failed (' + userRes.status + ')');
      }
      const gUser = await userRes.json();

      if (!gUser.email) {
        throw new Error('Google did not return an email address.');
      }

      statusEl.innerText = 'Signing in as ' + (gUser.name || gUser.email) + '...';

      // 3. Sync with local Alexa Gym MySQL database
      const syncRes = await fetch('../api/google_login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          email: gUser.email,
          name: gUser.name || gUser.email.split('@')[0],
          google_id: gUser.sub || ''
        })
      });

      const syncData = await syncRes.json();
      if (!syncData.success) {
        throw new Error(syncData.message || 'Server login failed');
      }

      statusEl.innerText = 'Welcome, ' + syncData.data.name + '! Redirecting to app...';

      // 4. Determine destination: Mobile app via native Android Deep Link vs Web
      const isApp = <?= json_encode((isset($_GET['from']) && $_GET['from'] === 'app') || (isset($_GET['state']) && $_GET['state'] === 'app') || (!empty($_SESSION['oauth_from']) && $_SESSION['oauth_from'] === 'app')) ?>;
      
      const queryParams = 'auth=success&user_id=' + syncData.data.user_id +
        '&role=' + encodeURIComponent(syncData.data.role || 'member') +
        '&name=' + encodeURIComponent(syncData.data.name || '') +
        '&email=' + encodeURIComponent(syncData.data.email || '') +
        '&member_id=' + (syncData.data.member_id || 0);

      const deepLink = 'alexagym://auth?' + queryParams;
      const intentUrl = 'intent://auth?' + queryParams + '#Intent;scheme=alexagym;package=com.alexagym.app;end';

      const appTarget = '../mobile/index.html#/?' + queryParams;
      const webTarget = (syncData.data.role === 'admin') ? '../admin/dashboard.php' : '../user/dashboard.php';

      if (isApp) {
        spinnerEl.style.display = 'none';
        statusEl.innerHTML = '<div style="font-size:42px; margin-bottom:12px;">✅</div>' +
          '<h3 style="color:#10B981; margin:0 0 8px 0; font-size:22px; font-weight:800;">Login Successful!</h3>' +
          '<p style="color:#ffffff; font-size:16px; margin:0 0 6px 0;">Welcome back, <strong>' + syncData.data.name + '</strong> 👋</p>' +
          '<p style="color:#94a3b8; font-size:13px; margin:0 0 20px 0;">Opening Alexa Gym App...</p>' +
          '<a href="' + deepLink + '" id="launch-app-btn" class="btn-retry" style="display:block; background:linear-gradient(135deg, #10B981, #059669); color:#fff; font-size:16px; font-weight:800; padding:16px 24px; border-radius:16px; text-decoration:none; box-shadow:0 10px 28px rgba(16,185,129,0.45); letter-spacing:0.5px;">👉 OPEN ALEXA GYM APP &rarr;</a>' +
          '<div style="margin-top:16px;"><a href="' + appTarget + '" style="color:#64748b; font-size:12px; text-decoration:underline;">Or continue in browser</a></div>';

        // Direct launch into installed Alexa Gym app
        const appBtn = document.getElementById('launch-app-btn');
        appBtn.onclick = function() {
          window.location.href = deepLink;
          setTimeout(function() {
            window.location.href = intentUrl;
          }, 300);
        };

        // Attempt automatic deep link navigation immediately
        try {
          window.location.href = deepLink;
        } catch(e) {}

        // Fallback intent launch attempt
        setTimeout(function() {
          try {
            window.location.href = intentUrl;
          } catch(e) {}
        }, 600);

      } else {
        // Standard website flow for desktop/browser users
        statusEl.innerHTML = 'Welcome, <strong>' + syncData.data.name + '</strong>! Redirecting...';
        setTimeout(function() {
          window.location.href = (window.innerWidth >= 768) ? webTarget : appTarget;
        }, 1000);
      }

    } catch (err) {
      console.error('Auth error:', err);
      spinnerEl.style.display = 'none';
      statusEl.innerText = 'Login error: ' + err.message;
      retryBtn.style.display = 'inline-block';
    }
    return;
  }

  // If no access token in hash, redirect to Google OAuth account chooser
  statusEl.innerText = 'Redirecting to Google Account Chooser...';
  window.location.href = googleAuthUrl;
})();
</script>

</body>
</html>
