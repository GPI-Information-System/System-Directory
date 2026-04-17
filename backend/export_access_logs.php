<?php
require_once '../config/session.php';
require_once '../config/database.php';

requireLogin();

// SuperAdmin only
if (!isSuperAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$conn = getDBConnection();

//  Filters 
$search       = trim($_GET['search']      ?? '');
$filterSystem = trim($_GET['system']      ?? '');
$filterDate   = trim($_GET['date_range']  ?? 'all');
$filterSource = trim($_GET['source']      ?? 'all');
$customDate   = trim($_GET['custom_date'] ?? '');
$sortCol      = $_GET['sort'] ?? 'accessed_at';
$sortDir      = ($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

if ($customDate !== '') $filterDate = 'all';

// sortable columns
$allowedSorts = ['id', 'system_name', 'ip_address', 'language_mode', 'accessed_from', 'accessed_at'];
if (!in_array($sortCol, $allowedSorts)) $sortCol = 'accessed_at';

// Build WHERE clause 
$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[]  = "(system_name LIKE ? OR ip_address LIKE ? OR browser_device LIKE ? OR language_mode LIKE ? OR accessed_from LIKE ?)";
    $s        = '%' . $search . '%';
    $params   = array_merge($params, [$s, $s, $s, $s, $s]);
    $types   .= 'sssss';
}

if ($filterSystem !== '') {
    $where[]  = "system_name = ?";
    $params[] = $filterSystem;
    $types   .= 's';
}

if ($filterSource === 'grid' || $filterSource === 'recents') {
    $where[]  = "accessed_from = ?";
    $params[] = $filterSource;
    $types   .= 's';
}

if ($customDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $customDate)) {
    $where[]  = "DATE(accessed_at) = ?";
    $params[] = $customDate;
    $types   .= 's';
} else {
    switch ($filterDate) {
        case 'today':
            $where[] = "DATE(accessed_at) = CURDATE()";
            break;
        case '7days':
            $where[] = "accessed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case '30days':
            $where[] = "accessed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
}

$whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Fetch ALL rows (no LIMIT) ──────────────────────────────────
$stmt = $conn->prepare("SELECT id, system_name, ip_address, browser_device, language_mode, accessed_from, accessed_at FROM access_logs $whereSql ORDER BY $sortCol $sortDir");
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

function parseBrowserDevice($ua) {
    if (empty($ua)) return ['browser' => 'Unknown', 'os' => 'Unknown'];

    $browser = 'Unknown';
    if (str_contains($ua, 'Edg/') || str_contains($ua, 'Edge/'))         $browser = 'Edge';
    elseif (str_contains($ua, 'OPR/') || str_contains($ua, 'Opera/'))    $browser = 'Opera';
    elseif (str_contains($ua, 'Chrome/') && !str_contains($ua, 'Chromium')) $browser = 'Chrome';
    elseif (str_contains($ua, 'Firefox/'))                                $browser = 'Firefox';
    elseif (str_contains($ua, 'Safari/') && !str_contains($ua, 'Chrome')) $browser = 'Safari';
    elseif (str_contains($ua, 'MSIE') || str_contains($ua, 'Trident/'))  $browser = 'Internet Explorer';
    elseif (str_contains($ua, 'Chromium/'))                               $browser = 'Chromium';

    $os = 'Unknown';
    if (str_contains($ua, 'Windows NT 10') || str_contains($ua, 'Windows NT 6.3') || str_contains($ua, 'Windows NT 6.2')) $os = 'Windows';
    elseif (str_contains($ua, 'Windows NT 6.1'))  $os = 'Windows 7';
    elseif (str_contains($ua, 'Windows'))         $os = 'Windows';
    elseif (str_contains($ua, 'Mac OS X') || str_contains($ua, 'macOS')) $os = 'macOS';
    elseif (str_contains($ua, 'iPhone'))          $os = 'iPhone';
    elseif (str_contains($ua, 'iPad'))            $os = 'iPad';
    elseif (str_contains($ua, 'Android'))         $os = 'Android';
    elseif (str_contains($ua, 'Linux'))           $os = 'Linux';

    return ['browser' => $browser, 'os' => $os];
}


$formatted = [];
foreach ($rows as $row) {
    $bd = parseBrowserDevice($row['browser_device']);
    $formatted[] = [
        'id'           => '#' . $row['id'],
        'system_name'  => $row['system_name'],
        'ip_address'   => $row['ip_address'],
        'browser'      => $bd['browser'] . ' / ' . $bd['os'],
        'language'     => strtoupper($row['language_mode']),
        'source'       => ucfirst($row['accessed_from']),
        'timestamp'    => date('M d, Y h:i A', strtotime($row['accessed_at'])),
    ];
}

echo json_encode(['success' => true, 'data' => $formatted, 'total' => count($formatted)]);
?>