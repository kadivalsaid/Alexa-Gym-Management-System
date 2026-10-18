<?php
/**
 * User & Admin Authentication API
 * Path: /api/login.php
 * Method: POST
 * 
 * Payload: { "email": "user@example.com", "password": "yourpassword" }
 */

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Only POST method is allowed', null, 405);
}

$input = getRequestData();
$email = trim($input['email'] ?? '');
$password = (string)($input['password'] ?? '');

if (empty($email) || empty($password)) {
    sendResponse(false, 'Please provide both email and password', null, 400);
}

try {
    // 1. User check karna (Email, Name ya Phone se - Case-insensitive & trimmed)
    $cleanPhone = preg_replace('/[^0-9]/', '', $email);
    $stmt = $pdo->prepare('
        SELECT id, name, email, password, role, status, profile_image 
        FROM users 
        WHERE LOWER(TRIM(email)) = LOWER(?) 
           OR LOWER(TRIM(name)) = LOWER(?)
           OR (phone IS NOT NULL AND phone != "" AND phone = ?)
           OR id IN (SELECT user_id FROM members WHERE phone = ? OR LOWER(TRIM(email)) = LOWER(?))
        LIMIT 1
    ');
    $stmt->execute([$email, $email, $cleanPhone, $cleanPhone, $email]);
    $user = $stmt->fetch();

    if (!$user) {
        sendResponse(false, 'Invalid email or password. Please check your credentials.', null, 200);
    }

    if ($user['status'] !== 'active') {
        sendResponse(false, 'Your account is inactive or banned. Please contact admin.', null, 200);
    }

    // 2. Password Match check (Plain-text, Bcrypt, MD5, SHA1 & Trimmed match support)
    $passwordValid = false;
    $dbPass = (string)$user['password'];

    if ($password === $dbPass || trim($password) === trim($dbPass)) {
        $passwordValid = true;
    } elseif (password_verify($password, $dbPass) || password_verify(trim($password), $dbPass)) {
        $passwordValid = true;
    } elseif (md5($password) === $dbPass || sha1($password) === $dbPass) {
        $passwordValid = true;
    }

    if (!$passwordValid) {
        sendResponse(false, 'Invalid email or password. Please check your credentials.', null, 200);
    }

    // 3. User details prepare karna (Password ko response se hata kar)
    $userData = [
        'user_id'       => (int)$user['id'],
        'name'          => $user['name'],
        'email'         => $user['email'],
        'role'          => $user['role'],
        'profile_image' => $user['profile_image'] ?? ''
    ];

    // 4. Agar user Member hai, to 'members' table se membership info bhi fetch karna
    if ($user['role'] === 'member') {
        $memberStmt = $pdo->prepare('
            SELECT m.id AS member_id, m.phone, m.gender, m.plan_id, m.trainer_id, 
                   m.join_date, m.expiry_date, m.status AS member_status,
                   p.name AS plan_name, t.full_name AS trainer_name
            FROM members m
            LEFT JOIN plans p ON m.plan_id = p.id
            LEFT JOIN trainers t ON m.trainer_id = t.id
            WHERE m.user_id = ?
            LIMIT 1
        ');
        $memberStmt->execute([$user['id']]);
        $memberInfo = $memberStmt->fetch();

        if ($memberInfo) {
            $userData['member_id']    = (int)$memberInfo['member_id'];
            $userData['phone']        = $memberInfo['phone'];
            $userData['gender']       = $memberInfo['gender'];
            $userData['plan_id']      = $memberInfo['plan_id'];
            $userData['plan_name']    = $memberInfo['plan_name'];
            $userData['trainer_id']   = $memberInfo['trainer_id'];
            $userData['trainer_name'] = $memberInfo['trainer_name'];
            $userData['join_date']    = $memberInfo['join_date'];
            $userData['expiry_date']  = $memberInfo['expiry_date'];
            $userData['status']       = $memberInfo['member_status'];
        }
    }

    sendResponse(true, 'Login successful!', $userData);

} catch (Exception $e) {
    sendResponse(false, 'Server error: ' . $e->getMessage(), null, 500);
}
