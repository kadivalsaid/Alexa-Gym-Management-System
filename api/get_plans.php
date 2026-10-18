<?php
/**
 * Get Membership Plans API
 * Path: /api/get_plans.php
 * Method: GET
 */

require_once __DIR__ . '/db.php';

try {
    $stmt = $pdo->query("
        SELECT id, name, duration_months, price, description, features, is_popular 
        FROM plans 
        WHERE status = 'active' 
        ORDER BY price ASC
    ");
    $plans = $stmt->fetchAll();

    // Features ko string se array (list) me convert karna taaki Flutter me bullets banana easy ho
    foreach ($plans as &$p) {
        $p['id'] = (int)$p['id'];
        $p['duration_months'] = (int)$p['duration_months'];
        $p['price'] = (float)$p['price'];
        $p['is_popular'] = (bool)$p['is_popular'];
        
        // Split features by newline
        $lines = explode("\n", str_replace("\r", "", $p['features'] ?? ''));
        $p['features_list'] = array_values(array_filter(array_map('trim', $lines)));
    }

    sendResponse(true, 'Plans retrieved successfully', $plans);
} catch (Exception $e) {
    sendResponse(false, 'Failed to fetch plans: ' . $e->getMessage(), null, 500);
}
