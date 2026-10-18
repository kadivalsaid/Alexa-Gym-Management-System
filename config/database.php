<?php
/**
 * Database Configuration & PDO Connection
 * Alexa Gym Management System
 */
// Set default timezone
date_default_timezone_set('Asia/Kolkata');

// Smart Environment Detection (Localhost vs Live Hosting)
$server_name = $_SERVER['SERVER_NAME'] ?? '';
$is_local = in_array($server_name, ['localhost', '127.0.0.1', '::1']) 
    || strpos($server_name, '192.168.') === 0 
    || strpos($server_name, '10.') === 0 
    || strpos($server_name, '172.') === 0 
    || php_sapi_name() === 'cli';

if ($is_local) {
    // -------------------------------------------------------------
    // 💻 LOCALHOST (WAMP) SETTINGS
    // -------------------------------------------------------------
    $DB_HOST = 'localhost';
    $DB_USER = 'root';
    $DB_PASS = '';
    $DB_NAME = 'gym_management';
} else {
    // -------------------------------------------------------------
    // 🌐 LIVE HOSTING (InfinityFree) SETTINGS
    // -------------------------------------------------------------
    $DB_HOST = 'sql312.infinityfree.com';
    $DB_USER = 'if0_42982084';
    $DB_PASS = 'YOUR_DB_PASSWORD'; // <-- Yahan apna hosting password daalein
    $DB_NAME = 'if0_42982084_gym';
}

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
