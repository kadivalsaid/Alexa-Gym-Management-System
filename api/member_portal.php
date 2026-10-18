<?php
/**
 * Member Portal Complete API
 * Path: /api/member_portal.php
 * Handles all Member Portal features matching website:
 * - Membership details & history
 * - Payments & Receipts
 * - Workouts split by muscle groups
 * - Assigned Trainer details
 * - Support Tickets (List & Create)
 * - Feedback & Reviews (List & Submit)
 * - Profile (Get, Update, Change Password)
 * - Notifications (Get, Mark Read)
 */

require_once __DIR__ . '/db.php';

$input = getRequestData();
$action = $_GET['action'] ?? $input['action'] ?? $_POST['action'] ?? '';
$userId = (int)($_GET['user_id'] ?? $input['user_id'] ?? $_POST['user_id'] ?? 0);
$memberId = (int)($_GET['member_id'] ?? $input['member_id'] ?? $_POST['member_id'] ?? 0);

// Resolve Member ID if only user_id provided
if ($memberId <= 0 && $userId > 0) {
    $mStmt = $pdo->prepare("SELECT id FROM members WHERE user_id = ? LIMIT 1");
    $mStmt->execute([$userId]);
    $memberId = (int)$mStmt->fetchColumn();
}

// Resolve User ID if only member_id provided
if ($userId <= 0 && $memberId > 0) {
    $uStmt = $pdo->prepare("SELECT user_id FROM members WHERE id = ? LIMIT 1");
    $uStmt->execute([$memberId]);
    $userId = (int)$uStmt->fetchColumn();
}

switch ($action) {

    // =========================================================================
    // 1. MEMBERSHIP & HISTORY
    // =========================================================================
    case 'get_membership':
        if ($userId <= 0 && $memberId <= 0) {
            sendResponse(false, 'Missing user_id or member_id', null, 400);
        }

        $stmt = $pdo->prepare("
            SELECT m.*, p.name AS plan_name, p.price AS plan_price, p.duration_months,
                   t.full_name AS trainer_name, t.specialization AS trainer_specialization
            FROM members m
            LEFT JOIN plans p ON m.plan_id = p.id
            LEFT JOIN trainers t ON m.trainer_id = t.id
            WHERE m.id = ? OR m.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$memberId, $userId]);
        $current = $stmt->fetch();

        $history = [];
        if ($current && !empty($current['id'])) {
            $hStmt = $pdo->prepare("
                SELECT ms.*, p.name AS plan_name, p.duration_months
                FROM memberships ms
                LEFT JOIN plans p ON ms.plan_id = p.id
                WHERE ms.member_id = ?
                ORDER BY ms.id DESC
            ");
            $hStmt->execute([$current['id']]);
            $history = $hStmt->fetchAll();
        }

        sendResponse(true, 'Membership details fetched', [
            'current' => $current,
            'history' => $history
        ]);
        break;

    // =========================================================================
    // 2. PAYMENTS & RECEIPTS
    // =========================================================================
    case 'get_payments':
        if ($memberId <= 0) {
            sendResponse(true, 'No payments yet', ['payments' => []]);
        }

        $pStmt = $pdo->prepare("
            SELECT p.*, pl.name AS plan_name, pl.duration_months
            FROM payments p
            LEFT JOIN plans pl ON p.plan_id = pl.id
            WHERE p.member_id = ?
            ORDER BY p.id DESC
        ");
        $pStmt->execute([$memberId]);
        $payments = $pStmt->fetchAll();

        sendResponse(true, 'Payments fetched', ['payments' => $payments]);
        break;

    // =========================================================================
    // 3. WORKOUTS (Split by category)
    // =========================================================================
    case 'get_workouts':
        $categories = ['Chest', 'Back', 'Shoulders', 'Arms', 'Legs', 'Cardio'];
        $grouped = [];
        foreach ($categories as $cat) {
            $grouped[$cat] = [];
        }

        // Fetch assigned workouts or default standard workouts
        $wStmt = $pdo->prepare("SELECT * FROM workout_assignments WHERE member_id = ? ORDER BY id ASC");
        $wStmt->execute([$memberId]);
        $workouts = $wStmt->fetchAll();

        if (empty($workouts)) {
            $wStmt2 = $pdo->query("SELECT * FROM workout_assignments WHERE member_id IS NULL ORDER BY id ASC");
            $workouts = $wStmt2->fetchAll();
        }

        foreach ($workouts as $w) {
            $cat = ucfirst(strtolower($w['category']));
            if (isset($grouped[$cat])) {
                $grouped[$cat][] = $w;
            } else {
                $grouped[$cat] = [$w];
            }
        }

        sendResponse(true, 'Workouts fetched', [
            'categories' => $categories,
            'workouts' => $grouped
        ]);
        break;

    // =========================================================================
    // 4. TRAINER DETAILS
    // =========================================================================
    case 'get_trainer':
        $trainer = null;
        if ($memberId > 0) {
            $tStmt = $pdo->prepare("
                SELECT t.* 
                FROM members m
                JOIN trainers t ON m.trainer_id = t.id
                WHERE m.id = ?
                LIMIT 1
            ");
            $tStmt->execute([$memberId]);
            $trainer = $tStmt->fetch();
        }

        if (!$trainer) {
            $trainer = $pdo->query("SELECT * FROM trainers WHERE status = 'active' ORDER BY id ASC LIMIT 1")->fetch();
        }

        sendResponse(true, 'Trainer details fetched', ['trainer' => $trainer]);
        break;

    // =========================================================================
    // 5. TICKETS
    // =========================================================================
    case 'get_tickets':
        if ($memberId <= 0) {
            sendResponse(true, 'No tickets found', ['tickets' => []]);
        }

        $tickStmt = $pdo->prepare("SELECT * FROM tickets WHERE member_id = ? ORDER BY id DESC");
        $tickStmt->execute([$memberId]);
        $tickets = $tickStmt->fetchAll();

        sendResponse(true, 'Tickets fetched', ['tickets' => $tickets]);
        break;

    case 'create_ticket':
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $mId = (int)($data['member_id'] ?? $memberId);
        $subject = trim($data['subject'] ?? '');
        $category = trim($data['category'] ?? 'General');
        $priority = trim($data['priority'] ?? 'Medium');
        $description = trim($data['description'] ?? '');

        if ($mId <= 0 || empty($subject) || empty($description)) {
            sendResponse(false, 'Member ID, subject and description are required', null, 400);
        }

        try {
            $ins = $pdo->prepare("
                INSERT INTO tickets (member_id, subject, category, priority, description, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'Pending', NOW())
            ");
            $ins->execute([$mId, $subject, $category, $priority, $description]);
            $ticketId = $pdo->lastInsertId();

            // Notify Admin
            $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
                VALUES (1, 'New Support Ticket Created', ?, 'info', 0, NOW())
            ")->execute(["New ticket #$ticketId: $subject"]);

            sendResponse(true, 'Ticket submitted successfully! Our staff will review it.', ['ticket_id' => $ticketId]);
        } catch (Exception $e) {
            sendResponse(false, 'Failed to create ticket: ' . $e->getMessage(), null, 500);
        }
        break;

    // =========================================================================
    // 6. FEEDBACK & REVIEWS
    // =========================================================================
    case 'get_feedback':
        $fbStmt = $pdo->prepare("SELECT * FROM feedback WHERE member_id = ? ORDER BY id DESC");
        $fbStmt->execute([$memberId]);
        $feedbacks = $fbStmt->fetchAll();

        sendResponse(true, 'Feedback fetched', ['feedbacks' => $feedbacks]);
        break;

    case 'submit_feedback':
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $mId = (int)($data['member_id'] ?? $memberId);
        $rating = (int)($data['rating'] ?? 5);
        $comment = trim($data['comment'] ?? '');

        if ($mId <= 0 || empty($comment)) {
            sendResponse(false, 'Member ID and comment are required', null, 400);
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO feedback (member_id, rating, comment, is_approved, created_at)
                VALUES (?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$mId, $rating, $comment]);

            sendResponse(true, 'Thank you! Your feedback has been submitted successfully.');
        } catch (Exception $e) {
            sendResponse(false, 'Error submitting feedback: ' . $e->getMessage(), null, 500);
        }
        break;

    // =========================================================================
    // 7. PROFILE & UPDATE
    // =========================================================================
    case 'get_profile':
        if ($userId <= 0) {
            sendResponse(false, 'Missing user_id', null, 400);
        }

        $stmt = $pdo->prepare("
            SELECT u.id AS user_id, u.name, u.email, u.phone AS user_phone, u.role, u.created_at AS user_created_at,
                   m.id AS member_id, m.full_name, m.phone AS member_phone, m.gender, m.dob, m.address,
                   m.join_date, m.expiry_date, m.status AS member_status,
                   p.name AS plan_name, t.full_name AS trainer_name
            FROM users u
            LEFT JOIN members m ON m.user_id = u.id
            LEFT JOIN plans p ON m.plan_id = p.id
            LEFT JOIN trainers t ON m.trainer_id = t.id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();

        sendResponse(true, 'Profile fetched', ['profile' => $profile]);
        break;

    case 'update_profile':
        $data = !empty($input) ? $input : (json_decode(file_get_contents('php://input'), true) ?? $_POST);
        $uId = (int)($data['user_id'] ?? $userId);
        $name = trim($data['name'] ?? $data['full_name'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $gender = trim($data['gender'] ?? 'male');
        $dob = !empty($data['dob']) ? trim($data['dob']) : null;
        $address = trim($data['address'] ?? '');

        if ($uId <= 0 || empty($name)) {
            sendResponse(false, 'User ID and full name are required', null, 400);
        }

        try {
            $pdo->beginTransaction();

            $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?")->execute([$name, $phone, $uId]);

            // Update member record if present
            $pdo->prepare("
                UPDATE members 
                SET full_name = ?, phone = ?, gender = ?, dob = ?, address = ?
                WHERE user_id = ?
            ")->execute([$name, $phone, $gender, $dob, $address, $uId]);

            $pdo->commit();
            sendResponse(true, 'Profile updated successfully!');
        } catch (Exception $e) {
            $pdo->rollBack();
            sendResponse(false, 'Failed to update profile: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'change_password':
        $data = !empty($input) ? $input : (json_decode(file_get_contents('php://input'), true) ?? $_POST);
        $uId = (int)($data['user_id'] ?? $userId);
        $currentPassword = (string)($data['current_password'] ?? '');
        $newPassword = (string)($data['new_password'] ?? '');

        if ($uId <= 0 || empty($currentPassword) || empty($newPassword)) {
            sendResponse(false, 'Current and new password are required', null, 400);
        }

        if (strlen($newPassword) < 6) {
            sendResponse(false, 'New password must be at least 6 characters', null, 400);
        }

        $userStmt = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $userStmt->execute([$uId]);
        $dbPass = (string)$userStmt->fetchColumn();

        $valid = false;
        if ($currentPassword === $dbPass || trim($currentPassword) === trim($dbPass) || password_verify($currentPassword, $dbPass) || password_verify(trim($currentPassword), $dbPass) || md5($currentPassword) === $dbPass || sha1($currentPassword) === $dbPass) {
            $valid = true;
        }

        if (!$valid) {
            sendResponse(false, 'Current password is incorrect.', null, 401);
        }

        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $uId]);

        sendResponse(true, 'Password changed successfully!');
        break;

    // =========================================================================
    // 8. NOTIFICATIONS
    // =========================================================================
    case 'get_notifications':
        if ($userId <= 0) {
            sendResponse(false, 'Missing user_id', null, 400);
        }

        $nStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 30");
        $nStmt->execute([$userId]);
        $notifs = $nStmt->fetchAll();

        $unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $unreadStmt->execute([$userId]);
        $unreadCount = (int)$unreadStmt->fetchColumn();

        sendResponse(true, 'Notifications fetched', [
            'notifications' => $notifs,
            'unread_count'  => $unreadCount
        ]);
        break;

    case 'mark_notifications_read':
        if ($userId <= 0) {
            sendResponse(false, 'Missing user_id', null, 400);
        }

        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$userId]);
        sendResponse(true, 'All notifications marked as read');
        break;

    default:
        sendResponse(false, 'Unknown action specified: ' . $action, null, 400);
        break;
}
