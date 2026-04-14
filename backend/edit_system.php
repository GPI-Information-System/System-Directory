<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once 'send_email_notification.php';

header('Content-Type: application/json');

if (!isLoggedIn() || (!isSuperAdmin() && !isAdmin())) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $systemId           = intval($_POST['id']                ?? 0);
    $name               = trim($_POST['name']               ?? '');
    $category           = trim($_POST['category']           ?? '');
    $domain             = trim($_POST['domain']             ?? '');
    $networkType        = trim($_POST['network_type']       ?? '');
    $badgeUrl           = trim($_POST['badge_url']          ?? '');
    $description        = trim($_POST['description']        ?? '');
    $status             = trim($_POST['status']             ?? 'online');
    $contactNumber      = trim($_POST['contact_number']     ?? '123');
    $changeNote         = trim($_POST['change_note']        ?? '');
    $excludeHealthCheck = isset($_POST['exclude_health_check']) ? 1 : 0;
    $japaneseDomain      = trim($_POST['japanese_domain']      ?? '');
    $japaneseDescription = trim($_POST['japanese_description'] ?? '');

    if ($systemId <= 0 || empty($name) || empty($domain)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input. Name and domain are required.']);
        exit();
    }

    // Validate network type
    if (empty($networkType) || !in_array($networkType, ['http', 'https'])) {
        echo json_encode(['success' => false, 'message' => 'Network Type is required. Please select HTTP or HTTPS.']);
        exit();
    }

    $conn = getDBConnection();

    // Validate category 
    if (empty($category)) {
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Category is required.']);
        exit();
    }
    $catCheck = $conn->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
    $catCheck->bind_param("s", $category);
    $catCheck->execute();
    if ($catCheck->get_result()->num_rows === 0) {
        $catCheck->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Invalid category selected.']);
        exit();
    }
    $catCheck->close();

    $oldStatusQuery = $conn->prepare("SELECT status, name, domain FROM systems WHERE id = ?");
    $oldStatusQuery->bind_param("i", $systemId);
    $oldStatusQuery->execute();
    $oldData      = $oldStatusQuery->get_result()->fetch_assoc();
    $oldStatus    = $oldData['status'] ?? 'online';
    $systemName   = $oldData['name'];
    $systemDomain = $oldData['domain'];
    $oldStatusQuery->close();

    $logoPath = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['logo']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['logo']['error'] === UPLOAD_ERR_FORM_SIZE) {
            $conn->close();
            echo json_encode(['success' => false, 'message' => 'Logo file is too large. Maximum allowed size is 5 MB.']);
            exit();
        }
        if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            $conn->close();
            echo json_encode(['success' => false, 'message' => 'Logo upload failed (error code: ' . $_FILES['logo']['error'] . ').']);
            exit();
        }
        $maxSize = 5 * 1024 * 1024;
        if ($_FILES['logo']['size'] > $maxSize) {
            $sizeMB = round($_FILES['logo']['size'] / (1024 * 1024), 1);
            $conn->close();
            echo json_encode(['success' => false, 'message' => "Logo file is too large ({$sizeMB} MB). Maximum allowed size is 5 MB."]);
            exit();
        }
        $fileExtension     = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($fileExtension, $allowedExtensions)) {
            $conn->close();
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WEBP images are allowed.']);
            exit();
        }
        $uploadDir = '../uploads/logos/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName = 'system_' . $systemId . '_' . time() . '.' . $fileExtension;
        $logoPath = 'uploads/logos/' . $fileName;
        if (!move_uploaded_file($_FILES['logo']['tmp_name'], '../' . $logoPath)) {
            $conn->close();
            echo json_encode(['success' => false, 'message' => 'Failed to upload logo. Please try again.']);
            exit();
        }
    }

    if ($logoPath) {
        $stmt = $conn->prepare("UPDATE systems SET name = ?, category = ?, domain = ?, network_type = ?, japanese_domain = ?, badge_url = ?, description = ?, japanese_description = ?, status = ?, contact_number = ?, logo = ?, exclude_health_check = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("sssssssssssii", $name, $category, $domain, $networkType, $japaneseDomain, $badgeUrl, $description, $japaneseDescription, $status, $contactNumber, $logoPath, $excludeHealthCheck, $systemId);
    } else {
        $stmt = $conn->prepare("UPDATE systems SET name = ?, category = ?, domain = ?, network_type = ?, japanese_domain = ?, badge_url = ?, description = ?, japanese_description = ?, status = ?, contact_number = ?, exclude_health_check = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssssssssssii", $name, $category, $domain, $networkType, $japaneseDomain, $badgeUrl, $description, $japaneseDescription, $status, $contactNumber, $excludeHealthCheck, $systemId);
    }

    if ($stmt->execute()) {
        $statusChanged = ($oldStatus !== $status);
        if ($statusChanged) {
            $currentUser = getCurrentUser();
            $userId      = $currentUser['id'];
            $logStmt     = $conn->prepare("INSERT INTO status_logs (system_id, old_status, new_status, changed_by, change_note) VALUES (?, ?, ?, ?, ?)");
            $logStmt->bind_param("issis", $systemId, $oldStatus, $status, $userId, $changeNote);
            $logStmt->execute();
            $logStmt->close();
            if (in_array($status, ['down', 'offline'])) {
                sendStatusChangeEmail($systemId, $systemName, $oldStatus, $status, $systemDomain, $currentUser['username'], $changeNote);
            }
        }
        echo json_encode(['success' => true, 'message' => 'System updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>