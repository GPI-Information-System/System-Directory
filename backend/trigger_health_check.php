<?php
/**
 * G-Portal - Trigger Health Check
 * Called by AJAX every 10 seconds from dashboard.php and viewer.php
 * FIXED: Systems with an active 'In Progress' or 'Scheduled' maintenance
 *        schedule are ALWAYS skipped — even after exceeding end_datetime.
 */

require_once '../config/session.php';
require_once '../config/database.php';
require_once __DIR__ . '/send_email_notification.php';

header('Content-Type: application/json');

define('HC_LOG_FILE',     __DIR__ . '/logs/health_check.log');
define('CHECK_TIMEOUT',   3);
define('DOMAIN_TIMEOUT',  2);
define('CONNECT_TIMEOUT', 2);
define('HC_USER_AGENT',   'G-Portal Health Monitor/1.0');

function hcLog($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logDir    = dirname(HC_LOG_FILE);
    if (!file_exists($logDir)) mkdir($logDir, 0777, true);

    // ── Log rotation: keep max 500 lines ──
    if (file_exists(HC_LOG_FILE)) {
        $lines = file(HC_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (count($lines) > 500) {
            $trimmed = array_slice($lines, -400); // keep last 400 lines
            file_put_contents(HC_LOG_FILE, implode(PHP_EOL, $trimmed) . PHP_EOL);
        }
    }

    @file_put_contents(HC_LOG_FILE, "[$timestamp] [HEALTH] $message" . PHP_EOL, FILE_APPEND);
}

$isLoggedIn = function_exists('isLoggedIn') && isLoggedIn();
$isViewer   = isset($_GET['source']) && $_GET['source'] === 'viewer';

if (!$isLoggedIn && !$isViewer) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

function hcCheckBadgeStatus($badgeUrl) {
    $ch = curl_init($badgeUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 2,
        CURLOPT_TIMEOUT        => CHECK_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => CONNECT_TIMEOUT,
        CURLOPT_USERAGENT      => HC_USER_AGENT,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);

    // HTTP 0 = complete connection failure (network unreachable, DNS fail, etc.)
    // Return 'UNREACHABLE' so caller can fallback to domain check
    if ($httpCode === 0 || $curlErrno === CURLE_COULDNT_RESOLVE_HOST || $curlErrno === CURLE_COULDNT_CONNECT) {
        hcLog("  BADGE UNREACHABLE: $curlError (HTTP $httpCode) — will fallback to domain");
        return 'UNREACHABLE';
    }

    if ($curlError || $httpCode < 200 || $httpCode >= 400 || empty($response)) {
        hcLog("  BADGE ERROR: $curlError (HTTP $httpCode)");
        return null;
    }

    $textMatches = [];
    preg_match_all('/<text[^>]*>([^<]+)<\/text>/i', $response, $textMatches);
    $statusText = '';
    foreach ($textMatches[1] as $text) {
        $cleaned = strtolower(trim(html_entity_decode($text)));
        if (in_array($cleaned, ['up', 'down', 'maintenance', 'degraded', 'pending'])) {
            $statusText = $cleaned; break;
        }
    }
    if (empty($statusText)) {
        $lower = strtolower($response);
        if (strpos($lower, '>up<') !== false || strpos($lower, '>up <') !== false) $statusText = 'up';
        elseif (strpos($lower, '>down<') !== false)        $statusText = 'down';
        elseif (strpos($lower, '>maintenance<') !== false) $statusText = 'maintenance';
    }
    $statusMap = ['up' => 'online', 'down' => 'down', 'maintenance' => 'maintenance', 'degraded' => 'down', 'pending' => 'maintenance'];
    if (isset($statusMap[$statusText])) {
        hcLog("  BADGE: '$statusText' => '{$statusMap[$statusText]}'");
        return $statusMap[$statusText];
    }
    hcLog("  BADGE WARNING: Could not parse status");
    return null;
}

function checkDomainsInParallel($domainSystems) {
    if (empty($domainSystems)) return [];
    $multiHandle = curl_multi_init();
    $handles     = [];
    foreach ($domainSystems as $systemId => $domain) {
        $url = preg_match("~^(?:f|ht)tps?://~i", $domain) ? $domain : 'http://' . $domain;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 2,
            CURLOPT_TIMEOUT        => DOMAIN_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => CONNECT_TIMEOUT,
            CURLOPT_USERAGENT      => HC_USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_NOBODY         => true,
            CURLOPT_HEADER         => true,
        ]);
        curl_multi_add_handle($multiHandle, $ch);
        $handles[$systemId] = $ch;
    }
    $running = null;
    do { curl_multi_exec($multiHandle, $running); curl_multi_select($multiHandle, 0.1); } while ($running > 0);
    $results = [];
    $errorMap = [6 => 'DNS resolution failed', 7 => 'Connection refused', 28 => 'Connection timeout', 35 => 'SSL error', 52 => 'Empty response'];
    foreach ($handles as $systemId => $ch) {
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $accessible = !$curlError && $httpCode >= 200 && $httpCode < 400;
        $results[$systemId] = [
            'accessible' => $accessible,
            'http_code'  => $httpCode,
            'error'      => $curlError ? ($errorMap[$curlErrno] ?? 'Connection error: ' . $curlError) : ($accessible ? null : 'HTTP ' . $httpCode . ' error'),
        ];
        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }
    curl_multi_close($multiHandle);
    return $results;
}

// -------------------------------------------------------
// KEY FIX: Skip systems that have an active maintenance
// schedule (In Progress OR Scheduled) regardless of whether
// end_datetime has passed. An exceeded schedule is still
// "in progress" until admin manually marks it Done.
// -------------------------------------------------------
function getSystemsUnderActiveMaintenance($conn) {
    $activeIds = [];
    $result = $conn->query("
        SELECT DISTINCT system_id
        FROM maintenance_schedules
        WHERE status IN ('In Progress', 'Scheduled')
          AND deleted_from_calendar = 0
          AND start_datetime <= NOW()
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $activeIds[$row['system_id']] = true;
        }
    }
    return $activeIds;
}

try {
    $conn = getDBConnection();

    $activeMaintenance = getSystemsUnderActiveMaintenance($conn);

    if (!empty($activeMaintenance)) {
        hcLog("Systems protected from health check (active/exceeded maintenance): " . implode(', ', array_keys($activeMaintenance)));
    }

    $result = $conn->query("
        SELECT id, name, domain, badge_url, status
        FROM systems
        WHERE status != 'archived'
          AND exclude_health_check = 0
        ORDER BY id ASC
    ");

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
        exit();
    }

    $systems = $badgeSystems = $domainSystems = [];

    while ($system = $result->fetch_assoc()) {
        $systemId = $system['id'];
        $badgeUrl = trim($system['badge_url'] ?? '');
        $systems[$systemId] = $system;

        // Skip systems under active OR exceeded maintenance
        if (isset($activeMaintenance[$systemId])) {
            hcLog("SKIP system #$systemId ({$system['name']}) — maintenance schedule active/exceeded");
            continue;
        }

        if (!empty($badgeUrl)) {
            $badgeSystems[$systemId] = $badgeUrl;
        } elseif ($system['status'] === 'online') {
            $domainSystems[$systemId] = $system['domain'];
        }    }

    $systemsChecked = $changed = 0;
    $updatedSystems = [];

    hcLog("=== Health check started ===");
    hcLog("Badge: " . count($badgeSystems) . " | Domain: " . count($domainSystems) . " | Skipped (maintenance): " . count($activeMaintenance));

    $domainResults = checkDomainsInParallel($domainSystems);

    foreach ($systems as $systemId => $system) {
        if (isset($activeMaintenance[$systemId])) continue;

        $systemName    = $system['name'];
        $systemDomain  = $system['domain'];
        $badgeUrl      = trim($system['badge_url'] ?? '');
        $currentStatus = $system['status'];

        $systemsChecked++;
        hcLog("Checking #$systemId ($systemName) — Current: $currentStatus");

        $newStatus = $errorDetails = null;

        if (!empty($badgeUrl)) {
            hcLog("  METHOD: Badge URL");
            $badgeStatus = hcCheckBadgeStatus($badgeUrl);

            if ($badgeStatus === 'UNREACHABLE') {
                // Badge server unreachable — fallback to domain check
                hcLog("  FALLBACK: Checking domain ($systemDomain) instead");
                $url    = preg_match("~^(?:f|ht)tps?://~i", $systemDomain) ? $systemDomain : 'http://' . $systemDomain;
                $ch     = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS      => 3,
                    CURLOPT_TIMEOUT        => CHECK_TIMEOUT,
                    CURLOPT_CONNECTTIMEOUT => CONNECT_TIMEOUT,
                    CURLOPT_USERAGENT      => HC_USER_AGENT,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_NOBODY         => true,
                ]);
                curl_exec($ch);
                $domainHttpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $domainCurlError = curl_error($ch);
                $domainErrno     = curl_errno($ch);
                curl_close($ch);

                $accessible = !$domainCurlError && $domainHttpCode >= 200 && $domainHttpCode < 400;
                if (!$accessible && $domainHttpCode === 0) {
                    // Domain also unreachable — mark as down
                    $newStatus    = 'down';
                    $errorDetails = "Badge & domain both unreachable (HTTP $domainHttpCode)";
                    hcLog("  DOWN: Badge & domain both unreachable");
                } elseif (!$accessible) {
                    $newStatus    = 'down';
                    $errorDetails = "Domain HTTP $domainHttpCode error";
                    hcLog("  DOWN: Domain HTTP $domainHttpCode");
                } else {
                    hcLog("  OK via domain: HTTP $domainHttpCode");
                }
            } elseif ($badgeStatus !== null && $badgeStatus !== $currentStatus) {
                if (!($currentStatus === 'maintenance' && $badgeStatus === 'maintenance')) {
                    $newStatus    = $badgeStatus;
                    $errorDetails = "Badge URL reported: $badgeStatus";
                }
            }
        } elseif (isset($domainResults[$systemId])) {
            hcLog("  METHOD: Domain ($systemDomain)");
            $health = $domainResults[$systemId];
            if (!$health['accessible']) {
                $newStatus    = 'down';
                $errorDetails = $health['error'];
                hcLog("  DOWN: {$health['error']}");
            } else {
                hcLog("  OK: HTTP {$health['http_code']}");
            }
        }

        if ($newStatus && $newStatus !== $currentStatus) {
            $updateStmt = $conn->prepare("UPDATE systems SET status = ?, updated_at = NOW() WHERE id = ?");
            $updateStmt->bind_param("si", $newStatus, $systemId);
            $updateStmt->execute();
            $updateStmt->close();

            $changeNote = 'Auto-detected: ' . $errorDetails;

            // Use system_monitor user if exists, otherwise fallback to first Super Admin
            $monitorUser = $conn->query("SELECT id FROM users WHERE username = 'system_monitor' LIMIT 1")->fetch_assoc();
            if (!$monitorUser) {
                $monitorUser = $conn->query("SELECT id FROM users WHERE role = 'Super Admin' ORDER BY id ASC LIMIT 1")->fetch_assoc();
            }
            $userId = $monitorUser ? $monitorUser['id'] : 1;

            $logStmt = $conn->prepare("INSERT INTO status_logs (system_id, old_status, new_status, changed_by, change_note) VALUES (?, ?, ?, ?, ?)");
            $logStmt->bind_param("issis", $systemId, $currentStatus, $newStatus, $userId, $changeNote);
            $logStmt->execute();
            $logStmt->close();

            if (in_array($newStatus, ['down', 'offline'])) {
                sendStatusChangeEmail($systemId, $systemName, $currentStatus, $newStatus, $systemDomain, 'System Monitor (Automated)', $changeNote);
            }

            hcLog("  CHANGED: $currentStatus -> $newStatus");
            $changed++;
            $updatedSystems[] = ['id' => $systemId, 'name' => $systemName, 'old_status' => $currentStatus, 'new_status' => $newStatus];
        }
    }

    hcLog("Done: $systemsChecked checked, $changed changed");
    hcLog("");
    $conn->close();

    echo json_encode([
        'success'        => true,
        'changed'        => $changed,
        'updatedSystems' => $updatedSystems,
        'message'        => $changed > 0 ? "$changed system(s) updated." : "All $systemsChecked system(s) checked — no changes.",
    ]);

} catch (Exception $e) {
    hcLog("FATAL: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Health check failed: ' . $e->getMessage()]);
}
?>