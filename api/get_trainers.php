<?php
/**
 * Get Gym Trainers API
 * Path: /api/get_trainers.php
 * Method: GET
 */

require_once __DIR__ . '/db.php';

try {
    $stmt = $pdo->query("
        SELECT id, full_name, email, phone, specialization, experience, bio, photo 
        FROM trainers 
        WHERE status = 'active' 
        ORDER BY id ASC
    ");
    $trainers = $stmt->fetchAll();

    foreach ($trainers as &$t) {
        $t['id'] = (int)$t['id'];
        // Photo URL path builder
        if (!empty($t['photo'])) {
            $t['photo_url'] = '../assets/images/' . $t['photo'];
        } else {
            $t['photo_url'] = '';
        }
    }

    sendResponse(true, 'Trainers retrieved successfully', $trainers);
} catch (Exception $e) {
    sendResponse(false, 'Failed to fetch trainers: ' . $e->getMessage(), null, 500);
}
