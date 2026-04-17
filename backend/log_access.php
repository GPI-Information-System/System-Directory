<?php

require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$systemId     = intval($_POST['system_id']     ?? 0);
$systemName   = trim($_POST['system_name']     ?? '');
$languageMode = trim($_POST['language_mode']   ?? 'en');
$accessedFrom = trim($_POST['accessed_from']   ?? 'grid');


if ($systemId <= 0 || empty($systemName)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit();
}


if (!in_array($languageMode, ['en', 'jp'])) {
    $languageMode = 'en';
}
if (!in_array($accessedFrom, ['grid', 'recents'])) {
    $accessedFrom = 'grid';
}


if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ipRaw     = $_SERVER['HTTP_X_FORWARDED_FOR'];
    $ipAddress = trim(explode(',', $ipRaw)[0]);
} elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ipAddress = trim($_SERVER['HTTP_CLIENT_IP']);
} else {
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}


if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}


$ipAddress = substr($ipAddress, 0, 45);

$browserDevice = substr(trim($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);


$conn = getDBConnection();

$stmt = $conn->prepare("
    INSERT INTO access_logs
        (system_id, system_name, ip_address, browser_device, language_mode, accessed_from)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("isssss",
    $systemId,
    $systemName,
    $ipAddress,
    $browserDevice,
    $languageMode,
    $accessedFrom
);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save access log.']);
}

$stmt->close();
$conn->close();
?>