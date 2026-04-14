<?php
require_once '../config/session.php';
require_once '../config/database.php';

requireLogin();
$currentUser = getCurrentUser();

$conn = getDBConnection();

// Load categories from DB 
$catResult = $conn->query("SELECT id, name, sort_order FROM categories ORDER BY sort_order ASC, name ASC");
$dbCategories = [];
while ($catRow = $catResult->fetch_assoc()) {
    $dbCategories[] = $catRow;
}

// Build category order 
$categoryOrder = [];
foreach ($dbCategories as $i => $cat) {
    $categoryOrder[$cat['name']] = $i + 1;
}

$result = $conn->query("SELECT * FROM systems ORDER BY created_at DESC");
$systems = [];
while ($row = $result->fetch_assoc()) { $systems[] = $row; }
$conn->close();

$statusPriority = ['online'=>1,'maintenance'=>2,'down'=>3,'offline'=>4,'archived'=>5];

usort($systems, function($a, $b) use ($categoryOrder, $statusPriority) {
    $catA = $categoryOrder[$a['category'] ?? ''] ?? 99;
    $catB = $categoryOrder[$b['category'] ?? ''] ?? 99;
    if ($catA !== $catB) return $catA - $catB;
    $pA = $statusPriority[$a['status'] ?? 'online'] ?? 999;
    $pB = $statusPriority[$b['status'] ?? 'online'] ?? 999;
    if ($pA !== $pB) return $pA - $pB;
    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
});

// Group systems by category
$groupedSystems = [];
foreach ($systems as $system) {
    $cat = $system['category'] ?? ($dbCategories[0]['name'] ?? 'Direct');
    $groupedSystems[$cat][] = $system;
}

$canScheduleMaintenance = isSuperAdmin() || isAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>G-Portal - Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/maintenance.css">
     <script src="../assets/js/chart.umd.min.js"></script>
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
                    <a href="dashboard.php" class="active">
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
                <?php if (isSuperAdmin()): ?>
                <li>
                    <a href="users.php">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        User Management
                    </a>
                </li>
                <?php endif; ?>
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
        <div class="dashboard-analytics-row">
            <div class="analytics-chart-section">
                <div class="chart-header">
                    <h3>Systems by Status</h3>
                    <a href="analytics.php" class="link-to-analytics">
                        View Full Analytics
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </a>
                </div>
                <div class="dashboard-chart-container">
                    <canvas id="systemsStatusChart"></canvas>
                </div>
            </div>

            <div class="maintenance-calendar-section">
                <div class="calendar-header">
                    <div class="calendar-header-title">
                        <h3>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            Maintenance Schedule
                        </h3>
                    </div>
                    <div class="calendar-nav">
                        <button class="cal-nav-btn" onclick="changeCalendarMonth(-1)" title="Previous month">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        </button>
                        <span class="cal-month-label" id="calMonthLabel"></span>
                        <button class="cal-nav-btn" onclick="changeCalendarMonth(1)" title="Next month">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </button>
                    </div>
                </div>
                <div class="calendar-body">
                    <div class="calendar-grid-wrapper">
                        <div class="calendar-day-labels">
                            <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                        </div>
                        <div class="calendar-days" id="calendarDays"></div>
                    </div>
                    <div class="calendar-legend">
                        <span class="legend-item"><span class="legend-dot legend-dot-has"></span>Has maintenance</span>
                        <span class="legend-item"><span class="legend-dot legend-dot-today"></span>Today</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="top-section">
            <div class="content-header"><h2>All Systems</h2></div>
            <div class="top-controls-row">
                <input type="text" id="searchBox" class="search-box" placeholder="Search systems..." onkeyup="searchSystems()">

                <!-- Status Filter -->
                <div class="filter-dropdown-container">
                    <button type="button" class="btn-filter" onclick="toggleFilterDropdown(event)">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="6" x2="20" y2="6"></line><line x1="4" y1="12" x2="20" y2="12"></line><line x1="4" y1="18" x2="20" y2="18"></line><circle cx="9" cy="6" r="2.5" fill="currentColor" stroke="none"></circle><circle cx="15" cy="12" r="2.5" fill="currentColor" stroke="none"></circle><circle cx="9" cy="18" r="2.5" fill="currentColor" stroke="none"></circle></svg>
                        Status Filter
                    </button>
                    <div class="filter-dropdown-menu">
                        <button type="button" onclick="filterSystems('all')" class="filter-option active" data-filter="all"><span class="filter-dot filter-all"></span>All Systems</button>
                        <button type="button" onclick="filterSystems('online')" class="filter-option" data-filter="online"><span class="filter-dot filter-online"></span>Online</button>
                        <button type="button" onclick="filterSystems('maintenance')" class="filter-option" data-filter="maintenance"><span class="filter-dot filter-maintenance"></span>Maintenance</button>
                        <button type="button" onclick="filterSystems('down')" class="filter-option" data-filter="down"><span class="filter-dot filter-down"></span>Down</button>
                        <button type="button" onclick="filterSystems('offline')" class="filter-option" data-filter="offline"><span class="filter-dot filter-offline"></span>Offline</button>
                        <button type="button" onclick="filterSystems('archived')" class="filter-option" data-filter="archived"><span class="filter-dot filter-archived"></span>Archived</button>
                    </div>
                </div>

                <!-- Category Filter -->
                <div class="filter-dropdown-container">
                    <button type="button" class="btn-filter" onclick="toggleCategoryDropdown(event)">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="6" x2="20" y2="6"></line><line x1="4" y1="12" x2="20" y2="12"></line><line x1="4" y1="18" x2="20" y2="18"></line><circle cx="9" cy="6" r="2.5" fill="currentColor" stroke="none"></circle><circle cx="15" cy="12" r="2.5" fill="currentColor" stroke="none"></circle><circle cx="9" cy="18" r="2.5" fill="currentColor" stroke="none"></circle></svg>
                        Category
                    </button>
                    <div class="filter-dropdown-menu" id="categoryFilterMenu">
                        <button type="button" onclick="filterByCategory('all')" class="filter-option active" data-cat="all">
                            <span class="filter-dot" style="background:var(--primary-light);"></span>All Categories
                        </button>
                        <?php foreach ($dbCategories as $dbCat): ?>
                        <button type="button"
                                onclick="filterByCategory('<?php echo htmlspecialchars($dbCat['name']); ?>')"
                                class="filter-option"
                                data-cat="<?php echo htmlspecialchars($dbCat['name']); ?>">
                            <span class="filter-dot" style="background:var(--primary-color);"></span>
                            <?php echo htmlspecialchars($dbCat['name']); ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="button" class="btn-clear-filters" id="btnClearFilters"
                        onclick="clearAllFilters()" style="display:none;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    Clear Filters
                </button>

                <div style="flex:1;"></div>

                <?php if ($canScheduleMaintenance): ?>
                <button class="btn-bulk-schedule" id="btnBulkSchedule" onclick="toggleBulkMode()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="4" height="4" rx="1"></rect><rect x="3" y="13" width="4" height="4" rx="1"></rect><line x1="10" y1="7" x2="21" y2="7"></line><line x1="10" y1="15" x2="21" y2="15"></line></svg>
                    <span id="btnBulkScheduleLabel">Schedule Multiple</span>
                </button>
                <?php endif; ?>

                <!-- Categories Management btn-->
                <button class="btn-categories" onclick="openCategoriesModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="8"  y1="6"  x2="21" y2="6"></line>
                        <line x1="8"  y1="12" x2="21" y2="12"></line>
                        <line x1="8"  y1="18" x2="21" y2="18"></line>
                        <line x1="3"  y1="6"  x2="3.01" y2="6"></line>
                        <line x1="3"  y1="12" x2="3.01" y2="12"></line>
                        <line x1="3"  y1="18" x2="3.01" y2="18"></line>
                    </svg>
                    Manage Categories
                </button>

                <?php if (isSuperAdmin()): ?>
                <button class="btn-add-top" onclick="openAddModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Add System
                </button>
                <?php endif; ?>
            </div>

            <!-- Japanese Domain Toggle -->
            <div class="lang-toggle-below-row">
                <label class="lang-toggle-switch">
                    <input type="checkbox" id="toggleJapaneseDomainInput" onchange="toggleJapaneseDomain(this.checked)">
                    <span class="lang-toggle-slider"></span>
                </label>
                <span class="lang-toggle-label">Show Japanese Domain</span>
            </div>

        </div>

        <!-- SYSTEMS GRID -->
        <div id="cardsGrid">
            <?php if (empty($systems)): ?>
                <div class="cards-grid">
                    <div class="empty-state" style="grid-column:1/-1;">
                        <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="9" x2="15" y2="15"></line><line x1="15" y1="9" x2="9" y2="15"></line></svg>
                        <h3>No Systems Found</h3>
                        <p>Start by adding your first system</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($dbCategories as $dbCat): ?>
                <?php $categoryName = $dbCat['name']; ?>
                <?php if (empty($groupedSystems[$categoryName])): continue; endif; ?>
                <div class="category-group" data-category="<?php echo htmlspecialchars($categoryName); ?>">
                    <div class="category-header">
                        <h3 class="category-title"><?php echo htmlspecialchars($categoryName); ?> Systems</h3>
                        <span class="category-count"><?php echo count($groupedSystems[$categoryName]); ?></span>
                    </div>
                    <div class="cards-grid">
                <?php foreach ($groupedSystems[$categoryName] as $system): ?>
                    <?php
                    $status = $system['status'] ?? 'online';
                    $isArchived = $status === 'archived';
                    $statusLabels = ['online'=>'Online','offline'=>'Offline','maintenance'=>'Maintenance','down'=>'Down','archived'=>'Archived'];
                    $statusLabel = $statusLabels[$status] ?? 'Online';
                    $contactNumber = $system['contact_number'] ?? '123';
                    $badgeUrl = $system['badge_url'] ?? '';
                    $networkType = $system['network_type'] ?? 'https';
                    ?>
                    <div class="system-card <?php echo $isArchived ? 'bulk-excluded' : ''; ?>"
                        data-status="<?php echo htmlspecialchars($status); ?>"
                        data-system-id="<?php echo $system['id']; ?>"
                        data-system-name="<?php echo htmlspecialchars(addslashes($system['name'])); ?>"
                        data-contact-number="<?php echo htmlspecialchars($contactNumber); ?>"
                        data-japanese-domain="<?php echo htmlspecialchars($system['japanese_domain'] ?? ''); ?>"
                        data-network-type="<?php echo htmlspecialchars($networkType); ?>"
                        <?php if (!$isArchived): ?>
                        onclick="handleCardClick(event, <?php echo $system['id']; ?>)"
                        style="cursor: pointer;"
                        <?php endif; ?>>

                        <?php if ($canScheduleMaintenance && !$isArchived): ?>
                        <div class="bulk-checkbox-overlay" onclick="toggleCardSelection(event,<?php echo $system['id']; ?>,'<?php echo addslashes($system['name']); ?>')">
                            <div class="bulk-checkbox" id="bulk-check-<?php echo $system['id']; ?>">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="card-header">
                            <div style="flex:1;">
                                <?php if (!empty($system['logo']) && file_exists('../'.$system['logo'])): ?>
                                    <a href="#" onclick="openDomain(<?php echo $system['id']; ?>);return false;" style="display:block;">
                                        <img src="../<?php echo htmlspecialchars($system['logo']); ?>" alt="<?php echo htmlspecialchars($system['name']); ?>" class="system-logo system-logo-clickable">
                                    </a>
                                <?php else: ?>
                                    <a href="#" onclick="openDomain(<?php echo $system['id']; ?>);return false;" style="display:block;">
                                        <div class="system-logo-placeholder system-logo-clickable">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                                        </div>
                                    </a>
                                <?php endif; ?>
                                <div class="card-status-badge status-<?php echo htmlspecialchars($status); ?>">
                                    <span class="status-indicator"></span>
                                    <?php echo $statusLabel; ?>
                                </div>
                                <h3 class="card-title"><?php echo htmlspecialchars($system['name']); ?></h3>
                                <a href="#" class="card-domain" onclick="openDomain(<?php echo $system['id']; ?>);return false;"><?php echo htmlspecialchars($system['domain']); ?></a>
                            </div>
                            <div class="card-menu">
                                <button class="menu-toggle" onclick="toggleDropdown(event,<?php echo $system['id']; ?>)">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="2"></circle><circle cx="12" cy="12" r="2"></circle><circle cx="12" cy="19" r="2"></circle></svg>
                                </button>
                                <div class="dropdown-menu">
                                    <?php if (isSuperAdmin() || isAdmin()): ?>
                                    <?php
                                    $logoPreviewPath = (!empty($system['logo']) && file_exists('../' . $system['logo']))
                                        ? '../' . $system['logo']
                                        : '';
                                    ?>
                                    <button onclick="openEditModal(
                                        <?php echo $system['id']; ?>,
                                        '<?php echo addslashes($system['name']); ?>',
                                        '<?php echo addslashes($system['domain']); ?>',
                                        '<?php echo addslashes($badgeUrl); ?>',
                                        '<?php echo addslashes($system['description']); ?>',
                                        '<?php echo addslashes($status); ?>',
                                        '<?php echo addslashes($contactNumber); ?>',
                                        <?php echo intval($system['exclude_health_check']??0); ?>,
                                        '<?php echo addslashes($logoPreviewPath); ?>',
                                        '<?php echo addslashes($system['category'] ?? ($dbCategories[0]['name'] ?? 'Direct')); ?>',
                                        '<?php echo addslashes($system['japanese_domain'] ?? ''); ?>',
                                        '<?php echo addslashes($system['japanese_description'] ?? ''); ?>',
                                        '<?php echo addslashes($networkType); ?>'
                                    )">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                        Edit
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($canScheduleMaintenance): ?>
                                    <button class="schedule-maintenance-btn" onclick="openMaintenanceModal(<?php echo $system['id']; ?>,'<?php echo addslashes($system['name']); ?>')">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                                        Schedule Maintenance
                                    </button>
                                    <?php endif; ?>
                                    <?php if (isSuperAdmin()): ?>
                                    <button class="delete-btn" onclick="deleteSystem(<?php echo $system['id']; ?>,'<?php echo addslashes($system['name']); ?>')">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        Delete
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($system['description'])): ?>
                        <p class="card-description"><?php echo htmlspecialchars($system['description']); ?></p>
                        <?php endif; ?>

                        <?php if (in_array($status, ['maintenance','offline','down'])): ?>
                        <div class="system-contact-message">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                            Contact <span class="contact-number"><?php echo htmlspecialchars($contactNumber); ?></span> for assistance
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- BULK MAINT BAR-->
<?php if ($canScheduleMaintenance): ?>
<div class="bulk-action-bar" id="bulkActionBar">
    <div class="bulk-action-bar-inner">
        <div class="bulk-action-info">
            <div class="bulk-action-icon">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="4" height="4" rx="1"></rect><rect x="3" y="13" width="4" height="4" rx="1"></rect><line x1="10" y1="7" x2="21" y2="7"></line><line x1="10" y1="15" x2="21" y2="15"></line></svg>
            </div>
            <div>
                <div class="bulk-action-count"><span class="count-badge" id="bulkSelectedCount">0</span>system<span id="bulkSelectedPlural">s</span> selected</div>
                <div class="bulk-action-hint">Click cards to select • Archived systems excluded</div>
            </div>
        </div>
        <div class="bulk-action-divider"></div>
        <div class="bulk-action-buttons">
            <button class="bulk-action-cancel" onclick="toggleBulkMode()">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                Cancel
            </button>
            <button class="bulk-action-select-all" id="bulkSelectAllBtn" onclick="toggleSelectAll()">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect></svg>
                <span id="bulkSelectAllLabel">Select All</span>
            </button>
            <button class="bulk-action-mark-online" id="bulkBarMarkOnlineBtn"
                    onclick="openBulkMarkOnlineModal()"
                    style="display:none;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                Mark <span id="bulkBarMarkOnlineCount">0</span> as Online
            </button>
            <button class="bulk-action-proceed" id="bulkProceedBtn" onclick="openBulkModal()" disabled>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                Schedule <span id="bulkProceedCount">0</span> Systems
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ADD SYSTEM MODAL-->
<?php if (isSuperAdmin()): ?>
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New System</h3>
            <button class="close-modal" onclick="closeAddModal()">&times;</button>
        </div>
        <form id="addSystemForm" onsubmit="addSystem(event)" enctype="multipart/form-data">
            <div class="modal-body">

                <div class="form-group">
                    <label>System Logo <span style="font-weight:400;color:var(--gray-400);font-size:12px;">(Optional)</span></label>
                    <div class="file-upload-zone" onclick="document.getElementById('systemLogo').click()">
                        <input type="file" id="systemLogo" name="logo" accept="image/*"
                               onchange="handleLogoUpload(this,'addLogoPreviewBox','addLogoThumb','addLogoFilename','addLogoFilesize')">
                        <div class="file-upload-trigger">
                            <svg class="file-upload-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                            <span class="file-upload-text">Choose image file</span>
                            <span class="file-upload-hint">PNG, JPG, GIF</span>
                        </div>
                    </div>
                    <div class="logo-preview-box" id="addLogoPreviewBox">
                        <img class="logo-preview-thumb" id="addLogoThumb" src="" alt="Preview">
                        <div class="logo-preview-meta">
                            <span class="logo-preview-filename" id="addLogoFilename"></span>
                            <span class="logo-preview-filesize" id="addLogoFilesize"></span>
                        </div>
                        <button type="button" class="logo-preview-remove" onclick="removeLogo('systemLogo','addLogoPreviewBox')" title="Remove">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        </button>
                    </div>
                </div>

                <div class="modal-section-divider"></div>

                <div class="form-group">
                    <div class="field-label-row">
                        <label for="systemName">System Name <span style="color:var(--danger)">*</span></label>
                        <span class="char-counter" id="addNameCounter">0 / 100</span>
                    </div>
                    <input type="text" id="systemName" name="name" required
                           placeholder="e.g., Asset Management System"
                           maxlength="100"
                           oninput="updateCharCounter(this,'addNameCounter',100)">
                </div>

                <div class="form-group">
                    <label for="systemCategory">Category <span style="color:var(--danger)">*</span></label>
                    <select id="systemCategory" name="category" required>
                        <option value="">Select a category...</option>
                        <?php foreach ($dbCategories as $dbCat): ?>
                        <option value="<?php echo htmlspecialchars($dbCat['name']); ?>">
                            <?php echo htmlspecialchars($dbCat['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="field-helper-row">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        Groups this system under the selected category on the dashboard
                    </div>
                </div>

                <div class="form-group">
                    <label for="systemStatus">Status <span style="color:var(--danger)">*</span></label>
                    <div class="status-select-wrap">
                        <span class="status-select-dot" id="addStatusDot"></span>
                        <select id="systemStatus" name="status" required onchange="updateStatusDot(this,'addStatusDot')">
                            <option value="online">Online</option>
                            <option value="offline">Offline</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="down">Down</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                </div>


  
                <div class="form-group">
                    <label>Network Type <span style="color:var(--danger)">*</span></label>
                    <div class="network-type-segment" id="addNetworkTypeSegment">
                        <button type="button" class="segment-btn" data-value="https" onclick="selectNetworkType('add', 'https')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            HTTPS
                        </button>
                        <button type="button" class="segment-btn" data-value="http" onclick="selectNetworkType('add', 'http')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 9.9-1"></path><line x1="17" y1="4" x2="17" y2="7"></line></svg>
                            HTTP
                        </button>
                    </div>
                    <input type="hidden" id="addNetworkTypeValue" name="network_type" value="">
                    <div class="field-helper-row">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        Select the protocol used to access this system's domain
                    </div>
                </div>



                <div class="form-group">
                    <label for="systemDomain">Domain <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="systemDomain" name="domain" required placeholder="e.g., ams.gpi.com">
                </div>

              

                <div class="form-group">
                    <label for="systemJapaneseDomain">Japanese Domain <span style="font-weight:400;color:var(--gray-400);font-size:12px;">(Optional)</span></label>
                    <input type="text" id="systemJapaneseDomain" name="japanese_domain" placeholder="e.g., glory.canteen.co.jp">
                    <div class="field-helper-row">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        Shown when Japanese translation is enabled
                    </div>
                </div>

                <div class="form-group">
                    <label for="systemBadgeUrl">Badge URL <span style="font-weight:400;color:var(--gray-400);font-size:12px;">(Optional)</span></label>
                    <input type="text" id="systemBadgeUrl" name="badge_url" placeholder="e.g., https://uptime.gpi.com/api/badge/33/status">
                    <div class="field-helper-row">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        Used for health checks instead of the domain URL
                    </div>
                </div>

                <div class="form-group">
                    <label for="systemContact">Contact Number</label>
                    <input type="text" id="systemContact" name="contact_number" placeholder="e.g., 09171234567" value="123">
                    <div class="field-helper-row">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        Displayed when system is Offline, Maintenance, or Down
                    </div>
                </div>

                <div class="form-group">
                    <label for="systemDescription">Description</label>
                    <textarea id="systemDescription" name="description" placeholder="Brief description of the system"></textarea>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Add System</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- EDIT SYSTEM MODAL-->
<?php if (isSuperAdmin() || isAdmin()): ?>
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit System</h3>
            <button class="close-modal" onclick="closeEditModal()">&times;</button>
        </div>
        <form id="editSystemForm" onsubmit="editSystem(event)" enctype="multipart/form-data">
            <div class="modal-body">

                <div class="form-group">
                    <label>System Logo <span style="font-weight:400;color:var(--gray-400);font-size:12px;">(Optional)</span></label>
                    <div class="file-upload-zone" onclick="document.getElementById('editSystemLogo').click()">
                        <input type="file" id="editSystemLogo" name="logo" accept="image/*"
                               onchange="handleLogoUpload(this,'editLogoPreviewBox','editLogoThumb','editLogoFilename','editLogoFilesize')">
                        <div class="file-upload-trigger">
                            <svg class="file-upload-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                            <span class="file-upload-text">Choose new image file</span>
                            <span class="file-upload-hint">PNG, JPG, GIF</span>
                        </div>
                    </div>
                    <div class="logo-preview-box" id="editLogoPreviewBox">
                        <img class="logo-preview-thumb" id="editLogoThumb" src="" alt="Preview">
                        <div class="logo-preview-meta">
                            <span class="logo-preview-filename" id="editLogoFilename"></span>
                            <span class="logo-preview-filesize" id="editLogoFilesize"></span>
                        </div>
                        <button type="button" class="logo-preview-remove" onclick="removeLogo('editSystemLogo','editLogoPreviewBox')" title="Remove">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        </button>
                    </div>
                </div>

                <div class="modal-section-divider"></div>

                <div class="form-group">
                    <div class="field-label-row">
                        <label for="editSystemName">System Name <span style="color:var(--danger)">*</span></label>
                        <span class="char-counter" id="editNameCounter">0 / 100</span>
                    </div>
                    <input type="text" id="editSystemName" name="name" required
                           maxlength="100"
                           oninput="updateCharCounter(this,'editNameCounter',100)">
                </div>

                <div class="form-group">
                    <label for="editSystemCategory">Category <span style="color:var(--danger)">*</span></label>
                    <select id="editSystemCategory" name="category" required>
                        <option value="">Select a category...</option>
                        <?php foreach ($dbCategories as $dbCat): ?>
                        <option value="<?php echo htmlspecialchars($dbCat['name']); ?>">
                            <?php echo htmlspecialchars($dbCat['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="field-helper-row">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        Groups this system under the selected category on the dashboard
                    </div>
                </div>


 
                <div class="form-group">
                    <label>Network Type <span style="color:var(--danger)">*</span></label>
                    <div class="network-type-segment" id="editNetworkTypeSegment">
                        <button type="button" class="segment-btn" data-value="https" onclick="selectNetworkType('edit', 'https')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            HTTPS
                        </button>
                        <button type="button" class="segment-btn" data-value="http" onclick="selectNetworkType('edit', 'http')">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 9.9-1"></path><line x1="17" y1="4" x2="17" y2="7"></line></svg>
                            HTTP
                        </button>
                    </div>
                    <input type="hidden" id="editNetworkTypeValue" name="network_type" value="">
                    <div class="field-helper-row">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        Select the protocol used to access this system's domain
                    </div>
                </div>




                <div class="form-group">
                    <label for="editSystemDomain">Domain <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="editSystemDomain" name="domain" required>
                </div>

               
                <div class="form-group">
                    <label for="editSystemJapaneseDomain">Japanese Domain <span style="font-weight:400;color:var(--gray-400);font-size:12px;">(Optional)</span></label>
                    <input type="text" id="editSystemJapaneseDomain" name="japanese_domain" placeholder="e.g., glory.canteen.co.jp">
                    <div class="field-helper-row">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        Shown when Japanese translation is enabled
                    </div>
                </div>

                <div class="form-group">
                    <label for="editSystemBadgeUrl">Badge URL <span style="font-weight:400;color:var(--gray-400);font-size:12px;">(Optional)</span></label>
                    <input type="text" id="editSystemBadgeUrl" name="badge_url" placeholder="e.g., https://uptime.gpi.com/api/badge/33/status">
                    <div class="field-helper-row">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        Used for health checks instead of the domain URL
                    </div>
                </div>

                <div class="form-group">
                    <label for="editSystemStatus">Status <span style="color:var(--danger)">*</span></label>
                    <div class="status-select-wrap">
                        <span class="status-select-dot" id="editStatusDot"></span>
                        <select id="editSystemStatus" name="status" required onchange="updateStatusDot(this,'editStatusDot')">
                            <option value="online">Online</option>
                            <option value="offline">Offline</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="down">Down</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="editSystemContact">Contact Number</label>
                    <input type="text" id="editSystemContact" name="contact_number" placeholder="e.g., 09171234567">
                    <div class="field-helper-row">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        Displayed when system is Offline, Maintenance, or Down
                    </div>
                </div>

                <div class="form-group">
                    <label for="editSystemDescription">Description</label>
                    <textarea id="editSystemDescription" name="description"></textarea>
                </div>

                <div class="form-group">
                    <label for="editSystemJapaneseDescription">Japanese Description <span style="font-weight:400;color:var(--gray-400);font-size:12px;">(Optional)</span></label>
                    <textarea id="editSystemJapaneseDescription" name="japanese_description" placeholder="日本語での説明..."></textarea>
                    <div class="field-helper-row">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        Shown when Japanese translation is enabled
                    </div>
                </div>

                <div class="change-note-box">
                    <div class="change-note-label">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        Change Note
                        <span style="font-weight:400;color:var(--gray-400);font-size:12px;">(Optional)</span>
                    </div>
                    <textarea id="editChangeNote" name="change_note" placeholder="e.g., Scheduled maintenance, Server upgrade..." rows="2"></textarea>
                    <div class="change-note-hint">Add a reason when changing status</div>
                </div>

                <div class="health-check-row">
                    <div class="health-check-row-text">
                        <div class="health-check-row-label">Exclude from Health Check</div>
                        <div class="health-check-row-desc">Disable auto status monitoring for this system</div>
                    </div>
                    <label class="health-check-toggle">
                        <input type="checkbox" id="editExcludeHealthCheck" name="exclude_health_check" value="1">
                        <span class="toggle-slider"></span>
                    </label>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Update System</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- DELETE MODAL -->
<div id="deleteModal" class="delete-modal">
    <div class="delete-modal-content">
        <div class="delete-modal-header">
            <div class="delete-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
            </div>
            <div class="delete-modal-title">
                <h3>Delete System</h3>
                <p>This action cannot be undone</p>
            </div>
        </div>
        <div class="delete-modal-body">
            <div class="delete-system-name"><strong id="deleteSystemName"></strong></div>
            <div class="delete-warning">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                <p>Are you sure you want to delete this system? This will permanently remove all associated data including maintenance schedules.</p>
            </div>
        </div>
        <div class="delete-modal-footer">
            <button type="button" class="btn-delete-cancel" onclick="closeDeleteModal()">Cancel</button>
            <button type="button" class="btn-delete-confirm" onclick="confirmDelete()">Delete System</button>
        </div>
    </div>
</div>

<!-- MAINTENANCE MODALS -->
<?php if ($canScheduleMaintenance): ?>
<div id="maintenanceModal" class="modal">
    <div class="modal-content maintenance-modal-content">
        <div class="modal-header maintenance-modal-header">
            <div class="maintenance-modal-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                <h3 id="maintenanceModalTitle">Schedule Maintenance</h3>
            </div>
            <button class="close-modal" onclick="closeMaintenanceModal()">&times;</button>
        </div>
        <div class="maintenance-system-badge" id="maintenanceSystemBadge">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            <span id="maintenanceSystemName">System Name</span>
        </div>
        <div id="markOnlineBanner" style="display:none;">
            <div class="mark-online-banner">
                <div class="mark-online-banner-info">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <span>This system is currently <strong>under maintenance</strong>. Ready to bring it back online?</span>
                </div>
                <button type="button" class="btn-mark-online" onclick="markSystemOnline()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Mark as Online
                </button>
            </div>
        </div>
        <form id="maintenanceForm" onsubmit="saveMaintenanceSchedule(event)">
            <input type="hidden" id="maintenanceAction" name="action" value="create">
            <input type="hidden" id="maintenanceId" name="id" value="">
            <input type="hidden" id="maintenanceSystemId" name="system_id" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label for="maintenanceTitle">Maintenance Title *</label>
                    <input type="text" id="maintenanceTitle" name="title" required placeholder="e.g., Server upgrade, Database migration...">
                </div>
                <div class="maintenance-datetime-row">
                    <div class="form-group">
                        <label for="maintenanceStart">Start Date & Time *</label>
                        <input type="datetime-local" id="maintenanceStart" name="start_datetime" required>
                    </div>
                    <div class="form-group">
                        <label for="maintenanceEnd">End Date & Time *</label>
                        <input type="datetime-local" id="maintenanceEnd" name="end_datetime" required>
                    </div>
                </div>
                <div class="form-group maint-status-hidden">
                    <label for="maintenanceStatus">Status *</label>
                    <select id="maintenanceStatus" name="status" required onchange="onMaintenanceStatusChange(this.value)">
                        <option value="Scheduled">Scheduled</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Done">Done</option>
                    </select>
                </div>
                <div class="form-group" id="changeToOnlineGroup" style="display:none;">
                    <label>Change to Online status?</label>
                    <div class="online-radio-group">
                        <label class="online-radio-option online-radio-yes">
                            <input type="radio" name="change_to_online" value="yes">
                            <span class="online-radio-indicator"></span>
                            <span class="online-radio-content">
                                <span class="online-radio-title">Yes, the system is ready.</span>
                                <span class="online-radio-desc">System status will be changed to Online.</span>
                            </span>
                        </label>
                        <label class="online-radio-option online-radio-no">
                            <input type="radio" name="change_to_online" value="no" checked>
                            <span class="online-radio-indicator"></span>
                            <span class="online-radio-content">
                                <span class="online-radio-title">No</span>
                                <span class="online-radio-desc">System stays at Maintenance status.</span>
                            </span>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="maintenanceDescription">Description (Optional)</label>
                    <textarea id="maintenanceDescription" name="description" rows="3" placeholder="Describe what will be done during this maintenance window..."></textarea>
                </div>
                <div class="email-notify-section" id="emailNotifySection">
                    <div class="email-notify-toggle">
                        <div class="email-notify-toggle-label">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                            <span>Notify employees by email?</span>
                        </div>
                        <label class="email-toggle-switch">
                            <input type="checkbox" id="emailNotifyToggle" onchange="toggleEmailNotify('emailRecipientArea')">
                            <span class="email-toggle-slider"></span>
                        </label>
                    </div>
                    <div class="email-recipient-area" id="emailRecipientArea" style="display:none;">
                        <label class="email-recipient-label">Recipients</label>
                        <div class="email-tag-input-wrapper" id="emailTagWrapper">
                            <div class="email-tags" id="emailTags"></div>
                            <input type="text" id="emailRecipientInput" class="email-tag-text-input" placeholder="Type email and press Enter or comma..." autocomplete="off"
                                oninput="onEmailInput(this,'emailTagWrapper','emailTags','emailSuggestions')"
                                onkeydown="onEmailKeydown(event,this,'emailTagWrapper','emailTags','emailSuggestions')"
                                onblur="onEmailBlur(this,'emailTagWrapper','emailTags','emailSuggestions')">
                        </div>
                        <div class="email-suggestions" id="emailSuggestions" style="display:none;"></div>
                        <div class="email-recipient-hint">Press <kbd>Enter</kbd> or <kbd>,</kbd> to add · <kbd>Backspace</kbd> to remove last</div>
                    </div>
                </div>
                <div class="existing-schedules-section" id="existingSchedulesSection">
                    <div class="existing-schedules-header">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        Existing Schedules
                    </div>
                    <div id="existingSchedulesList" class="existing-schedules-list">
                        <div class="schedules-loading">Loading...</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeMaintenanceModal()">Cancel</button>
                <button type="submit" class="btn btn-maintenance-save" id="maintenanceSaveBtn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    Save Schedule
                </button>
            </div>
        </form>
    </div>
</div>

<!-- BULK MAINTENANCE MODAL -->
<div id="bulkMaintenanceModal" class="modal">
    <div class="modal-content bulk-modal-content">
        <div class="modal-header maintenance-modal-header">
            <div class="maintenance-modal-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="4" height="4" rx="1"></rect><rect x="3" y="13" width="4" height="4" rx="1"></rect><line x1="10" y1="7" x2="21" y2="7"></line><line x1="10" y1="15" x2="21" y2="15"></line></svg>
                <h3>Bulk Schedule Maintenance</h3>
            </div>
            <button class="close-modal" onclick="requestCloseBulkModal()">&times;</button>
        </div>
        <div class="bulk-modal-systems-bar" id="bulkModalSystemsBar">
            <div class="bulk-modal-systems-label">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                Applying to <strong id="bulkModalCount">0</strong> selected system(s):
            </div>
            <div class="bulk-modal-systems-list" id="bulkModalSystemsList"></div>
        </div>
        <div id="bulkConflictBanner" class="bulk-conflict-banner" style="display:none;">
            <div class="bulk-conflict-banner-inner">
                <div class="bulk-conflict-icon">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                </div>
                <div class="bulk-conflict-text">
                    <p id="bulkConflictTitle" class="bulk-conflict-banner-title"></p>
                    <p id="bulkConflictDetail" class="bulk-conflict-detail"></p>
                </div>
            </div>
        </div>
        <form id="bulkMaintenanceForm">
            <div class="modal-body">
                <div class="form-group">
                    <label for="bulkTitle">Maintenance Title *</label>
                    <input type="text" id="bulkTitle" name="title" required placeholder="e.g., Server upgrade, Database migration...">
                </div>
                <div class="maintenance-datetime-row">
                    <div class="form-group">
                        <label for="bulkStart">Start Date & Time *</label>
                        <input type="datetime-local" id="bulkStart" name="start_datetime" required>
                    </div>
                    <div class="form-group">
                        <label for="bulkEnd">End Date & Time *</label>
                        <input type="datetime-local" id="bulkEnd" name="end_datetime" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="bulkStatus">Status *</label>
                    <select id="bulkStatus" name="status" required>
                        <option value="Scheduled">Scheduled</option>
                        <option value="In Progress">In Progress</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="bulkDescription">Description (Optional)</label>
                    <textarea id="bulkDescription" name="description" rows="3" placeholder="Describe what will be done during this maintenance window..."></textarea>
                </div>
                <div class="email-notify-section" id="bulkEmailNotifySection">
                    <div class="email-notify-toggle">
                        <div class="email-notify-toggle-label">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                            <span>Notify employees by email?</span>
                        </div>
                        <label class="email-toggle-switch">
                            <input type="checkbox" id="bulkEmailNotifyToggle" onchange="toggleEmailNotify('bulkEmailRecipientArea')">
                            <span class="email-toggle-slider"></span>
                        </label>
                    </div>
                    <div class="email-recipient-area" id="bulkEmailRecipientArea" style="display:none;">
                        <label class="email-recipient-label">Recipients</label>
                        <div class="email-tag-input-wrapper" id="bulkEmailTagWrapper">
                            <div class="email-tags" id="bulkEmailTags"></div>
                            <input type="text" id="bulkEmailRecipientInput" class="email-tag-text-input" placeholder="Type email and press Enter or comma..." autocomplete="off"
                                oninput="onEmailInput(this,'bulkEmailTagWrapper','bulkEmailTags','bulkEmailSuggestions')"
                                onkeydown="onEmailKeydown(event,this,'bulkEmailTagWrapper','bulkEmailTags','bulkEmailSuggestions')"
                                onblur="onEmailBlur(this,'bulkEmailTagWrapper','bulkEmailTags','bulkEmailSuggestions')">
                        </div>
                        <div class="email-suggestions" id="bulkEmailSuggestions" style="display:none;"></div>
                        <div class="email-recipient-hint">Press <kbd>Enter</kbd> or <kbd>,</kbd> to add · <kbd>Backspace</kbd> to remove last</div>
                    </div>
                </div>
                <div class="bulk-results" id="bulkResults" style="display:none;">
                    <div class="bulk-results-header">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                        Save Results
                    </div>
                    <div id="bulkResultsList" class="bulk-results-list"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="requestCloseBulkModal()">Cancel</button>
                <button type="button" class="btn btn-maintenance-save" id="bulkSaveBtn" onclick="saveBulkMaintenance(event)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    Save for All Systems
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Bulk Cancel Confirm -->
<div id="bulkCancelConfirm" class="bulk-confirm-overlay" style="display:none;">
    <div class="bulk-confirm-box">
        <div class="bulk-confirm-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
        </div>
        <h4 class="bulk-confirm-title">Discard changes?</h4>
        <p class="bulk-confirm-msg">You've already filled in the maintenance form. Closing will lose your input.</p>
        <div class="bulk-confirm-actions">
            <button class="btn btn-secondary" onclick="dismissBulkConfirm()">Keep Editing</button>
            <button class="btn btn-danger" onclick="confirmCloseBulkModal()">Discard & Close</button>
        </div>
    </div>
</div>

<div id="maintenanceSidePanel" class="maintenance-side-panel">
    <div class="side-panel-overlay"></div>
    <div class="side-panel-content">
        <div class="side-panel-header">
            <div class="side-panel-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                <div>
                    <h3>Scheduled Maintenance</h3>
                    <span id="sidePanelDate" class="side-panel-date-label"></span>
                </div>
            </div>
            <button class="side-panel-close" onclick="closeSidePanel()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        <div class="side-panel-body" id="sidePanelBody"></div>
    </div>
</div>

<div id="deleteMaintenanceModal" class="delete-modal">
    <div class="delete-modal-content">
        <div class="delete-modal-header">
            <div class="delete-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
            </div>
            <div class="delete-modal-title"><h3>Delete Schedule</h3><p>This action cannot be undone</p></div>
        </div>
        <div class="delete-modal-body">
            <div class="delete-system-name"><strong id="deleteMaintenanceName"></strong></div>
            <div class="delete-warning">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                <p>Are you sure you want to delete this maintenance schedule?</p>
            </div>
        </div>
        <div class="delete-modal-footer">
            <button type="button" class="btn-delete-cancel" onclick="closeDeleteMaintenanceModal()">Cancel</button>
            <button type="button" class="btn-delete-confirm" onclick="confirmDeleteMaintenance()">Delete Schedule</button>
        </div>
    </div>
</div>

<div id="scheduleDetailModal" class="modal">
    <div class="modal-content sdm-modal-content">
        <div class="modal-header maintenance-modal-header">
            <div class="maintenance-modal-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                <h3 id="scheduleDetailTitle">Schedule Details</h3>
            </div>
            <button class="close-modal" onclick="closeScheduleDetailModal()">&times;</button>
        </div>
        <div class="modal-body" id="scheduleDetailBody"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeScheduleDetailModal()">Close</button>
        </div>
    </div>
</div>

<!-- BULK MARK AS ONLINE MODAL -->
<div id="bulkMarkOnlineModal" class="modal">
    <div class="modal-content bmo-modal-content">
        <div class="modal-header maintenance-modal-header">
            <div class="maintenance-modal-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <h3>Mark Systems as Online</h3>
            </div>
            <button class="close-modal" onclick="closeBulkMarkOnlineModal()">&times;</button>
        </div>
        <div class="bmo-header-note">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            <span>Select the systems to bring back online. Their maintenance schedule will be marked as <strong>Done</strong> and removed from the calendar.</span>
        </div>
        <div class="modal-body" style="padding:0;">
            <div id="bulkMarkOnlineList" class="bmo-list"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeBulkMarkOnlineModal()">Cancel</button>
            <button type="button" class="btn btn-bmo-confirm" id="bmoConfirmBtn" onclick="confirmBulkMarkOnline()" disabled>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                Mark <span id="bmoConfirmCount">0</span> as Online
            </button>
        </div>
    </div>
</div>

<!-- CATEGORIES MANAGEMENT MODAL -->
<div id="categoriesModal"
     class="modal"
     data-super-admin="<?php echo isSuperAdmin() ? '1' : '0'; ?>">
    <div class="modal-content categories-modal-content">
        <div class="modal-header">
            <div style="display:flex;align-items:center;gap:10px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                <h3>Categories Management</h3>
            </div>
            <button class="close-modal" onclick="closeCategoriesModal()">&times;</button>
        </div>
        <div class="modal-body" style="padding:0;">
            <div class="cat-info-bar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span>Drag rows to reorder. Changes apply to the dashboard immediately.</span>
            </div>
            <div id="catList" class="cat-list"></div>
            <div id="catAddSection" class="cat-add-section" style="display:none;">
                <div class="cat-add-row">
                    <input type="text"
                           id="catNewNameInput"
                           class="cat-add-input"
                           placeholder="New category name..."
                           maxlength="100"
                           onkeydown="onCatAddKeydown(event)">
                    <button id="catAddBtn" class="cat-btn-add-confirm" onclick="addNewCategory()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        Add
                    </button>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeCategoriesModal()">Close</button>
        </div>
    </div>
</div>

<!-- CATEGORY DELETE CONFIRMATION MODAL -->
<div id="catDeleteModal" class="delete-modal">
    <div class="delete-modal-content">
        <div class="delete-modal-header">
            <div class="delete-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            </div>
            <div class="delete-modal-title">
                <h3>Delete Category</h3>
                <p>This action cannot be undone</p>
            </div>
        </div>
        <div class="delete-modal-body">
            <div class="delete-system-name"><strong id="catDeleteName"></strong></div>
            <div class="delete-warning">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                <p id="catDeleteSystemNote"></p>
            </div>
            <div id="catReassignSection" class="cat-reassign-section" style="display:none;">
                <label for="catReassignSelect">Reassign systems to:</label>
                <select id="catReassignSelect" class="cat-reassign-select"></select>
            </div>
        </div>
        <div class="delete-modal-footer">
            <button type="button" class="btn-delete-cancel" onclick="closeCatDeleteModal()">Cancel</button>
            <button type="button" class="btn-delete-confirm" id="catDeleteConfirmBtn" onclick="confirmDeleteCategory()">Delete</button>
        </div>
    </div>
</div>

<script src="../assets/js/jquery-3.7.1.min.js"></script>
<script src="../assets/js/main.js"></script>
<script src="../assets/js/maintenance.js"></script>
<script src="../assets/js/health_check.js"></script>
<script src="../assets/js/categories.js"></script>
</body>
</html>