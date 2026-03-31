<?php
/* G-Portal - Trigger Maintenance Check*/

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once __DIR__ . '/check_maintenance_schedule.php';

header('Content-Type: application/json');

$isLoggedIn = function_exists('isLoggedIn') && isLoggedIn();
$isViewer   = isset($_GET['source']) && $_GET['source'] === 'viewer';

if (!$isLoggedIn && !$isViewer) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

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
?>