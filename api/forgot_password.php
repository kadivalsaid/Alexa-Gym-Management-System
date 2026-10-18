<?php
/**
 * Forgot & Reset Password API (4-Digit OTP)
 * Path: /api/forgot_password.php
 * Methods:
 * 1. Send OTP: { "action": "send_otp", "email": "user@example.com" }
 * 2. Verify & Reset: { "action": "reset_password", "email": "user@example.com", "otp": "1234", "new_password": "pass" }
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../config/mail.php';

$input = getRequestData();
$action = trim($input['action'] ?? 'send_otp');
$email = trim($input['email'] ?? '');

if (empty($email)) {
    sendResponse(false, 'Please provide email address', null, 400);
}

try {
    // 1. SEND OTP ACTION
    if ($action === 'send_otp') {
        $stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            sendResponse(false, 'No account found with this email address.', null, 404);
        }

        // Generate 4-digit numeric OTP
        $otp = (string)random_int(1000, 9999);

        // Delete old tokens
        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

        // Insert new token
        $ins = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW())");
        $ins->execute([$email, $otp]);

        // Create in-app notification
        try {
            $notifTitle = 'Password Reset OTP Requested 🔐';
            $notifMsg   = "A 4-digit verification code was requested for your account: $otp (Valid for 10 minutes).";
            $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'warning', 0, NOW())")->execute([$user['id'], $notifTitle, $notifMsg]);
        } catch (Throwable $nEx) {}

        // Send Email via Gmail SMTP
        try {
            $subject = "Your 4-Digit Reset OTP: $otp — Alexa Gym";
            $userName = htmlspecialchars($user['name'] ?? 'User');
            $body = "
                <div style=\"font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; max-width: 540px; margin: 0 auto; background: #0b0d14; border: 1px solid #1e293b; border-radius: 14px; overflow: hidden; color: #f8fafc; box-shadow: 0 10px 30px rgba(0,0,0,0.5);\">
                    <div style=\"background: linear-gradient(135deg, #0f172a, #1e1b4b); padding: 28px 24px; text-align: center; border-bottom: 2px solid #00f2fe;\">
                        <h1 style=\"color: #00f2fe; margin: 0; font-size: 24px; font-weight: 900; letter-spacing: 2px;\">ALEXA GYM</h1>
                        <p style=\"color: #94a3b8; margin: 6px 0 0 0; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;\">Password Recovery</p>
                    </div>
                    <div style=\"padding: 30px 24px; text-align: center;\">
                        <h2 style=\"color: #ffffff; font-size: 20px; margin: 0 0 12px 0;\">Password Reset Code</h2>
                        <p style=\"color: #cbd5e1; font-size: 14.5px; line-height: 1.6; margin: 0 0 18px 0;\">
                            Hello <strong style=\"color: #00f2fe;\">{$userName}</strong>,<br>
                            Please enter this 4-digit verification code to reset your password:
                        </p>
                        <div style=\"background: #111827; border: 2px dashed #00f2fe; border-radius: 12px; padding: 18px 24px; display: inline-block; margin: 0 auto 20px auto;\">
                            <span style=\"font-family: monospace; font-size: 34px; font-weight: 900; letter-spacing: 10px; color: #00f2fe; display: block;\">{$otp}</span>
                        </div>
                        <p style=\"color: #94a3b8; font-size: 13px; margin: 0 0 12px 0;\">This code is valid for <strong>10 minutes</strong>.</p>
                        <p style=\"color: #64748b; font-size: 12px; margin: 0;\">If you did not request this, you can safely ignore this email.</p>
                    </div>
                </div>
            ";
            if (function_exists('send_app_mail')) {
                send_app_mail($email, $subject, $body);
            }
        } catch (Throwable $mEx) {}

        sendResponse(true, 'A 4-digit verification code has been sent to your registered email address. Please check your inbox.', [
            'email' => $email,
        ]);
    }

    // 2. VERIFY OTP & RESET PASSWORD ACTION
    if ($action === 'reset_password') {
        $otp = trim($input['otp'] ?? '');
        $newPassword = (string)($input['new_password'] ?? '');

        if (empty($otp) || empty($newPassword)) {
            sendResponse(false, 'Please provide OTP and new password', null, 400);
        }

        if (strlen($newPassword) < 6) {
            sendResponse(false, 'New password must be at least 6 characters long', null, 400);
        }

        $checkStmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $checkStmt->execute([$email, $otp]);
        $resetRecord = $checkStmt->fetch();

        if (!$resetRecord) {
            sendResponse(false, 'Invalid or expired OTP. Please try again.', null, 400);
        }

        // Update password in users table
        $pdo->prepare("UPDATE users SET password = ? WHERE email = ?")->execute([$newPassword, $email]);

        // Delete token
        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

        // Success notification
        try {
            $uStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $uStmt->execute([$email]);
            $uId = $uStmt->fetchColumn();
            if ($uId) {
                $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, 'Password Reset Successful ✅', 'Your account password has been updated successfully. You can now log in using your new password.', 'success', 0, NOW())")->execute([$uId]);
            }
        } catch (Throwable $sEx) {}

        sendResponse(true, 'Password has been reset successfully! You can now log in.');
    }

    sendResponse(false, 'Invalid action', null, 400);

} catch (Throwable $e) {
    sendResponse(false, 'Server error: ' . $e->getMessage(), null, 500);
}
