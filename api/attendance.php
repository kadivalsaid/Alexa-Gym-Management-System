<?php
/**
 * Attendance API (Member View & Admin Management)
 * Path: /api/attendance.php
 * Supports:
 * - Member: get attendance metrics (Total Days, Current Month, Percentage) + Daily Log Table
 * - Admin: view all members checked in on selected date
 * - Admin: record member check-in
 * - Admin: remove check-in record
 */

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// If GET request
if ($method === 'GET') {
    $memberId = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
    $date = trim($_GET['date'] ?? date('Y-m-d'));

    // ADMIN: Fetch all attendance for a date
    if ($action === 'admin_daily' || isset($_GET['date']) && $memberId <= 0) {
        try {
            $stmt = $pdo->prepare("
                SELECT a.id, a.member_id, a.attend_date, a.check_in, a.check_out, a.status,
                       m.full_name, m.phone, p.name AS plan_name
                FROM attendance a
                JOIN members m ON a.member_id = m.id
                LEFT JOIN plans p ON m.plan_id = p.id
                WHERE a.attend_date = ?
                ORDER BY a.check_in DESC
            ");
            $stmt->execute([$date]);
            $dailyList = $stmt->fetchAll();

            // All active members list for dropdown
            $mListStmt = $pdo->query("SELECT id, full_name, phone FROM members WHERE status = 'active' ORDER BY full_name ASC");
            $activeMembers = $mListStmt->fetchAll();

            sendResponse(true, 'Admin attendance data fetched', [
                'date' => $date,
                'total_present' => count($dailyList),
                'records' => $dailyList,
                'active_members' => $activeMembers
            ]);
        } catch (Exception $e) {
            sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
        }
    }

    // MEMBER: Fetch single member attendance stats & records
    if ($memberId > 0) {
        try {
            // 1. All records for this member
            $stmt = $pdo->prepare("
                SELECT id, attend_date, check_in, check_out, status 
                FROM attendance 
                WHERE member_id = ? 
                ORDER BY attend_date DESC 
                LIMIT 60
            ");
            $stmt->execute([$memberId]);
            $history = $stmt->fetchAll();
            $totalDays = count($history);

            // 2. Current Month count
            $mStmt = $pdo->prepare("
                SELECT COUNT(*) FROM attendance 
                WHERE member_id = ? 
                AND MONTH(attend_date) = MONTH(CURRENT_DATE()) 
                AND YEAR(attend_date) = YEAR(CURRENT_DATE())
            ");
            $mStmt->execute([$memberId]);
            $currentMonthDays = (int)$mStmt->fetchColumn();

            // 3. Percentage
            $workingDays = max(1, (int)date('j'));
            $percentage = min(100, (int)round(($currentMonthDays / $workingDays) * 100));

            // Today's log
            $today = date('Y-m-d');
            $todayStmt = $pdo->prepare("SELECT id, check_in, check_out, status FROM attendance WHERE member_id = ? AND attend_date = ? LIMIT 1");
            $todayStmt->execute([$memberId, $today]);
            $todayLog = $todayStmt->fetch();

            sendResponse(true, 'Member attendance data fetched', [
                'total_days' => $totalDays,
                'current_month_days' => $currentMonthDays,
                'percentage' => $percentage,
                'today' => $todayLog ?: null,
                'history' => $history
            ]);
        } catch (Exception $e) {
            sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
        }
    }

    sendResponse(false, 'Please provide member_id or date parameter', null, 400);

} elseif ($method === 'POST') {
    // ADMIN: Record member check-in or delete
    $input = getRequestData();
    $subAction = $input['action'] ?? $action ?? 'mark_checkin';

    if ($subAction === 'delete_checkin') {
        $delId = (int)($input['attendance_id'] ?? $input['id'] ?? 0);
        if ($delId <= 0) {
            sendResponse(false, 'Invalid attendance ID', null, 400);
        }
        try {
            $pdo->prepare("DELETE FROM attendance WHERE id = ?")->execute([$delId]);
            sendResponse(true, 'Attendance record removed successfully');
        } catch (Exception $e) {
            sendResponse(false, 'Error deleting record: ' . $e->getMessage(), null, 500);
        }
    }

    // Default: Admin Marks Check-in
    $memberId = (int)($input['member_id'] ?? 0);
    $attendDate = trim($input['attend_date'] ?? date('Y-m-d'));
    $checkInTime = trim($input['check_in'] ?? date('H:i:s'));

    if ($memberId <= 0) {
        sendResponse(false, 'Please select a valid member', null, 400);
    }

    try {
        // Check if already marked present on this date
        $checkStmt = $pdo->prepare("SELECT id, check_in FROM attendance WHERE member_id = ? AND attend_date = ?");
        $checkStmt->execute([$memberId, $attendDate]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            sendResponse(false, 'This member is already marked present for ' . $attendDate . ' at ' . $existing['check_in'], null, 409);
        }

        $insStmt = $pdo->prepare("
            INSERT INTO attendance (member_id, attend_date, check_in, status, created_at) 
            VALUES (?, ?, ?, 'present', NOW())
        ");
        $insStmt->execute([$memberId, $attendDate, $checkInTime]);

        sendResponse(true, 'Attendance check-in recorded successfully for ' . $attendDate, [
            'id' => $pdo->lastInsertId(),
            'member_id' => $memberId,
            'attend_date' => $attendDate,
            'check_in' => $checkInTime,
            'status' => 'present'
        ]);

    } catch (Exception $e) {
        sendResponse(false, 'Failed to record attendance: ' . $e->getMessage(), null, 500);
    }
} else {
    sendResponse(false, 'Method not allowed', null, 405);
}
