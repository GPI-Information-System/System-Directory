<?php
/**
 * G-Portal - Trigger Maintenance Check (Dashboard Load)
 * 
 * Lightweight endpoint called silently via AJAX when dashboard loads.
 * Runs the maintenance schedule check and returns results as JSON.
 * 
 * Access: Must be logged in
 */

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once __DIR__ . '/check_maintenance_schedule.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// No throttle — JS polling (every 30s) handles frequency
$now = time();

try {
    $result = runMaintenanceScheduleCheck();
    echo json_encode([
        'success'  => true,
        'skipped'  => false,
        'switched' => $result['switched'],
        'message'  => $result['switched'] > 0
            ? "{$result['switched']} system(s) switched to maintenance."
            : 'No changes needed.'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Check failed: ' . $e->getMessage()
    ]);
}