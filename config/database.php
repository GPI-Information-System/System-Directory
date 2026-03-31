<?php
/* G-Portal — Database Configuration*/

// Database configuration
define('DB_HOST',      'localhost');
define('DB_USER',      'root');
define('DB_PASS',      '');
define('DB_NAME',      'system_directory_db');
define('DB_CHARSET',   'utf8mb4');
define('DB_COLLATION', 'utf8mb4_general_ci');


define('DB_PERSISTENT', false);
define('DB_TIMEOUT',    5);  // Reduced from 10s to 5s


function getDBConnection() {
    static $conn = null;

    // Reuse within the same request only
    if ($conn !== null && $conn->ping()) {
        return $conn;
    }

    // Always use a fresh non-persistent connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        error_log("Database Connection Error: " . $conn->connect_error);
        die("Connection failed. Please try again later.");
    }

    // Set character set
    if (!$conn->set_charset(DB_CHARSET)) {
        error_log("Error setting charset: " . $conn->error);
    }

    // Set connection timeout
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, DB_TIMEOUT);

    return $conn;
}


function getDBConnectionReadOnly() {
    return getDBConnection();
}


function closeDBConnection($conn) {
    if ($conn) {
        $conn->close();
    }
}


function executePreparedQuery($query, $params = [], $types = '') {
    $conn = getDBConnection();

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }

    if (!empty($params)) {
        if (empty($types)) {
            $types = str_repeat('s', count($params));
        }
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }

    $result = $stmt->get_result();
    $stmt->close();

    return $result;
}


function testDBConnection() {
    try {
        $conn = getDBConnection();
        return $conn->ping();
    } catch (Exception $e) {
        error_log("DB Connection Test Failed: " . $e->getMessage());
        return false;
    }
}
?>