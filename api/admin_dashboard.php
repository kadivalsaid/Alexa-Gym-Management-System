<?php
/**
 * Admin Dashboard API
 * Path: /api/admin_dashboard.php
 * Method: GET
 */

require_once __DIR__ . '/db.php';

try {
    // 1. KPI Stats
    $total_members = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active' AND plan_id IS NOT NULL")->fetchColumn();
    $all_members_count = (int)$pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
    $expiring_soon = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active' AND plan_id IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
    $total_trainers = (int)$pdo->query("SELECT COUNT(*) FROM trainers WHERE status = 'active'")->fetchColumn();
    $month_revenue = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE MONTH(paid_on) = MONTH(CURDATE()) AND YEAR(paid_on) = YEAR(CURDATE()) AND status = 'completed'")->fetchColumn();
    $total_revenue = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed'")->fetchColumn();
    $today_checkins = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE attend_date = CURDATE()")->fetchColumn();
    $pending_tickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'Pending'")->fetchColumn();

    // 2. Recent Active Members (Limit 6)
    $stmt = $pdo->query("
        SELECT m.id, m.full_name, m.email, m.phone, m.status, m.join_date, m.expiry_date,
               COALESCE(p.name, 'No Plan') AS plan_name,
               COALESCE(t.full_name, 'None') AS trainer_name
        FROM members m
        LEFT JOIN plans p ON m.plan_id = p.id
        LEFT JOIN trainers t ON m.trainer_id = t.id
        ORDER BY m.id DESC LIMIT 10
    ");
    $recent_members = $stmt->fetchAll();

    // 3. Recent Attendance Checkins (Today)
    $attStmt = $pdo->query("
        SELECT a.id, a.attend_date, a.check_in, a.status,
               m.full_name AS member_name, m.phone
        FROM attendance a
        INNER JOIN members m ON a.member_id = m.id
        WHERE a.attend_date = CURDATE()
        ORDER BY a.check_in DESC LIMIT 8
    ");
    $recent_attendance = $attStmt->fetchAll();

    // 4. Plans List with member count
    $plansStmt = $pdo->query("
        SELECT p.id, p.name, p.price, p.duration_months, p.features,
               COUNT(m.id) as active_subscribers
        FROM plans p
        LEFT JOIN members m ON m.plan_id = p.id AND m.status = 'active'
        GROUP BY p.id
        ORDER BY p.price ASC
    ");
    $plans = $plansStmt->fetchAll();

    // 5. PENDING MEMBERSHIP REQUESTS (Awaiting admin approval)
    $pendingStmt = $pdo->query("
        SELECT mr.id, mr.user_id, mr.full_name, mr.email, mr.phone, mr.payment_method, mr.transaction_id, mr.amount, mr.created_at,
               COALESCE(p.name, 'Gym Plan') AS plan_name
        FROM membership_requests mr
        LEFT JOIN plans p ON mr.plan_id = p.id
        WHERE mr.status = 'pending'
        ORDER BY mr.id DESC
    ");
    $pendingRequests = $pendingStmt->fetchAll();

    // 6. Recent Payments Ledger (Limit 6)
    $payStmt = $pdo->query("
        SELECT p.id, p.amount, p.method, p.paid_on, p.created_at,
               m.full_name AS member_name
        FROM payments p
        INNER JOIN members m ON p.member_id = m.id
        WHERE p.status = 'completed'
        ORDER BY p.id DESC LIMIT 6
    ");
    $recent_payments = $payStmt->fetchAll();

    sendResponse(true, 'Admin dashboard loaded successfully', [
        'stats' => [
            'active_members'   => $total_members,
            'total_members'    => $all_members_count,
            'expiring_soon'    => $expiring_soon,
            'total_trainers'   => $total_trainers,
            'month_revenue'    => $month_revenue,
            'monthly_revenue'  => $month_revenue,
            'total_revenue'    => $total_revenue,
            'today_checkins'   => $today_checkins,
            'today_attendance' => $today_checkins,
            'pending_tickets'  => $pending_tickets,
            'open_tickets'     => $pending_tickets,
            'pending_requests' => count($pendingRequests),
        ],
        'recent_members'     => $recent_members,
        'recent_attendance'  => $recent_attendance,
        'recent_payments'    => $recent_payments,
        'plans'              => $plans,
        'pending_requests'   => $pendingRequests,
    ]);

} catch (Exception $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
}
