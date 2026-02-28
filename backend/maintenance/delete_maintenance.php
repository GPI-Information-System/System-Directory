<?php
/**
 * G-Portal - Delete Maintenance Schedule (Soft Delete)
 * 
 * IMPORTANT: This performs a SOFT DELETE, not a hard DELETE.
 * 
 * - Sets deleted_from_calendar = 1 → hides from dashboard calendar
 * - Record is PRESERVED in the database → still shows in Analytics
 * - This ensures company audit trail is never lost
 * 
 * Access: Super Admin & Admin only
 */

require_once '../../config/session.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn() || (!isSuperAdmin() && !isAdmin())) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid schedule ID']);
    exit();
}

$conn = getDBConnection();

// Soft delete: hide from calendar but preserve for analytics
$stmt = $conn->prepare("
    UPDATE maintenance_schedules 
    SET deleted_from_calendar = 1 
    WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$affectedRows = $stmt->affected_rows;
$stmt->close();

if ($affectedRows > 0) {
    // Rows updated — successful soft delete
    echo json_encode(['success' => true, 'message' => 'Schedule removed successfully']);
} else {
    // 0 rows affected — either already deleted (deleted_from_calendar already = 1)
    // or record doesn't exist. Check which one:
    $checkStmt = $conn->prepare("SELECT id, deleted_from_calendar FROM maintenance_schedules WHERE id = ?");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $row = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if ($row) {
        // Record exists but was already soft-deleted — treat as success
        echo json_encode(['success' => true, 'message' => 'Schedule removed successfully']);
    } else {
        // Record genuinely doesn't exist
        echo json_encode(['success' => false, 'message' => 'Schedule not found']);
    }
}

$conn->close();
?>