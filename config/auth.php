<?php
/**
 * Authentication & Security Configuration
 * Alexa Gym Management System
 */

if (session_status() === PHP_SESSION_NONE) {
    // Secure cookie parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

/**
 * =========================================================================
 * GOOGLE OAUTH 2.0 CONFIGURATION
 * =========================================================================
 * To enable real Google Login:
 * 1. Go to Google Cloud Console: https://console.cloud.google.com/
 * 2. Create a project and configure the OAuth consent screen.
 * 3. Create "OAuth 2.0 Client IDs" credentials for Web application.
 * 4. Add your Authorized redirect URI:
 *    http://localhost/gym-management-php/auth/google-login.php
 *    (or http://localhost/gym-management/auth/google-login.php)
 * 5. Paste your Client ID and Client Secret below:
 * =========================================================================
 */
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
// Dynamic Redirect URI (Auto-detects Localhost vs Live Hosting)
$is_local_auth = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1']) || php_sapi_name() === 'cli';
$auth_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$auth_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$auth_path = $is_local_auth ? '/gym-management-php/auth/google-login.php' : '/auth/google-login.php';
define('GOOGLE_REDIRECT_URI', $auth_protocol . $auth_host . $auth_path);

// Enable development simulation mode when keys are placeholders
define('GOOGLE_AUTH_SIMULATION', (strpos(GOOGLE_CLIENT_ID, 'YOUR_GOOGLE_CLIENT_ID') !== false));

/**
 * CSRF Protection Helpers
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Role & Session Checking
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function get_logged_in_user() {
    if (!is_logged_in()) return null;
    return [
        'id'    => $_SESSION['user_id'] ?? null,
        'name'  => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? '',
        'phone' => $_SESSION['user_phone'] ?? '',
        'role'  => strtolower($_SESSION['user_role'] ?? 'member'),
        'member_id' => $_SESSION['member_id'] ?? null,
    ];
}

function require_login($redirect_to = null) {
    if (!is_logged_in()) {
        $login_url = $redirect_to ?: '/gym-management-php/auth/login.php';
        header("Location: $login_url");
        exit;
    }
}

function require_admin() {
    require_login();
    $role = strtolower($_SESSION['user_role'] ?? '');
    if ($role !== 'admin') {
        header('Location: /gym-management-php/user/dashboard.php?error=unauthorized');
        exit;
    }
}

function require_member() {
    require_login();
    $role = strtolower($_SESSION['user_role'] ?? '');
    if ($role === 'admin') {
        header('Location: /gym-management-php/admin/dashboard.php');
        exit;
    }
    if ($role !== 'member') {
        header('Location: /gym-management-php/auth/login.php?error=unauthorized');
        exit;
    }
}

function require_auth($redirect_to = null) {
    require_login($redirect_to);
}

