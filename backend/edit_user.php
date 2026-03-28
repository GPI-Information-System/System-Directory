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

$userId          = intval($_POST['id']             ?? 0);
$username        = trim($_POST['username']         ?? '');
$role            = trim($_POST['role']             ?? '');
$email           = trim($_POST['email']            ?? '');
$password        = trim($_POST['password']         ?? '');
$passwordConfirm = trim($_POST['password_confirm'] ?? '');
$changePassword  = !empty($password);

if ($userId <= 0 || empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit();
}
if (!in_array($role, ['Super Admin', 'Admin'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid role selected.']);
    exit();
}
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit();
}
if ($changePassword) {
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        exit();
    }
    if ($password !== $passwordConfirm) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit();
    }
}

$conn = getDBConnection();

// Check username uniqueness (exclude current user)
$check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
$check->bind_param("si", $username, $userId);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    $check->close(); $conn->close();
    echo json_encode(['success' => false, 'message' => 'Username already taken by another user.']);
    exit();
}
$check->close();

$emailVal = !empty($email) ? $email : null;

if ($changePassword) {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET username = ?, role = ?, email = ?, password = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $username, $role, $emailVal, $hashedPassword, $userId);
} else {
    $stmt = $conn->prepare("UPDATE users SET username = ?, role = ?, email = ? WHERE id = ?");
    $stmt->bind_param("sssi", $username, $role, $emailVal, $userId);
}

if ($stmt->execute()) {
    $stmt->close(); $conn->close();
    echo json_encode([
        'success' => true,
        'message' => 'User updated successfully.',
        'user'    => [
            'id'       => $userId,
            'username' => $username,
            'role'     => $role,
            'email'    => $emailVal ?? '',
        ]
    ]);
} else {
    $stmt->close(); $conn->close();
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
?>