<?php
/**
 * G-Portal - Get Maintenance Schedules
 * Supports:
 *   ?action=calendar&month=YYYY-MM  → all schedules in a month (for calendar dots)
 *   ?action=day&date=YYYY-MM-DD     → schedules on a specific date (side panel)
 *   ?action=system&system_id=N      → all schedules for a system
 *   ?action=single&id=N             → single schedule by id
 */

require_once '../../config/session.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = trim($_GET['action'] ?? '');
$conn   = getDBConnection();

// -------------------------------------------------------
// CALENDAR: Get all schedules in a given month
// -------------------------------------------------------
if ($action === 'calendar') {
    $month = trim($_GET['month'] ?? date('Y-m'));

    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        echo json_encode(['success' => false, 'message' => 'Invalid month format. Use YYYY-MM']);
        exit();
    }

    $startOfMonth = $month . '-01 00:00:00';
    $endOfMonth   = date('Y-m-t 23:59:59', strtotime($startOfMonth));

    $stmt = $conn->prepare("
        SELECT 
            ms.id,
            ms.system_id,
            ms.title,
            ms.start_datetime,
            ms.end_datetime,
            ms.status,
            s.name AS system_name
        FROM maintenance_schedules ms
        JOIN systems s ON ms.system_id = s.id
        WHERE ms.start_datetime <= ? AND ms.end_datetime >= ?
          AND ms.status != 'Done'
          AND ms.deleted_from_calendar = 0
        ORDER BY ms.start_datetime ASC
    ");
    $stmt->bind_param("ss", $endOfMonth, $startOfMonth);
    $stmt->execute();
    $result = $stmt->get_result();

    $schedules = [];
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'data' => $schedules]);

// -------------------------------------------------------
// DAY: Get schedules for a specific date (side panel)
// -------------------------------------------------------
} elseif ($action === 'day') {
    $date = trim($_GET['date'] ?? '');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD']);
        exit();
    }

    $dayStart = $date . ' 00:00:00';
    $dayEnd   = $date . ' 23:59:59';

    $stmt = $conn->prepare("
        SELECT 
            ms.id,
            ms.system_id,
            ms.title,
            ms.description,
            ms.start_datetime,
            ms.end_datetime,
            ms.status,
            ms.exceeded_duration,
            ms.created_at,
            s.name AS system_name,
            s.status AS system_status,
            u.username AS created_by_name
        FROM maintenance_schedules ms
        JOIN systems s ON ms.system_id = s.id
        JOIN users u ON ms.created_by = u.id
        WHERE ms.start_datetime <= ? AND ms.end_datetime >= ?
          AND ms.deleted_from_calendar = 0
        ORDER BY ms.start_datetime ASC
    ");
    $stmt->bind_param("ss", $dayEnd, $dayStart);
    $stmt->execute();
    $result = $stmt->get_result();

    $schedules = [];
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'data' => $schedules, 'date' => $date]);

// -------------------------------------------------------
// SYSTEM: Get all schedules for a specific system
// -------------------------------------------------------
} elseif ($action === 'system') {
    $systemId = intval($_GET['system_id'] ?? 0);

    if ($systemId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid system ID']);
        exit();
    }

    $stmt = $conn->prepare("
        SELECT 
            ms.id,
            ms.system_id,
            ms.title,
            ms.description,
            ms.start_datetime,
            ms.end_datetime,
            ms.status,
            ms.exceeded_duration,
            ms.created_at,
            u.username AS created_by_name
        FROM maintenance_schedules ms
        JOIN users u ON ms.created_by = u.id
        WHERE ms.system_id = ?
          AND ms.deleted_from_calendar = 0
          AND ms.status IN ('Scheduled', 'In Progress')
        ORDER BY ms.start_datetime DESC
    ");
    $stmt->bind_param("i", $systemId);
    $stmt->execute();
    $result = $stmt->get_result();

    $schedules = [];
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'data' => $schedules]);

// -------------------------------------------------------
// SINGLE: Get one schedule by ID
// -------------------------------------------------------
} elseif ($action === 'single') {
    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        exit();
    }

    $stmt = $conn->prepare("
        SELECT 
            ms.*,
            s.name AS system_name,
            u.username AS created_by_name
        FROM maintenance_schedules ms
        JOIN systems s ON ms.system_id = s.id
        JOIN users u ON ms.created_by = u.id
        WHERE ms.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $schedule = $result->fetch_assoc();
    $stmt->close();

    if ($schedule) {
        echo json_encode(['success' => true, 'data' => $schedule]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Schedule not found']);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>