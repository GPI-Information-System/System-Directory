<?php
/**
 * G-Portal - Reorder Categories
 * Accepts an ordered array of category IDs and updates sort_order accordingly.
 * Both Super Admin and Admin can reorder.
 */

require_once '../../config/session.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn() || (!isSuperAdmin() && !isAdmin())) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$ids = $_POST['ids'] ?? [];
if (!is_array($ids) || count($ids) === 0) {
    echo json_encode(['success' => false, 'message' => 'No category IDs provided']);
    exit();
}

$conn = getDBConnection();

$stmt = $conn->prepare("UPDATE categories SET sort_order = ? WHERE id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB prepare error: ' . $conn->error]);
    exit();
}

foreach ($ids as $index => $rawId) {
    $catId     = intval($rawId);
    $sortOrder = $index + 1;
    $stmt->bind_param('ii', $sortOrder, $catId);
    $stmt->execute();
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'message' => 'Category order saved']);
?>