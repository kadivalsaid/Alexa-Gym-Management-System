<?php
/**
 * Alexa Gym — Contact Page
 */
$page_title = 'Contact Us & Location — Alexa Gym';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

$contact_success = false;
$contact_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    $c_name    = trim($_POST['name'] ?? '');
    $c_email   = trim($_POST['email'] ?? '');
    $c_phone   = trim($_POST['phone'] ?? '');
    $c_message = trim($_POST['message'] ?? '');

    if (empty($c_name) || empty($c_email) || empty($c_message)) {
        $contact_error = 'Please fill in all required fields (Name, Email, Message).';
    } elseif (!filter_var($c_email, FILTER_VALIDATE_EMAIL)) {
        $contact_error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, phone, message, status, created_at) VALUES (?, ?, ?, ?, 'unread', NOW())");
            $stmt->execute([$c_name, $c_email, $c_phone, $c_message]);
            $contact_success = true;
        } catch (Exception $ex) {
            $contact_error = 'Error saving message. Please try again or call our reception directly.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<section style="padding: 130px 0 60px 0; background: radial-gradient(circle at top right, rgba(0, 242, 254, 0.12), transparent 45%), var(--bg); border-bottom: 1px solid var(--line);">
  <div class="container text-center" style="text-align: center;">
    <div class="section-tag"><i class="fa-solid fa-envelope"></i> Reach Out</div>
    <h1 class="section-title">Contact &amp; Location</h1>
    <p class="section-desc" style="margin: 0 auto; max-width: 650px;">
      Visit our facility or send our concierge team an inquiry regarding memberships, guest passes, or private training.
    </p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="contact-grid">
      <!-- Gym Info -->
      <div class="contact-info-card">
        <h2 style="font-size: 24px; font-weight: 800; margin-bottom: 24px;">Gym Headquarters</h2>
        
        <div class="contact-item">
          <div class="contact-icon"><i class="fa-solid fa-location-dot"></i></div>
          <div>
            <strong style="display: block; font-size: 16px; margin-bottom: 2px;">Address:</strong>
            <span style="color: var(--text-muted); font-size: 14.5px;">Ground Floor, Shop No. 39, Shukan Plaza, Palanpur Highway, Vadgam, Banaskantha, Gujarat – 385410</span>
          </div>
        </div>

        <div class="contact-item">
          <div class="contact-icon"><i class="fa-solid fa-phone"></i></div>
          <div>
            <strong style="display: block; font-size: 16px; margin-bottom: 2px;">Phone Numbers:</strong>
            <span style="color: var(--text-muted); font-size: 14.5px;">+91 98765 43210 &bull; +91 91234 56780</span>
          </div>
        </div>

        <div class="contact-item">
          <div class="contact-icon"><i class="fa-solid fa-envelope"></i></div>
          <div>
            <strong style="display: block; font-size: 16px; margin-bottom: 2px;">Email Support:</strong>
            <span style="color: var(--text-muted); font-size: 14.5px;">support@alexagym.com &bull; info@alexagym.com</span>
          </div>
        </div>

        <div class="contact-item">
          <div class="contact-icon"><i class="fa-solid fa-clock"></i></div>
          <div>
            <strong style="display: block; font-size: 16px; margin-bottom: 2px;">Working Hours:</strong>
            <span style="color: var(--text-muted); font-size: 14.5px;">
              Monday – Saturday: 06:00 AM – 10:00 PM<br>
              Sunday: 07:00 AM – 01:00 PM
            </span>
          </div>
        </div>

        <!-- Google Maps Placeholder Card -->
        <div style="margin-top: 30px; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--line); height: 180px; background: var(--panel-2); display: flex; align-items: center; justify-content: center; position: relative;">
          <div style="text-align: center; color: var(--text-muted);">
            <i class="fa-solid fa-map-location-dot" style="font-size: 32px; color: var(--cyan); margin-bottom: 8px;"></i>
            <span style="display: block; font-size: 14px; font-weight: 600;">Shukan Plaza, Palanpur Highway, Vadgam (Banaskantha)</span>
          </div>
        </div>
      </div>

      <!-- Contact Form -->
      <div class="contact-form-card">
        <h2 style="font-size: 24px; font-weight: 800; margin-bottom: 20px;">Send An Inquiry</h2>

        <?php if ($contact_success): ?>
          <div class="alert alert-success">
            <i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i>
            Thank you! Your message has been safely saved. An Alexa Gym representative will contact you shortly.
          </div>
        <?php elseif ($contact_error): ?>
          <div class="alert alert-danger">
            <i class="fa-solid fa-triangle-exclamation" style="margin-right: 8px;"></i>
            <?= e($contact_error) ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="contact.php" id="contact-form">
          <div class="form-group">
            <label class="form-label">Full Name *</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Rahul Deshmukh" required value="<?= e($_POST['name'] ?? '') ?>">
          </div>
          <div class="form-row-2">
            <div class="form-group">
              <label class="form-label">Email Address *</label>
              <input type="email" name="email" class="form-control" placeholder="you@example.com" required value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Phone Number</label>
              <input type="tel" name="phone" class="form-control" placeholder="98XXXXXXXX" value="<?= e($_POST['phone'] ?? '') ?>">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Message *</label>
            <textarea name="message" rows="5" class="form-control" placeholder="Enter your inquiry or request a free consultation..." required><?= e($_POST['message'] ?? '') ?></textarea>
          </div>
          <button type="submit" name="contact_submit" class="btn btn-primary btn-block">
            <i class="fa-solid fa-paper-plane"></i> Send Message
          </button>
        </form>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
