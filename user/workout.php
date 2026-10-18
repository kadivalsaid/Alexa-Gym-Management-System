<?php
/**
 * Alexa Gym — Member Portal: My Workout
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_member();

$u = get_logged_in_user();
$member = get_member_by_user_id($pdo, $u['id']);

if (!$member || empty($member['plan_id']) || ($member['status'] ?? '') !== 'active') {
    header('Location: dashboard.php');
    exit;
}

$categories = ['Chest', 'Back', 'Shoulders', 'Arms', 'Legs', 'Cardio'];
$workouts_by_cat = [];

foreach ($categories as $cat) {
    $workouts_by_cat[$cat] = [];
}

if ($member) {
    $stmt = $pdo->prepare("SELECT * FROM workout_assignments WHERE member_id = ? ORDER BY id ASC");
    $stmt->execute([$member['id']]);
    $all_w = $stmt->fetchAll();

    // If no specific workout assigned yet for new member, fallback to standard program
    if (empty($all_w)) {
        $fallback_stmt = $pdo->query("SELECT * FROM workout_assignments LIMIT 13");
        $all_w = $fallback_stmt->fetchAll();
    }

    foreach ($all_w as $w) {
        $cat = ucfirst(strtolower($w['category']));
        if (isset($workouts_by_cat[$cat])) {
            $workouts_by_cat[$cat][] = $w;
        }
    }
}

$page_title   = 'My Workout Plan — Alexa Gym';
$topbar_title = 'Personalized Workout Routine';
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
  <style>
    .exercise-idx-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      border-radius: 9px;
      background: rgba(0, 242, 254, 0.08);
      border: 1px solid rgba(0, 242, 254, 0.25);
      color: var(--cyan);
      font-weight: 800;
      font-size: 13px;
    }
    .set-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      padding: 6px 14px;
      border-radius: 999px;
      background: rgba(0, 242, 254, 0.1);
      border: 1px solid rgba(0, 242, 254, 0.25);
      color: var(--cyan);
      font-weight: 700;
      font-size: 13px;
      white-space: nowrap !important;
      letter-spacing: 0.3px;
    }
    .reps-num {
      font-size: 14.5px;
      font-weight: 700;
      color: var(--text);
      white-space: nowrap;
    }
  </style>
</head>
<body>

<div class="app-layout">
  <?php require_once __DIR__ . '/../includes/user_sidebar.php'; ?>

  <main class="app-main">
    <?php require_once __DIR__ . '/../includes/user_topbar.php'; ?>

    <div class="dashboard-content">
      <div style="margin-bottom: 28px;">
        <h2 style="font-size: 26px; font-weight: 800; margin-bottom: 4px;">My Assigned Training Split</h2>
        <p style="color: var(--muted); font-size: 14.5px;">Curated by your coach for optimal hypertrophy, strength conditioning, and progressive overload.</p>
      </div>

      <!-- Categories Navigation Tabs -->
      <div class="tabs-nav">
        <?php foreach ($categories as $idx => $cat): ?>
          <button type="button" class="tab-btn <?= $idx === 0 ? 'active' : '' ?>" data-tab="<?= strtolower($cat) ?>" onclick="switchTab('<?= strtolower($cat) ?>')">
            <i class="fa-solid <?= $cat === 'Cardio' ? 'fa-person-running' : 'fa-dumbbell' ?>" style="margin-right: 6px;"></i>
            <?= $cat ?> (<?= count($workouts_by_cat[$cat]) ?>)
          </button>
        <?php endforeach; ?>
      </div>

      <!-- Tab Panes -->
      <?php foreach ($categories as $idx => $cat): 
          $c_lower = strtolower($cat);
          $items = $workouts_by_cat[$cat];
      ?>
        <div class="tab-pane <?= $idx === 0 ? 'active' : '' ?>" id="tab-<?= $c_lower ?>">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title"><?= $cat ?> Workout Exercises</h3>
              <span style="color: var(--cyan); font-size: 13.5px; font-weight: 600;"><?= count($items) ?> Exercises Prescribed</span>
            </div>

            <div class="custom-table-wrap">
              <table class="custom-table">
                <thead>
                  <tr>
                    <th style="width: 70px; text-align: center;">No.</th>
                    <th>Exercise Name</th>
                    <th style="width: 120px; text-align: center;">Sets</th>
                    <th style="width: 130px; text-align: center;">Reps / Target</th>
                    <th style="width: 140px;">Duration</th>
                    <th>Trainer Coach Notes</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($items)): ?>
                    <?php foreach ($items as $e_idx => $ex): ?>
                      <tr>
                        <td style="text-align: center;">
                          <span class="exercise-idx-badge"><?= sprintf('%02d', $e_idx + 1) ?></span>
                        </td>
                        <td>
                          <strong style="font-size: 15px; color: var(--text);"><?= e($ex['exercise_name']) ?></strong>
                        </td>
                        <td style="text-align: center;">
                          <span class="set-badge"><i class="fa-solid fa-layer-group" style="font-size: 11px;"></i> <?= (int)$ex['sets'] ?> Sets</span>
                        </td>
                        <td style="text-align: center;">
                          <span class="reps-num"><?= e($ex['reps']) ?></span>
                          <small style="color: var(--muted); font-size: 11.5px; display: block;">Reps</small>
                        </td>
                        <td style="white-space: nowrap;">
                          <i class="fa-regular fa-clock" style="color: var(--cyan); margin-right: 5px;"></i> <?= e($ex['duration']) ?>
                        </td>
                        <td style="color: #cbd5e1; font-size: 13.5px; line-height: 1.4;">
                          <?= e($ex['trainer_notes'] ?: 'Maintain tight core and controlled tempo.') ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="6" style="text-align: center; color: var(--muted); padding: 30px;">
                        No exercises scheduled for <?= $cat ?> yet. Ask your coach to assign routines!
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

    </div>
  </main>
</div>

<script src="../assets/js/user.js?v=2.0"></script>
</body>
</html>
