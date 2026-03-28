<?php
require_once '../config/session.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isSuperAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$username        = trim($_POST['username']         ?? '');
$role            = trim($_POST['role']             ?? '');
$email           = trim($_POST['email']            ?? '');
$password        = trim($_POST['password']         ?? '');
$passwordConfirm = trim($_POST['password_confirm'] ?? '');

// Validation
if (empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Username is required.']);
    exit();
}
if (!in_array($role, ['Super Admin', 'Admin'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid role selected.']);
    exit();
}
if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password is required.']);
    exit();
}
if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
    exit();
}
if ($password !== $passwordConfirm) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit();
}
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit();
}

$conn = getDBConnection();

// Check if username already exists
$check = $conn->prepare("SELECT id FROM users WHERE username = ?");
$check->bind_param("s", $username);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    $check->close(); $conn->close();
    echo json_encode(['success' => false, 'message' => 'Username already exists. Please choose a different one.']);
    exit();
}
$check->close();

// Hash password and insert
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$emailVal       = !empty($email) ? $email : null;

$stmt = $conn->prepare("INSERT INTO users (username, password, role, email) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $username, $hashedPassword, $role, $emailVal);

if ($stmt->execute()) {
    $newId = $conn->insert_id;
    $stmt->close(); $conn->close();
    echo json_encode([
        'success'  => true,
        'message'  => 'User added successfully.',
        'user'     => [
            'id'         => $newId,
            'username'   => $username,
            'role'       => $role,
            'email'      => $emailVal ?? '',
            'created_at' => date('M d, Y'),
        ]
    ]);
} else {
    $stmt->close(); $conn->close();
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
?>