<?php
require_once '../config/session.php';
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and is Super Admin
if (!isLoggedIn() || !isSuperAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $domain = trim($_POST['domain'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $contactNumber = trim($_POST['contact_number'] ?? '123'); // NEW: Contact number field
    $logo = null;
    
    // Validation
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'System name is required']);
        exit();
    }
    
    if (empty($domain)) {
        echo json_encode(['success' => false, 'message' => 'Domain is required']);
        exit();
    }
    
    // Handle logo upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = $_FILES['logo']['type'];
        
        if (in_array($fileType, $allowedTypes)) {
            $uploadDir = '../uploads/logos/';
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('logo_') . '.' . $extension;
            $uploadPath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath)) {
                $logo = 'uploads/logos/' . $filename;
            }
        }
    }
    
    // UPDATED: Insert into database - Now includes contact_number
    $conn = getDBConnection();
    $stmt = $conn->prepare("INSERT INTO systems (name, domain, logo, description, contact_number) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $domain, $logo, $description, $contactNumber);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'System added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding system']);
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>