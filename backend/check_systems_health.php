<?php
// AUTOMATIC SYSTEM HEALTH MONITORING
// Checks all systems and auto-updates status based on accessibility

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/send_email_notification.php';

// Configuration
define('CHECK_TIMEOUT', 10);
define('USER_AGENT', 'G-Portal Health Monitor/1.0');
define('SYSTEM_USER_ID', 1);
define('LOG_FILE', __DIR__ . '/logs/health_check.log');

// Ensure log directory exists
$logDir = dirname(LOG_FILE);
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

// Write to log file
function writeLog($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);
}

// Check if a URL is accessible
function checkSystemHealth($domain) {
    $url = $domain;
    
    // Add http:// if no protocol specified
    if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
        $url = "http://" . $url;
    }
    
    $result = [
        'accessible' => false,
        'http_code' => null,
        'error' => null,
        'response_time' => null
    ];
    
    $startTime = microtime(true);
    
    // Initialize cURL
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => CHECK_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => CHECK_TIMEOUT,
        CURLOPT_USERAGENT => USER_AGENT,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_NOBODY => true,
        CURLOPT_HEADER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    
    $endTime = microtime(true);
    $result['response_time'] = round(($endTime - $startTime) * 1000, 2);
    
    // Analyze result
    if ($curlError) {
        $result['accessible'] = false;
        $result['error'] = getReadableError($curlErrno, $curlError);
    } elseif ($httpCode >= 200 && $httpCode < 400) {
        $result['accessible'] = true;
        $result['http_code'] = $httpCode;
    } else {
        $result['accessible'] = false;
        $result['http_code'] = $httpCode;
        $result['error'] = "HTTP $httpCode " . getHttpErrorMessage($httpCode);
    }
    
    return $result;
}

// Convert cURL error codes to readable messages
function getReadableError($errno, $error) {
    $errors = [
        6 => 'DNS resolution failed - Could not resolve host',
        7 => 'Connection refused - Server not accepting connections',
        28 => 'Connection timeout - Server did not respond within ' . CHECK_TIMEOUT . ' seconds',
        35 => 'SSL connection error - Certificate issue',
        51 => 'SSL certificate validation failed',
        52 => 'Empty response from server',
        56 => 'Network error - Failed receiving data'
    ];
    
    return $errors[$errno] ?? "Connection error: $error";
}

// Get HTTP status code message
function getHttpErrorMessage($code) {
    $messages = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout'
    ];
    
    return $messages[$code] ?? 'Error';
}

// Update system status in database
function updateSystemStatus($systemId, $newStatus, $oldStatus, $errorDetails, $systemName, $systemDomain) {
    $conn = getDBConnection();
    
    // Update system status
    $stmt = $conn->prepare("UPDATE systems SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $newStatus, $systemId);
    $stmt->execute();
    $stmt->close();
    
    // Create change note
    $changeNote = "Auto-detected: " . $errorDetails;
    
    // Log the status change
    $logStmt = $conn->prepare("INSERT INTO status_logs (system_id, old_status, new_status, changed_by, change_note) VALUES (?, ?, ?, ?, ?)");
    $systemUserId = SYSTEM_USER_ID;
    $logStmt->bind_param("issis", $systemId, $oldStatus, $newStatus, $systemUserId, $changeNote);
    $logStmt->execute();
    $logStmt->close();
    
    // Send email notification for critical status changes
    if (in_array($newStatus, ['down', 'offline'])) {
        sendStatusChangeEmail(
            $systemId,
            $systemName,
            $oldStatus,
            $newStatus,
            $systemDomain,
            'System Monitor (Automated)',
            $changeNote
        );
    }
    
    writeLog("Updated system #$systemId ($systemName): $oldStatus -> $newStatus - $errorDetails");
}

// Main monitoring function
function monitorAllSystems() {
    writeLog("Starting health check");
    
    $conn = getDBConnection();
    
    // Get all non-archived systems
    $result = $conn->query("
        SELECT id, name, domain, status 
        FROM systems 
        WHERE status != 'archived' 
        ORDER BY id ASC
    ");
    
    if (!$result) {
        writeLog("ERROR: Could not fetch systems - " . $conn->error);
        return;
    }
    
    $systemsChecked = 0;
    $systemsChanged = 0;
    
    while ($system = $result->fetch_assoc()) {
        $systemId = $system['id'];
        $systemName = $system['name'];
        $systemDomain = $system['domain'];
        $currentStatus = $system['status'];
        
        writeLog("Checking system #$systemId: $systemName ($systemDomain) - Current status: $currentStatus");
        
        // Check system health
        $health = checkSystemHealth($systemDomain);
        $systemsChecked++;
        
        // Determine new status based on health check
        $newStatus = null;
        $errorDetails = null;
        
        if ($health['accessible']) {
            // System is accessible
            if ($currentStatus !== 'online' && $currentStatus !== 'maintenance') {
                $newStatus = 'online';
                $errorDetails = "System is now accessible (HTTP {$health['http_code']}, {$health['response_time']}ms)";
            } else {
                writeLog("  OK: System accessible (HTTP {$health['http_code']}, {$health['response_time']}ms) - No change needed");
            }
        } else {
            // System is NOT accessible
            if ($currentStatus === 'online') {
                $newStatus = 'down';
                $errorDetails = $health['error'];
            } elseif ($currentStatus === 'maintenance') {
                writeLog("  WARNING: System not accessible but in maintenance mode - Keeping maintenance status");
            } else {
                writeLog("  ERROR: System not accessible ({$health['error']}) - Already marked as $currentStatus");
            }
        }
        
        // Update status if changed
        if ($newStatus && $newStatus !== $currentStatus) {
            updateSystemStatus($systemId, $newStatus, $currentStatus, $errorDetails, $systemName, $systemDomain);
            $systemsChanged++;
        }
        
        // Small delay to avoid overwhelming servers
        usleep(500000);
    }
    
    writeLog("Health check completed: $systemsChecked systems checked, $systemsChanged status changes");
    writeLog("");
}

// Run monitoring
try {
    monitorAllSystems();
    echo "Health check completed successfully.\n";
} catch (Exception $e) {
    writeLog("FATAL ERROR: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>