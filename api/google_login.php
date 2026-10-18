<?php
/**
 * Google Sign-In API Simulation & Backend Sync
 * Path: /api/google_login.php
 * Method: POST
 * 
 * Payload: { "email": "test@gmail.com", "name": "Google User", "google_id": "123456" }
 */

require_once __DIR__ . '/db.php';

// Allow GET to fetch active Google & gym user accounts for the Account Chooser
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query("SELECT id, name, email, role, status FROM users WHERE status = 'active' ORDER BY role ASC, name ASC");
        $accounts = $stmt->fetchAll();
        sendResponse(true, 'Google accounts fetched', $accounts);
    } catch (Exception $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Only POST or GET method is allowed', null, 405);
}

$input = getRequestData();
$email = trim($input['email'] ?? '');
$name = trim($input['name'] ?? '');
$googleId = trim($input['google_id'] ?? '');

if (empty($email)) {
    sendResponse(false, 'Email is required for Google sign-in', null, 400);
}

try {
    // 1. Check if user already exists
    $stmt = $pdo->prepare("SELECT id, name, email, role, status, google_id FROM users WHERE LOWER(TRIM(email)) = LOWER(?) LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Create new member user account
        $userName = !empty($name) ? $name : explode('@', $email)[0];
        $randPass = 'Google_' . bin2hex(random_bytes(6));

        $ins = $pdo->prepare("
            INSERT INTO users (name, email, password, role, google_id, status, created_at)
            VALUES (?, ?, ?, 'member', ?, 'active', NOW())
        ");
        $ins->execute([$userName, $email, $randPass, $googleId ?: null]);
        $newUserId = (int)$pdo->lastInsertId();

        $user = [
            'id' => $newUserId,
            'name' => $userName,
            'email' => $email,
            'role' => 'member',
            'status' => 'active'
        ];

        // Welcome notification
        try {
            $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                VALUES (?, 'Welcome to Alexa Gym! 👋', 'Your account was created via Google Sign-In. Welcome to the gym family!', 'info', 0, NOW())
            ")->execute([$newUserId]);
        } catch (Throwable $nx) {}
    } else {
        if (empty($user['google_id']) && !empty($googleId)) {
            $pdo->prepare("UPDATE users SET google_id = ? WHERE id = ?")->execute([$googleId, $user['id']]);
        }
    }

    if ($user['status'] !== 'active') {
        sendResponse(false, 'Your account is inactive or banned. Please contact gym administration.', null, 403);
    }

    $userData = [
        'user_id' => (int)$user['id'],
        'name'    => $user['name'],
        'email'   => $user['email'],
        'role'    => $user['role'],
    ];

    // Check or create member profile
    $mStmt = $pdo->prepare("SELECT id, plan_id, status FROM members WHERE user_id = ? LIMIT 1");
    $mStmt->execute([$user['id']]);
    $member = $mStmt->fetch();
    if (!$member && $user['role'] === 'member') {
        try {
            $insM = $pdo->prepare("
                INSERT INTO members (user_id, full_name, email, phone, status, joined_date, trainer_id, plan_id)
                VALUES (?, ?, ?, '', 'active', CURDATE(), 1, 1)
            ");
            $insM->execute([$user['id'], $user['name'], $user['email']]);
            $userData['member_id'] = (int)$pdo->lastInsertId();
            $userData['status'] = 'active';
        } catch (Throwable $mx) {}
    } elseif ($member) {
        $userData['member_id'] = (int)$member['id'];
        $userData['status'] = $member['status'];
    }

    // Set PHP Session for web authentication
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['user_id']    = $userData['user_id'];
    $_SESSION['user_name']  = $userData['name'];
    $_SESSION['user_email'] = $userData['email'];
    $_SESSION['user_role']  = $userData['role'];
    if (!empty($userData['member_id'])) {
        $_SESSION['member_id'] = $userData['member_id'];
    }

    sendResponse(true, 'Google Sign-in successful!', $userData);

} catch (Throwable $e) {
    sendResponse(false, 'Google Sign-in error: ' . $e->getMessage(), null, 500);
}
