<?php
/**
 * ============================================================
 * EMAIL RECIPIENTS API
 * Handles autocomplete suggestions and recipient management
 * 
 * Actions:
 *   GET  ?action=suggest&q=john    → autocomplete suggestions
 *   POST action=add                → add/update a recipient
 * ============================================================
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');

// Only admins/superadmins can access
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$conn   = getDBConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Autocomplete suggestions ──────────────────────────────────
if ($action === 'suggest') {
    $q = trim($_GET['q'] ?? '');

    if (strlen($q) < 1) {
        echo json_encode(['success' => true, 'suggestions' => []]);
        exit();
    }

    $like  = '%' . $q . '%';
    $stmt  = $conn->prepare("
        SELECT email, name, use_count
        FROM email_recipients
        WHERE email LIKE ? OR name LIKE ?
        ORDER BY use_count DESC, last_used DESC
        LIMIT 8
    ");
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'suggestions' => $rows]);
    exit();
}

// ── Fallback ──────────────────────────────────────────────────
echo json_encode(['success' => false, 'message' => 'Unknown action']);
exit();