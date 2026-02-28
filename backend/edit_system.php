<?php
/**
 * ============================================================
 * PHASE 3 UPDATE: Edit System with Email Notifications
 * UPDATED: Added exclude_health_check support
 * ============================================================
 */

require_once '../config/session.php';
require_once '../config/database.php';
require_once 'send_email_notification.php';

header('Content-Type: application/json');

if (!isLoggedIn() || (!isSuperAdmin() && !isAdmin())) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $systemId           = intval($_POST['id'] ?? 0);
    $name               = trim($_POST['name'] ?? '');
    $domain             = trim($_POST['domain'] ?? '');
    $description        = trim($_POST['description'] ?? '');
    $status             = trim($_POST['status'] ?? 'online');
    $contactNumber      = trim($_POST['contact_number'] ?? '123');
    $changeNote         = trim($_POST['change_note'] ?? '');
    $excludeHealthCheck = isset($_POST['exclude_health_check']) ? 1 : 0; // NEW
    
    if ($systemId <= 0 || empty($name) || empty($domain)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit();
    }
    
    if (!empty($contactNumber) && !ctype_digit($contactNumber)) {
        echo json_encode(['success' => false, 'message' => 'Contact number must contain only numbers']);
        exit();
    }
    
    $conn = getDBConnection();
    
    // Get old status BEFORE updating
    $oldStatusQuery = $conn->prepare("SELECT status, name, domain FROM systems WHERE id = ?");
    $oldStatusQuery->bind_param("i", $systemId);
    $oldStatusQuery->execute();
    $oldStatusResult = $oldStatusQuery->get_result();
    $oldData         = $oldStatusResult->fetch_assoc();
    $oldStatus       = $oldData['status'] ?? 'online';
    $systemName      = $oldData['name'];
    $systemDomain    = $oldData['domain'];
    $oldStatusQuery->close();
    
    // Handle logo upload
    $logoPath = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/logos/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension    = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $fileName = 'system_' . $systemId . '_' . time() . '.' . $fileExtension;
            $logoPath = 'uploads/logos/' . $fileName;
            
            if (!move_uploaded_file($_FILES['logo']['tmp_name'], '../' . $logoPath)) {
                $logoPath = null;
            }
        }
    }
    
    // Update system — include exclude_health_check in both branches
    if ($logoPath) {
        $stmt = $conn->prepare("UPDATE systems SET name = ?, domain = ?, description = ?, status = ?, contact_number = ?, logo = ?, exclude_health_check = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("sssssisi", $name, $domain, $description, $status, $contactNumber, $logoPath, $excludeHealthCheck, $systemId);
    } else {
        $stmt = $conn->prepare("UPDATE systems SET name = ?, domain = ?, description = ?, status = ?, contact_number = ?, exclude_health_check = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("sssssii", $name, $domain, $description, $status, $contactNumber, $excludeHealthCheck, $systemId);
    }
    
    if ($stmt->execute()) {
        $statusChanged = ($oldStatus !== $status);
        
        if ($statusChanged) {
            $currentUser = getCurrentUser();
            $userId      = $currentUser['id'];
            
            $logStmt = $conn->prepare("INSERT INTO status_logs (system_id, old_status, new_status, changed_by, change_note) VALUES (?, ?, ?, ?, ?)");
            $logStmt->bind_param("issis", $systemId, $oldStatus, $status, $userId, $changeNote);
            $logStmt->execute();
            $logStmt->close();
            
            if (in_array($status, ['down', 'offline'])) {
                $changedBy = $currentUser['username'];
                sendStatusChangeEmail(
                    $systemId,
                    $systemName,
                    $oldStatus,
                    $status,
                    $systemDomain,
                    $changedBy,
                    $changeNote
                );
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'System updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating system: ' . $stmt->error]);
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>