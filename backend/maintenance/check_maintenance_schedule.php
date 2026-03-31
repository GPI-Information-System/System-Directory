<?php
/* G-Portal - Maintenance Schedule Auto-Switch */

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


function resolveMaintUserId($conn) {
    $row = $conn->query("SELECT id FROM users WHERE username = 'system_monitor' LIMIT 1")->fetch_assoc();
    if ($row) return $row['id'];
    $row = $conn->query("SELECT id FROM users WHERE role = 'Super Admin' ORDER BY id ASC LIMIT 1")->fetch_assoc();
    return $row ? $row['id'] : null;
}


function runMaintenanceScheduleCheck() {
    $conn     = getDBConnection();
    $switched = 0;

    
    $nowResult = $conn->query("SELECT NOW() AS now_time");
    $nowRow    = $nowResult->fetch_assoc();
    $dbNow     = $nowRow['now_time'];

    writeMaintLog("Running maintenance schedule check (db now: $dbNow)");

    
    $userId = resolveMaintUserId($conn);
    if (!$userId) {
        writeMaintLog("WARNING: No valid user found for status_logs — log entries will be skipped");
    }

   

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