<?php
/**
 * Admin Portal Complete API
 * Path: /api/admin_portal.php
 * Handles all Admin operations matching website:
 * - Members (Pending Requests & Active Members list)
 * - Plans (List with active counts, Add, Delete)
 * - Trainers (List with assigned counts, Add, Delete)
 * - Payments (Metrics, Manual Payment Record, List)
 * - Support Tickets (List, Reply & Update Status)
 * - Feedback & Reviews (List, Toggle Approval, Delete)
 * - Reports & Analytics (Financial & Plan distributions)
 */

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = getRequestData();
$action = $_GET['action'] ?? $input['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // =========================================================================
    // 1. MEMBERS (Pending Requests & Active Members)
    // =========================================================================
    case 'get_members':
        try {
            // Pending Requests
            $pStmt = $pdo->query("
                SELECT mr.*, p.name AS plan_name, p.duration_months
                FROM membership_requests mr
                LEFT JOIN plans p ON mr.plan_id = p.id
                WHERE mr.status = 'pending'
                ORDER BY mr.id DESC
            ");
            $pending = $pStmt->fetchAll();

            // All Members
            $mStmt = $pdo->query("
                SELECT m.*, p.name AS plan_name, t.full_name AS trainer_name
                FROM members m
                LEFT JOIN plans p ON m.plan_id = p.id
                LEFT JOIN trainers t ON m.trainer_id = t.id
                ORDER BY m.id DESC
            ");
            $members = $mStmt->fetchAll();

            sendResponse(true, 'Members data fetched', [
                'pending_requests' => $pending,
                'members' => $members
            ]);
        } catch (Exception $e) {
            sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
        }
        break;

    // =========================================================================
    // 2. PLANS (List, Add, Delete)
    // =========================================================================
    case 'get_plans':
        try {
            $stmt = $pdo->query("
                SELECT p.*, 
                       (SELECT COUNT(*) FROM members m WHERE m.plan_id = p.id AND m.status = 'active') AS member_count
                FROM plans p
                ORDER BY p.duration_months ASC
            ");
            sendResponse(true, 'Plans fetched', ['plans' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'add_plan':
        $data = getRequestData();
        $name = trim($data['name'] ?? '');
        $duration = (int)($data['duration_months'] ?? 1);
        $price = (float)($data['price'] ?? 0);
        $desc = trim($data['description'] ?? '');
        $features = trim($data['features'] ?? '');
        $popular = !empty($data['is_popular']) ? 1 : 0;

        if (empty($name) || $price <= 0) {
            sendResponse(false, 'Plan name and a valid price are required', null, 400);
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO plans (name, duration_months, price, description, features, is_popular, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([$name, $duration, $price, $desc, $features, $popular]);
            sendResponse(true, "Plan '$name' added successfully!");
        } catch (Exception $e) {
            sendResponse(false, 'Failed to add plan: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'delete_plan':
        $data = getRequestData();
        $planId = (int)($data['plan_id'] ?? $_GET['plan_id'] ?? 0);
        if ($planId <= 0) {
            sendResponse(false, 'Invalid plan ID', null, 400);
        }
        try {
            $pdo->prepare("DELETE FROM plans WHERE id = ?")->execute([$planId]);
            sendResponse(true, 'Plan removed successfully');
        } catch (Exception $e) {
            sendResponse(false, 'Failed to delete plan: ' . $e->getMessage(), null, 500);
        }
        break;

    // =========================================================================
    // 3. TRAINERS (List, Add, Delete)
    // =========================================================================
    case 'get_trainers':
        try {
            $stmt = $pdo->query("
                SELECT t.*, 
                       (SELECT COUNT(*) FROM members m WHERE m.trainer_id = t.id AND m.status = 'active') AS assigned_count
                FROM trainers t
                ORDER BY t.id DESC
            ");
            sendResponse(true, 'Trainers fetched', ['trainers' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'add_trainer':
        $data = getRequestData();
        $name = trim($data['full_name'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $email = trim($data['email'] ?? '');
        $spec = trim($data['specialization'] ?? 'Fitness & Conditioning');
        $exp = trim($data['experience'] ?? '3+ Years Experience');
        $salary = (float)($data['salary'] ?? 0);
        $bio = trim($data['bio'] ?? '');

        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (empty($name) || strlen($digits) !== 10) {
            sendResponse(false, 'Valid name and 10-digit mobile number required', null, 400);
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO trainers (full_name, email, phone, specialization, experience, bio, salary, joined_on, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 'active', NOW())
            ");
            $stmt->execute([$name, $email, $digits, $spec, $exp, $bio, $salary]);
            sendResponse(true, "Trainer $name added successfully!");
        } catch (Exception $e) {
            sendResponse(false, 'Failed to add trainer: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'delete_trainer':
        $data = getRequestData();
        $tId = (int)($data['trainer_id'] ?? $_GET['trainer_id'] ?? 0);
        if ($tId <= 0) {
            sendResponse(false, 'Invalid trainer ID', null, 400);
        }
        try {
            $pdo->prepare("DELETE FROM trainers WHERE id = ?")->execute([$tId]);
            sendResponse(true, 'Trainer removed successfully');
        } catch (Exception $e) {
            sendResponse(false, 'Failed to delete trainer: ' . $e->getMessage(), null, 500);
        }
        break;

    // =========================================================================
    // 4. PAYMENTS (Metrics, Manual Record, List)
    // =========================================================================
    case 'get_payments':
        try {
            $total = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed'")->fetchColumn();
            $upi = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND method = 'upi'")->fetchColumn();
            $cash = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND method = 'cash'")->fetchColumn();

            $pStmt = $pdo->query("
                SELECT p.*, m.full_name AS member_name, m.phone AS member_phone, pl.name AS plan_name
                FROM payments p
                LEFT JOIN members m ON p.member_id = m.id
                LEFT JOIN plans pl ON p.plan_id = pl.id
                ORDER BY p.id DESC
            ");
            $payments = $pStmt->fetchAll();

            sendResponse(true, 'Payments fetched', [
                'metrics' => [
                    'total_collected' => $total,
                    'upi_collected'   => $upi,
                    'cash_collected'  => $cash,
                ],
                'payments' => $payments
            ]);
        } catch (Exception $e) {
            sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'record_payment':
        $data = getRequestData();
        $memberId = (int)($data['member_id'] ?? 0);
        $amount = (float)($data['amount'] ?? 0);
        $method = trim($data['method'] ?? 'cash');
        $paidOn = trim($data['paid_on'] ?? date('Y-m-d'));
        $txnId = trim($data['transaction_id'] ?? 'OFFLINE-' . strtoupper(bin2hex(random_bytes(3))));
        $note = trim($data['note'] ?? '');

        if ($memberId <= 0 || $amount <= 0) {
            sendResponse(false, 'Please select a valid member and positive amount', null, 400);
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO payments (member_id, amount, method, transaction_id, paid_on, status, note, created_at)
                VALUES (?, ?, ?, ?, ?, 'completed', ?, NOW())
            ");
            $stmt->execute([$memberId, $amount, $method, $txnId, $paidOn, $note]);
            sendResponse(true, "Payment of ₹$amount recorded successfully!");
        } catch (Exception $e) {
            sendResponse(false, 'Failed to record payment: ' . $e->getMessage(), null, 500);
        }
        break;

    // =========================================================================
    // 5. SUPPORT TICKETS (List & Reply)
    // =========================================================================
    case 'get_tickets':
        try {
            $priority = trim($_GET['priority'] ?? '');
            $sql = "
                SELECT t.*, m.full_name AS member_name, m.phone AS member_phone, m.email AS member_email
                FROM tickets t
                JOIN members m ON t.member_id = m.id
                WHERE 1=1
            ";
            $params = [];
            if (!empty($priority)) {
                $sql .= " AND t.priority = ?";
                $params[] = $priority;
            }
            $sql .= " ORDER BY (CASE WHEN t.priority = 'High' THEN 1 WHEN t.priority = 'Medium' THEN 2 ELSE 3 END), t.id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'Pending'")->fetchColumn();

            sendResponse(true, 'Tickets fetched', [
                'pending_count' => $pendingCount,
                'tickets' => $stmt->fetchAll()
            ]);
        } catch (Exception $e) {
            sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'update_ticket':
        $data = getRequestData();
        $ticketId = (int)($data['ticket_id'] ?? 0);
        $newStatus = trim($data['status'] ?? 'In Progress');
        $adminReply = trim($data['admin_reply'] ?? '');

        if ($ticketId <= 0) {
            sendResponse(false, 'Invalid ticket ID', null, 400);
        }

        try {
            $stmt = $pdo->prepare("UPDATE tickets SET status = ?, admin_reply = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newStatus, $adminReply, $ticketId]);

            // Notify member
            $tRec = $pdo->prepare("SELECT m.user_id FROM tickets t JOIN members m ON t.member_id = m.id WHERE t.id = ?");
            $tRec->execute([$ticketId]);
            $uId = (int)$tRec->fetchColumn();

            if ($uId > 0) {
                $pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
                    VALUES (?, ?, ?, 'info', 0, NOW())
                ")->execute([
                    $uId,
                    "Ticket #$ticketId Updated",
                    "Status changed to $newStatus. Response: " . ($adminReply ?: 'Reviewed by desk.')
                ]);
            }

            sendResponse(true, 'Ticket updated successfully!');
        } catch (Exception $e) {
            sendResponse(false, 'Error updating ticket: ' . $e->getMessage(), null, 500);
        }
        break;

    // =========================================================================
    // 6. FEEDBACK & REVIEWS
    // =========================================================================
    case 'get_feedback':
        try {
            $stmt = $pdo->query("
                SELECT f.*, m.full_name AS member_name, m.phone AS member_phone
                FROM feedback f
                JOIN members m ON f.member_id = m.id
                ORDER BY f.id DESC
            ");
            sendResponse(true, 'Feedback fetched', ['feedbacks' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'toggle_feedback':
        $data = getRequestData();
        $fbId = (int)($data['feedback_id'] ?? $_GET['feedback_id'] ?? 0);
        if ($fbId <= 0) {
            sendResponse(false, 'Invalid feedback ID', null, 400);
        }
        try {
            $pdo->prepare("UPDATE feedback SET is_approved = NOT is_approved WHERE id = ?")->execute([$fbId]);
            sendResponse(true, 'Feedback visibility updated');
        } catch (Exception $e) {
            sendResponse(false, 'Error updating feedback: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'delete_feedback':
        $data = getRequestData();
        $fbId = (int)($data['feedback_id'] ?? $_GET['feedback_id'] ?? 0);
        if ($fbId <= 0) {
            sendResponse(false, 'Invalid feedback ID', null, 400);
        }
        try {
            $pdo->prepare("DELETE FROM feedback WHERE id = ?")->execute([$fbId]);
            sendResponse(true, 'Feedback deleted');
        } catch (Exception $e) {
            sendResponse(false, 'Error deleting feedback: ' . $e->getMessage(), null, 500);
        }
        break;

    // =========================================================================
    // 7. REPORTS & ANALYTICS
    // =========================================================================
    case 'get_reports':
        try {
            $totalRev = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed'")->fetchColumn();
            $monthRev = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE MONTH(paid_on) = MONTH(CURDATE()) AND YEAR(paid_on) = YEAR(CURDATE()) AND status = 'completed'")->fetchColumn();
            $totalCount = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'completed'")->fetchColumn();
            $avgTicket = $totalCount > 0 ? round($totalRev / $totalCount, 2) : 0;

            $planDist = $pdo->query("
                SELECT p.name AS plan_name, p.price, COUNT(m.id) AS member_count, COALESCE(SUM(p.price), 0) AS total_val
                FROM plans p
                LEFT JOIN members m ON m.plan_id = p.id AND m.status = 'active'
                GROUP BY p.id
                ORDER BY member_count DESC
            ")->fetchAll();

            $totalActiveMembers = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active' AND plan_id IS NOT NULL")->fetchColumn();

            sendResponse(true, 'Reports fetched', [
                'metrics' => [
                    'total_revenue' => $totalRev,
                    'month_revenue' => $monthRev,
                    'payments_count' => $totalCount,
                    'avg_ticket' => $avgTicket,
                    'active_members' => $totalActiveMembers,
                ],
                'plan_distribution' => $planDist
            ]);
        } catch (Exception $e) {
            sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
        }
        break;

    // =========================================================================
    // 8. ADMIN PROFILE & PASSWORD
    // =========================================================================
    case 'get_profile':
        try {
            $uId = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
            if ($uId <= 0) {
                sendResponse(false, 'Missing user_id', null, 400);
            }
            $stmt = $pdo->prepare("SELECT id, name, email, phone, role, status, created_at FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$uId]);
            $admin = $stmt->fetch();
            if (!$admin) {
                sendResponse(false, 'Admin user not found', null, 404);
            }
            sendResponse(true, 'Admin profile fetched', ['profile' => $admin]);
        } catch (Exception $e) {
            sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'update_profile':
        try {
            $data = !empty($input) ? $input : (json_decode(file_get_contents('php://input'), true) ?? $_POST);
            $uId = (int)($data['user_id'] ?? 0);
            $fullName = trim($data['full_name'] ?? $data['name'] ?? '');
            $phone = trim($data['phone'] ?? '');

            if ($uId <= 0 || empty($fullName)) {
                sendResponse(false, 'Full Name is required', null, 400);
            }

            $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?")->execute([$fullName, $phone, $uId]);
            sendResponse(true, 'Admin profile updated successfully!');
        } catch (Exception $e) {
            sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'change_password':
        try {
            $data = !empty($input) ? $input : (json_decode(file_get_contents('php://input'), true) ?? $_POST);
            $uId = (int)($data['user_id'] ?? 0);
            $currentPassword = (string)($data['current_password'] ?? '');
            $newPassword = (string)($data['new_password'] ?? '');

            if ($uId <= 0 || empty($currentPassword) || empty($newPassword)) {
                sendResponse(false, 'Please fill in all password fields.', null, 400);
            }

            if (strlen($newPassword) < 6) {
                sendResponse(false, 'New password must be at least 6 characters long.', null, 400);
            }

            $uStmt = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
            $uStmt->execute([$uId]);
            $currHash = (string)$uStmt->fetchColumn();

            $valid = false;
            if ($currentPassword === $currHash || trim($currentPassword) === trim($currHash) || password_verify($currentPassword, $currHash) || password_verify(trim($currentPassword), $currHash) || md5($currentPassword) === $currHash || sha1($currentPassword) === $currHash) {
                $valid = true;
            }

            if (!$valid) {
                sendResponse(false, 'Current password is incorrect.', null, 401);
            }

            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $uId]);
            sendResponse(true, 'Password updated successfully!');
        } catch (Exception $e) {
            sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
        }
        break;

    default:
        sendResponse(false, 'Unknown action: ' . $action, null, 400);
        break;
}
