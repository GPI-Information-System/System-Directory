<?php
/**
 * G-Portal - Trigger Maintenance Check
 *
 * Called by AJAX every 10 seconds from BOTH:
 *   - pages/dashboard.php (admin)
 *   - pages/viewer.php    (public)
 *
 * FIXED: Removed isLoggedIn() requirement so maintenance switches
 * happen even when no admin is logged in — as long as the viewer
 * page is open in any browser.
 *
 * Public access is safe here because this endpoint only:
 *   - READS maintenance_schedules
 *   - UPDATES system status to 'maintenance' (scheduled action)
 * No sensitive data is exposed in the response.
 */

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once __DIR__ . '/check_maintenance_schedule.php';

header('Content-Type: application/json');

// -------------------------------------------------------
// Allow both logged-in admins AND public viewer
// Maintenance must fire regardless of who has the page open
// -------------------------------------------------------
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