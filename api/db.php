<?php
/**
 * API Database & Helper Configuration
 * Path: /api/db.php
 * 
 * Yeh file sabhi API files ke liye common header aur database connection set karti hai.
 * Isse existing website ke kisi bhi code par koi asar nahi padta.
 */

// 1. Flutter aur mobile apps ke liye CORS headers allow karna
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Agar preflight OPTIONS request aaye to turant 200 OK bhej do
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Existing database connection file ko include karna (Safe PDO connection)
require_once __DIR__ . '/../config/database.php';

/**
 * Standard JSON Response bhejane ka helper function
 * Flutter app ko hamesha ek standard format me response milega
 */
function sendResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => (bool)$success,
        'message' => (string)$message,
        'data'    => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Flutter app se aane wala raw JSON data read karne ka helper
 */
function getRequestData() {
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);
    if (is_array($jsonData)) {
        return $jsonData;
    }
    // Agar form-data / POST aaya ho
    return $_POST;
}
