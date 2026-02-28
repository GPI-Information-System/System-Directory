<?php
/**
 * G-Portal - Save Maintenance Schedule
 * Handles CREATE, UPDATE, and BULK_CREATE operations
 * 
 * When status is set to 'Done':
 * - deleted_from_calendar = 1 → auto-hides from dashboard calendar
 * - Record is PRESERVED in database → always visible in Analytics
 * 
 * Access: Super Admin & Admin only
 */

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once __DIR__ . '/send_maintenance_email.php';

header('Content-Type: application/json');

if (!isLoggedIn() || (!isSuperAdmin() && !isAdmin())) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Read POST fields
$action         = trim($_POST['action']           ?? '');
$id             = intval($_POST['id']             ?? 0);
$systemId       = intval($_POST['system_id']      ?? 0);
$title          = trim($_POST['title']            ?? '');
$description    = trim($_POST['description']      ?? '');
$startDt        = trim($_POST['start_datetime']   ?? '');
$endDt          = trim($_POST['end_datetime']     ?? '');
$status         = trim($_POST['status']           ?? 'Scheduled');
$changeToOnline = trim($_POST['change_to_online'] ?? 'no');

// Email notification fields
$sendEmail       = ($_POST['send_email'] ?? 'no') === 'yes';
$emailRecipients = [];
if ($sendEmail && !empty($_POST['email_recipients'])) {
    // Accept JSON array or comma-separated string
    $raw = $_POST['email_recipients'];
    if (is_array($raw)) {
        $emailRecipients = $raw;
    } else {
        $decoded = json_decode($raw, true);
        $emailRecipients = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $raw)));
    }
    // Sanitize
    $emailRecipients = array_values(array_filter($emailRecipients, fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
}

// -------------------------------------------------------
// ACTION VALIDATION
// -------------------------------------------------------
if (!in_array($action, ['create', 'update', 'bulk_create'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action: "' . htmlspecialchars($action) . '".'
    ]);
    exit();
}

// -------------------------------------------------------
// FIELD VALIDATION
// -------------------------------------------------------
if (empty($title)) {
    echo json_encode(['success' => false, 'message' => 'Title is required']);
    exit();
}

if (empty($startDt) || empty($endDt)) {
    echo json_encode(['success' => false, 'message' => 'Start and end date/time are required']);
    exit();
}

if (strtotime($endDt) <= strtotime($startDt)) {
    echo json_encode(['success' => false, 'message' => 'End date/time must be after start date/time']);
    exit();
}

$allowedStatuses = ['Scheduled', 'In Progress', 'Done'];
if (!in_array($status, $allowedStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status: ' . htmlspecialchars($status)]);
    exit();
}

// -------------------------------------------------------
// GET CURRENT USER
// -------------------------------------------------------
$currentUser     = getCurrentUser();
$currentUserId   = $currentUser['id'];
$currentUsername = $currentUser['username'] ?? 'Administrator';

if (!$currentUserId) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit();
}

$conn = getDBConnection();

// ================================================================
// BULK CREATE — server-side batch endpoint
// Receives system_ids[] array and creates one schedule per system.
// The current JS sends individual 'create' requests per system,
// but this endpoint is available for native batch usage.
// ================================================================
if ($action === 'bulk_create') {

    $systemIds = $_POST['system_ids'] ?? [];

    if (!is_array($systemIds) || count($systemIds) === 0) {
        echo json_encode(['success' => false, 'message' => 'No system IDs provided for bulk create']);
        exit();
    }

    $results = [];
    $anyOk   = false;

    foreach ($systemIds as $rawId) {
        $sysId = intval($rawId);
        if ($sysId <= 0) {
            $results[] = ['system_id' => $rawId, 'success' => false, 'message' => 'Invalid system ID'];
            continue;
        }

        // Conflict check
        $checkStmt = $conn->prepare("
            SELECT id, title FROM maintenance_schedules
            WHERE system_id = ?
              AND status IN ('Scheduled', 'In Progress')
              AND deleted_from_calendar = 0
            LIMIT 1
        ");
        $checkStmt->bind_param("i", $sysId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $existing = $checkResult->fetch_assoc();
            $checkStmt->close();
            $results[] = [
                'system_id' => $sysId,
                'success'   => false,
                'message'   => 'Already has active schedule "' . $existing['title'] . '"',
            ];
            continue;
        }
        $checkStmt->close();

        // Insert
        $stmt = $conn->prepare("
            INSERT INTO maintenance_schedules
                (system_id, title, description, start_datetime, end_datetime, status, created_by, deleted_from_calendar)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0)
        ");

        if (!$stmt) {
            $results[] = ['system_id' => $sysId, 'success' => false, 'message' => 'DB prepare error'];
            continue;
        }

        $stmt->bind_param("isssssi", $sysId, $title, $description, $startDt, $endDt, $status, $currentUserId);

        if ($stmt->execute()) {
            $results[] = ['system_id' => $sysId, 'success' => true, 'message' => 'Scheduled successfully', 'id' => $conn->insert_id];
            $anyOk = true;
        } else {
            $results[] = ['system_id' => $sysId, 'success' => false, 'message' => 'DB error: ' . $stmt->error];
        }

        $stmt->close();
    }

    $conn->close();
    $successCount = count(array_filter($results, fn($r) => $r['success']));
    echo json_encode([
        'success' => $anyOk,
        'results' => $results,
        'message' => $anyOk
            ? "$successCount schedule(s) created successfully."
            : 'No schedules were created.',
    ]);
    exit();
}

// ================================================================
// SINGLE CREATE
// ================================================================
if ($action === 'create') {

    if ($systemId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid system ID: ' . $systemId]);
        exit();
    }

    $isDryRun = !empty($_POST['dry_run']) && $_POST['dry_run'] === '1';

    // Conflict check
    $checkStmt = $conn->prepare("
        SELECT id, title FROM maintenance_schedules
        WHERE system_id = ?
          AND status IN ('Scheduled', 'In Progress')
          AND deleted_from_calendar = 0
        LIMIT 1
    ");
    $checkStmt->bind_param("i", $systemId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    if ($checkResult->num_rows > 0) {
        $existing = $checkResult->fetch_assoc();
        $checkStmt->close();
        echo json_encode([
            'success' => false,
            'message' => 'This system already has an active maintenance schedule "' . $existing['title'] . '". Please complete or delete it before creating a new one.'
        ]);
        exit();
    }
    $checkStmt->close();

    // If this is just a conflict check (dry run), return success without inserting
    if ($isDryRun) {
        echo json_encode(['success' => true, 'message' => 'No conflict found.']);
        exit();
    }

    $stmt = $conn->prepare("
        INSERT INTO maintenance_schedules 
            (system_id, title, description, start_datetime, end_datetime, status, created_by, deleted_from_calendar)
        VALUES (?, ?, ?, ?, ?, ?, ?, 0)
    ");

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param("isssssi", $systemId, $title, $description, $startDt, $endDt, $status, $currentUserId);

    if ($stmt->execute()) {
        $newId = $conn->insert_id;

        // Send maintenance notification emails
        if ($sendEmail && !empty($emailRecipients)) {
            $scheduleData = [
                'system_id'      => $systemId,
                'title'          => $title,
                'description'    => $description,
                'start_datetime' => $startDt,
                'end_datetime'   => $endDt,
                'status'         => $status,
                'scheduled_by'   => $currentUsername ?? 'Administrator',
            ];
            sendMaintenanceEmail('created', $scheduleData, $emailRecipients);
        }

        echo json_encode(['success' => true, 'message' => 'Maintenance scheduled successfully', 'id' => $newId]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Execute failed: ' . $stmt->error]);
    }
    $stmt->close();
    $conn->close();
    exit();
}

// ================================================================
// UPDATE
// ================================================================
if ($action === 'update') {

    if ($systemId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid system ID: ' . $systemId]);
        exit();
    }

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid schedule ID']);
        exit();
    }

    // Conflict check (exclude current schedule)
    $checkStmt = $conn->prepare("
        SELECT id, title FROM maintenance_schedules
        WHERE system_id = ?
          AND status IN ('Scheduled', 'In Progress')
          AND deleted_from_calendar = 0
          AND id != ?
        LIMIT 1
    ");
    $checkStmt->bind_param("ii", $systemId, $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    if ($checkResult->num_rows > 0) {
        $existing = $checkResult->fetch_assoc();
        $checkStmt->close();
        echo json_encode([
            'success' => false,
            'message' => 'This system already has another active maintenance schedule "' . $existing['title'] . '". Please complete or delete it first.'
        ]);
        exit();
    }
    $checkStmt->close();

    // Fetch old data before update (for change detection in email)
    $oldDataStmt = $conn->prepare("
        SELECT ms.title, ms.description, ms.start_datetime, ms.end_datetime, ms.status,
               s.name AS system_name, s.contact_number
        FROM maintenance_schedules ms
        JOIN systems s ON s.id = ms.system_id
        WHERE ms.id = ?
    ");
    $oldDataStmt->bind_param("i", $id);
    $oldDataStmt->execute();
    $oldScheduleData = $oldDataStmt->get_result()->fetch_assoc() ?? [];
    $oldDataStmt->close();

    // Exceeded duration calculation
    $exceededDuration    = null;
    $deletedFromCalendar = 0;

    $fetchStmt = $conn->prepare("
        SELECT status, end_datetime, exceeded_duration, deleted_from_calendar
        FROM maintenance_schedules WHERE id = ?
    ");
    $fetchStmt->bind_param("i", $id);
    $fetchStmt->execute();
    $fetchResult = $fetchStmt->get_result();
    $current     = $fetchResult->fetch_assoc();
    $fetchStmt->close();

    if ($current) {
        $deletedFromCalendar = intval($current['deleted_from_calendar']);

        if ($status === 'Done' && $current['status'] !== 'Done') {
            $nowResult        = $conn->query("SELECT TIMESTAMPDIFF(SECOND, '" . $conn->real_escape_string($current['end_datetime']) . "', NOW()) AS diff_seconds");
            $nowRow           = $nowResult->fetch_assoc();
            $diffSeconds      = intval($nowRow['diff_seconds']);
            $exceededDuration    = $diffSeconds > 0 ? $diffSeconds : null;
            $deletedFromCalendar = 1;
        } elseif ($status === 'Done' && $current['status'] === 'Done') {
            $exceededDuration    = $current['exceeded_duration'];
            $deletedFromCalendar = 1;
        }

        if ($current['status'] === 'Done' && $status !== 'Done') {
            $deletedFromCalendar = 0;
        }
    }

    $stmt = $conn->prepare("
        UPDATE maintenance_schedules 
        SET system_id = ?, title = ?, description = ?, 
            start_datetime = ?, end_datetime = ?, 
            status = ?, exceeded_duration = ?,
            deleted_from_calendar = ?
        WHERE id = ?
    ");

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param("isssssiii", $systemId, $title, $description, $startDt, $endDt, $status, $exceededDuration, $deletedFromCalendar, $id);

    if ($stmt->execute()) {
        // Send update email notification
        if ($sendEmail && !empty($emailRecipients) && in_array($status, ['Scheduled', 'In Progress'])) {
            $scheduleData = [
                'system_id'      => $systemId,
                'title'          => $title,
                'description'    => $description,
                'start_datetime' => $startDt,
                'end_datetime'   => $endDt,
                'status'         => $status,
                'scheduled_by'   => $currentUsername ?? 'Administrator',
            ];
            sendMaintenanceEmail('updated', $scheduleData, $emailRecipients, $oldScheduleData);
        }

        if ($status === 'Done' && $changeToOnline === 'yes') {
            $onlineStmt = $conn->prepare("UPDATE systems SET status = 'online', updated_at = NOW() WHERE id = ?");
            if ($onlineStmt) {
                $onlineStmt->bind_param("i", $systemId);
                $onlineStmt->execute();
                $onlineStmt->close();

                $logNote = 'Maintenance completed. System is ready to use.';
                $logStmt = $conn->prepare("INSERT INTO status_logs (system_id, old_status, new_status, changed_by, change_note) VALUES (?, 'maintenance', 'online', ?, ?)");
                if ($logStmt) {
                    $logStmt->bind_param("iis", $systemId, $currentUserId, $logNote);
                    $logStmt->execute();
                    $logStmt->close();
                }
            }
            echo json_encode(['success' => true, 'message' => 'Schedule marked Done. System switched to Online.', 'system_switched' => true]);
        } else {
            echo json_encode(['success' => true, 'message' => 'Schedule updated successfully', 'system_switched' => false]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Execute failed: ' . $stmt->error]);
    }
    $stmt->close();
    $conn->close();
    exit();
}

$conn->close();
?>