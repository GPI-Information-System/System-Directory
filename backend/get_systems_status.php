<?php
/**
 * G-Portal - Get Systems Status
 * Lightweight endpoint that returns current status of all systems.
 * Called by health_check.js after a maintenance switch to update
 * card statuses in-place without a full page reload.
 *
 * Access: Logged-in admins OR public viewer
 */

require_once '../config/session.php';
require_once '../config/database.php';

header('Content-Type: application/json');

$isLoggedIn = function_exists('isLoggedIn') && isLoggedIn();
$isViewer   = isset($_GET['source']) && $_GET['source'] === 'viewer';

if (!$isLoggedIn && !$isViewer) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $conn   = getDBConnection();
    $result = $conn->query("
        SELECT id, status
        FROM systems
        WHERE status != 'archived'
        ORDER BY id ASC
    ");

    $systems = [];
    while ($row = $result->fetch_assoc()) {
        $systems[] = [
            'id'     => (int) $row['id'],
            'status' => $row['status'],
        ];
    }

    $conn->close();

    echo json_encode([
        'success' => true,
        'systems' => $systems,
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>