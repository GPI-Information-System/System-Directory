<?php
/**
 * G-Portal - Save Category
 * Handles:
 *   action=add    — Super Admin only: create a new category
 *   action=rename — Super Admin or Admin: rename an existing category
 *                   also updates all systems.category where old name matched
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

$action  = trim($_POST['action']   ?? '');
$id      = intval($_POST['id']     ?? 0);
$name    = trim($_POST['name']     ?? '');

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Category name is required']);
    exit();
}

if (strlen($name) > 100) {
    echo json_encode(['success' => false, 'message' => 'Category name must be 100 characters or fewer']);
    exit();
}

$conn = getDBConnection();

// ── ADD ──────────────────────────────────────────────────────
if ($action === 'add') {
    if (!isSuperAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Only Super Admins can add new categories']);
        exit();
    }

    // Check for duplicate name (case-insensitive)
    $check = $conn->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?)");
    $check->bind_param('s', $name);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $check->close();
        echo json_encode(['success' => false, 'message' => "A category named \"$name\" already exists"]);
        exit();
    }
    $check->close();

    // Get the next sort_order value
    $maxRow = $conn->query("SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order FROM categories")->fetch_assoc();
    $nextOrder = (int)$maxRow['next_order'];

    $stmt = $conn->prepare("INSERT INTO categories (name, sort_order) VALUES (?, ?)");
    $stmt->bind_param('si', $name, $nextOrder);
    if ($stmt->execute()) {
        $newId = $conn->insert_id;
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => true, 'message' => "Category \"$name\" added successfully", 'id' => $newId, 'sort_order' => $nextOrder]);
    } else {
        $err = $stmt->error;
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $err]);
    }
    exit();
}

// ── RENAME ───────────────────────────────────────────────────
if ($action === 'rename') {
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
        exit();
    }

    // Fetch old name
    $fetchStmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
    $fetchStmt->bind_param('i', $id);
    $fetchStmt->execute();
    $row = $fetchStmt->get_result()->fetch_assoc();
    $fetchStmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Category not found']);
        exit();
    }
    $oldName = $row['name'];

    // Check for duplicate (ignore self)
    $dupStmt = $conn->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?) AND id != ?");
    $dupStmt->bind_param('si', $name, $id);
    $dupStmt->execute();
    if ($dupStmt->get_result()->num_rows > 0) {
        $dupStmt->close();
        echo json_encode(['success' => false, 'message' => "A category named \"$name\" already exists"]);
        exit();
    }
    $dupStmt->close();

    // Rename category
    $updStmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
    $updStmt->bind_param('si', $name, $id);
    if (!$updStmt->execute()) {
        $err = $updStmt->error;
        $updStmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $err]);
        exit();
    }
    $updStmt->close();

    // Update all systems that had the old category name
    $sysStmt = $conn->prepare("UPDATE systems SET category = ? WHERE category = ?");
    $sysStmt->bind_param('ss', $name, $oldName);
    $sysStmt->execute();
    $affectedSystems = $sysStmt->affected_rows;
    $sysStmt->close();

    $conn->close();
    echo json_encode([
        'success'          => true,
        'message'          => "Category renamed from \"$oldName\" to \"$name\"",
        'old_name'         => $oldName,
        'new_name'         => $name,
        'affected_systems' => $affectedSystems,
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
$conn->close();
?>