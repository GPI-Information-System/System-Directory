<?php
require_once '../config/session.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isSuperAdmin()) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to perform this action. Please contact your administrator.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = trim($_POST['name']           ?? '');
    $category      = trim($_POST['category']       ?? '');
    $domain        = trim($_POST['domain']         ?? '');
    $networkType   = trim($_POST['network_type']   ?? '');
    $badgeUrl      = trim($_POST['badge_url']      ?? '');
    $description   = trim($_POST['description']    ?? '');
    $contactNumber = trim($_POST['contact_number'] ?? '123');
    $logo          = null;



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

    if (empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Category is required. Please select a category for this system.']);
        exit();
    }

    if (empty($networkType) || !in_array($networkType, ['http', 'https'])) {
        echo json_encode(['success' => false, 'message' => 'Network Type is required. Please select either HTTP or HTTPS for this system.']);
        exit();
    }



    $conn = getDBConnection();
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

 

    if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {


        if ($_FILES['logo']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['logo']['error'] === UPLOAD_ERR_FORM_SIZE) {
            echo json_encode(['success' => false, 'message' => 'The logo image is too large to upload. Maximum allowed size is 5 MB. Please reduce the image size and try again.']);
            exit();
        }

        // Other upload errors
        if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_PARTIAL    => 'The logo was only partially uploaded. Please try again.',
                UPLOAD_ERR_NO_TMP_DIR => 'Upload failed: missing temporary folder on the server. Please contact your administrator.',
                UPLOAD_ERR_CANT_WRITE => 'Upload failed: unable to write file to disk. Please contact your administrator.',
                UPLOAD_ERR_EXTENSION  => 'Upload failed: a server extension blocked the upload. Please contact your administrator.',
            ];
            $errMsg = $uploadErrors[$_FILES['logo']['error']] ?? 'Logo upload failed unexpectedly. Please try again.';
            echo json_encode(['success' => false, 'message' => $errMsg]);
            exit();
        }

 
        $maxSize = 5 * 1024 * 1024;
        if ($_FILES['logo']['size'] > $maxSize) {
            $sizeMB = round($_FILES['logo']['size'] / (1024 * 1024), 1);
            echo json_encode(['success' => false, 'message' => "The logo image is too large ({$sizeMB} MB). Maximum allowed size is 5 MB. Please compress or resize the image and try again."]);
            exit();
        }

  
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($_FILES['logo']['type'], $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid image format. Only JPG, PNG, GIF, and WEBP files are accepted for the system logo.']);
            exit();
        }

        // Save file
        $uploadDir = '../uploads/logos/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        $extension  = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $filename   = uniqid('logo_') . '.' . $extension;
        $uploadPath = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath)) {
            $logo = 'uploads/logos/' . $filename;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save the logo image on the server. Please try again or contact your administrator.']);
            exit();
        }
    }

    // Additional fields 

    $japaneseDomain      = trim($_POST['japanese_domain']      ?? '');
    $japaneseDescription = trim($_POST['japanese_description'] ?? '');

    $status = trim($_POST['status'] ?? 'online');
    $allowedStatuses = ['online', 'offline', 'maintenance', 'down', 'archived'];
    if (!in_array($status, $allowedStatuses)) {
        $status = 'online';
    }

    //  Insert 

    $stmt = $conn->prepare("
        INSERT INTO systems
            (name, category, domain, network_type, japanese_domain, badge_url, logo, description, japanese_description, contact_number, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "sssssssssss",
        $name, $category, $domain, $networkType, $japaneseDomain,
        $badgeUrl, $logo, $description, $japaneseDescription,
        $contactNumber, $status
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'System added successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save the system to the database. Please try again or contact your administrator.']);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh the page and try again.']);
}
?>