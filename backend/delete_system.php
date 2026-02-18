<?php
require_once '../config/session.php';
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and is Super Admin
if (!isLoggedIn() || !isSuperAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    
    // Validation
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid system ID']);
        exit();
    }
    
    // Delete from database
    $conn = getDBConnection();
    $stmt = $conn->prepare("DELETE FROM systems WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'System deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting system']);
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>