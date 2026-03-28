<?php
require_once '../config/session.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isSuperAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$userId      = intval($_POST['id'] ?? 0);
$currentUser = getCurrentUser();

if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
    exit();
}

// Prevent deleting yourself
if ($userId == $currentUser['id']) {
    echo json_encode(['success' => false, 'message' => 'You cannot delete your own account.']);
    exit();
}

$conn = getDBConnection();

// Make sure at least one Super Admin remains
$countStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE role = 'Super Admin' AND id != ?");
$countStmt->bind_param("i", $userId);
$countStmt->execute();
$countRow = $countStmt->get_result()->fetch_assoc();
$countStmt->close();

// Get role of user being deleted
$roleStmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$roleStmt->bind_param("i", $userId);
$roleStmt->execute();
$roleRow = $roleStmt->get_result()->fetch_assoc();
$roleStmt->close();

if ($roleRow['role'] === 'Super Admin' && $countRow['cnt'] < 1) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Cannot delete the last Super Admin account.']);
    exit();
}

$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);

if ($stmt->execute()) {
    $stmt->close(); $conn->close();
    echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
} else {
    $stmt->close(); $conn->close();
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
?>