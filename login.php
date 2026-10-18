<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Location: auth/login.php');
    exit;
}

header('Content-Type: application/json');
require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? $_POST;
    $email = trim($data['email'] ?? '');
    $password = (string)($data['password'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? OR name = ? LIMIT 1');
    $stmt->execute([$email, $email]);
    $user = $stmt->fetch();

    $verified = false;
    if ($user) {
        // Normal plain text password comparison
        if ($password === $user['password']) {
            $verified = true;
        }
    }

    if ($verified) {
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['logged_in'] = true;

        $redirect = ($user['role'] === 'admin') ? 'admin/dashboard.php' : 'user/dashboard.php';
        echo json_encode([
            'success' => true,
            'role' => $user['role'],
            'redirect' => $redirect
        ]);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Invalid email or password.']);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
