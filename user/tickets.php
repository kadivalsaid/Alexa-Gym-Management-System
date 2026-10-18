<?php
/**
 * Alexa Gym — Member Portal: Support Tickets
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_member();

$u = get_logged_in_user();
$member = get_member_by_user_id($pdo, $u['id']);

$ticket_success = '';
$ticket_error   = '';

// Handle New Ticket Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    $subject  = trim($_POST['subject'] ?? '');
    $category = trim($_POST['category'] ?? 'General');
    $priority = trim($_POST['priority'] ?? 'Medium');
    $desc     = trim($_POST['description'] ?? '');

    if (empty($subject) || empty($desc)) {
        $ticket_error = 'Subject and Description are required.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO tickets (member_id, subject, category, priority, description, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'Pending', NOW())
            ");
            $stmt->execute([$member['id'], $subject, $category, $priority, $desc]);

            // Notify Admin
            $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                VALUES (1, 'New Member Support Ticket', ?, 'info', 0, NOW())
            ")->execute(["New ticket #{$pdo->lastInsertId()} from {$u['name']}: {$subject}"]);

            $ticket_success = 'Your support ticket has been submitted! Our desk or coaches will review and respond.';
        } catch (Exception $e) {
            $ticket_error = 'Failed to submit ticket: ' . $e->getMessage();
        }
    }
}

// Fetch member's tickets
$tickets = [];
if ($member) {
    $t_stmt = $pdo->prepare("SELECT * FROM tickets WHERE member_id = ? ORDER BY id DESC");
    $t_stmt->execute([$member['id']]);
    $tickets = $t_stmt->fetchAll();
}

$page_title   = 'Support Tickets — Alexa Gym';
$topbar_title = 'Member Helpdesk & Tickets';
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
  <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

  <main class="app-main">
    <?php require_once __DIR__ . '/../includes/user_topbar.php'; ?>

    <div class="dashboard-content">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; flex-wrap: wrap; gap: 16px;">
        <div>
          <h2 style="font-size: 26px; font-weight: 800; margin-bottom: 4px;">Support &amp; Service Tickets</h2>
          <p style="color: var(--muted); font-size: 14.5px;">Submit inquiries about equipment, lockers, trainer scheduling, or billing.</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="openModal('new-ticket-modal')">
          <i class="fa-solid fa-plus"></i> Create New Ticket
        </button>
      </div>

      <?php if (!empty($ticket_success)): ?>
        <div class="alert alert-success" style="margin-bottom: 22px;">
          <i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i> <?= e($ticket_success) ?>
        </div>
      <?php elseif (!empty($ticket_error)): ?>
        <div class="alert alert-danger" style="margin-bottom: 22px;">
          <i class="fa-solid fa-triangle-exclamation" style="margin-right: 8px;"></i> <?= e($ticket_error) ?>
        </div>
      <?php endif; ?>

      <!-- Ticket History Table -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><i class="fa-solid fa-ticket" style="color: var(--cyan); margin-right: 8px;"></i> My Ticket History</h3>
        </div>

        <div class="custom-table-wrap">
          <table class="custom-table">
            <thead>
              <tr>
                <th style="width: 130px; white-space: nowrap;">Ticket ID</th>
                <th>Subject</th>
                <th>Category</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Admin Response</th>
                <th style="white-space: nowrap;">Submitted</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($tickets)): ?>
                <?php foreach ($tickets as $t): ?>
                  <tr>
                    <td style="white-space: nowrap;"><strong class="code-badge">#TCK-<?= str_pad($t['id'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                    <td>
                      <strong style="color: var(--text); display: block;"><?= e($t['subject']) ?></strong>
                      <small style="color: var(--muted);"><?= e(substr($t['description'], 0, 75)) ?>...</small>
                    </td>
                    <td><span class="badge-status" style="background: var(--panel-2); color: var(--cyan);"><?= e($t['category']) ?></span></td>
                    <td>
                      <span style="font-weight: 700; color: <?= $t['priority'] === 'High' ? 'var(--bad)' : ($t['priority'] === 'Medium' ? 'var(--warning)' : 'var(--muted)') ?>;">
                        <?= e($t['priority']) ?>
                      </span>
                    </td>
                    <td>
                      <?php 
                        $st = strtolower($t['status']);
                        $badge_cls = ($st === 'resolved') ? 'resolved' : (($st === 'in progress') ? 'in-progress' : 'pending');
                      ?>
                      <span class="badge-status <?= $badge_cls ?>">
                        <?= strtoupper($t['status']) ?>
                      </span>
                    </td>
                    <td style="max-width: 250px; font-size: 13.5px;">
                      <?php if (!empty($t['admin_reply'])): ?>
                        <span style="color: var(--ok);"><i class="fa-solid fa-reply" style="margin-right: 4px;"></i> <?= e($t['admin_reply']) ?></span>
                      <?php else: ?>
                        <span style="color: var(--muted); font-style: italic;">Awaiting staff review</span>
                      <?php endif; ?>
                    </td>
                    <td style="white-space: nowrap;"><?= format_date($t['created_at']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" style="text-align: center; color: var(--muted); padding: 32px;">No support tickets created yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- New Ticket Modal -->
<div class="modal-backdrop" id="new-ticket-modal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 style="font-size: 18px; font-weight: 800;"><i class="fa-solid fa-headset" style="color: var(--cyan); margin-right: 8px;"></i> Create Support Ticket</h3>
      <button type="button" class="action-btn-circle" onclick="closeModal('new-ticket-modal')">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <form method="POST" action="tickets.php">
      <div class="modal-body">
        <div style="margin-bottom: 16px;">
          <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Subject *</label>
          <input type="text" name="subject" class="form-control" placeholder="e.g. Locker Assignment or Equipment Issue" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
          <div>
            <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Category</label>
            <select name="category" class="form-control">
              <option value="General">General Inquiry</option>
              <option value="Billing">Billing &amp; Payments</option>
              <option value="Trainer" <?= (isset($_GET['category']) && $_GET['category'] === 'Trainer') ? 'selected' : '' ?>>Trainer Consultation</option>
              <option value="Equipment">Gym Equipment Maintenance</option>
              <option value="Locker">Locker &amp; Facilities</option>
            </select>
          </div>
          <div>
            <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Priority Level</label>
            <select name="priority" class="form-control">
              <option value="Low">Low</option>
              <option value="Medium" selected>Medium</option>
              <option value="High">High (Urgent)</option>
            </select>
          </div>
        </div>

        <div style="margin-bottom: 16px;">
          <label style="display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 6px;">Description *</label>
          <textarea name="description" rows="4" class="form-control" placeholder="Please describe your request in detail..." required></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('new-ticket-modal')">Cancel</button>
        <button type="submit" name="submit_ticket" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-paper-plane"></i> Submit Ticket
        </button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/js/user.js?v=2.0"></script>
<?php if (isset($_GET['category']) && $_GET['category'] === 'Trainer'): ?>
<script>
  window.addEventListener('DOMContentLoaded', () => {
    openModal('new-ticket-modal');
  });
</script>
<?php endif; ?>
</body>
</html>
