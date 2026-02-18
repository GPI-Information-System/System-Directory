<?php
/**
 * ============================================================
 * PHASE 2 ENHANCEMENT: Update Status Log Note
 * Allows admins to edit notes for existing status change logs
 * ============================================================
 */

require_once '../config/session.php';
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and has permission (Super Admin or Admin)
if (!isLoggedIn() || (!isSuperAdmin() && !isAdmin())) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logId = intval($_POST['log_id'] ?? 0);
    $newNote = trim($_POST['note'] ?? '');
    
    // Validation
    if ($logId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid log ID']);
        exit();
    }
    
    // Get current user info
    $currentUser = getCurrentUser();
    $userId = $currentUser['id'];
    
    // Update database
    $conn = getDBConnection();
    
    // Update the note and timestamp
    $stmt = $conn->prepare("UPDATE status_logs SET change_note = ?, changed_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $newNote, $logId);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'Note updated successfully',
                'new_note' => $newNote ? $newNote : 'No note',
                'new_timestamp' => date('M d, Y, h:i A')
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No changes made or log not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating note: ' . $stmt->error]);
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>