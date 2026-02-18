<?php
/**
 * ============================================================
 * PHASE 3 UPDATE: Edit System with Email Notifications
 * This is the UPDATED version of edit_system.php
 * 
 * CHANGES FROM PHASE 2:
 * - Added email notification trigger for critical status changes
 * - Sends emails when system goes Down or Offline
 * ============================================================
 */

require_once '../config/session.php';
require_once '../config/database.php';
require_once 'send_email_notification.php'; // PHASE 3: Email function

header('Content-Type: application/json');

// Check if user is logged in and has permission
if (!isLoggedIn() || (!isSuperAdmin() && !isAdmin())) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $systemId = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $domain = trim($_POST['domain'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? 'online');
    $contactNumber = trim($_POST['contact_number'] ?? '123');
    $changeNote = trim($_POST['change_note'] ?? ''); // PHASE 2: Change note
    
    // Validation
    if ($systemId <= 0 || empty($name) || empty($domain)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit();
    }
    
    // Validate contact number (numbers only)
    if (!empty($contactNumber) && !ctype_digit($contactNumber)) {
        echo json_encode(['success' => false, 'message' => 'Contact number must contain only numbers']);
        exit();
    }
    
    $conn = getDBConnection();
    
    // PHASE 2 & 3: Get old status BEFORE updating (for logging and email)
    $oldStatusQuery = $conn->prepare("SELECT status, name, domain FROM systems WHERE id = ?");
    $oldStatusQuery->bind_param("i", $systemId);
    $oldStatusQuery->execute();
    $oldStatusResult = $oldStatusQuery->get_result();
    $oldData = $oldStatusResult->fetch_assoc();
    $oldStatus = $oldData['status'] ?? 'online';
    $systemName = $oldData['name'];
    $systemDomain = $oldData['domain'];
    $oldStatusQuery->close();
    
    // Handle logo upload
    $logoPath = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/logos/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $fileName = 'system_' . $systemId . '_' . time() . '.' . $fileExtension;
            $logoPath = 'uploads/logos/' . $fileName;
            
            if (!move_uploaded_file($_FILES['logo']['tmp_name'], '../' . $logoPath)) {
                $logoPath = null;
            }
        }
    }
    
    // Update system
    if ($logoPath) {
        $stmt = $conn->prepare("UPDATE systems SET name = ?, domain = ?, description = ?, status = ?, contact_number = ?, logo = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssssssi", $name, $domain, $description, $status, $contactNumber, $logoPath, $systemId);
    } else {
        $stmt = $conn->prepare("UPDATE systems SET name = ?, domain = ?, description = ?, status = ?, contact_number = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("sssssi", $name, $domain, $description, $status, $contactNumber, $systemId);
    }
    
    if ($stmt->execute()) {
        // PHASE 2: Log status change if status changed
        $statusChanged = ($oldStatus !== $status);
        
        if ($statusChanged) {
            $currentUser = getCurrentUser();
            $userId = $currentUser['id'];
            
            $logStmt = $conn->prepare("INSERT INTO status_logs (system_id, old_status, new_status, changed_by, change_note) VALUES (?, ?, ?, ?, ?)");
            $logStmt->bind_param("issis", $systemId, $oldStatus, $status, $userId, $changeNote);
            $logStmt->execute();
            $logStmt->close();
            
            // PHASE 3: Send email notification for critical status changes
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