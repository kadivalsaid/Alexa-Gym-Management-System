<?php
/**
 * Member Dashboard API
 * Path: /api/get_dashboard.php
 * Method: GET
 * Query Params: ?user_id=5
 * 
 * Provides complete website-matching Member Dashboard dataset:
 * - State lifecycle: choose_plan, in_progress / pending_approval, active, expired
 * - 6 KPI Metric Cards matching website: Plan, Status, Expiry, Remaining Days, Attendance, Trainer
 * - Donut / Validity progress percentage & Days elapsed
 * - 4-Step Onboarding Stepper for pending members
 * - Today's Workout preview exercises
 * - Recent Attendance history
 * - User Profile details
 */

require_once __DIR__ . '/db.php';

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$memberId = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;

if ($userId <= 0 && $memberId <= 0) {
    sendResponse(false, 'Please provide user_id or member_id', null, 400);
}

try {
    if ($userId <= 0 && $memberId > 0) {
        $uStmt = $pdo->prepare("SELECT user_id FROM members WHERE id = ?");
        $uStmt->execute([$memberId]);
        $userId = (int)$uStmt->fetchColumn();
    }

    $uStmt = $pdo->prepare("SELECT id, name, email, phone, role, status FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([$userId]);
    $user = $uStmt->fetch();

    if (!$user) {
        sendResponse(false, 'User account not found', null, 404);
    }

    // 1. Fetch member profile
    $stmt = $pdo->prepare("
        SELECT m.*, 
               p.name AS plan_name, p.price AS plan_price, p.duration_months,
               t.full_name AS trainer_name, t.phone AS trainer_phone, t.specialization AS trainer_specialization
        FROM members m
        LEFT JOIN plans p ON m.plan_id = p.id
        LEFT JOIN trainers t ON m.trainer_id = t.id
        WHERE m.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $member = $stmt->fetch();

    // 2. Fetch pending request (if any)
    $reqStmt = $pdo->prepare("
        SELECT mr.*, p.name AS plan_name, p.price AS plan_price, p.duration_months,
               t.full_name AS trainer_name, t.specialization AS trainer_specialization
        FROM membership_requests mr
        LEFT JOIN plans p ON mr.plan_id = p.id
        LEFT JOIN trainers t ON mr.trainer_id = t.id
        WHERE mr.user_id = ? AND (mr.status = 'pending' OR mr.status = 'in_progress')
        ORDER BY mr.id DESC
        LIMIT 1
    ");
    $reqStmt->execute([$userId]);
    $pendingRequest = $reqStmt->fetch();

    // If member not yet active, but request is in progress/pending:
    if ((!$member || empty($member['plan_id']) || $member['status'] !== 'active') && $pendingRequest) {
        sendResponse(true, 'Membership request in progress', [
            'state' => 'pending_approval',
            'pending_request' => [
                'request_id'     => (int)$pendingRequest['id'],
                'plan_name'      => $pendingRequest['plan_name'],
                'duration_months'=> (int)($pendingRequest['duration_months'] ?? 1),
                'amount'         => (float)$pendingRequest['amount'],
                'payment_method' => strtoupper($pendingRequest['payment_method']),
                'transaction_id' => $pendingRequest['transaction_id'],
                'created_at'     => $pendingRequest['created_at'],
                'trainer_name'   => $pendingRequest['trainer_name'] ?? 'General Gym Coach',
                'trainer_specialization' => $pendingRequest['trainer_specialization'] ?? 'Certified Trainer',
            ],
            'user' => [
                'user_id' => (int)$user['id'],
                'name'    => $user['name'],
                'email'   => $user['email'],
                'phone'   => $pendingRequest['phone'] ?? $user['phone'] ?? '',
            ]
        ]);
    }

    // If no plan chosen yet:
    if (!$member || empty($member['plan_id']) || $member['status'] !== 'active') {
        sendResponse(true, 'No plan selected yet', [
            'state' => 'choose_plan',
            'user' => [
                'user_id' => (int)$user['id'],
                'name'    => $user['name'],
                'email'   => $user['email'],
                'phone'   => $user['phone'] ?? '',
            ]
        ]);
    }

    // 3. ACTIVE MEMBER FULL DASHBOARD
    $actualMemberId = (int)$member['id'];

    $today = new DateTime();
    $daysRemaining = 0;
    $daysElapsed   = 0;
    $totalDays     = 30;
    $progressPct   = 100;
    $isExpired     = false;

    if (!empty($member['expiry_date'])) {
        $expiryDate = new DateTime($member['expiry_date']);
        $startDate  = !empty($member['join_date']) ? new DateTime($member['join_date']) : clone $today;

        $totalDays  = max(1, (int)$startDate->diff($expiryDate)->days);
        $diff = $today->diff($expiryDate);

        if ($today > $expiryDate) {
            $isExpired     = true;
            $daysRemaining = 0;
            $daysElapsed   = $totalDays;
            $progressPct   = 0;
        } else {
            $daysRemaining = (int)$diff->days;
            $daysElapsed   = max(0, $totalDays - $daysRemaining);
            $progressPct   = min(100, max(0, (int)round(($daysRemaining / $totalDays) * 100)));
        }
    }

    // Attendance stats
    $attStmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE member_id = ? AND status = 'present'");
    $attStmt->execute([$actualMemberId]);
    $attCount = (int)$attStmt->fetchColumn();

    // Check if checked in today
    $todayAttStmt = $pdo->prepare("SELECT id FROM attendance WHERE member_id = ? AND attend_date = CURDATE() LIMIT 1");
    $todayAttStmt->execute([$actualMemberId]);
    $checkedInToday = (bool)$todayAttStmt->fetch();

    // Recent 4 Attendance logs
    $recentAttStmt = $pdo->prepare("
        SELECT attend_date, check_in, check_out, status 
        FROM attendance 
        WHERE member_id = ? 
        ORDER BY attend_date DESC 
        LIMIT 4
    ");
    $recentAttStmt->execute([$actualMemberId]);
    $recentAttendance = $recentAttStmt->fetchAll();

    // Today / Week Workouts
    $wStmt = $pdo->prepare("
        SELECT * 
        FROM workout_assignments 
        WHERE member_id = ? 
        ORDER BY id ASC 
        LIMIT 6
    ");
    $wStmt->execute([$actualMemberId]);
    $workouts = $wStmt->fetchAll();

    if (empty($workouts)) {
        // Fallback default exercises from website
        $workouts = [
            ['exercise_name' => 'Barbell Bench Press', 'sets' => 3, 'reps' => '12 Reps', 'category' => 'Chest', 'duration' => '15 mins'],
            ['exercise_name' => 'Incline Dumbbell Press', 'sets' => 4, 'reps' => '10 Reps', 'category' => 'Upper Chest', 'duration' => '12 mins'],
            ['exercise_name' => 'Cable Tricep Pushdown', 'sets' => 3, 'reps' => '15 Reps', 'category' => 'Triceps', 'duration' => '10 mins'],
        ];
    }

    $response = [
        'state' => $isExpired ? 'expired' : 'active',
        'profile' => [
            'member_id'      => $actualMemberId,
            'user_id'        => (int)$member['user_id'],
            'name'           => $member['full_name'],
            'email'          => $member['email'] ?: $user['email'],
            'phone'          => $member['phone'],
            'gender'         => $member['gender'] ?? 'male',
            'dob'            => $member['dob'] ?? '',
            'address'        => $member['address'] ?? '',
            'status'         => $member['status'],
            'join_date'      => $member['join_date'],
            'expiry_date'    => $member['expiry_date'],
            'days_remaining' => $daysRemaining,
            'days_elapsed'   => $daysElapsed,
            'total_days'     => $totalDays,
            'progress_pct'   => $progressPct,
            'is_expired'     => $isExpired,
        ],
        'membership' => [
            'plan_id'        => (int)$member['plan_id'],
            'plan_name'      => $member['plan_name'] ?? 'Active Plan',
            'plan_price'     => (float)($member['plan_price'] ?? 0),
            'duration_months'=> (int)($member['duration_months'] ?? 1),
            'trainer_name'   => $member['trainer_name'] ?? 'General Gym Coach',
            'trainer_phone'  => $member['trainer_phone'] ?? '',
            'trainer_specialization' => $member['trainer_specialization'] ?? 'Personal Coach',
        ],
        'stats' => [
            'attendance_count' => $attCount,
            'checked_in_today' => $checkedInToday,
        ],
        'recent_attendance' => $recentAttendance,
        'workouts' => $workouts
    ];

    sendResponse(true, 'Dashboard loaded successfully', $response);

} catch (Exception $e) {
    sendResponse(false, 'Error loading dashboard: ' . $e->getMessage(), null, 500);
}
