<?php
//G-Portal Public Viewer Page 

require_once '../config/database.php';

$conn = getDBConnection();

// ── Fetch categories 
$catResult  = $conn->query("SELECT name, sort_order FROM categories ORDER BY sort_order ASC");
$dbCategories = [];
while ($row = $catResult->fetch_assoc()) {
    $dbCategories[] = $row;
}

// category order 
$categoryOrder = [];
foreach ($dbCategories as $i => $cat) {
    $categoryOrder[$cat['name']] = $cat['sort_order'] ?? ($i + 1);
}

// Fetch all systems 
$result = $conn->query("SELECT *, COALESCE(japanese_domain, '') as japanese_domain FROM systems ORDER BY created_at DESC");
$systems = [];
while ($row = $result->fetch_assoc()) { $systems[] = $row; }
$conn->close();

$statusPriority = ['online' => 1, 'maintenance' => 2, 'down' => 3, 'offline' => 4, 'archived' => 5];

usort($systems, function($a, $b) use ($categoryOrder, $statusPriority) {
    $catA = $categoryOrder[$a['category'] ?? ''] ?? 99;
    $catB = $categoryOrder[$b['category'] ?? ''] ?? 99;
    if ($catA !== $catB) return $catA - $catB;
    $pA = $statusPriority[$a['status'] ?? 'online'] ?? 999;
    $pB = $statusPriority[$b['status'] ?? 'online'] ?? 999;
    if ($pA !== $pB) return $pA - $pB;
    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
});

// Group by category
$groupedSystems = [];
foreach ($systems as $system) {
    $cat = $system['category'] ?? ($dbCategories[0]['name'] ?? 'Direct');
    $groupedSystems[$cat][] = $system;
}

$statusLabels = [
    'online' => 'Online', 'offline' => 'Offline',
    'maintenance' => 'Maintenance', 'down' => 'Down', 'archived' => 'Archived'
];

$totalSystems = count(array_filter($systems, fn($s) => ($s['status'] ?? '') !== 'archived'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>G-Portal - System Directory</title>
    <link rel="stylesheet" href="../assets/css/viewer.css">
    <link rel="stylesheet" href="../assets/css/notifications.css">
</head>
<body class="viewer-page">
    <header class="viewer-header">
        <div class="header-content">
            <h1>G-Portal</h1>
            <div class="header-right">
                <div class="jp-lang-switcher" id="jpLangSwitcher">
                    <button class="jp-lang-option" id="jpLangEng" onclick="setLanguage('en')">
                        Eng
                    </button>
                    <div class="jp-lang-divider"></div>
                    <button class="jp-lang-option" id="jpLangJp" onclick="setLanguage('jp')">
                        日本語
                    </button>
                </div>

                <div class="notification-bell">
                    <button class="bell-button" onclick="toggleNotifications()" title="Notifications">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <span id="notificationBadge" class="notification-badge" style="display: none;"></span>
                    </button>
                    <div id="notificationDropdown" class="notification-dropdown">
                        <div class="notification-header">
                            <h3>Recent Updates</h3>
                            <div class="notification-header-actions">
                                <button id="markAllReadBtn" onclick="markAllRead()">Mark all read</button>
                                <span id="notificationCount" class="notification-count">0</span>
                            </div>
                        </div>
                        <div id="notificationList" class="notification-list">
                            <div class="notification-loading">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle></svg>
                                <p>Loading...</p>
                            </div>
                        </div>
                    </div>
                </div>
                <a href="../index.php?show=login" class="login-button-slide" id="loginButton">Admin Login</a>
                <button class="arrow-toggle" id="arrowToggle" onclick="toggleLogin()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
            </div>
        </div>
    </header>

    <main class="viewer-main">
        <div class="viewer-container">
            <div class="page-header">
                <div class="page-title-section">
                    <h2 class="page-title">System Directory</h2>
                    <p class="page-subtitle">
                        Browse all available systems
                        <span class="system-count" id="systemCount">(<?php echo $totalSystems; ?> total)</span>
                    </p>
                </div>

                <div class="search-section">
                    <div class="search-wrapper-viewer">

                        <!-- Search box -->
                        <input type="text" id="viewerSearchBox" class="search-box-viewer"
                               placeholder="Search systems..." onkeyup="searchSystemsViewer()" aria-label="Search systems">

                        <!-- Status Filter -->
                        <div class="filter-container-viewer">
                            <button type="button" class="btn-filter-viewer" id="statusFilterBtn" onclick="toggleFilterViewer(event)" aria-label="Filter by status">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="4" y1="6" x2="20" y2="6"></line>
                                    <line x1="4" y1="12" x2="20" y2="12"></line>
                                    <line x1="4" y1="18" x2="20" y2="18"></line>
                                    <circle cx="9" cy="6" r="2.5" fill="currentColor" stroke="none"></circle>
                                    <circle cx="15" cy="12" r="2.5" fill="currentColor" stroke="none"></circle>
                                    <circle cx="9" cy="18" r="2.5" fill="currentColor" stroke="none"></circle>
                                </svg>
                                Status Filter
                            </button>
                            <div class="filter-dropdown-viewer" id="statusDropdownViewer">
                                <button type="button" onclick="filterSystemsViewer('all')" class="filter-item active" data-filter="all"><span class="filter-dot filter-all"></span>All Systems</button>
                                <button type="button" onclick="filterSystemsViewer('online')" class="filter-item" data-filter="online"><span class="filter-dot filter-online"></span>Online</button>
                                <button type="button" onclick="filterSystemsViewer('maintenance')" class="filter-item" data-filter="maintenance"><span class="filter-dot filter-maintenance"></span>Maintenance</button>
                                <button type="button" onclick="filterSystemsViewer('down')" class="filter-item" data-filter="down"><span class="filter-dot filter-down"></span>Down</button>
                                <button type="button" onclick="filterSystemsViewer('offline')" class="filter-item" data-filter="offline"><span class="filter-dot filter-offline"></span>Offline</button>
                                <button type="button" onclick="filterSystemsViewer('archived')" class="filter-item" data-filter="archived"><span class="filter-dot filter-archived"></span>Archived</button>
                            </div>
                        </div>

                        <!-- Category Filter -->
                        <div class="filter-container-viewer">
                            <button type="button" class="btn-filter-viewer" id="categoryFilterBtn" onclick="toggleCategoryFilterViewer(event)" aria-label="Filter by category">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="4" y1="6" x2="20" y2="6"></line>
                                    <line x1="4" y1="12" x2="20" y2="12"></line>
                                    <line x1="4" y1="18" x2="20" y2="18"></line>
                                    <circle cx="9" cy="6" r="2.5" fill="currentColor" stroke="none"></circle>
                                    <circle cx="15" cy="12" r="2.5" fill="currentColor" stroke="none"></circle>
                                    <circle cx="9" cy="18" r="2.5" fill="currentColor" stroke="none"></circle>
                                </svg>
                                Category
                            </button>
                            <div class="filter-dropdown-viewer" id="categoryDropdownViewer">
                                <button type="button" onclick="filterCategoryViewer('all')" class="filter-item active" data-cat="all">
                                    <span class="filter-dot" style="background:var(--primary-light);"></span>All Categories
                                </button>
                                <?php foreach ($dbCategories as $cat): ?>
                                <button type="button" onclick="filterCategoryViewer('<?php echo htmlspecialchars($cat['name']); ?>')" class="filter-item" data-cat="<?php echo htmlspecialchars($cat['name']); ?>">
                                    <span class="filter-dot" style="background:var(--primary-color);"></span>
                                    <?php echo htmlspecialchars($cat['name']); ?> Systems
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Maintenance Schedule btn -->
                        <div class="maint-btn-wrapper" id="maintBtnWrapper">
                            <a href="viewer_maintenance.php" class="btn-maintenance-viewer" id="maintBtn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                Maintenance Schedule
                            </a>
                            <div class="maint-popover" id="maintPopover" style="display: none;">
                                <div class="maint-popover-inner" id="maintPopoverInner"></div>
                                <span class="maint-popover-tail maint-popover-tail--lg"></span>
                                <span class="maint-popover-tail maint-popover-tail--sm"></span>
                            </div>
                        </div>

                        <a href="https://uptime.gpi.com" target="_blank" rel="noopener noreferrer" class="btn-status-logs-viewer" title="View system status change history">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                            Status Logs
                        </a>

                    </div>

                    <div class="active-filters-container" id="activeFilters" style="display: none;">
                        <div class="active-filters-content">
                            <span class="filter-results-text" id="filterResultsText">Showing all systems</span>
                            <button class="btn-clear-filters-viewer" onclick="clearAllFilters()" id="clearFiltersBtn" style="display: none;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                                Clear Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="viewerSystemsContainer">
                <?php if (empty($systems)): ?>
                    <div class="systems-grid-viewer">
                        <div class="empty-state-viewer">
                            <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="9" x2="15" y2="15"></line><line x1="15" y1="9" x2="9" y2="15"></line></svg>
                            <h3>No Systems Available</h3>
                            <p>There are currently no systems to display</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($dbCategories as $dbCat):
                        $categoryName = $dbCat['name'];
                        if (empty($groupedSystems[$categoryName])) continue;
                    ?>
                    <div class="viewer-category-group" data-category="<?php echo htmlspecialchars($categoryName); ?>">
                        <h3 class="viewer-category-title"><?php echo htmlspecialchars($categoryName); ?> Systems</h3>
                        <div class="systems-grid-viewer">
                        <?php foreach ($groupedSystems[$categoryName] as $system): ?>
                            <?php
                            $status        = $system['status'] ?? 'online';
                            $statusLabel   = $statusLabels[$status] ?? 'Online';
                            $contactNumber = $system['contact_number'] ?? '123';
                            ?>
                            <div class="system-card-viewer"
                                 data-system-id="<?php echo $system['id']; ?>"
                                 data-status="<?php echo htmlspecialchars($status); ?>"
                                 data-category="<?php echo htmlspecialchars($system['category'] ?? $dbCategories[0]['name'] ?? 'Direct'); ?>"
                                 data-contact-number="<?php echo htmlspecialchars($contactNumber); ?>"
                                 data-japanese-domain="<?php echo htmlspecialchars($system['japanese_domain'] ?? ''); ?>"
                                 data-description="<?php echo htmlspecialchars($system['description'] ?? ''); ?>"
                                 data-japanese-description="<?php echo htmlspecialchars($system['japanese_description'] ?? ''); ?>"
                                 tabindex="0" role="article"
                                 onclick="openDomainViewer(this)"
                                 style="cursor: pointer;">

                                <a href="#" class="logo-link-viewer" aria-label="Open <?php echo htmlspecialchars($system['name']); ?>" onclick="event.stopPropagation();">
                                    <?php if (!empty($system['logo']) && file_exists('../' . $system['logo'])): ?>
                                        <img src="../<?php echo htmlspecialchars($system['logo']); ?>" alt="<?php echo htmlspecialchars($system['name']); ?> logo" class="system-logo-viewer">
                                    <?php else: ?>
                                        <div class="system-logo-placeholder-viewer">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                                        </div>
                                    <?php endif; ?>
                                </a>

                                <div class="status-badge-viewer status-<?php echo htmlspecialchars($status); ?>">
                                    <span class="status-indicator-viewer"></span>
                                    <?php echo $statusLabel; ?>
                                </div>

                                <h3 class="system-name-viewer">
                                    <?php echo htmlspecialchars($system['name']); ?>
                                </h3>
                                <span class="system-domain-viewer"><?php echo htmlspecialchars($system['domain']); ?></span>

                                <?php if (!empty($system['description'])): ?>
                                    <p class="system-description-viewer"><?php echo htmlspecialchars($system['description']); ?></p>
                                <?php endif; ?>

                                <?php if (in_array($status, ['maintenance', 'offline', 'down'])): ?>
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
        </div>
    </main>

    <footer class="viewer-footer">
        <p>&copy; <?php echo date('Y'); ?> G-Portal. All rights reserved.</p>
    </footer>

    <script>
        const TOTAL_SYSTEMS = <?php echo $totalSystems; ?>;
        const DB_CATEGORIES = <?php echo json_encode(array_column($dbCategories, 'name')); ?>;

        function toggleLogin() {
            const loginButton = document.getElementById('loginButton');
            const arrowToggle = document.getElementById('arrowToggle');
            loginButton.classList.toggle('show');
            arrowToggle.classList.toggle('active');
        }
    </script>
    <script src="../assets/js/jquery-3.7.1.min.js"></script>
    <script src="../assets/js/viewer.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script src="../assets/js/health_check.js"></script>
</body>
</html>