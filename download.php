<?php
/**
 * Secure APK Download Dispatcher
 * Bypasses free hosting extension filters by streaming the package
 */
$candidates = [
    __DIR__ . '/downloads/AlexaGym.bin',
    __DIR__ . '/downloads/AlexaGym.apk',
    __DIR__ . '/downloads/app.bin'
];

$target = null;
foreach ($candidates as $c) {
    if (file_exists($c)) {
        $target = $c;
        break;
    }
}

if ($target) {
    // Clear any output buffering
    if (ob_get_level()) ob_end_clean();

    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.android.package-archive');
    header('Content-Disposition: attachment; filename="AlexaGym.apk"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($target));
    readfile($target);
    exit;
} else {
    // Fallback redirect to app page with guidance
    header('Location: app.php?err=file_missing');
    exit;
}
