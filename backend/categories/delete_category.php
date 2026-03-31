<?php
/*
 * G-Portal - Delete Category - Super Admin only. - Requires a reassign_to category name for any systems currently in this category.*/

require_once '../../config/session.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isSuperAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Only Super Admins can delete categories']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$id          = intval($_POST['id']          ?? 0);
$reassignTo  = trim($_POST['reassign_to']   ?? '');

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
    exit();
}

$conn = getDBConnection();

// Fetch the category to delete
$fetchStmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
$fetchStmt->bind_param('i', $id);
$fetchStmt->execute();
$row = $fetchStmt->get_result()->fetch_assoc();
$fetchStmt->close();

if (!$row) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Category not found']);
    exit();
}
$categoryName = $row['name'];

// Prevent deleting the last category
$countRow = $conn->query("SELECT COUNT(*) AS total FROM categories")->fetch_assoc();
if ((int)$countRow['total'] <= 1) {
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Cannot delete the last remaining category']);
    exit();
}

// Count systems in this category
$sysCountRow = $conn->query("SELECT COUNT(*) AS cnt FROM systems WHERE category = '" . $conn->real_escape_string($categoryName) . "'")->fetch_assoc();
$systemCount = (int)$sysCountRow['cnt'];

// If systems exist, reassign_to is required
if ($systemCount > 0) {
    if (empty($reassignTo)) {
        $conn->close();
        echo json_encode([
            'success'      => false,
            'message'      => 'This category has systems. Please choose a category to reassign them to.',
            'system_count' => $systemCount,
            'needs_reassign' => true,
        ]);
        exit();
    }

    // Validate reassign target exists and is not the same category
    $targetStmt = $conn->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
    $targetStmt->bind_param('si', $reassignTo, $id);
    $targetStmt->execute();
    if ($targetStmt->get_result()->num_rows === 0) {
        $targetStmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Reassign target category not found']);
        exit();
    }
    $targetStmt->close();

    // Reassign systems
    $reassignStmt = $conn->prepare("UPDATE systems SET category = ? WHERE category = ?");
    $reassignStmt->bind_param('ss', $reassignTo, $categoryName);
    $reassignStmt->execute();
    $reassignStmt->close();
}

// Delete the category
$delStmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
$delStmt->bind_param('i', $id);
if ($delStmt->execute()) {
    $delStmt->close();
    $conn->close();
    echo json_encode([
        'success'           => true,
        'message'           => "Category \"$categoryName\" deleted" . ($systemCount > 0 ? ". $systemCount system(s) reassigned to \"$reassignTo\"." : '.'),
        'reassigned_count'  => $systemCount,
    ]);
} else {
    $err = $delStmt->error;
    $delStmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $err]);
}
?>