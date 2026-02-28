<?php
/**
 * G-Portal Analytics Data API
 * Handles: uptime stats, status history, completed maintenance,
 *          monthly reports, systems by status
 */

require_once '../config/session.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn() || (!isSuperAdmin() && !isAdmin())) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$action = $_GET['action'] ?? '';
$conn   = getDBConnection();

switch ($action) {

    // ============================================================
    // Systems by Status (for dashboard donut chart)
    // ============================================================
    case 'systems_by_status':
        $result = $conn->query("
            SELECT status, COUNT(*) as count
            FROM systems
            GROUP BY status
            ORDER BY FIELD(status, 'online', 'maintenance', 'down', 'offline', 'archived')
        ");
        $data = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    // ============================================================
    // Status Change History (Patch Logs)
    // ============================================================
    case 'status_history':
        $days   = intval($_GET['days'] ?? 30);
        $limit  = intval($_GET['limit'] ?? 1000);
        $offset = 0;
        if ($limit > 1000) $limit = 1000;

        $sql = "
            SELECT
                sl.id,
                sl.system_id,
                s.name  AS system_name,
                sl.old_status,
                sl.new_status,
                sl.change_note,
                sl.changed_at,
                u.username AS changed_by
            FROM status_logs sl
            JOIN systems s ON sl.system_id = s.id
            JOIN users   u ON sl.changed_by = u.id
        ";
        if ($days > 0) {
            $sql .= " WHERE sl.changed_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
        }
        $sql .= " ORDER BY sl.changed_at DESC LIMIT $limit OFFSET $offset";

        $result = $conn->query($sql);
        $data   = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;

        $countSql = "SELECT COUNT(*) AS total FROM status_logs sl";
        if ($days > 0) $countSql .= " WHERE sl.changed_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
        $totalCount = $conn->query($countSql)->fetch_assoc()['total'] ?? 0;

        echo json_encode([
            'success'    => true,
            'data'       => $data,
            'pagination' => [
                'total'       => $totalCount,
                'total_pages' => ceil($totalCount / max($limit, 1))
            ]
        ]);
        break;

    // ============================================================
    // Completed Maintenance Table (NEW)
    // Shows all maintenance_schedules with status = 'Done'
    // Includes exceeded_duration = actual end - scheduled end (seconds)
    // ============================================================
    case 'completed_maintenance':
        $days = intval($_GET['days'] ?? 30);

        $sql = "
            SELECT
                ms.id,
                ms.title,
                ms.start_datetime,
                ms.end_datetime,
                ms.status,
                ms.updated_at,
                s.name  AS system_name,
                u.username AS done_by,
                /* exceeded_duration: seconds past the scheduled end time.
                   We use updated_at as the actual completion timestamp.
                   If completed BEFORE end_datetime, value is 0 or negative → show 'Within schedule' */
                GREATEST(0, TIMESTAMPDIFF(SECOND, ms.end_datetime, ms.updated_at)) AS exceeded_duration
            FROM maintenance_schedules ms
            JOIN systems s ON ms.system_id = s.id
            LEFT JOIN users u ON ms.created_by = u.id
            WHERE ms.status = 'Done'
        ";

        if ($days > 0) {
            $sql .= " AND ms.updated_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
        }

        $sql .= " ORDER BY ms.updated_at DESC LIMIT 500";

        $result = $conn->query($sql);

        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
            break;
        }

        $data = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;

        echo json_encode(['success' => true, 'data' => $data]);
        break;

    // ============================================================
    // Uptime Statistics
    // ============================================================
    case 'uptime_stats':
        $systemId = intval($_GET['system_id'] ?? 0);
        $days     = intval($_GET['days']      ?? 30);

        if ($systemId > 0) {
            $sql = "
                SELECT DATE(changed_at) AS date, new_status AS status, changed_at
                FROM status_logs
                WHERE system_id = $systemId
                  AND changed_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
                ORDER BY changed_at ASC
                LIMIT 1000
            ";
        } else {
            $sql = "
                SELECT DATE(changed_at) AS date, new_status AS status, COUNT(*) AS count
                FROM status_logs
                WHERE changed_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
                GROUP BY DATE(changed_at), new_status
                ORDER BY date ASC
                LIMIT 365
            ";
        }

        $result = $conn->query($sql);
        $data   = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    // ============================================================
    // Monthly Report
    // ============================================================
    case 'monthly_report':
        $systemId = intval($_GET['system_id'] ?? 0);
        $month    = $conn->real_escape_string($_GET['month'] ?? date('Y-m'));

        if ($systemId <= 0) {
            echo json_encode(['success' => false, 'message' => 'System ID required']);
            break;
        }

        $stmt = $conn->prepare("SELECT id, name, domain, status, description FROM systems WHERE id = ?");
        $stmt->bind_param('i', $systemId);
        $stmt->execute();
        $systemInfo = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$systemInfo) {
            echo json_encode(['success' => false, 'message' => 'System not found']);
            break;
        }

        $stmt = $conn->prepare("
            SELECT sl.old_status, sl.new_status, sl.change_note, sl.changed_at, u.username AS changed_by
            FROM status_logs sl
            JOIN users u ON sl.changed_by = u.id
            WHERE sl.system_id = ?
              AND DATE_FORMAT(sl.changed_at, '%Y-%m') = ?
            ORDER BY sl.changed_at ASC
            LIMIT 1000
        ");
        $stmt->bind_param('is', $systemId, $month);
        $stmt->execute();
        $changesResult = $stmt->get_result();
        $statusChanges = [];
        while ($row = $changesResult->fetch_assoc()) $statusChanges[] = $row;
        $stmt->close();

        // Calculate time in each status
        $timeInStatus = ['online' => 0, 'offline' => 0, 'maintenance' => 0, 'down' => 0, 'archived' => 0];
        $firstDay    = $month . '-01 00:00:00';
        $lastDay     = date('Y-m-t 23:59:59', strtotime($firstDay));
        $currentTime = min(time(), strtotime($lastDay));
        $totalSec    = $currentTime - strtotime($firstDay);
        $onlineSec   = 0;
        $downtimeIncidents = 0;

        if (count($statusChanges) === 0) {
            $cs = $systemInfo['status'] ?? 'online';
            $timeInStatus[$cs] = $totalSec;
            if ($cs === 'online') $onlineSec = $totalSec;
        } else {
            $cs             = $systemInfo['status'] ?? 'online';
            $lastChangeTime = strtotime($firstDay);

            foreach ($statusChanges as $change) {
                $changeTime = strtotime($change['changed_at']);
                $duration   = max(0, $changeTime - $lastChangeTime);
                if (isset($timeInStatus[$change['old_status']])) {
                    $timeInStatus[$change['old_status']] += $duration;
                    if ($change['old_status'] === 'online') $onlineSec += $duration;
                }
                if (in_array($change['new_status'], ['down', 'offline'])) $downtimeIncidents++;
                $lastChangeTime = $changeTime;
                $cs = $change['new_status'];
            }

            $remaining = max(0, $currentTime - $lastChangeTime);
            if (isset($timeInStatus[$cs])) {
                $timeInStatus[$cs] += $remaining;
                if ($cs === 'online') $onlineSec += $remaining;
            }
        }

        $uptimePct = $totalSec > 0 ? ($onlineSec / $totalSec) * 100 : 0;

        foreach ($timeInStatus as $s => $sec) {
            $timeInStatus[$s] = ['seconds' => $sec, 'formatted' => formatDuration($sec)];
        }

        echo json_encode([
            'success' => true,
            'data'    => [
                'system'               => $systemInfo,
                'month'                => $month,
                'uptime_percentage'    => round($uptimePct, 2),
                'status_changes_count' => count($statusChanges),
                'status_changes'       => $statusChanges,
                'time_in_status'       => $timeInStatus,
                'downtime_incidents'   => $downtimeIncidents
            ]
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function formatDuration($seconds) {
    if ($seconds < 60)    return $seconds . ' seconds';
    if ($seconds < 3600)  { $m = floor($seconds/60); return $m . ' minute' . ($m!=1?'s':''); }
    if ($seconds < 86400) { $h = floor($seconds/3600); $m = floor(($seconds%3600)/60); return $h.'h'.($m>0?', '.$m.'m':''); }
    $d = floor($seconds/86400); $h = floor(($seconds%86400)/3600);
    return $d . ' day' . ($d!=1?'s':'') . ($h>0?', '.$h.'h':'');
}
?>