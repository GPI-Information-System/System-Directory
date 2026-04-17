<?php
require_once '../config/session.php';
require_once '../config/database.php';

requireLogin();

// SuperAdmin only
if (!isSuperAdmin()) {
    header('Location: ../pages/access_logs.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    $conn->query("TRUNCATE TABLE access_logs");
    $conn->close();
}

header('Location: ../pages/access_logs.php');
exit();
?>