<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once 'send_email_notification.php';

header('Content-Type: application/json');

if (!isLoggedIn() || (!isSuperAdmin() && !isAdmin())) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to perform this action. Please contact your administrator.']);
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


    if ($systemId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid system ID. Please refresh the page and try again.']);
        exit();
    }

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'System name is required. Please enter a name for this system.']);
        exit();
    }

    if (strlen($name) > 100) {
        echo json_encode(['success' => false, 'message' => 'System name is too long. Maximum allowed length is 100 characters.']);
        exit();
    }

    if (empty($domain)) {
        echo json_encode(['success' => false, 'message' => 'Domain is required. Please enter the domain for this system (e.g., ams.gpi.com).']);
        exit();
    }

    if (empty($networkType) || !in_array($networkType, ['http', 'https'])) {
        echo json_encode(['success' => false, 'message' => 'Network Type is required. Please select either HTTP or HTTPS for this system.']);
        exit();
    }

    // Category validation 

    $conn = getDBConnection();

    if (empty($category)) {
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Category is required. Please select a category for this system.']);
        exit();
    }

    $catCheck = $conn->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
    $catCheck->bind_param("s", $category);
    $catCheck->execute();
    if ($catCheck->get_result()->num_rows === 0) {
        $catCheck->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'The selected category is not valid. Please select a category from the list.']);
        exit();
    }
    $catCheck->close();


    $oldStatusQuery = $conn->prepare("SELECT status, name, domain FROM systems WHERE id = ?");
    $oldStatusQuery->bind_param("i", $systemId);
    $oldStatusQuery->execute();
    $oldData = $oldStatusQuery->get_result()->fetch_assoc();
    $oldStatusQuery->close();

    if (!$oldData) {
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'System not found. It may have been deleted. Please refresh the page.']);
        exit();
    }

    $oldStatus    = $oldData['status'] ?? 'online';
    $systemName   = $oldData['name'];
    $systemDomain = $oldData['domain'];

  

    $logoPath = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES['logo']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['logo']['error'] === UPLOAD_ERR_FORM_SIZE) {
            $conn->close();
            echo json_encode(['success' => false, 'message' => 'The logo image is too large to upload. Maximum allowed size is 5 MB. Please reduce the image size and try again.']);
            exit();
        }

        // Other upload errors
        if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_PARTIAL    => 'The logo was only partially uploaded. Please try again.',
                UPLOAD_ERR_NO_TMP_DIR => 'Upload failed: missing temporary folder on the server.',
                UPLOAD_ERR_CANT_WRITE => 'Upload failed: unable to write file to disk.',
                UPLOAD_ERR_EXTENSION  => 'Upload failed: a server extension blocked the upload.',
            ];
            $errMsg = $uploadErrors[$_FILES['logo']['error']] ?? 'Logo upload failed unexpectedly. Please try again.';
            $conn->close();
            echo json_encode(['success' => false, 'message' => $errMsg]);
            exit();
        }


        $maxSize = 5 * 1024 * 1024;
        if ($_FILES['logo']['size'] > $maxSize) {
            $sizeMB = round($_FILES['logo']['size'] / (1024 * 1024), 1);
            $conn->close();
            echo json_encode(['success' => false, 'message' => "The logo image is too large ({$sizeMB} MB). Maximum allowed size is 5 MB. Please compress or resize the image and try again."]);
            exit();
        }

        // File type check
        $fileExtension     = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($fileExtension, $allowedExtensions)) {
            $conn->close();
            echo json_encode(['success' => false, 'message' => 'Invalid image format. Only JPG, PNG, GIF, and WEBP files are accepted for the system logo.']);
            exit();
        }

        // Save file
        $uploadDir = '../uploads/logos/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName = 'system_' . $systemId . '_' . time() . '.' . $fileExtension;
        $logoPath = 'uploads/logos/' . $fileName;
        if (!move_uploaded_file($_FILES['logo']['tmp_name'], '../' . $logoPath)) {
            $conn->close();
            echo json_encode(['success' => false, 'message' => 'Failed to save the logo image on the server. Please try again or contact your administrator.']);
            exit();
        }
    }

    //  Update query 

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
        echo json_encode(['success' => false, 'message' => 'Failed to update the system in the database. Please try again or contact your administrator. Error: ' . $conn->error]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh the page and try again.']);
}
?>