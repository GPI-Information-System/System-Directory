<?php
require_once '../config/session.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isSuperAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
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
        echo json_encode(['success' => false, 'message' => 'System name is required.']);
        exit();
    }

    if (empty($domain)) {
        echo json_encode(['success' => false, 'message' => 'Domain is required.']);
        exit();
    }

    if (empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Category is required.']);
        exit();
    }

    // Validate network type
    if (empty($networkType) || !in_array($networkType, ['http', 'https'])) {
        echo json_encode(['success' => false, 'message' => 'Network Type is required. Please select HTTP or HTTPS.']);
        exit();
    }

    // Validate category 
    $conn = getDBConnection();
    $catCheck = $conn->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
    $catCheck->bind_param("s", $category);
    $catCheck->execute();
    if ($catCheck->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid category selected.']);
        exit();
    }
    $catCheck->close();

    // Handle logo upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['logo']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['logo']['error'] === UPLOAD_ERR_FORM_SIZE) {
            echo json_encode(['success' => false, 'message' => 'Logo file is too large. Maximum allowed size is 5 MB.']);
            exit();
        }
        if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Logo upload failed (error code: ' . $_FILES['logo']['error'] . ').']);
            exit();
        }
        $maxSize = 5 * 1024 * 1024;
        if ($_FILES['logo']['size'] > $maxSize) {
            $sizeMB = round($_FILES['logo']['size'] / (1024 * 1024), 1);
            echo json_encode(['success' => false, 'message' => "Logo file is too large ({$sizeMB} MB). Maximum allowed size is 5 MB."]);
            exit();
        }
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($_FILES['logo']['type'], $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WEBP images are allowed.']);
            exit();
        }
        $uploadDir = '../uploads/logos/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        $extension  = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $filename   = uniqid('logo_') . '.' . $extension;
        $uploadPath = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath)) {
            $logo = 'uploads/logos/' . $filename;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload logo. Please try again.']);
            exit();
        }
    }

    $japaneseDomain      = trim($_POST['japanese_domain']      ?? '');
    $japaneseDescription = trim($_POST['japanese_description'] ?? '');

    $status = trim($_POST['status'] ?? 'online');
    $allowedStatuses = ['online', 'offline', 'maintenance', 'down', 'archived'];
    if (!in_array($status, $allowedStatuses)) {
        $status = 'online';
    }

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
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>