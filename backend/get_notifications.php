<?php
/**
 * ============================================================
 * PHASE 3: GET RECENT NOTIFICATIONS API
 * Fetches recent status changes for the notification bell
 * ============================================================
 */

require_once '../config/database.php';

header('Content-Type: application/json');

// Get recent status changes (last 24 hours by default)
$hours = isset($_GET['hours']) ? intval($_GET['hours']) : 24;

$conn = getDBConnection();

$sql = "
    SELECT 
        sl.id,
        sl.system_id,
        s.name as system_name,
        sl.old_status,
        sl.new_status,
        sl.changed_at,
        u.username as changed_by
    FROM status_logs sl
    JOIN systems s ON sl.system_id = s.id
    JOIN users u ON sl.changed_by = u.id
    WHERE sl.changed_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
    ORDER BY sl.changed_at DESC
    LIMIT 10
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $hours);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

echo json_encode([
    'success' => true,
    'count' => count($notifications),
    'notifications' => $notifications
]);

$stmt->close();
$conn->close();
?>