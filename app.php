<?php
/**
 * Alexa Gym — Official Android App Download Portal
 */
$page_title = 'Download Alexa Gym Android App (.apk)';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

$apk_file = file_exists(__DIR__ . '/downloads/AlexaGym.bin') ? __DIR__ . '/downloads/AlexaGym.bin' : __DIR__ . '/downloads/AlexaGym.apk';
$apk_size = 'Lightweight Edition';
if (file_exists($apk_file)) {
    $bytes = filesize($apk_file);
    $apk_size = ($bytes < 1048576) ? round($bytes / 1024) . ' KB' : round($bytes / (1024 * 1024), 1) . ' MB';
}
?>

<!-- HEADER HERO -->
<section style="padding: 130px 0 60px 0; background: radial-gradient(circle at top left, rgba(16, 185, 129, 0.15), transparent 45%), var(--bg); border-bottom: 1px solid var(--line);">
  <div class="container text-center" style="text-align: center;">
    <div class="section-tag" style="background: rgba(16, 185, 129, 0.12); color: #10B981; border: 1px solid rgba(16, 185, 129, 0.25);">
      <i class="fa-brands fa-android" style="font-size: 14px;"></i> Official Android Application
    </div>
    <h1 class="section-title">Download Alexa Gym App</h1>
    <p class="section-desc" style="margin: 0 auto; max-width: 650px;">
      Seamless workout tracking, live attendance check-ins, payment receipts, and trainer guidance directly on your Android phone and tablet.
    </p>
  </div>
</section>

<!-- DOWNLOAD SECTION -->
<section class="section" style="padding: 60px 0;">
  <div class="container">
    <div style="max-width: 820px; margin: 0 auto;">
      
      <!-- PRIMARY CARD: ANDROID APK DOWNLOAD -->
      <div style="background: var(--panel); border: 2px solid rgba(16, 185, 129, 0.4); border-radius: 24px; padding: 40px; position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; box-shadow: var(--shadow-md);">
        <div style="position: absolute; top: -50px; right: -50px; width: 160px; height: 160px; background: rgba(16, 185, 129, 0.12); border-radius: 50%; pointer-events: none;"></div>
        
        <div>
          <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(16, 185, 129, 0.15); color: #10B981; padding: 6px 14px; border-radius: 30px; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 18px;">
            <i class="fa-brands fa-android"></i> Android 7.0 to Android 15+
          </div>
          <h2 style="font-size: 28px; font-weight: 900; color: var(--text); margin-bottom: 12px; display: flex; align-items: center; gap: 12px;">
            <i class="fa-brands fa-android" style="color: #10B981; font-size: 34px;"></i>
            Android App (.apk)
          </h2>
          <p style="color: var(--text-muted); font-size: 15px; line-height: 1.6; margin-bottom: 24px;">
            Official standalone application package for gym members and administrators. Fast 60FPS fluid UI, offline workout plans, and instant live check-in scanning.
          </p>

          <ul style="list-style: none; padding: 0; margin: 0 0 28px 0; display: flex; flex-direction: column; gap: 12px;">
            <li style="color: var(--text); font-size: 14.5px; display: flex; align-items: center; gap: 10px;">
              <i class="fa-solid fa-circle-check" style="color: #10B981;"></i> Direct Package (<code style="color: #10B981; background: rgba(16,185,129,0.1); padding: 2px 6px; border-radius: 4px;">AlexaGym.apk</code> &bull; <?= $apk_size ?>)
            </li>
            <li style="color: var(--text); font-size: 14.5px; display: flex; align-items: center; gap: 10px;">
              <i class="fa-solid fa-circle-check" style="color: #10B981;"></i> Works on all Samsung, Xiaomi, OnePlus, Realme & Pixel devices
            </li>
            <li style="color: var(--text); font-size: 14.5px; display: flex; align-items: center; gap: 10px;">
              <i class="fa-solid fa-circle-check" style="color: #10B981;"></i> Full Member Portal & Admin Management Portal
            </li>
            <li style="color: var(--text); font-size: 14.5px; display: flex; align-items: center; gap: 10px;">
              <i class="fa-solid fa-circle-check" style="color: #10B981;"></i> 100% Free & Signed with Verified Security Certificate
            </li>
          </ul>
        </div>

        <div style="display: flex; flex-direction: column; gap: 12px;">
          <a href="<?= $base_url ?>/download.php" class="btn btn-primary" style="background: linear-gradient(135deg, #10B981, #059669); border-color: #10B981; color: #ffffff !important; text-align: center; justify-content: center; width: 100%; padding: 16px 24px; font-size: 15px; font-weight: 800; box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);" download="AlexaGym.apk">
            <i class="fa-solid fa-download"></i> DOWNLOAD ANDROID APP (<?= $apk_size ?>)
          </a>
        </div>
      </div>
    </div>

    <!-- HOW TO INSTALL ON ANDROID GUIDE -->
    <div style="max-width: 1080px; margin: 40px auto 0 auto; background: var(--panel-2); border: 1px solid var(--line); border-radius: 20px; padding: 28px 32px; box-shadow: var(--shadow-sm);">
      <h3 style="color: var(--text); font-size: 18px; font-weight: 800; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
        <i class="fa-brands fa-android" style="color: #10B981;"></i> How to Install the APK on your Android Phone:
      </h3>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px;">
        <div style="background: var(--panel); border: 1px solid var(--line); border-radius: 14px; padding: 18px; box-shadow: var(--shadow-sm);">
          <div style="font-size: 12px; font-weight: 800; color: #10B981; margin-bottom: 6px;">STEP 1</div>
          <div style="font-weight: 700; color: var(--text); margin-bottom: 4px;">Download APK</div>
          <p style="font-size: 13px; color: var(--text-muted); margin: 0; line-height: 1.5;">Tap the green download button above to save <code style="color: #10B981; background: rgba(16,185,129,0.1); padding: 1px 4px; border-radius: 4px;">AlexaGym.apk</code> to your device.</p>
        </div>
        <div style="background: var(--panel); border: 1px solid var(--line); border-radius: 14px; padding: 18px; box-shadow: var(--shadow-sm);">
          <div style="font-size: 12px; font-weight: 800; color: #10B981; margin-bottom: 6px;">STEP 2</div>
          <div style="font-weight: 700; color: var(--text); margin-bottom: 4px;">Allow Unknown Apps</div>
          <p style="font-size: 13px; color: var(--text-muted); margin: 0; line-height: 1.5;">When prompted by Android, tap <strong>Settings</strong> and enable <em>"Allow from this source"</em>.</p>
        </div>
        <div style="background: var(--panel); border: 1px solid var(--line); border-radius: 14px; padding: 18px; box-shadow: var(--shadow-sm);">
          <div style="font-size: 12px; font-weight: 800; color: #10B981; margin-bottom: 6px;">STEP 3</div>
          <div style="font-weight: 700; color: var(--text); margin-bottom: 4px;">Install & Open</div>
          <p style="font-size: 13px; color: var(--text-muted); margin: 0; line-height: 1.5;">Tap <strong>Install</strong>. Once done, tap <strong>Open</strong> to launch Alexa Gym directly!</p>
        </div>
      </div>
    </div>

  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
