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
 * - FIX 1: Removed AND ms.end_datetime >= NOW() — a schedule that has exceeded its
 *   end time still keeps the system in 'maintenance' until manually marked Done.
 * - FIX 2: Replaced hardcoded MAINT_SYSTEM_USER_ID = 1 with a dynamic lookup
 *   (system_monitor user → first Super Admin fallback). The hardcoded ID silently
 *   failed with a FK constraint after the reset_user_ids_safe migration, causing
 *   maintenance status changes to never appear in the Patch Logs.
 * - When maintenance window ends, status stays 'maintenance' (manual revert required)
 * - Logs every change to status_logs with a clear auto note
 * - Updates the maintenance schedule status to 'In Progress' once started
 */

require_once __DIR__ . '/../../config/database.php';

if (!defined('MAINT_LOG_FILE')) define('MAINT_LOG_FILE', __DIR__ . '/../../backend/logs/health_check.log');

function writeMaintLog($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logFile   = MAINT_LOG_FILE;
    $logDir    = dirname($logFile);
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    @file_put_contents($logFile, "[$timestamp] [MAINT] $message" . PHP_EOL, FILE_APPEND);
}

/**
 * Resolve the user ID to use for automated status_logs entries.
 * Mirrors the same pattern used in trigger_health_check.php.
 * Returns the ID of system_monitor if it exists, otherwise the
 * first Super Admin. Returns null if no valid user is found.
 */
function resolveMaintUserId($conn) {
    $row = $conn->query("SELECT id FROM users WHERE username = 'system_monitor' LIMIT 1")->fetch_assoc();
    if ($row) return $row['id'];
    $row = $conn->query("SELECT id FROM users WHERE role = 'Super Admin' ORDER BY id ASC LIMIT 1")->fetch_assoc();
    return $row ? $row['id'] : null;
}

/**
 * Main function — called from trigger_maintenance_check.php
 * Returns array with count of systems switched
 */
function runMaintenanceScheduleCheck() {
    $conn     = getDBConnection();
    $switched = 0;

    // Use MySQL NOW() directly to avoid PHP/MySQL timezone mismatch
    $nowResult = $conn->query("SELECT NOW() AS now_time");
    $nowRow    = $nowResult->fetch_assoc();
    $dbNow     = $nowRow['now_time'];

    writeMaintLog("Running maintenance schedule check (db now: $dbNow)");

    // FIX 2: Resolve user ID once before the loop instead of using hardcoded constant
    $userId = resolveMaintUserId($conn);
    if (!$userId) {
        writeMaintLog("WARNING: No valid user found for status_logs — log entries will be skipped");
    }

    // -------------------------------------------------------
    // Find all schedules that have started but whose system
    // hasn't been switched to maintenance yet.
    //
    // FIX 1: Removed AND ms.end_datetime >= NOW()
    // Previously, any schedule past its end_datetime was
    // excluded from this query — meaning an exceeded schedule
    // would never switch the system to maintenance.
    // Now we only check that start_datetime has passed,
    // regardless of whether end_datetime has been exceeded.
    // The system stays in 'maintenance' until admin marks Done.
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
          AND ms.status IN ('Scheduled', 'In Progress')
          AND ms.deleted_from_calendar = 0
          AND s.status NOT IN ('maintenance', 'archived')
        ORDER BY ms.start_datetime ASC
    ");

    if (!$stmt) {
        writeMaintLog("ERROR: prepare failed — " . $conn->error);
        $conn->close();
        return ['switched' => 0];
    }

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
        // FIX 2: Use dynamically resolved $userId instead of hardcoded constant.
        // Hardcoded ID 1 silently failed FK constraint after reset_user_ids_safe,
        // causing maintenance changes to never appear in Patch Logs.
        if ($userId) {
            $changeNote = "Auto-maintenance: Scheduled maintenance \"$scheduleTitle\" started.";
            $logStmt    = $conn->prepare("
                INSERT INTO status_logs (system_id, old_status, new_status, changed_by, change_note)
                VALUES (?, ?, ?, ?, ?)
            ");
            if (!$logStmt) {
                writeMaintLog("  ERROR: prepare failed for INSERT status_logs: " . $conn->error);
            } else {
                $logStmt->bind_param("issis", $systemId, $oldStatus, $newStatus, $userId, $changeNote);
                if (!$logStmt->execute()) {
                    writeMaintLog("  ERROR: execute failed for INSERT status_logs: " . $logStmt->error);
                }
                $logStmt->close();
            }
        }

        // 3. Update maintenance schedule status to 'In Progress' if still Scheduled
        if ($row['schedule_status'] === 'Scheduled') {
            $updateSched = $conn->prepare("
                UPDATE maintenance_schedules SET status = 'In Progress' WHERE id = ?
            ");
            if ($updateSched) {
                $updateSched->bind_param("i", $scheduleId);
                $updateSched->execute();
                $updateSched->close();
                writeMaintLog("  Schedule #$scheduleId status updated: Scheduled → In Progress");
            }
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
    // FIX 1: Also removed end_datetime >= NOW() here so exceeded
    // schedules still get their badge updated to 'In Progress'.
    // -------------------------------------------------------
    $stmtInProgress = $conn->prepare("
        UPDATE maintenance_schedules
        SET status = 'In Progress'
        WHERE start_datetime <= NOW()
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

    $conn->close();
    return ['switched' => $switched];
}
?>