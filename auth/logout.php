<?php
/**
 * Alexa Gym — Authentication: Logout
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Clear remember me cookie
if (isset($_COOKIE['gym_remember'])) {
    setcookie('gym_remember', '', time() - 3600, '/');
}

session_destroy();
header('Location: login.php');
exit;
