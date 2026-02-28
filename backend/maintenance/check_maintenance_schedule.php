<?php
/**
 * G-Portal - Maintenance Schedule Auto-Switch
 * 
 * Checks maintenance_schedules and automatically sets system status
 * to 'maintenance' when the scheduled start time has been reached.
 * 
 * Rules:
 * - If NOW >= start_datetime AND schedule status is 'Scheduled' or 'In Progress'
 *   → set system status to 'maintenance' (only if not already maintenance/archived)
 * - When maintenance window ends, status stays 'maintenance' (manual revert required)
 * - Logs every change to status_logs with a clear auto note
 * - Updates the maintenance schedule status to 'In Progress' once started
 */

require_once __DIR__ . '/../../config/database.php';

if (!defined('MAINT_LOG_FILE'))       define('MAINT_LOG_FILE',       __DIR__ . '/../../backend/logs/health_check.log');
if (!defined('MAINT_SYSTEM_USER_ID')) define('MAINT_SYSTEM_USER_ID', 1);

function writeMaintLog($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logFile = MAINT_LOG_FILE;
    $logDir  = dirname($logFile);
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    @file_put_contents($logFile, "[$timestamp] [MAINT] $message" . PHP_EOL, FILE_APPEND);
}

/**
 * Main function — call this from check_systems_health.php or trigger_maintenance_check.php
 * Returns array with count of systems switched
 */
function runMaintenanceScheduleCheck() {
    $conn = getDBConnection();
    $switched = 0;

    // Use MySQL NOW() directly to avoid PHP/MySQL timezone mismatch
    $nowResult = $conn->query("SELECT NOW() AS now_time");
    $nowRow    = $nowResult->fetch_assoc();
    $dbNow     = $nowRow['now_time'];

    writeMaintLog("Running maintenance schedule check (db now: $dbNow)");

    // -------------------------------------------------------
    // Find all schedules that have started but system hasn't
    // been switched to maintenance yet
    // Uses MySQL NOW() to avoid PHP/MySQL timezone mismatch
    // -------------------------------------------------------
    $stmt = $conn->prepare("
        SELECT 
            ms.id          AS schedule_id,
            ms.system_id,
            ms.title       AS schedule_title,
            ms.start_datetime,
            ms.end_datetime,
            ms.status      AS schedule_status,
            s.name         AS system_name,
            s.status       AS system_status
        FROM maintenance_schedules ms
        JOIN systems s ON ms.system_id = s.id
        WHERE ms.start_datetime <= NOW()
          AND ms.end_datetime   >= NOW()
          AND ms.status IN ('Scheduled', 'In Progress')
          AND ms.deleted_from_calendar = 0
          AND s.status NOT IN ('maintenance', 'archived')
        ORDER BY ms.start_datetime ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $scheduleId    = $row['schedule_id'];
        $systemId      = $row['system_id'];
        $systemName    = $row['system_name'];
        $oldStatus     = $row['system_status'];
        $scheduleTitle = $row['schedule_title'];
        $newStatus     = 'maintenance';

        writeMaintLog("Auto-switching system #$systemId ($systemName) from '$oldStatus' to 'maintenance' — Schedule: \"$scheduleTitle\"");

        // 1. Update system status to maintenance
        $updateSystem = $conn->prepare("
            UPDATE systems SET status = 'maintenance', updated_at = NOW() WHERE id = ?
        ");
        if (!$updateSystem) {
            writeMaintLog("  ERROR: prepare failed for UPDATE systems: " . $conn->error);
            continue;
        }
        $updateSystem->bind_param("i", $systemId);
        if (!$updateSystem->execute()) {
            writeMaintLog("  ERROR: execute failed for UPDATE systems: " . $updateSystem->error);
            $updateSystem->close();
            continue;
        }
        $updateSystem->close();

        // 2. Log the status change in status_logs
        $changeNote = "Auto-maintenance: Scheduled maintenance \"$scheduleTitle\" started.";
        $userId     = MAINT_SYSTEM_USER_ID;
        $logStmt    = $conn->prepare("
            INSERT INTO status_logs (system_id, old_status, new_status, changed_by, change_note)
            VALUES (?, ?, ?, ?, ?)
        ");
        if (!$logStmt) {
            writeMaintLog("  ERROR: prepare failed for INSERT status_logs: " . $conn->error);
            continue;
        }
        $logStmt->bind_param("issis", $systemId, $oldStatus, $newStatus, $userId, $changeNote);
        if (!$logStmt->execute()) {
            writeMaintLog("  ERROR: execute failed for INSERT status_logs: " . $logStmt->error);
        }
        $logStmt->close();

        // 3. Update maintenance schedule status to 'In Progress'
        if ($row['schedule_status'] === 'Scheduled') {
            $updateSched = $conn->prepare("
                UPDATE maintenance_schedules SET status = 'In Progress' WHERE id = ?
            ");
            $updateSched->bind_param("i", $scheduleId);
            $updateSched->execute();
            $updateSched->close();
            writeMaintLog("  Schedule #$scheduleId status updated: Scheduled → In Progress");
        }

        $switched++;
        writeMaintLog("  Done — system #$systemId ($systemName) is now 'maintenance'");
    }
    $stmt->close();

    // -------------------------------------------------------
    // SEPARATE PASS: Update schedule status to 'In Progress'
    // for schedules that have started but whose system is
    // ALREADY in maintenance (skipped by the main query above).
    // This ensures the badge always shows the correct state.
    // -------------------------------------------------------
    $stmtInProgress = $conn->prepare("
        UPDATE maintenance_schedules
        SET status = 'In Progress'
        WHERE start_datetime <= NOW()
          AND end_datetime   >= NOW()
          AND status = 'Scheduled'
          AND deleted_from_calendar = 0
    ");
    if ($stmtInProgress) {
        $stmtInProgress->execute();
        $affected = $stmtInProgress->affected_rows;
        if ($affected > 0) {
            writeMaintLog("Updated $affected schedule(s) to 'In Progress' (system already in maintenance).");
        }
        $stmtInProgress->close();
    }

    if ($switched === 0) {
        writeMaintLog("No systems needed maintenance status update.");
    } else {
        writeMaintLog("Maintenance check done — $switched system(s) switched to maintenance.");
    }

    return ['switched' => $switched];
}