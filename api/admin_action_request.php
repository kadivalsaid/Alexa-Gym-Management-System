<?php
/**
 * Admin Accept / Reject Membership Request API
 * Path: /api/admin_action_request.php
 * Method: POST
 * 
 * Payload: { "request_id": 1, "action": "accept" } // or "reject"
 */

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Only POST method is allowed', null, 405);
}

$input = getRequestData();
$requestId = (int)($input['request_id'] ?? 0);
$action = strtolower(trim($input['action'] ?? 'accept'));

if ($requestId <= 0) {
    sendResponse(false, 'Please provide request_id', null, 400);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT mr.*, p.duration_months, p.name AS plan_name, p.price AS plan_price
        FROM membership_requests mr
        LEFT JOIN plans p ON mr.plan_id = p.id
        WHERE mr.id = ? AND mr.status = 'pending'
        LIMIT 1
    ");
    $stmt->execute([$requestId]);
    $targetReq = $stmt->fetch();

    if (!$targetReq) {
        $pdo->rollBack();
        sendResponse(false, 'Pending request not found', null, 404);
    }

    if ($action === 'reject') {
        // Mark request as rejected
        $pdo->prepare("UPDATE membership_requests SET status = 'rejected' WHERE id = ?")->execute([$requestId]);

        // Send notification
        $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, 'Membership Request Update', 'Your gym membership request was declined by admin. Please contact support.', 'warning', 0, NOW())");
        $notif->execute([$targetReq['user_id']]);

        $pdo->commit();
        sendResponse(true, 'Membership request rejected');
    }

    // ACTION: ACCEPT
    $months = (int)($targetReq['duration_months'] ?? 1);
    if ($months <= 0) $months = 1;
    $startDate = date('Y-m-d');
    $expiryDate = date('Y-m-d', strtotime("+$months months"));

    // Check if member already exists
    $existStmt = $pdo->prepare("SELECT id FROM members WHERE user_id = ? OR (email = ? AND email != '') LIMIT 1");
    $existStmt->execute([$targetReq['user_id'], $targetReq['email']]);
    $existingM = $existStmt->fetch();

    if (!$existingM) {
        // Insert new member into members table
        $insM = $pdo->prepare("
            INSERT INTO members (user_id, full_name, email, phone, gender, dob, address, plan_id, trainer_id, join_date, expiry_date, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $insM->execute([
            $targetReq['user_id'],
            $targetReq['full_name'],
            $targetReq['email'],
            $targetReq['phone'],
            $targetReq['gender'] ?? 'male',
            !empty($targetReq['dob']) ? $targetReq['dob'] : null,
            $targetReq['address'] ?? '',
            $targetReq['plan_id'],
            $targetReq['trainer_id'] ?: 1,
            $startDate,
            $expiryDate
        ]);
        $memberId = (int)$pdo->lastInsertId();
    } else {
        $memberId = (int)$existingM['id'];
        $updM = $pdo->prepare("
            UPDATE members 
            SET plan_id = ?, trainer_id = ?, join_date = ?, expiry_date = ?, status = 'active', user_id = ?
            WHERE id = ?
        ");
        $updM->execute([
            $targetReq['plan_id'],
            $targetReq['trainer_id'] ?: 1,
            $startDate,
            $expiryDate,
            $targetReq['user_id'],
            $memberId
        ]);
    }

    // Record completed payment (exact website schema: method, plan_id, note, status)
    $pdo->prepare("
        INSERT INTO payments (member_id, plan_id, amount, method, transaction_id, paid_on, status, note, created_at) 
        VALUES (?, ?, ?, ?, ?, CURDATE(), 'completed', ?, NOW())
    ")->execute([
        $memberId,
        $targetReq['plan_id'],
        $targetReq['amount'] ?? ($targetReq['plan_price'] ?? 0),
        $targetReq['payment_method'] ?? 'upi',
        $targetReq['transaction_id'] ?? ('ADM-' . strtoupper(bin2hex(random_bytes(3)))),
        'Approved Plan: ' . ($targetReq['plan_name'] ?? 'Membership')
    ]);

    // Log in memberships table (same as website admin/members.php)
    if (!empty($targetReq['plan_id'])) {
        $pdo->prepare("
            INSERT INTO memberships (member_id, plan_id, start_date, end_date, price, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'active', NOW())
        ")->execute([
            $memberId,
            $targetReq['plan_id'],
            $startDate,
            $expiryDate,
            $targetReq['amount'] ?? ($targetReq['plan_price'] ?? 0.00)
        ]);
    }

    // Mark request as accepted / approved in membership_requests
    $pdo->prepare("UPDATE membership_requests SET status = 'approved' WHERE id = ?")->execute([$requestId]);

    // Send congratulations notification to member
    $notif = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
        VALUES (?, 'Membership Approved! 🎉', ?, 'success', 0, NOW())
    ");
    $notif->execute([
        $targetReq['user_id'],
        "Your membership plan {$targetReq['plan_name']} has been approved! Your full gym dashboard is now active until {$expiryDate}."
    ]);

    $pdo->commit();
    sendResponse(true, 'Member request successfully approved! Member is now active.', ['member_id' => $memberId]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendResponse(false, 'Approval failed: ' . $e->getMessage(), null, 500);
}
