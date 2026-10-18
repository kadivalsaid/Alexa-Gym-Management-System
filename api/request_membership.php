<?php
/**
 * Request Membership Plan API
 * Path: /api/request_membership.php
 * Method: POST
 * 
 * Payload: { "user_id": 5, "plan_id": 2, "payment_method": "upi", "phone": "9876543210", "transaction_id": "UPI-123" }
 */

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Only POST method is allowed', null, 405);
}

$input = getRequestData();
$userId = (int)($input['user_id'] ?? 0);
$planId = (int)($input['plan_id'] ?? 0);
$method = trim($input['payment_method'] ?? 'upi');
$phone  = trim($input['phone'] ?? '');
$txnId  = trim($input['transaction_id'] ?? '');

if ($userId <= 0 || $planId <= 0) {
    sendResponse(false, 'Please provide user_id and plan_id', null, 400);
}

try {
    // 1. Get User
    $uStmt = $pdo->prepare("SELECT id, name, email, phone FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([$userId]);
    $user = $uStmt->fetch();

    if (!$user) {
        sendResponse(false, 'User not found', null, 404);
    }

    // 2. Get Plan
    $pStmt = $pdo->prepare("SELECT * FROM plans WHERE id = ? LIMIT 1");
    $pStmt->execute([$planId]);
    $plan = $pStmt->fetch();

    if (!$plan) {
        sendResponse(false, 'Plan not found', null, 404);
    }

    if (empty($phone)) {
        $phone = $user['phone'] ?? '';
    }

    if (empty($txnId)) {
        $txnId = strtoupper($method) . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    $amount = (float)$plan['price'];

    $trainerId = (int)($input['trainer_id'] ?? 0);
    if ($trainerId <= 0) {
        $trainerId = 1;
    }

    // 3. Insert into membership_requests
    $pdo->beginTransaction();

    $reqIns = $pdo->prepare("
        INSERT INTO membership_requests (user_id, full_name, email, phone, gender, plan_id, trainer_id, payment_method, transaction_id, amount, status, created_at)
        VALUES (?, ?, ?, ?, 'male', ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $reqIns->execute([
        $user['id'],
        $user['name'],
        $user['email'],
        $phone,
        $plan['id'],
        $trainerId,
        $method,
        $txnId,
        $amount
    ]);

    // Notification for user
    $notif = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
        VALUES (?, 'Plan Request Submitted ⏳', ?, 'info', 0, NOW())
    ");
    $notif->execute([
        $user['id'],
        "Your request for plan {$plan['name']} (₹{$amount}) has been submitted for Admin approval."
    ]);

    // Notification for admin
    $admStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin'");
    $admIns = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
        VALUES (?, 'New Membership Request', ?, 'info', 0, NOW())
    ");
    while ($adm = $admStmt->fetch()) {
        $admIns->execute([
            $adm['id'],
            "{$user['name']} has requested the {$plan['name']} plan. Please review and accept."
        ]);
    }

    $pdo->commit();

    sendResponse(true, 'Membership request submitted successfully! Awaiting admin approval.', [
        'plan_name' => $plan['name'],
        'amount' => $amount,
        'transaction_id' => $txnId
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
