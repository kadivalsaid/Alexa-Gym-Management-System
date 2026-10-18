<?php
/**
 * Full Member Registration API (Exact match with website fields & rules)
 * Path: /api/register.php
 * Method: POST
 * 
 * Payload:
 * {
 *   "full_name": "Rahul Deshmukh",
 *   "email": "rahul@gmail.com",
 *   "phone": "9876543210",
 *   "dob": "2000-01-01",
 *   "gender": "male", // male, female, other
 *   "address": "Pune, Maharashtra",
 *   "password": "password123",
 *   "confirm_password": "password123",
 *   "terms": true
 * }
 */

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Only POST method is allowed', null, 405);
}

$input = getRequestData();
$fullName  = trim($input['full_name'] ?? $input['name'] ?? '');
$email     = trim($input['email'] ?? '');
$phone     = trim($input['phone'] ?? '');
$dob       = trim($input['dob'] ?? '');
$gender    = trim($input['gender'] ?? 'male');
$address   = trim($input['address'] ?? '');
$password  = (string)($input['password'] ?? '');
$confirmP  = (string)($input['confirm_password'] ?? $password);
$terms     = (bool)($input['terms'] ?? true);

$cleanPhone = preg_replace('/[^0-9]/', '', $phone);
$emailRegex = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';

// Exact validations as website register.php
if (empty($fullName) || empty($email) || empty($phone) || empty($password)) {
    sendResponse(false, 'Please fill in all mandatory fields marked with an asterisk (*).', null, 400);
}

if (strpos($email, '@') === false) {
    sendResponse(false, 'Invalid email: Email address must contain "@" (e.g. user@gmail.com).', null, 400);
}

if (!preg_match('/\.[a-zA-Z]{2,}$/', $email) || !preg_match($emailRegex, $email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Invalid email: Email must contain a valid domain extension like .com (e.g. user@gmail.com).', null, 400);
}

if (strlen($cleanPhone) !== 10 || !preg_match('/^[6-9]\d{9}$/', $cleanPhone)) {
    sendResponse(false, 'Invalid phone number: Please provide a valid 10-digit mobile number starting with 6, 7, 8, or 9.', null, 400);
}

if (strlen($password) < 6) {
    sendResponse(false, 'Password is too weak: Password must be at least 6 characters long.', null, 400);
}

if ($password !== $confirmP) {
    sendResponse(false, 'Passwords do not match. Please verify and re-type.', null, 400);
}

if (!$terms) {
    sendResponse(false, 'You must agree to the Terms & Conditions to register.', null, 400);
}

try {
    // Check duplicate email
    $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $checkStmt->execute([$email]);
    if ($checkStmt->fetch()) {
        sendResponse(false, 'An account with this email address already exists. Please Login.', null, 409);
    }

    $pdo->beginTransaction();

    // 1. Create User
    $uInsert = $pdo->prepare('
        INSERT INTO users (name, email, phone, password, role, status, created_at) 
        VALUES (?, ?, ?, ?, "member", "active", NOW())
    ');
    $uInsert->execute([$fullName, $email, $cleanPhone, $password]);
    $newUserId = (int)$pdo->lastInsertId();

    // 2. Notification
    $notif = $pdo->prepare('
        INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
        VALUES (?, "Welcome to Alexa Gym! 🏋️", "Your account was created successfully. Choose your membership plan to start training!", "info", 0, NOW())
    ');
    $notif->execute([$newUserId]);

    $pdo->commit();

    sendResponse(true, 'Registration successful! Welcome to Alexa Gym.', [
        'user_id' => $newUserId,
        'name'    => $fullName,
        'email'   => $email,
        'phone'   => $cleanPhone,
        'gender'  => $gender,
        'dob'     => $dob,
        'address' => $address,
        'role'    => 'member'
    ], 201);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendResponse(false, 'Registration could not be completed: ' . $e->getMessage(), null, 500);
}
