<?php
/* G-Portal - Delete Maintenance Schedule (Soft Delete)*/

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
    
    echo json_encode(['success' => true, 'message' => 'Schedule removed successfully']);
} else {

    $checkStmt = $conn->prepare("SELECT id, deleted_from_calendar FROM maintenance_schedules WHERE id = ?");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $row = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if ($row) {
        
        echo json_encode(['success' => true, 'message' => 'Schedule removed successfully']);
    } else {
        
        echo json_encode(['success' => false, 'message' => 'Schedule not found']);
    }
}

$conn->close();
?>