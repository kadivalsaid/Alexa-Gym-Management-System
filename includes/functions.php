<?php
/**
 * Global Helper Functions
 * Alexa Gym Management System
 */

// XSS Protection
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Format Currency
function format_currency($amount) {
    return '₹' . number_format((float)$amount, 2);
}

// Format Date
function format_date($date_str, $format = 'd M Y') {
    if (empty($date_str) || $date_str === '0000-00-00') return 'N/A';
    $timestamp = strtotime($date_str);
    return $timestamp ? date($format, $timestamp) : 'N/A';
}

// Auto-detect Base URL
function get_base_url() {
    $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    // Remove subfolders like /auth, /user, /admin, /includes from base
    $base = preg_replace('#/(auth|user|admin|includes|config)(/.*)?$#i', '', $script_dir);
    $base = rtrim($base, '/');
    return ($base === '' || $base === '.') ? '' : $base;
}

// Flash Message Set
function set_flash($type, $message) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash_messages'][] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

// Flash Messages Get & Clear
function get_flashes() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $flashes = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $flashes;
}

// Display HTML Flash Alerts
function display_flashes() {
    $flashes = get_flashes();
    if (empty($flashes)) return;
    
    echo '<div class="flash-container">';
    foreach ($flashes as $f) {
        $type = e($f['type']);
        $msg  = e($f['message']);
        echo "<div class=\"alert alert-{$type} alert-dismissible fade show\" role=\"alert\">
                {$msg}
                <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button>
              </div>";
    }
    echo '</div>';
}

// Fetch member record for logged in user
function get_member_by_user_id($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT m.*, p.name AS plan_name, p.duration_months, p.price AS plan_price,
               t.full_name AS trainer_name, t.phone AS trainer_phone, t.specialization AS trainer_specialization
        FROM members m
        LEFT JOIN plans p ON m.plan_id = p.id
        LEFT JOIN trainers t ON m.trainer_id = t.id
        WHERE m.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

// Fetch latest pending plan request for user
function get_pending_request_by_user_id($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT mr.*, p.name AS plan_name, p.duration_months, p.price AS plan_price,
                   t.full_name AS trainer_name, t.phone AS trainer_phone, t.specialization AS trainer_specialization
            FROM membership_requests mr
            LEFT JOIN plans p ON mr.plan_id = p.id
            LEFT JOIN trainers t ON mr.trainer_id = t.id
            WHERE mr.user_id = ? AND mr.status = 'pending'
            ORDER BY mr.id DESC
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

// Convert amount to Indian English Words
function number_to_words_inr($amount) {
    $amount = (float)$amount;
    $number = floor($amount);
    $decimal = round(($amount - $number) * 100);

    $words = [
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty',
        30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy',
        80 => 'Eighty', 90 => 'Ninety'
    ];

    if ($number == 0) return 'Zero Rupees Only';

    $crores  = floor($number / 10000000);
    $number -= $crores * 10000000;
    $lakhs   = floor($number / 100000);
    $number -= $lakhs * 100000;
    $thousands = floor($number / 1000);
    $number   -= $thousands * 1000;
    $hundreds  = floor($number / 100);
    $number   -= $hundreds * 100;
    $tens      = $number;

    $convert_sub = function($num) use ($words) {
        if ($num <= 20) return $words[$num] ?? '';
        $t = floor($num / 10) * 10;
        $u = $num % 10;
        return ($words[$t] ?? '') . ($u > 0 ? ' ' . ($words[$u] ?? '') : '');
    };

    $res = [];
    if ($crores > 0) $res[] = $convert_sub($crores) . ' Crore';
    if ($lakhs > 0) $res[] = $convert_sub($lakhs) . ' Lakh';
    if ($thousands > 0) $res[] = $convert_sub($thousands) . ' Thousand';
    if ($hundreds > 0) $res[] = ($words[$hundreds] ?? '') . ' Hundred';
    if ($tens > 0) $res[] = $convert_sub($tens);

    $final = trim(implode(' ', array_filter($res))) . ' Rupees';
    if ($decimal > 0) {
        $final .= ' and ' . $convert_sub($decimal) . ' Paise';
    }
    return $final . ' Only';
}
