<?php
/**
 * Shared Public Footer Include
 * Alexa Gym Management System
 */
$base_url = get_base_url();
?>
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <!-- Col 1: Brand -->
      <div class="footer-col">
        <div class="navbar-brand" style="margin-bottom: 16px;">
          <div class="brand-logo-badge"><i class="fa-solid fa-dumbbell"></i></div>
          <div class="brand-info">
            <span class="brand-name">Alexa Gym</span>
            <span class="brand-sub">Build Your Better Self</span>
          </div>
        </div>

        <!-- ANDROID APP DOWNLOAD BADGE (Directly After Brand Name) -->
        <div style="margin-bottom: 20px;">
          <a href="<?= $base_url ?>/download.php" class="footer-android-badge" download="AlexaGym.apk" style="display: inline-flex; align-items: center; gap: 12px; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.35); border-radius: 12px; padding: 10px 16px; text-decoration: none; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);">
            <div style="width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, #10B981, #059669); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 20px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4); flex-shrink: 0;">
              <i class="fa-brands fa-android"></i>
            </div>
            <div style="text-align: left;">
              <span style="display: block; font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #10B981; line-height: 1.2;">GET IT ON ANDROID</span>
              <span style="display: block; font-size: 13.5px; font-weight: 800; color: #ffffff; line-height: 1.2; margin-top: 2px;">Alexa Gym App (.apk)</span>
            </div>
          </a>
        </div>

        <p style="color: #cbd5e1; font-size: 14.5px; line-height: 1.6; max-width: 320px; margin-bottom: 18px;">
          Alexa Gym is a state-of-the-art strength, conditioning, and lifestyle transformation center dedicated to helping you sculpt your greatest physical and mental performance.
        </p>
        <div class="social-links-row">
          <a href="#" class="social-btn" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
          <a href="#" class="social-btn" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
          <a href="#" class="social-btn" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
          <a href="#" class="social-btn" aria-label="Twitter"><i class="fa-brands fa-x-twitter"></i></a>
        </div>
      </div>

      <!-- Col 2: Quick Links -->
      <div class="footer-col">
        <h3 class="footer-col-title">Quick Links</h3>
        <ul class="footer-links">
          <li><a href="<?= $base_url ?>/index.php">Home</a></li>
          <li><a href="<?= $base_url ?>/index.php#about">About Us</a></li>
          <li><a href="<?= $base_url ?>/facilities.php">Gym Facilities</a></li>
          <li><a href="<?= $base_url ?>/trainers.php">Certified Trainers</a></li>
          <li><a href="<?= $base_url ?>/gallery.php">Photo Gallery</a></li>
          <li><a href="<?= $base_url ?>/contact.php">Contact & Location</a></li>
        </ul>
      </div>

      <!-- Col 3: Membership -->
      <div class="footer-col">
        <h3 class="footer-col-title">Membership</h3>
        <ul class="footer-links">
          <?php if (is_logged_in() && ($user_info['role'] ?? '') === 'admin'): ?>
            <li><a href="<?= $base_url ?>/admin/plans.php">Manage Gym Plans</a></li>
            <li><a href="<?= $base_url ?>/admin/members.php">Active Members</a></li>
            <li><a href="<?= $base_url ?>/admin/dashboard.php">Admin Dashboard</a></li>
            <li><a href="<?= $base_url ?>/admin/payments.php">Payment Records</a></li>
            <li><a href="<?= $base_url ?>/auth/logout.php">Logout</a></li>
          <?php elseif (is_logged_in() && ($user_info['role'] ?? '') === 'member'): ?>
            <li><a href="<?= $base_url ?>/user/plans.php">My Membership Plan</a></li>
            <li><a href="<?= $base_url ?>/user/dashboard.php">Member Dashboard</a></li>
            <li><a href="<?= $base_url ?>/user/payments.php">Payment Receipts</a></li>
            <li><a href="<?= $base_url ?>/auth/logout.php">Logout</a></li>
          <?php else: ?>
            <li><a href="<?= $base_url ?>/plans.php">Basic Monthly (₹999)</a></li>
            <li><a href="<?= $base_url ?>/plans.php">Standard Pro (₹2,499)</a></li>
            <li><a href="<?= $base_url ?>/plans.php">Annual VIP (₹7,999)</a></li>
            <li><a href="<?= $base_url ?>/auth/register.php">Create Account</a></li>
            <li><a href="<?= $base_url ?>/auth/login.php">Member Login</a></li>
            <li><a href="<?= $base_url ?>/auth/admin-login.php">Admin Portal</a></li>
          <?php endif; ?>
        </ul>
      </div>

      <!-- Col 4: Contact & Hours -->
      <div class="footer-col">
        <h3 class="footer-col-title">Visit Alexa Gym</h3>
        <div style="color: #cbd5e1; font-size: 14px; display: flex; flex-direction: column; gap: 14px;">
          <div>
            <strong style="color: #ffffff; display: block; margin-bottom: 3px; font-size: 14.5px;">
              <i class="fa-solid fa-location-dot" style="color: var(--cyan); margin-right: 8px;"></i> Address:
            </strong>
            <span style="color: #cbd5e1; line-height: 1.5; display: block;">Ground Floor, Shop No. 39, Shukan Plaza, Palanpur Highway, Vadgam, Banaskantha, Gujarat – 385410</span>
          </div>
          <div>
            <strong style="color: #ffffff; display: block; margin-bottom: 3px; font-size: 14.5px;">
              <i class="fa-solid fa-phone" style="color: var(--cyan); margin-right: 8px;"></i> Phone:
            </strong>
            <span style="color: #cbd5e1; display: block;">+91 9328689022 / +91 7600643495</span>
          </div>
          <div>
            <strong style="color: #ffffff; display: block; margin-bottom: 3px; font-size: 14.5px;">
              <i class="fa-solid fa-envelope" style="color: var(--cyan); margin-right: 8px;"></i> Email:
            </strong>
            <span style="color: #cbd5e1; display: block;">alexagym01@gmail.com</span>
          </div>
          <div>
            <strong style="color: #ffffff; display: block; margin-bottom: 3px; font-size: 14.5px;">
              <i class="fa-solid fa-clock" style="color: var(--cyan); margin-right: 8px;"></i> Hours:
            </strong>
            <span style="color: #cbd5e1; line-height: 1.5; display: block;">Mon - Sat: 06:00 AM – 10:00 PM<br>Sunday: 07:00 AM – 01:00 PM</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom Copyright -->
    <div class="footer-bottom">
      <div>
        &copy; <?= date('Y') ?> <strong>Alexa Gym</strong>. All rights reserved. Built with PHP &amp; MySQL.
      </div>
      <div>
        Designed &amp; Developed <span style="color: #ef4444;">&hearts;</span> by <strong>Kadival Said &amp; Tushar Barot</strong> &bull; BCA Final Year Project
      </div>
    </div>
  </div>
</footer>

<!-- Core Public JS -->
<script src="<?= $base_url ?>/assets/js/main.js?v=2.0"></script>
</body>
</html>
