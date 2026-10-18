<?php
/**
 * Alexa Gym — Membership Plans Page & API
 * Supports both Public Plans HTML view and Admin SPA JSON API
 */

// If requested via API (JSON/AJAX or POST/Action)
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
            $data = [trim($input['name'] ?? ''), (int)($input['duration_months'] ?? 0), (float)($input['price'] ?? 0), trim($input['description'] ?? '')];
            
            if ($action === 'create') {
                $pdo->prepare('INSERT INTO plans (name,duration_months,price,description) VALUES (?,?,?,?)')->execute($data);
                echo json_encode(['success' => true, 'message' => 'Plan created.']);
                exit;
            } elseif ($action === 'update') {
                $data[] = (int)$input['id'];
                $pdo->prepare('UPDATE plans SET name=?,duration_months=?,price=?,description=? WHERE id=?')->execute($data);
                echo json_encode(['success' => true, 'message' => 'Plan updated.']);
                exit;
            }
        }
    }

    if ($action === 'delete' && isset($_GET['id'])) {
        $pdo->prepare('DELETE FROM plans WHERE id=?')->execute([(int)$_GET['id']]);
        echo json_encode(['success' => true, 'message' => 'Plan deleted.']);
        exit;
    }

    if ($action === 'edit' && isset($_GET['id'])) {
        $st = $pdo->prepare('SELECT * FROM plans WHERE id=?');
        $st->execute([(int)$_GET['id']]);
        $edit = $st->fetch() ?: null;
        echo json_encode(['success' => true, 'plan' => $edit]);
        exit;
    }

    $plans = $pdo->query('SELECT p.*, (SELECT COUNT(*) FROM members m WHERE m.plan_id = p.id AND m.status = \'active\') AS member_count FROM plans p ORDER BY duration_months')->fetchAll();

    echo json_encode([
        'success' => true,
        'plans' => $plans,
        'user' => [
            'name' => $_SESSION['user_name'] ?? 'Admin',
            'role' => $_SESSION['user_role'] ?? 'Admin'
        ]
    ]);
    exit;
}

// ----------------------------------------------------
// PUBLIC MEMBERSHIP PLANS HTML PAGE
// ----------------------------------------------------
$page_title = 'Membership Plans & Pricing — Alexa Gym';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

try {
    $plans_stmt = $pdo->query("SELECT * FROM plans WHERE status = 'active' ORDER BY price ASC");
    $plans_list = $plans_stmt->fetchAll();
} catch (Exception $e) {
    $plans_list = [];
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<section style="padding: 130px 0 60px 0; background: radial-gradient(circle at top center, rgba(0, 242, 254, 0.15), transparent 45%), var(--bg); border-bottom: 1px solid var(--line);">
  <div class="container text-center" style="text-align: center;">
    <div class="section-tag"><i class="fa-solid fa-tag"></i> Membership Pricing</div>
    <h1 class="section-title">Transparent &amp; Flexible Plans</h1>
    <p class="section-desc" style="margin: 0 auto; max-width: 650px;">
      Invest in yourself. Every plan comes packed with elite facility access, locker privileges, and certified coach support.
    </p>
  </div>
</section>

<section class="section">
  <div class="container">
    <?php
    $is_logged = is_logged_in();
    $curr_u = get_logged_in_user();
    $r = $curr_u['role'] ?? '';
    ?>
    <?php if ($is_logged && $r === 'admin'): ?>
      <div style="background: rgba(124, 58, 237, 0.12); border: 1px solid var(--purple); border-radius: var(--radius-md); padding: 14px 22px; margin-bottom: 35px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px; color: var(--text);">
          <i class="fa-solid fa-shield-halved" style="color: var(--cyan); font-size: 20px;"></i>
          <span><strong>Administrator Notice:</strong> You are viewing the public plans catalog. To edit prices, create new plans, or view plan analytics, access the Admin Plans Portal.</span>
        </div>
        <a href="<?= $base_url ?>/admin/plans.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-gear"></i> Admin Plans Portal</a>
      </div>
    <?php elseif ($is_logged && $r === 'member'): ?>
      <div style="background: rgba(0, 242, 254, 0.1); border: 1px solid var(--cyan); border-radius: var(--radius-md); padding: 14px 22px; margin-bottom: 35px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px; color: var(--text);">
          <i class="fa-solid fa-circle-check" style="color: var(--ok); font-size: 20px;"></i>
          <span><strong>Member Account:</strong> Welcome back! Click any plan below to select or upgrade your membership in your Member Portal.</span>
        </div>
        <a href="<?= $base_url ?>/user/plans.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-bolt"></i> Member Plans Portal</a>
      </div>
    <?php endif; ?>

    <div class="pricing-grid">
      <?php if (!empty($plans_list)): ?>
        <?php foreach ($plans_list as $plan): 
            $features = !empty($plan['features']) ? explode("\n", trim($plan['features'])) : [
                'Full Gym Floor Access',
                'Cardio & Strength Training Zone',
                'Locker Room & Shower Access',
                'Free Initial Assessment'
            ];
            $is_popular = !empty($plan['is_popular']);
        ?>
          <div class="pricing-card <?= $is_popular ? 'popular' : '' ?>">
            <?php if ($is_popular): ?>
              <div class="popular-badge">RECOMMENDED</div>
            <?php endif; ?>
            <div class="plan-name"><?= e($plan['name']) ?></div>
            <div class="plan-desc"><?= e($plan['description'] ?: 'Structured fitness access designed for your progression.') ?></div>
            <div class="plan-price-wrap">
              <span class="plan-currency">₹</span>
              <span class="plan-amount"><?= number_format((float)$plan['price'], 0) ?></span>
              <span class="plan-duration">/ <?= (int)$plan['duration_months'] === 1 ? 'Month' : (int)$plan['duration_months'] . ' Months' ?></span>
            </div>
            <ul class="plan-features-list">
              <?php foreach ($features as $feat): ?>
                <?php if (!empty(trim($feat))): ?>
                  <li><i class="fa-solid fa-circle-check"></i> <?= e(trim($feat)) ?></li>
                <?php endif; ?>
              <?php endforeach; ?>
            </ul>
            <?php
            $is_user = is_logged_in();
            $logged_user = get_logged_in_user();
            $user_role = $logged_user['role'] ?? '';

            if ($user_role === 'admin') {
                $plan_btn_url = $base_url . '/admin/plans.php';
                $plan_btn_text = '<i class="fa-solid fa-pen-to-square"></i> Manage Plan';
            } elseif ($user_role === 'member') {
                $plan_btn_url = $base_url . '/user/plans.php?plan_id=' . (int)$plan['id'];
                $plan_btn_text = '<i class="fa-solid fa-bolt"></i> Choose Plan';
            } else {
                $plan_btn_url = $base_url . '/auth/register.php?plan_id=' . (int)$plan['id'];
                $plan_btn_text = 'JOIN NOW';
            }
            ?>
            <a href="<?= $plan_btn_url ?>" class="btn <?= $is_popular ? 'btn-primary' : 'btn-outline' ?> btn-block">
              <?= $plan_btn_text ?>
            </a>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <!-- Preset Default Cards -->
        <?php
        $is_user = is_logged_in();
        $logged_user = get_logged_in_user();
        $user_role = $logged_user['role'] ?? '';

        if ($user_role === 'admin') {
            $fallback_url = $base_url . '/admin/plans.php';
            $fallback_text = '<i class="fa-solid fa-pen-to-square"></i> Manage Plans';
        } elseif ($user_role === 'member') {
            $fallback_url = $base_url . '/user/plans.php?plan_id=1';
            $fallback_text = '<i class="fa-solid fa-bolt"></i> Choose Plan';
        } else {
            $fallback_url = $base_url . '/auth/register.php?plan_id=1';
            $fallback_text = 'JOIN NOW';
        }
        ?>
        <div class="pricing-card">
          <div class="plan-name">BASIC</div>
          <div class="plan-desc">Standard access for general conditioning and workouts.</div>
          <div class="plan-price-wrap"><span class="plan-currency">₹</span><span class="plan-amount">999</span><span class="plan-duration">/ Month</span></div>
          <ul class="plan-features-list">
            <li><i class="fa-solid fa-circle-check"></i> Full Gym Floor Access</li>
            <li><i class="fa-solid fa-circle-check"></i> Locker &amp; Shower Access</li>
          </ul>
          <a href="<?= $fallback_url ?>" class="btn btn-outline btn-block"><?= $fallback_text ?></a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
