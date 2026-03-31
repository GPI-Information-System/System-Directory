<?php


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

    case 'status_history':
        $days  = intval($_GET['days']  ?? 30);
        $limit = intval($_GET['limit'] ?? 1000);
        if ($limit > 1000) $limit = 1000;

        $sql = "
            SELECT sl.id, sl.system_id, s.name AS system_name,
                   sl.old_status, sl.new_status, sl.change_note,
                   sl.changed_at, COALESCE(u.username, 'System Monitor') AS changed_by
            FROM status_logs sl
            JOIN systems s ON sl.system_id = s.id
            LEFT JOIN users u ON sl.changed_by = u.id
        ";
        if ($days > 0) $sql .= " WHERE sl.changed_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
        
        $sql .= " ORDER BY sl.changed_at DESC, sl.id DESC LIMIT $limit";

        $result = $conn->query($sql);
        $data   = [];
        while ($row = $result->fetch_assoc()) $data[] = $row;

        $countSql = "SELECT COUNT(*) AS total FROM status_logs sl";
        if ($days > 0) $countSql .= " WHERE sl.changed_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
        $totalCount = $conn->query($countSql)->fetch_assoc()['total'] ?? 0;

        echo json_encode(['success' => true, 'data' => $data,
            'pagination' => ['total' => $totalCount, 'total_pages' => ceil($totalCount / max($limit, 1))]
        ]);
        break;


    // Completed Maintenance — includes actual end date & time
    case 'completed_maintenance':
        $days = intval($_GET['days'] ?? 30);

        $sql = "
            SELECT
                ms.id,
                ms.title,
                ms.description,
                ms.start_datetime,
                ms.end_datetime,
                ms.actual_end_datetime,
                ms.status,
                ms.updated_at,
                s.name     AS system_name,
                COALESCE(ms.done_by_username, u.username, 'Unknown') AS done_by,
                CASE
                    WHEN ms.actual_end_datetime IS NOT NULL
                    THEN GREATEST(0, TIMESTAMPDIFF(SECOND, ms.end_datetime, ms.actual_end_datetime))
                    ELSE GREATEST(0, TIMESTAMPDIFF(SECOND, ms.end_datetime, ms.updated_at))
                END AS exceeded_duration
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

    case 'uptime_stats':
        $systemId = intval($_GET['system_id'] ?? 0);
        $days     = intval($_GET['days']      ?? 30);

        if ($systemId > 0) {
            
            $sql = "SELECT DATE(changed_at) AS date, new_status AS status, changed_at
                    FROM status_logs
                    WHERE system_id = $systemId
                      AND changed_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
                    ORDER BY changed_at ASC LIMIT 1000";

            $result = $conn->query($sql);
            $data   = [];
            while ($row = $result->fetch_assoc()) $data[] = $row;

            echo json_encode(['success' => true, 'data' => $data]);

        } else {
           
            $sql = "SELECT DATE(changed_at) AS date, new_status AS status, COUNT(*) AS count
                    FROM status_logs
                    WHERE changed_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
                    GROUP BY DATE(changed_at), new_status
                    ORDER BY date ASC LIMIT 365";

            $result = $conn->query($sql);
            $data   = [];
            while ($row = $result->fetch_assoc()) $data[] = $row;

           
            $statusResult = $conn->query("
                SELECT status, COUNT(*) as count
                FROM systems
                WHERE status != 'archived'
                GROUP BY status
            ");
            $currentStats = [];
            while ($row = $statusResult->fetch_assoc()) {
                $currentStats[$row['status']] = (int)$row['count'];
            }
            $totalSystems = array_sum($currentStats);
            $onlineCount  = $currentStats['online'] ?? 0;
            $currentPct   = $totalSystems > 0
                ? round(($onlineCount / $totalSystems) * 100, 1)
                : 0;

            echo json_encode([
                'success'        => true,
                'data'           => $data,
                'current_uptime' => $currentPct,
                'total_systems'  => $totalSystems,
                'online_count'   => $onlineCount,
            ]);
        }
        break;

    case 'monthly_report':
        $systemId = intval($_GET['system_id'] ?? 0);
        $month    = $conn->real_escape_string($_GET['month'] ?? date('Y-m'));

        if ($systemId <= 0) { echo json_encode(['success' => false, 'message' => 'System ID required']); break; }

        $stmt = $conn->prepare("SELECT id, name, domain, status, description, created_at FROM systems WHERE id = ?");
        $stmt->bind_param('i', $systemId);
        $stmt->execute();
        $systemInfo = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$systemInfo) { echo json_encode(['success' => false, 'message' => 'System not found']); break; }

        $stmt = $conn->prepare("
            SELECT sl.old_status, sl.new_status, sl.change_note, sl.changed_at,
                   COALESCE(u.username, 'System Monitor') AS changed_by
            FROM status_logs sl
            LEFT JOIN users u ON sl.changed_by = u.id
            WHERE sl.system_id = ? AND DATE_FORMAT(sl.changed_at, '%Y-%m') = ?
            ORDER BY sl.changed_at ASC, sl.id ASC LIMIT 1000
        ");
        $stmt->bind_param('is', $systemId, $month);
        $stmt->execute();
        $changesResult = $stmt->get_result();
        $statusChanges = [];
        while ($row = $changesResult->fetch_assoc()) $statusChanges[] = $row;
        $stmt->close();

        $timeInStatus   = ['online' => 0, 'offline' => 0, 'maintenance' => 0, 'down' => 0, 'archived' => 0];
        $statusChangeCounts = ['online' => 0, 'offline' => 0, 'maintenance' => 0, 'down' => 0, 'archived' => 0];
        $firstDay     = $month . '-01 00:00:00';
        $lastDay      = date('Y-m-t 23:59:59', strtotime($firstDay));
        $currentTime  = min(time(), strtotime($lastDay));

        $systemCreatedAt   = strtotime($systemInfo['created_at'] ?? $firstDay);
        $reportActualStart = max(strtotime($firstDay), $systemCreatedAt);

        if ($systemCreatedAt > strtotime($lastDay)) {
            echo json_encode(['success' => true, 'data' => [
                'system'               => $systemInfo,
                'month'                => $month,
                'uptime_percentage'    => 0,
                'status_changes_count' => 0,
                'status_changes'       => [],
                'time_in_status'       => [],
                'daily_breakdown'      => [],
                'downtime_incidents'   => 0,
            ]]);
            break;
        }

        $totalSec  = $currentTime - $reportActualStart;
        $onlineSec = 0;
        $downtimeIncidents = 0;

        if (count($statusChanges) === 0) {
            $cs = $systemInfo['status'] ?? 'online';
            $timeInStatus[$cs] = $totalSec;
            if ($cs === 'online') $onlineSec = $totalSec;
        } else {
            $lastChangeTime = $reportActualStart;
            $cs = $statusChanges[0]['old_status'] ?? ($systemInfo['status'] ?? 'online');
            foreach ($statusChanges as $change) {
                $changeTime = strtotime($change['changed_at']);
                $duration   = max(0, $changeTime - $lastChangeTime);
                if (isset($timeInStatus[$change['old_status']])) {
                    $timeInStatus[$change['old_status']] += $duration;
                    if ($change['old_status'] === 'online') $onlineSec += $duration;
                }
                if (in_array($change['new_status'], ['down', 'offline'])) $downtimeIncidents++;
                if (isset($statusChangeCounts[$change['new_status']])) {
                    $statusChangeCounts[$change['new_status']]++;
                }
                $lastChangeTime = $changeTime;
                $cs = $change['new_status'];
            }
            $remaining = max(0, $currentTime - $lastChangeTime);
            if (isset($timeInStatus[$cs])) {
                $timeInStatus[$cs] += $remaining;
                if ($cs === 'online') $onlineSec += $remaining;
            }
        }

        $uptimePct   = $totalSec > 0 ? min(100, ($onlineSec / $totalSec) * 100) : 0;
        $daysInMonth = (int) date('t', strtotime($firstDay));
        $dailyBreakdown = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dayStr   = sprintf('%s-%02d', $month, $d);
            $dayStart = strtotime($dayStr . ' 00:00:00');
            if ($dayStart < strtotime(date('Y-m-d', $systemCreatedAt) . ' 00:00:00')) continue;
            $dailyBreakdown[$dayStr] = ['online' => 0, 'offline' => 0, 'maintenance' => 0, 'down' => 0, 'archived' => 0];
        }

        if (count($statusChanges) === 0) {
            $cs2 = $systemInfo['status'] ?? 'online';
            foreach ($dailyBreakdown as $dayStr => &$dayData) {
                $dayStart = max(strtotime($dayStr . ' 00:00:00'), $reportActualStart);
                $dayEnd   = min(strtotime($dayStr . ' 23:59:59'), $currentTime);
                if ($dayEnd > $dayStart && isset($dayData[$cs2])) $dayData[$cs2] += max(0, $dayEnd - $dayStart);
            }
            unset($dayData);
        } else {
            $timeline = [];
            $cs2 = $statusChanges[0]['old_status'] ?? ($systemInfo['status'] ?? 'online');
            $timeline[] = ['time' => $reportActualStart, 'status' => $cs2];
            foreach ($statusChanges as $change) $timeline[] = ['time' => strtotime($change['changed_at']), 'status' => $change['new_status']];
            $timeline[] = ['time' => $currentTime, 'status' => '__end__'];
            for ($i = 0; $i < count($timeline) - 1; $i++) {
                $segStart  = $timeline[$i]['time'];
                $segEnd    = $timeline[$i + 1]['time'];
                $segStatus = $timeline[$i]['status'];
                if (!isset($timeInStatus[$segStatus])) continue;
                $cursor = $segStart;
                while ($cursor < $segEnd) {
                    $dayStr   = date('Y-m-d', $cursor);
                    $dayEnd2  = min(strtotime(date('Y-m-d', $cursor) . ' 23:59:59'), $segEnd);
                    $duration = max(0, $dayEnd2 - $cursor);
                    if (isset($dailyBreakdown[$dayStr][$segStatus])) $dailyBreakdown[$dayStr][$segStatus] += $duration;
                    $cursor = strtotime(date('Y-m-d', $cursor) . ' 00:00:00') + 86400;
                }
            }
        }

        $dailyBreakdownFormatted = [];
        foreach ($dailyBreakdown as $dayStr => $dayData) {
            $hasActivity = false; $statuses = [];
            foreach ($dayData as $st => $sec) {
                if ($sec > 0) { $hasActivity = true; $statuses[$st] = ['seconds' => $sec, 'formatted' => formatDuration($sec)]; }
            }
            if ($hasActivity) $dailyBreakdownFormatted[] = ['date' => $dayStr, 'label' => date('M j', strtotime($dayStr)), 'statuses' => $statuses];
        }

        foreach ($timeInStatus as $s => $sec) {
            $timeInStatus[$s] = [
                'seconds'   => $sec,
                'formatted' => formatDuration($sec),
                'count'     => $statusChangeCounts[$s] ?? 0,
            ];
        }

        echo json_encode(['success' => true, 'data' => [
            'system' => $systemInfo, 'month' => $month,
            'uptime_percentage'    => round($uptimePct, 2),
            'status_changes_count' => count($statusChanges),
            'status_changes'       => $statusChanges,
            'time_in_status'       => $timeInStatus,
            'daily_breakdown'      => $dailyBreakdownFormatted,
            'downtime_incidents'   => $downtimeIncidents,
        ]]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function formatDuration($seconds) {
    if ($seconds <= 0)    return '0 seconds';
    if ($seconds < 60)    return $seconds . ' second' . ($seconds != 1 ? 's' : '');
    if ($seconds < 3600)  { $m = floor($seconds/60); return $m . ' minute' . ($m != 1 ? 's' : ''); }
    if ($seconds < 86400) { $h = floor($seconds/3600); $m = floor(($seconds % 3600) / 60); return $h . 'h' . ($m > 0 ? ' ' . $m . 'm' : ''); }
    $d = floor($seconds / 86400);
    $h = floor(($seconds % 86400) / 3600);
    return $d . ' day' . ($d != 1 ? 's' : '') . ($h > 0 ? ', ' . $h . 'h' : '');
}
?>