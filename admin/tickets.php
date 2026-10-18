<?php
/**
 * Alexa Gym — Admin Portal: Support Tickets
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$msg_success = '';
$msg_error   = '';

// Handle Status & Reply Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ticket'])) {
    $ticket_id   = (int)($_POST['ticket_id'] ?? 0);
    $new_status  = trim($_POST['status'] ?? 'In Progress');
    $admin_reply = trim($_POST['admin_reply'] ?? '');

    try {
        $stmt = $pdo->prepare("UPDATE tickets SET status = ?, admin_reply = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $admin_reply, $ticket_id]);

        // Find member's user_id to notify them
        $t_rec = $pdo->prepare("SELECT t.*, m.user_id, m.full_name FROM tickets t JOIN members m ON t.member_id = m.id WHERE t.id = ?");
        $t_rec->execute([$ticket_id]);
        $ticket_info = $t_rec->fetch();

        if ($ticket_info && !empty($ticket_info['user_id'])) {
            $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                VALUES (?, ?, ?, 'info', 0, NOW())
            ")->execute([
                $ticket_info['user_id'],
                "Support Ticket #TCK-" . str_pad($ticket_id, 4, '0', STR_PAD_LEFT) . " Updated",
                "Status changed to {$new_status}. Staff note: " . ($admin_reply ?: 'Your ticket has been reviewed.')
            ]);
        }

        $msg_success = "Ticket #TCK-" . str_pad($ticket_id, 4, '0', STR_PAD_LEFT) . " updated successfully!";
    } catch (Exception $e) {
        $msg_error = "Failed to update ticket: " . $e->getMessage();
    }
}

// Filter by Priority
$priority_filter = trim($_GET['priority'] ?? '');
$sql = "
    SELECT t.*, m.full_name AS member_name, m.phone AS member_phone, m.email AS member_email 
    FROM tickets t 
    JOIN members m ON t.member_id = m.id 
    WHERE 1=1
";
$params = [];
if (!empty($priority_filter)) {
    $sql .= " AND t.priority = ?";
    $params[] = $priority_filter;
}
$sql .= " ORDER BY (CASE WHEN t.priority = 'High' THEN 1 WHEN t.priority = 'Medium' THEN 2 ELSE 3 END), t.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets_list = $stmt->fetchAll();

$page_title   = 'Manage Tickets — Alexa Gym Admin';
$topbar_title = 'Member Support Helpdesk';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/admin.css?v=2.3">
</head>
<body>

<div class="app-layout">
  <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

  <main class="app-main">
    <?php require_once __DIR__ . '/../includes/admin_topbar.php'; ?>

    <div class="dashboard-content">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; flex-wrap: wrap; gap: 16px;">
        <div>
          <h2 style="font-size: 26px; font-weight: 800; margin-bottom: 4px;">Member Support Tickets</h2>
          <p style="color: var(--muted); font-size: 14.5px;">Resolve member queries regarding facilities, lockers, subscriptions, and training splits.</p>
        </div>
        <div>
          <form method="GET" action="tickets.php" style="display: flex; gap: 10px;">
            <select name="priority" class="form-control" onchange="this.form.submit()" style="width: 170px;">
              <option value="">All Priorities</option>
              <option value="High" <?= $priority_filter === 'High' ? 'selected' : '' ?>>High Priority</option>
              <option value="Medium" <?= $priority_filter === 'Medium' ? 'selected' : '' ?>>Medium Priority</option>
              <option value="Low" <?= $priority_filter === 'Low' ? 'selected' : '' ?>>Low Priority</option>
            </select>
          </form>
        </div>
      </div>

      <?php if (!empty($msg_success)): ?>
        <div class="alert alert-success" style="margin-bottom: 20px;">
          <i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i> <?= e($msg_success) ?>
        </div>
      <?php elseif (!empty($msg_error)): ?>
        <div class="alert alert-danger" style="margin-bottom: 20px;">
          <i class="fa-solid fa-triangle-exclamation" style="margin-right: 8px;"></i> <?= e($msg_error) ?>
        </div>
      <?php endif; ?>

      <div class="card">
        <div class="custom-table-wrap">
          <table class="custom-table">
            <thead>
              <tr>
                <th style="width: 70px;">ID</th>
                <th>Member</th>
                <th>Subject &amp; Issue</th>
                <th>Priority</th>
                <th>Staff Reply</th>
                <th style="text-align: right; width: 100px;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($tickets_list)): ?>
                <?php foreach ($tickets_list as $t): ?>
                  <tr>
                    <td><strong style="color: var(--cyan); font-size: 14px; font-weight: 700;"><?= (int)$t['id'] ?></strong></td>
                    <td>
                      <strong style="color: var(--text);"><?= e($t['member_name']) ?></strong>
                      <small style="display: block; color: var(--muted); font-size: 12px;"><?= e($t['member_phone']) ?></small>
                    </td>
                    <td>
                      <strong style="color: var(--text); font-size: 14.5px; display: block; margin-bottom: 2px;"><?= e($t['subject']) ?></strong>
                      <div style="color: var(--muted); font-size: 12.5px;"><?= e(substr($t['description'], 0, 80)) ?><?= strlen($t['description']) > 80 ? '...' : '' ?></div>
                    </td>
                    <td>
                      <strong style="color: <?= $t['priority'] === 'High' ? 'var(--bad)' : ($t['priority'] === 'Medium' ? 'var(--warning)' : 'var(--muted)') ?>; font-size: 13px;">
                        <?= e($t['priority']) ?>
                      </strong>
                    </td>
                    <td style="font-size: 13px;">
                      <?= !empty($t['admin_reply']) ? e(substr($t['admin_reply'], 0, 45)) . (strlen($t['admin_reply']) > 45 ? '...' : '') : '<span style="color:var(--muted); font-style:italic;">None</span>' ?>
                    </td>
                    <td style="text-align: right;">
                      <button type="button" class="btn btn-primary btn-sm" style="padding: 6px 14px; font-weight: 700;"
                              onclick="openReplyModal(<?= $t['id'] ?>, '<?= e(addslashes($t['subject'])) ?>', '<?= e(addslashes($t['status'])) ?>', '<?= e(addslashes($t['admin_reply'] ?? '')) ?>')">
                        <i class="fa-solid fa-reply"></i> Reply
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" style="text-align: center; color: var(--muted); padding: 32px;">No support tickets found.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- Reply Modal -->
<div class="modal-backdrop" id="reply-ticket-modal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 18px; font-weight: 800;"><i class="fa-solid fa-headset" style="color: var(--cyan); margin-right: 8px;"></i> Resolve Ticket</h3>
      <button type="button" class="action-btn-circle" onclick="closeModal('reply-ticket-modal')">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form method="POST" action="tickets.php">
      <input type="hidden" name="ticket_id" id="reply-ticket-id" value="0">
      
      <div class="modal-body">
        <div style="margin-bottom: 16px;">
          <span style="font-size: 12px; color: var(--muted); text-transform: uppercase;">Ticket Subject</span>
          <h4 id="reply-ticket-subject" style="font-size: 16px; font-weight: 700; color: var(--text);">Subject Here</h4>
        </div>

        <div style="margin-bottom: 16px;">
          <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Update Status</label>
          <select name="status" id="reply-ticket-status" class="form-control">
            <option value="Pending">Pending</option>
            <option value="In Progress">In Progress</option>
            <option value="Resolved">Resolved</option>
          </select>
        </div>

        <div style="margin-bottom: 16px;">
          <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Admin Response / Staff Notes *</label>
          <textarea name="admin_reply" id="reply-ticket-text" rows="4" class="form-control" placeholder="Type resolution or response to member..." required></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('reply-ticket-modal')">Cancel</button>
        <button type="submit" name="update_ticket" class="btn btn-primary btn-sm">Save &amp; Notify Member</button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/js/user.js?v=2.0"></script>
<script>
function openReplyModal(id, subject, status, reply) {
    document.getElementById('reply-ticket-id').value = id;
    document.getElementById('reply-ticket-subject').innerText = subject;
    document.getElementById('reply-ticket-status').value = status;
    document.getElementById('reply-ticket-text').value = reply;
    openModal('reply-ticket-modal');
}
</script>
</body>
</html>
