<?php
require_once '../config/session.php';
require_once '../config/database.php';

requireLogin();

// SuperAdmin only
if (!isSuperAdmin()) {
    header('Location: dashboard.php');
    exit();
}

$currentUser = getCurrentUser();
$conn        = getDBConnection();

//  Pagination
$perPage     = 7;
$currentPage = max(1, intval($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

// Filters 
$search       = trim($_GET['search']      ?? '');
$filterSystem = trim($_GET['system']      ?? '');
$filterDate   = trim($_GET['date_range']  ?? 'all');
$filterSource = trim($_GET['source']      ?? 'all');
$customDate   = trim($_GET['custom_date'] ?? '');
$sortCol      = $_GET['sort'] ?? 'accessed_at';
$sortDir      = ($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

// If custom date is set, override date
if ($customDate !== '') {
    $filterDate = 'all';
}

// ortable columns
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

// Custom date takes priority 
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

//  Total count 
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM access_logs $whereSql");
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalRows  = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = max(1, ceil($totalRows / $perPage));
$countStmt->close();

//  Fetch logs
$dataStmt  = $conn->prepare("SELECT * FROM access_logs $whereSql ORDER BY $sortCol $sortDir LIMIT ? OFFSET ?");
$allParams = array_merge($params, [$perPage, $offset]);
$allTypes  = $types . 'ii';
$dataStmt->bind_param($allTypes, ...$allParams);
$dataStmt->execute();
$logs = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();


if ($customDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $customDate)) {
    $summaryWhere  = "WHERE DATE(accessed_at) = '$customDate'";
    $summaryLabel  = ($customDate === date('Y-m-d')) ? 'Today' : date('M d, Y', strtotime($customDate));
} elseif ($filterDate === '7days') {
    $summaryWhere  = "WHERE accessed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $summaryLabel  = 'Last 7 Days';
} elseif ($filterDate === '30days') {
    $summaryWhere  = "WHERE accessed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $summaryLabel  = 'Last 30 Days';
} elseif ($filterDate === 'today') {
    $summaryWhere  = "WHERE DATE(accessed_at) = CURDATE()";
    $summaryLabel  = 'Today';
} else {
    $summaryWhere  = "WHERE DATE(accessed_at) = CURDATE()";
    $summaryLabel  = 'Today';
}

$todayTotal = $conn->query("SELECT COUNT(*) as c FROM access_logs $summaryWhere")->fetch_assoc()['c'];
$mostRow    = $conn->query("SELECT system_name, COUNT(*) as c FROM access_logs $summaryWhere GROUP BY system_name HAVING c > 1 ORDER BY c DESC LIMIT 1")->fetch_assoc();
$mostSystem = $mostRow ? $mostRow['system_name'] : '—';

//  All systems from systems table 
$allSystems = [];
$sysResult  = $conn->query("SELECT name FROM systems ORDER BY name ASC");
while ($sysRow = $sysResult->fetch_assoc()) {
    $allSystems[] = $sysRow['name'];
}

$conn->close();


function parseBrowserDevice($ua) {
    if (empty($ua)) return ['browser' => 'Unknown', 'os' => 'Unknown'];

    $browser = 'Unknown';
    if (str_contains($ua, 'Edg/') || str_contains($ua, 'Edge/')) {
        $browser = 'Edge';
    } elseif (str_contains($ua, 'OPR/') || str_contains($ua, 'Opera/')) {
        $browser = 'Opera';
    } elseif (str_contains($ua, 'Chrome/') && !str_contains($ua, 'Chromium')) {
        $browser = 'Chrome';
    } elseif (str_contains($ua, 'Firefox/')) {
        $browser = 'Firefox';
    } elseif (str_contains($ua, 'Safari/') && !str_contains($ua, 'Chrome')) {
        $browser = 'Safari';
    } elseif (str_contains($ua, 'MSIE') || str_contains($ua, 'Trident/')) {
        $browser = 'Internet Explorer';
    } elseif (str_contains($ua, 'Chromium/')) {
        $browser = 'Chromium';
    }

    $os = 'Unknown';
    if (str_contains($ua, 'Windows NT 10') || str_contains($ua, 'Windows NT 6.3') || str_contains($ua, 'Windows NT 6.2')) {
        $os = 'Windows';
    } elseif (str_contains($ua, 'Windows NT 6.1')) {
        $os = 'Windows 7';
    } elseif (str_contains($ua, 'Windows')) {
        $os = 'Windows';
    } elseif (str_contains($ua, 'Mac OS X') || str_contains($ua, 'macOS')) {
        $os = 'macOS';
    } elseif (str_contains($ua, 'iPhone')) {
        $os = 'iPhone';
    } elseif (str_contains($ua, 'iPad')) {
        $os = 'iPad';
    } elseif (str_contains($ua, 'Android')) {
        $os = 'Android';
    } elseif (str_contains($ua, 'Linux')) {
        $os = 'Linux';
    }

    return ['browser' => $browser, 'os' => $os];
}

function sortUrl($col, $currentCol, $currentDir) {
    $dir    = ($col === $currentCol && $currentDir === 'asc') ? 'desc' : 'asc';
    $params = array_merge($_GET, ['sort' => $col, 'dir' => $dir, 'page' => 1]);
    return '?' . http_build_query($params);
}

function sortIcon($col, $currentCol, $currentDir) {
    if ($col !== $currentCol) {
        return '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity:0.3"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>';
    }
    if ($currentDir === 'asc') {
        return '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>';
    }
    return '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>G-Portal — Access Logs</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/logo.png">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/access_logs.css">
</head>
<body>
<div class="container">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h1>G-Portal</h1>
            <div class="user-info">
                Welcome, <strong><?php echo htmlspecialchars($currentUser['username']); ?></strong>
                <span class="user-role"><?php echo htmlspecialchars($currentUser['role']); ?></span>
            </div>
        </div>
        <nav>
            <ul class="nav-menu">
                <li>
                    <a href="dashboard.php">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="analytics.php">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line></svg>
                        Analytics
                    </a>
                </li>
                <li>
                    <a href="access_logs.php" class="active">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                        Access Logs
                    </a>
                </li>
                <li>
                    <a href="users.php">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        User Management
                    </a>
                </li>
            </ul>
            <div class="user-profile">
                <div class="user-avatar-large">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($currentUser['username']); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($currentUser['role']); ?></div>
                </div>
            </div>
            <form action="../backend/logout.php" method="POST">
                <button type="submit" class="btn-logout">Logout</button>
            </form>
        </nav>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <div class="al-page">

            <!-- Page Header -->
            <div class="al-page-header">
                <div class="al-page-title">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    Access Logs
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <button class="al-btn al-btn-pdf" onclick="exportPDF()">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                        Export PDF
                    </button>
                    <button class="al-btn al-btn-danger" onclick="document.getElementById('clearConfirm').classList.add('show')">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        Clear Logs
                    </button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="al-summary-row">
                <div class="al-summary-card">
                    <div class="al-summary-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    </div>
                    <div>
                        <div class="al-summary-label">Total Accesses — <?php echo $summaryLabel; ?></div>
                        <div class="al-summary-value"><?php echo number_format($todayTotal); ?></div>
                    </div>
                </div>
                <div class="al-summary-card">
                    <div class="al-summary-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                    </div>
                    <div>
                        <div class="al-summary-label">Most Accessed — <?php echo $summaryLabel; ?></div>
                        <div class="al-summary-value" title="<?php echo htmlspecialchars($mostSystem); ?>"><?php echo htmlspecialchars($mostSystem); ?></div>
                    </div>
                </div>
            </div>

            <!-- Controls / Filters -->
            <form method="GET" action="access_logs.php" id="filterForm">
                <div class="al-controls">

                    <!-- Custom Date Picker -->
            <div class="al-date-picker-wrap">
                <svg class="al-date-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                <span class="al-date-label <?php echo $customDate !== '' ? 'has-value' : ''; ?>" id="dateLabelText">
                  <?php
                        $todayStr    = date('Y-m-d');
                        $displayDate = ($customDate === $todayStr) ? 'Today' : ($customDate !== '' ? date('M d, Y', strtotime($customDate)) : 'Select Date');
                        echo $displayDate;
                    ?>
                </span>
                <input type="date" name="custom_date" id="customDateInput"
                    class="al-date-input"
                    value="<?php echo htmlspecialchars($customDate); ?>"
                    onchange="onCustomDateChange(this)">
                <svg class="al-date-arrow" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </div>

                    <!-- Search -->
                    <div class="al-search-wrap">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>
                        <input type="text" name="search" class="al-search"
                               placeholder="Search by system, IP, browser..."
                               value="<?php echo htmlspecialchars($search); ?>"
                               oninput="debounceSubmit()"
                               onblur="onSearchBlur()">
                    </div>

                    <!-- Searchable System Dropdown -->
                    <div class="al-system-dropdown-wrap" id="systemDropdownWrap">
                        <div class="al-system-dropdown-trigger" id="systemDropdownTrigger" onclick="toggleSystemDropdown()">
                            <span id="systemDropdownLabel"><?php echo $filterSystem ? htmlspecialchars($filterSystem) : 'All Systems'; ?></span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </div>
                        <div class="al-system-dropdown-menu" id="systemDropdownMenu">
                            <div class="al-system-dropdown-search-wrap">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>
                                <input type="text" class="al-system-dropdown-search"
                                       id="systemDropdownSearch"
                                       placeholder="Search system..."
                                       oninput="filterSystemOptions(this.value)"
                                       autocomplete="off">
                            </div>
                            <div class="al-system-dropdown-list" id="systemDropdownList">
                                <div class="al-system-dropdown-option <?php echo $filterSystem === '' ? 'selected' : ''; ?>"
                                     onclick="selectSystem('')">All Systems</div>
                                <?php foreach ($allSystems as $sysName): ?>
                                <div class="al-system-dropdown-option <?php echo $filterSystem === $sysName ? 'selected' : ''; ?>"
                                     onclick="selectSystem('<?php echo addslashes(htmlspecialchars($sysName)); ?>')"
                                     data-name="<?php echo htmlspecialchars(strtolower($sysName)); ?>">
                                    <?php echo htmlspecialchars($sysName); ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <input type="hidden" name="system" id="systemHiddenInput" value="<?php echo htmlspecialchars($filterSystem); ?>">
                    </div>

                    <!-- Source Filter -->
                    <select name="source" class="al-select" onchange="this.form.submit()">
                        <option value="all"     <?php echo $filterSource === 'all'     ? 'selected' : ''; ?>>All Sources</option>
                        <option value="grid"    <?php echo $filterSource === 'grid'    ? 'selected' : ''; ?>>Grid</option>
                        <option value="recents" <?php echo $filterSource === 'recents' ? 'selected' : ''; ?>>Recents</option>
                    </select>

                    <!-- Date Range — hidden when custom date is active -->
                    <select name="date_range" id="dateRangeSelect" class="al-select" onchange="onDateRangeChange(this)" <?php echo $customDate !== '' ? 'style="display:none;"' : ''; ?>>
                        <option value="all"    <?php echo $filterDate === 'all'    ? 'selected' : ''; ?>>All Time</option>
                        <option value="today"  <?php echo $filterDate === 'today'  ? 'selected' : ''; ?>>Today</option>
                        <option value="7days"  <?php echo $filterDate === '7days'  ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="30days" <?php echo $filterDate === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                    </select>

                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortCol); ?>">
                    <input type="hidden" name="dir"  value="<?php echo htmlspecialchars($sortDir); ?>">
                    <input type="hidden" name="page" value="1">

                    <!-- Auto-refresh Toggle -->
                    <div class="al-refresh-wrap" id="refreshWrap" onclick="toggleAutoRefresh()">
                        <label class="al-refresh-toggle" onclick="event.stopPropagation()">
                            <input type="checkbox" id="refreshToggle" onchange="toggleAutoRefresh()">
                            <span class="al-refresh-slider"></span>
                        </label>
                        <span class="al-refresh-label">Auto-refresh</span>
                        <span class="al-refresh-countdown" id="refreshCountdown">30s</span>
                    </div>

                    <?php if ($search || $filterSystem || $filterDate !== 'all' || $filterSource !== 'all' || $customDate !== ''): ?>
                    <a href="access_logs.php" class="al-btn al-btn-clear">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        Clear
                    </a>
                    <?php endif; ?>

                </div>
            </form>

            <!-- Active custom date indicator -->
            <?php if ($customDate !== ''): ?>
            <div class="al-active-date-badge">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                Showing logs for: <strong><?php echo ($customDate === date('Y-m-d')) ? 'Today' : date('F d, Y', strtotime($customDate)); ?></strong>
            </div>
            <?php endif; ?>

            <!-- Table -->
            <div class="al-table-wrap" id="logsTable">
                <?php if (empty($logs)): ?>
                <div class="al-empty">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                    <h3>No Logs Found</h3>
                    <p><?php echo ($search || $filterSystem || $filterDate !== 'all' || $filterSource !== 'all' || $customDate !== '') ? 'No logs match your current filters.' : 'No access logs recorded yet.'; ?></p>
                </div>
                <?php else: ?>
                <table class="al-table" id="exportTable">
                    <thead>
                        <tr>
                            <th><a href="<?php echo sortUrl('id', $sortCol, $sortDir); ?>"># <?php echo sortIcon('id', $sortCol, $sortDir); ?></a></th>
                            <th><a href="<?php echo sortUrl('system_name', $sortCol, $sortDir); ?>">System Name <?php echo sortIcon('system_name', $sortCol, $sortDir); ?></a></th>
                            <th><a href="<?php echo sortUrl('ip_address', $sortCol, $sortDir); ?>">IP Address <?php echo sortIcon('ip_address', $sortCol, $sortDir); ?></a></th>
                            <th>Browser / Device</th>
                            <th><a href="<?php echo sortUrl('language_mode', $sortCol, $sortDir); ?>">Language <?php echo sortIcon('language_mode', $sortCol, $sortDir); ?></a></th>
                            <th><a href="<?php echo sortUrl('accessed_from', $sortCol, $sortDir); ?>">Accessed From <?php echo sortIcon('accessed_from', $sortCol, $sortDir); ?></a></th>
                            <th><a href="<?php echo sortUrl('accessed_at', $sortCol, $sortDir); ?>">Timestamp <?php echo sortIcon('accessed_at', $sortCol, $sortDir); ?></a></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log):
                            $bd = parseBrowserDevice($log['browser_device']);
                        ?>
                        <tr>
                            <td><span class="al-id-badge">#<?php echo $log['id']; ?></span></td>
                            <td>
                                <span class="al-system-name" title="<?php echo htmlspecialchars($log['system_name']); ?>">
                                    <?php echo htmlspecialchars($log['system_name']); ?>
                                </span>
                            </td>
                            <td><span class="al-ip"><?php echo htmlspecialchars($log['ip_address']); ?></span></td>
                            <td>
                                <div class="al-browser-cell" title="<?php echo htmlspecialchars($log['browser_device']); ?>">
                                    <span class="al-browser-name"><?php echo htmlspecialchars($bd['browser']); ?></span>
                                    <span class="al-browser-os"><?php echo htmlspecialchars($bd['os']); ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="al-lang-badge <?php echo $log['language_mode'] === 'jp' ? 'al-lang-jp' : 'al-lang-en'; ?>">
                                    <?php echo strtoupper($log['language_mode']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="al-from-badge <?php echo $log['accessed_from'] === 'recents' ? 'al-from-recents' : 'al-from-grid'; ?>">
                                    <?php echo ucfirst($log['accessed_from']); ?>
                                </span>
                            </td>
                            <td><span class="al-timestamp"><?php echo date('M d, Y h:i A', strtotime($log['accessed_at'])); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="al-pagination">
                    <div class="al-pagination-info">
                        Showing <?php echo min($offset + 1, $totalRows); ?>–<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo number_format($totalRows); ?> records
                    </div>
                    <div class="al-pagination-btns">
                        <?php
                        $baseParams = array_merge($_GET, ['sort' => $sortCol, 'dir' => $sortDir]);
                        $prevPage   = max(1, $currentPage - 1);
                        $nextPage   = min($totalPages, $currentPage + 1);
                        ?>
                        <a href="?<?php echo http_build_query(array_merge($baseParams, ['page' => 1])); ?>" class="al-page-btn <?php echo $currentPage === 1 ? 'disabled' : ''; ?>" title="First">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="11 17 6 12 11 7"></polyline><polyline points="18 17 13 12 18 7"></polyline></svg>
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($baseParams, ['page' => $prevPage])); ?>" class="al-page-btn <?php echo $currentPage === 1 ? 'disabled' : ''; ?>" title="Previous">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        </a>
                        <?php
                        $startPage = max(1, $currentPage - 2);
                        $endPage   = min($totalPages, $currentPage + 2);
                        for ($p = $startPage; $p <= $endPage; $p++):
                        ?>
                        <a href="?<?php echo http_build_query(array_merge($baseParams, ['page' => $p])); ?>" class="al-page-btn <?php echo $p === $currentPage ? 'active' : ''; ?>"><?php echo $p; ?></a>
                        <?php endfor; ?>
                        <a href="?<?php echo http_build_query(array_merge($baseParams, ['page' => $nextPage])); ?>" class="al-page-btn <?php echo $currentPage === $totalPages ? 'disabled' : ''; ?>" title="Next">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($baseParams, ['page' => $totalPages])); ?>" class="al-page-btn <?php echo $currentPage === $totalPages ? 'disabled' : ''; ?>" title="Last">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="13 17 18 12 13 7"></polyline><polyline points="6 17 11 12 6 7"></polyline></svg>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>

<!-- Clear Logs Confirm Modal -->
<div class="al-confirm-overlay" id="clearConfirm">
    <div class="al-confirm-box">
        <div class="al-confirm-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
        </div>
        <h4>Clear All Logs?</h4>
        <p>This will permanently delete all access log records. This action cannot be undone.</p>
        <div class="al-confirm-actions">
            <button class="al-btn al-btn-clear" onclick="document.getElementById('clearConfirm').classList.remove('show')">Cancel</button>
            <form method="POST" action="../backend/clear_access_logs.php" style="display:inline;">
                <button type="submit" class="al-btn al-btn-danger">Yes, Clear All</button>
            </form>
        </div>
    </div>
</div>

<script src="../assets/js/jspdf.umd.min.js"></script>
<script src="../assets/js/access_logs.js"></script>
</body>
</html>