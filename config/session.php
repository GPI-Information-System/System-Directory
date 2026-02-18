<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is Super Admin
function isSuperAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'Super Admin';
}

// Check if user is Admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit();
    }
}

// Get current user info
function getCurrentUser() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null
    ];
}
?>