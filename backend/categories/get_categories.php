<?php
/* G-Portal - Get Categories -  Returns all categories ordered by sort_order with system counts.*/

require_once '../../config/session.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

$conn = getDBConnection();

$result = $conn->query("
    SELECT
        c.id,
        c.name,
        c.sort_order,
        COUNT(s.id) AS system_count
    FROM categories c
    LEFT JOIN systems s ON s.category = c.name
    GROUP BY c.id, c.name, c.sort_order
    ORDER BY c.sort_order ASC, c.name ASC
");

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
    exit();
}

$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[] = [
        'id'           => (int)$row['id'],
        'name'         => $row['name'],
        'sort_order'   => (int)$row['sort_order'],
        'system_count' => (int)$row['system_count'],
    ];
}

$conn->close();
echo json_encode(['success' => true, 'data' => $categories]);
?>