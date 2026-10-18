<?php
/**
 * Alexa Gym — Trainers Page & API
 * Supports both Public Trainers HTML view and Admin SPA JSON API
 */

$is_api_request = (
    $_SERVER['REQUEST_METHOD'] === 'POST' ||
    isset($_GET['action']) ||
    (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
    isset($_GET['api'])
);

if ($is_api_request) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    require_once __DIR__ . '/config/database.php';

    $action = $_GET['action'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        if ($action === 'create' || $action === 'update') {
            $data = [trim($input['full_name'] ?? ''), trim($input['phone'] ?? ''), trim($input['specialization'] ?? ''), (float)($input['salary'] ?? 0), ($input['joined_on'] ?: null)];
            
            if ($action === 'create') {
                $pdo->prepare('INSERT INTO trainers (full_name,phone,specialization,salary,joined_on) VALUES (?,?,?,?,?)')->execute($data);
                echo json_encode(['success' => true, 'message' => 'Trainer added.']);
                exit;
            } elseif ($action === 'update') {
                $data[] = (int)$input['id'];
                $pdo->prepare('UPDATE trainers SET full_name=?,phone=?,specialization=?,salary=?,joined_on=? WHERE id=?')->execute($data);
                echo json_encode(['success' => true, 'message' => 'Trainer updated.']);
                exit;
            }
        }
    }

    if ($action === 'delete' && isset($_GET['id'])) {
        $pdo->prepare('DELETE FROM trainers WHERE id=?')->execute([(int)$_GET['id']]);
        echo json_encode(['success' => true, 'message' => 'Trainer removed.']);
        exit;
    }

    if ($action === 'edit' && isset($_GET['id'])) {
        $st = $pdo->prepare('SELECT * FROM trainers WHERE id=?');
        $st->execute([(int)$_GET['id']]);
        $edit = $st->fetch() ?: null;
        echo json_encode(['success' => true, 'trainer' => $edit]);
        exit;
    }

    $trainers = $pdo->query('SELECT * FROM trainers ORDER BY id DESC')->fetchAll();

    echo json_encode([
        'success' => true,
        'trainers' => $trainers,
        'user' => [
            'name' => $_SESSION['user_name'] ?? 'Admin',
            'role' => $_SESSION['user_role'] ?? 'Admin'
        ]
    ]);
    exit;
}

// ----------------------------------------------------
// PUBLIC TRAINERS HTML PAGE
// ----------------------------------------------------
$page_title = 'Expert Fitness Coaches & Personal Trainers — Alexa Gym';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

try {
    $t_stmt = $pdo->query("SELECT * FROM trainers WHERE status = 'active' ORDER BY id ASC");
    $trainers_list = $t_stmt->fetchAll();
} catch (Exception $e) {
    $trainers_list = [];
}

// Fallback images for trainers
$default_photos = [
    'assets/images/trainer-1.jpg',
    'assets/images/trainer-2.jpg',
    'assets/images/trainer-3.jpg',
    'assets/images/trainer-4.jpg'
];

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<section style="padding: 130px 0 60px 0; background: radial-gradient(circle at top right, rgba(124, 58, 237, 0.15), transparent 45%), var(--bg); border-bottom: 1px solid var(--line);">
  <div class="container text-center" style="text-align: center;">
    <div class="section-tag"><i class="fa-solid fa-certificate"></i> Certified Coaches</div>
    <h1 class="section-title">Our Expert Coaching Staff</h1>
    <p class="section-desc" style="margin: 0 auto; max-width: 650px;">
      Work 1-on-1 with elite coaches who hold international credentials and a passion for your personal triumph.
    </p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="trainers-grid">
      <?php if (!empty($trainers_list)): ?>
        <?php foreach ($trainers_list as $idx => $tr): 
            $photo_src = $tr['photo'] ? $tr['photo'] : ($base_url . '/' . $default_photos[$idx % count($default_photos)]);
        ?>
          <div class="trainer-card">
            <div class="trainer-photo-wrap">
              <img src="<?= e($photo_src) ?>" alt="<?= e($tr['full_name']) ?>">
              <div class="trainer-overlay"></div>
            </div>
            <div class="trainer-info">
              <div class="trainer-spec"><?= e($tr['specialization'] ?: 'General Fitness Coach') ?></div>
              <h3 class="trainer-name"><?= e($tr['full_name']) ?></h3>
              <div class="trainer-exp"><i class="fa-solid fa-briefcase"></i> <?= e($tr['experience'] ?: '3+ Years Experience') ?></div>
              <p class="trainer-bio"><?= e($tr['bio'] ?: 'Dedicated to helping members establish proper biomechanics, continuous overload, and dietary discipline.') ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="text-center" style="color: var(--text-muted);">Trainer profiles are being updated. Check back shortly!</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
