<?php
/**
 * G-Portal - Trigger Health Check (Dashboard Load)
 * Throttled: runs at most once every 2 minutes per session
 */

require_once '../config/session.php';
require_once '../config/database.php';
require_once __DIR__ . '/send_email_notification.php';

header('Content-Type: application/json');

// Log file (same as health_check.log)
define('HC_LOG_FILE', __DIR__ . '/logs/health_check.log');
define('CHECK_TIMEOUT', 8);
define('HC_USER_AGENT', 'G-Portal Health Monitor/1.0');

function hcLog($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logDir = dirname(HC_LOG_FILE);
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    @file_put_contents(HC_LOG_FILE, "[$timestamp] [HEALTH] $message" . PHP_EOL, FILE_APPEND);
}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Throttle: only run once every 2 minutes per session
if (isset($_SESSION['last_health_check'])) {
    $elapsed = time() - $_SESSION['last_health_check'];
    if ($elapsed < 120) {
        echo json_encode([
            'success' => true,
            'skipped' => true,
            'message' => 'Throttled. Next check in ' . (120 - $elapsed) . 's.'
        ]);
        exit();
    }
}

$_SESSION['last_health_check'] = time();

try {
    $conn = getDBConnection();

    // Only check 'online' systems that are not excluded
    $result = $conn->query("
        SELECT id, name, domain, status 
        FROM systems 
        WHERE status = 'online'
          AND exclude_health_check = 0
        ORDER BY id ASC
    ");

    if (!$result) {
        hcLog("DB error: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
        exit();
    }

    $systemsChecked = 0;
    $changed        = 0;

    hcLog("=== Health check started ===");

    while ($system = $result->fetch_assoc()) {
        $systemId     = $system['id'];
        $systemName   = $system['name'];
        $systemDomain = $system['domain'];
        $systemsChecked++;

        // Build URL
        $url = $systemDomain;
        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
            $url = 'http://' . $url;
        }

        hcLog("Checking system #$systemId ($systemName) — $url");

        // Check URL accessibility via cURL
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => CHECK_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => CHECK_TIMEOUT,
            CURLOPT_USERAGENT      => HC_USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_NOBODY         => true,
            CURLOPT_HEADER         => true,
        ]);

        curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        $accessible = !$curlError && $httpCode >= 200 && $httpCode < 400;

        if ($accessible) {
            hcLog("  OK: Accessible (HTTP $httpCode) — no change");
        } else {
            // Build readable error message
            $errorMap = [
                6  => 'DNS resolution failed - Could not resolve host',
                7  => 'Connection refused - Server not accepting connections',
                28 => 'Connection timeout - Server did not respond in time',
                35 => 'SSL connection error',
                52 => 'Empty response from server',
            ];
            $errorMsg = $curlError
                ? ($errorMap[$curlErrno] ?? 'Connection error: ' . $curlError)
                : 'HTTP ' . $httpCode . ' error';

            hcLog("  DOWN: $errorMsg — switching to 'down'");

            // Switch system to 'down'
            $updateStmt = $conn->prepare("UPDATE systems SET status = 'down', updated_at = NOW() WHERE id = ?");
            $updateStmt->bind_param("i", $systemId);
            $updateStmt->execute();
            $updateStmt->close();

            // Log the change to status_logs
            $changeNote = 'Auto-detected: ' . $errorMsg;
            $userId     = 1;
            $logStmt    = $conn->prepare("INSERT INTO status_logs (system_id, old_status, new_status, changed_by, change_note) VALUES (?, 'online', 'down', ?, ?)");
            $logStmt->bind_param("iis", $systemId, $userId, $changeNote);
            $logStmt->execute();
            $logStmt->close();

            // Send email notification
            sendStatusChangeEmail(
                $systemId,
                $systemName,
                'online',
                'down',
                $systemDomain,
                'System Monitor (Automated)',
                $changeNote
            );

            $changed++;
        }
    }

    hcLog("Health check done: $systemsChecked checked, $changed changed");
    hcLog("");

    $conn->close();

    echo json_encode([
        'success' => true,
        'skipped' => false,
        'changed' => $changed,
        'message' => $changed > 0
            ? "$changed system(s) switched to down."
            : "All $systemsChecked online system(s) are accessible."
    ]);

} catch (Exception $e) {
    hcLog("FATAL ERROR: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Health check failed: ' . $e->getMessage()]);
}
?>