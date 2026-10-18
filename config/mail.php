<?php
/**
 * Mail / SMTP Configuration
 * Alexa Gym Management System
 */
require_once __DIR__ . '/database.php';

// Gmail SMTP Server Settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 465); // Port 465 (SSL) or 587 (TLS)

/**
 * --------------------------------------------------------------------------
 * GOOGLE APP PASSWORD CONFIGURATION
 * --------------------------------------------------------------------------
 * To send real live emails via Gmail SMTP to member inboxes:
 * 1. Go to your Google Account (myaccount.google.com) for your admin email
 * 2. Enable 2-Step Verification (Security tab)
 * 3. Search "App passwords" -> Create one named "Alexa Gym"
 * 4. Paste the 16-character code below (e.g. 'xxxx yyyy zzzz wwww')
 *
 * If left blank, the system works in instant local test & notification mode!
 */
define('GMAIL_SENDER_EMAIL', 'alexagym01@gmail.com');
define('GMAIL_APP_PASSWORD', 'YOUR_GMAIL_APP_PASSWORD');

/**
 * Fetch Sender (Admin) Email
 */
function get_admin_sender_email() {
    return defined('GMAIL_SENDER_EMAIL') ? GMAIL_SENDER_EMAIL : 'alexagym01@gmail.com';
}

/**
 * Fetch Sender (Admin) Name dynamically from database
 */
function get_admin_sender_name() {
    global $pdo;
    static $admin_name = null;
    if ($admin_name !== null) return $admin_name;

    try {
        if (!isset($pdo)) {
            require_once __DIR__ . '/database.php';
        }
        if (isset($pdo)) {
            $stmt = $pdo->query("SELECT name FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
            $res = $stmt->fetchColumn();
            if (!empty($res)) {
                $admin_name = trim($res) . ' — Alexa Gym';
                return $admin_name;
            }
        }
    } catch (Exception $e) {}

    return 'Alexa Gym Admin';
}

/**
 * Send Mail via direct SMTP Socket (Zero dependencies, works natively in PHP on WAMP)
 */
function send_smtp_socket($smtp_host, $smtp_port, $username, $password, $from_email, $from_name, $to, $subject, $html_body) {
    $timeout = 15;
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    $prefix = ($smtp_port == 465) ? 'ssl://' : '';
    $socket = @stream_socket_client($prefix . $smtp_host . ':' . $smtp_port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        return ['success' => false, 'error' => "SMTP connection failed: $errstr ($errno)"];
    }

    $response = fgets($socket, 512);
    if (substr($response, 0, 3) !== '220') {
        fclose($socket);
        return ['success' => false, 'error' => "Server error: $response"];
    }

    $send_cmd = function($cmd, $expected_code) use ($socket) {
        fwrite($socket, $cmd . "\r\n");
        $reply = '';
        while ($str = fgets($socket, 512)) {
            $reply .= $str;
            if (isset($str[3]) && $str[3] === ' ') break;
        }
        $code = substr($reply, 0, 3);
        if ($code !== (string)$expected_code) {
            return false;
        }
        return true;
    };

    if (!$send_cmd('EHLO ' . gethostname(), 250)) {
        fclose($socket);
        return ['success' => false, 'error' => 'EHLO failed'];
    }

    // Upgrade to STARTTLS if on port 587
    if ($smtp_port == 587) {
        if (!$send_cmd('STARTTLS', 220)) {
            fclose($socket);
            return ['success' => false, 'error' => 'STARTTLS failed'];
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return ['success' => false, 'error' => 'TLS crypto handshake failed'];
        }
        if (!$send_cmd('EHLO ' . gethostname(), 250)) {
            fclose($socket);
            return ['success' => false, 'error' => 'EHLO after TLS failed'];
        }
    }

    // AUTH LOGIN with Gmail App Password
    if (!empty($username) && !empty($password)) {
        if (!$send_cmd('AUTH LOGIN', 334)) {
            fclose($socket);
            return ['success' => false, 'error' => 'AUTH LOGIN command not accepted'];
        }
        if (!$send_cmd(base64_encode($username), 334)) {
            fclose($socket);
            return ['success' => false, 'error' => 'Username rejected by Gmail'];
        }
        if (!$send_cmd(base64_encode(str_replace(' ', '', $password)), 235)) {
            fclose($socket);
            return ['success' => false, 'error' => 'Gmail App Password authentication failed.'];
        }
    }

    if (!$send_cmd("MAIL FROM: <$from_email>", 250)) {
        fclose($socket);
        return ['success' => false, 'error' => "MAIL FROM rejected: $from_email"];
    }

    if (!$send_cmd("RCPT TO: <$to>", 250)) {
        fclose($socket);
        return ['success' => false, 'error' => "RCPT TO rejected: $to"];
    }

    if (!$send_cmd('DATA', 354)) {
        fclose($socket);
        return ['success' => false, 'error' => 'DATA rejected'];
    }

    $headers = [];
    $headers[] = "Date: " . date('r');
    $headers[] = "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <$from_email>";
    $headers[] = "Reply-To: <$from_email>";
    $headers[] = "To: <$to>";
    $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/html; charset=UTF-8";
    $headers[] = "Content-Transfer-Encoding: base64";
    $headers[] = "X-Mailer: AlexaGym-Mailer/2.0";

    $message_data = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($html_body)) . "\r\n.";

    if (!$send_cmd($message_data, 250)) {
        fclose($socket);
        return ['success' => false, 'error' => 'Message body rejected'];
    }

    $send_cmd('QUIT', 221);
    fclose($socket);

    return ['success' => true];
}

/**
 * Universal Application Mailer
 * Automatically uses Admin Email from Database as Sender
 */
function send_app_mail($to, $subject, $html_content) {
    $from_email = get_admin_sender_email();
    $from_name  = get_admin_sender_name();

    // Save in session for instant test feedback
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['last_simulated_email'] = [
            'from'    => $from_email,
            'to'      => $to,
            'subject' => $subject,
            'body'    => $html_content,
            'time'    => date('H:i:s')
        ];
    }

    // Try Live Gmail SMTP if App Password is set
    $smtp_pass = defined('GMAIL_APP_PASSWORD') ? trim(GMAIL_APP_PASSWORD) : '';
    if (!empty($smtp_pass)) {
        $smtp_res = send_smtp_socket(SMTP_HOST, SMTP_PORT, $from_email, $smtp_pass, $from_email, $from_name, $to, $subject, $html_content);
        if ($smtp_res['success']) {
            return ['success' => true, 'mode' => 'live_smtp', 'from' => $from_email];
        }
    }

    // Fallback: standard PHP mail()
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <$from_email>\r\n";
    $headers .= "Reply-To: <$from_email>\r\n";
    $headers .= "X-Mailer: AlexaGym-Mailer/2.0\r\n";

    @mail($to, $subject, $html_content, $headers);

    return ['success' => true, 'mode' => 'local_logged', 'from' => $from_email];
}

if (!function_exists('sendMail')) {
    function sendMail($to, $subject, $html_content) {
        return send_app_mail($to, $subject, $html_content);
    }
}

