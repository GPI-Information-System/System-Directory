<?php
// AUTOMATIC SYSTEM HEALTH MONITORING
// Checks all systems and auto-updates status based on badge URL or domain accessibility

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/send_email_notification.php';
require_once __DIR__ . '/maintenance/check_maintenance_schedule.php';

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

function writeLog($message) {
    $timestamp  = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);
}


function checkBadgeStatus($badgeUrl) {
    $ch = curl_init($badgeUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => CHECK_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => CHECK_TIMEOUT,
        CURLOPT_USERAGENT      => USER_AGENT,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode < 200 || $httpCode >= 400 || empty($response)) {
        writeLog("  BADGE ERROR: Could not fetch badge URL — $curlError (HTTP $httpCode)");
        return null;
    }


    $textMatches = [];
    preg_match_all('/<text[^>]*>([^<]+)<\/text>/i', $response, $textMatches);

    $statusText = '';
    foreach ($textMatches[1] as $text) {
        $cleaned = strtolower(trim(html_entity_decode($text)));
        if (in_array($cleaned, ['up', 'down', 'maintenance', 'degraded', 'pending'])) {
            $statusText = $cleaned;
            break;
        }
    }

    if (empty($statusText)) {
        $lower = strtolower($response);
        if (strpos($lower, '>up<') !== false || strpos($lower, '>up <') !== false) {
            $statusText = 'up';
        } elseif (strpos($lower, '>down<') !== false) {
            $statusText = 'down';
        } elseif (strpos($lower, '>maintenance<') !== false) {
            $statusText = 'maintenance';
        }
    }

    // Map badge status to G-Portal status
    $statusMap = [
        'up'          => 'online',
        'down'        => 'down',
        'maintenance' => 'maintenance',
        'degraded'    => 'down',
        'pending'     => 'maintenance',
    ];

    if (isset($statusMap[$statusText])) {
        writeLog("  BADGE: Parsed status '$statusText' => '{$statusMap[$statusText]}'");
        return $statusMap[$statusText];
    }

    writeLog("  BADGE WARNING: Could not parse status from badge response");
    return null;
}


// DOMAIN URL CHECK (fallback when no badge URL)
function checkSystemHealth($domain) {
    $url = $domain;

    if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
        $url = "http://" . $url;
    }

    $result = [
        'accessible'    => false,
        'http_code'     => null,
        'error'         => null,
        'response_time' => null
    ];

    $startTime = microtime(true);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => CHECK_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => CHECK_TIMEOUT,
        CURLOPT_USERAGENT      => USER_AGENT,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_NOBODY         => true,
        CURLOPT_HEADER         => true
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);

    $result['response_time'] = round((microtime(true) - $startTime) * 1000, 2);

    if ($curlError) {
        $result['accessible'] = false;
        $result['error']      = getReadableError($curlErrno, $curlError);
    } elseif ($httpCode >= 200 && $httpCode < 400) {
        $result['accessible'] = true;
        $result['http_code']  = $httpCode;
    } else {
        $result['accessible'] = false;
        $result['http_code']  = $httpCode;
        $result['error']      = "HTTP $httpCode " . getHttpErrorMessage($httpCode);
    }

    return $result;
}

function getReadableError($errno, $error) {
    $errors = [
        6  => 'DNS resolution failed - Could not resolve host',
        7  => 'Connection refused - Server not accepting connections',
        28 => 'Connection timeout - Server did not respond within ' . CHECK_TIMEOUT . ' seconds',
        35 => 'SSL connection error - Certificate issue',
        51 => 'SSL certificate validation failed',
        52 => 'Empty response from server',
        56 => 'Network error - Failed receiving data'
    ];
    return $errors[$errno] ?? "Connection error: $error";
}

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

function updateSystemStatus($systemId, $newStatus, $oldStatus, $errorDetails, $systemName, $systemDomain) {
    $conn = getDBConnection();

    $stmt = $conn->prepare("UPDATE systems SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $newStatus, $systemId);
    $stmt->execute();
    $stmt->close();

    $changeNote   = "Auto-detected: " . $errorDetails;
    $systemUserId = SYSTEM_USER_ID;
    $logStmt      = $conn->prepare("INSERT INTO status_logs (system_id, old_status, new_status, changed_by, change_note) VALUES (?, ?, ?, ?, ?)");
    $logStmt->bind_param("issis", $systemId, $oldStatus, $newStatus, $systemUserId, $changeNote);
    $logStmt->execute();
    $logStmt->close();

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

    writeLog("Updated system #$systemId ($systemName): $oldStatus -> $newStatus — $errorDetails");
}

// MAIN MONITORING FUNCTION

function monitorAllSystems() {
    writeLog("=== Health check started ===");

    // STEP 1: Run maintenance schedule check first
    writeLog("--- Step 1: Checking maintenance schedules ---");
    $maintResult = runMaintenanceScheduleCheck();
    writeLog("Maintenance check done: {$maintResult['switched']} system(s) switched to maintenance.");

    // STEP 2: Run health checks
    writeLog("--- Step 2: Running health checks ---");

    $conn   = getDBConnection();
    $result = $conn->query("
        SELECT id, name, domain, badge_url, status
        FROM systems
        WHERE status != 'archived'
          AND exclude_health_check = 0
        ORDER BY id ASC
    ");

    if (!$result) {
        writeLog("ERROR: Could not fetch systems - " . $conn->error);
        return;
    }

    $systemsChecked = 0;
    $systemsChanged = 0;

    while ($system = $result->fetch_assoc()) {
        $systemId      = $system['id'];
        $systemName    = $system['name'];
        $systemDomain  = $system['domain'];
        $badgeUrl      = trim($system['badge_url'] ?? '');
        $currentStatus = $system['status'];

        writeLog("Checking system #$systemId: $systemName — Current: $currentStatus");

        $newStatus    = null;
        $errorDetails = null;

        //  Use Badge URL if available 
        if (!empty($badgeUrl)) {
            writeLog("  METHOD: Badge URL ($badgeUrl)");
            $badgeStatus = checkBadgeStatus($badgeUrl);

            if ($badgeStatus !== null) {
                if ($badgeStatus !== $currentStatus) {
                    // Skip if system is in maintenance and badge also says maintenance
                    if ($currentStatus === 'maintenance' && $badgeStatus === 'maintenance') {
                        writeLog("  OK: Already in maintenance — no change");
                    } else {
                        $newStatus    = $badgeStatus;
                        $errorDetails = "Badge URL reported status: $badgeStatus";
                    }
                } else {
                    writeLog("  OK: Badge status matches current ($currentStatus) — no change");
                }
            } else {
                writeLog("  SKIP: Badge URL returned unknown status — skipping this system");
            }

        //  Fallback: Domain URL check 
        } else {
            writeLog("  METHOD: Domain URL ($systemDomain)");
            $health = checkSystemHealth($systemDomain);

            if ($health['accessible']) {
                if ($currentStatus !== 'online' && $currentStatus !== 'maintenance') {
                    $newStatus    = 'online';
                    $errorDetails = "System is now accessible (HTTP {$health['http_code']}, {$health['response_time']}ms)";
                } else {
                    writeLog("  OK: Accessible (HTTP {$health['http_code']}, {$health['response_time']}ms) — no change");
                }
            } else {
                if ($currentStatus === 'online') {
                    $newStatus    = 'down';
                    $errorDetails = $health['error'];
                } elseif ($currentStatus === 'maintenance') {
                    writeLog("  WARNING: Not accessible but in maintenance — keeping maintenance status");
                } else {
                    writeLog("  ERROR: Not accessible ({$health['error']}) — already marked as $currentStatus");
                }
            }
        }

        $systemsChecked++;

        if ($newStatus && $newStatus !== $currentStatus) {
            updateSystemStatus($systemId, $newStatus, $currentStatus, $errorDetails, $systemName, $systemDomain);
            $systemsChanged++;
        }

        usleep(500000);
    }

    writeLog("Health check completed: $systemsChecked checked, $systemsChanged changed");
    writeLog("");
}


try {
    monitorAllSystems();
    echo "Health check completed successfully.\n";
} catch (Exception $e) {
    writeLog("FATAL ERROR: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>