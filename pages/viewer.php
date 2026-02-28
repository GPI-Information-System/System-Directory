<?php
/**
 * G-Portal Public Viewer Page
 * Public-facing system directory for employees
 * 
 * Access: No authentication required
 */

require_once '../config/database.php';

$conn = getDBConnection();
$result = $conn->query("SELECT * FROM systems ORDER BY created_at DESC");
$systems = [];
while ($row = $result->fetch_assoc()) {
    $systems[] = $row;
}
$conn->close();

$statusPriority = [
    'online' => 1,
    'maintenance' => 2,
    'down' => 3,
    'offline' => 4,
    'archived' => 5
];

usort($systems, function($a, $b) use ($statusPriority) {
    $priorityA = $statusPriority[$a['status'] ?? 'online'] ?? 999;
    $priorityB = $statusPriority[$b['status'] ?? 'online'] ?? 999;
    return $priorityA - $priorityB;
});

$statusLabels = [
    'online'      => 'Online',
    'offline'     => 'Offline',
    'maintenance' => 'Maintenance',
    'down'        => 'Down',
    'archived'    => 'Archived'
];

$totalSystems = count($systems);
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
<body>
    <header class="viewer-header">
        <div class="header-content">
            <h1>G-Portal</h1>

            <div class="header-right">
                <!-- Notification Bell -->
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
                            <span id="notificationCount" class="notification-count">0</span>
                        </div>
                        <div id="notificationList" class="notification-list">
                            <div class="notification-loading">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                </svg>
                                <p>Loading...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Login button: hidden by default, slides in on toggle -->
                <a href="../index.php?show=login" class="login-button-slide" id="loginButton">
                    Admin Login
                </a>

                <!-- Arrow toggle: stationary, only rotates -->
                <button class="arrow-toggle" id="arrowToggle" onclick="toggleLogin()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
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
                        <input
                            type="text"
                            id="viewerSearchBox"
                            class="search-box-viewer"
                            placeholder="Search systems..."
                            onkeyup="searchSystemsViewer()"
                            aria-label="Search systems"
                        >

                        <div class="filter-container-viewer">
                            <button type="button" class="btn-filter-viewer" onclick="toggleFilterViewer(event)" aria-label="Filter by status">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                                </svg>
                                Status Filter
                            </button>

                            <div class="filter-dropdown-viewer">
                                <button type="button" onclick="filterSystemsViewer('all')" class="filter-item active" data-filter="all">
                                    <span class="filter-dot filter-all"></span>All Systems
                                </button>
                                <button type="button" onclick="filterSystemsViewer('online')" class="filter-item" data-filter="online">
                                    <span class="filter-dot filter-online"></span>Online
                                </button>
                                <button type="button" onclick="filterSystemsViewer('maintenance')" class="filter-item" data-filter="maintenance">
                                    <span class="filter-dot filter-maintenance"></span>Maintenance
                                </button>
                                <button type="button" onclick="filterSystemsViewer('down')" class="filter-item" data-filter="down">
                                    <span class="filter-dot filter-down"></span>Down
                                </button>
                                <button type="button" onclick="filterSystemsViewer('offline')" class="filter-item" data-filter="offline">
                                    <span class="filter-dot filter-offline"></span>Offline
                                </button>
                                <button type="button" onclick="filterSystemsViewer('archived')" class="filter-item" data-filter="archived">
                                    <span class="filter-dot filter-archived"></span>Archived
                                </button>
                            </div>
                        </div>

                        <!-- Maintenance Schedule Button — with calendar icon -->
                        <a href="viewer_maintenance.php" class="btn-maintenance-viewer">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            Maintenance Schedule
                        </a>
                    </div>

                    <div class="active-filters-container" id="activeFilters" style="display: none;">
                        <div class="active-filters-content">
                            <span class="filter-results-text" id="filterResultsText">Showing all systems</span>
                            <button class="btn-clear-filters" onclick="clearAllFilters()" id="clearFiltersBtn" style="display: none;">
                                Clear filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="systems-grid-viewer">
                <?php if (empty($systems)): ?>
                    <div class="empty-state-viewer">
                        <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                        </svg>
                        <h3>No Systems Available</h3>
                        <p>There are currently no systems to display</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($systems as $system): ?>
                        <?php
                        $status        = $system['status'] ?? 'online';
                        $statusLabel   = $statusLabels[$status] ?? 'Online';
                        $contactNumber = $system['contact_number'] ?? '123';
                        ?>
                        <div class="system-card-viewer"
                             data-system-id="<?php echo $system['id']; ?>"
                             data-status="<?php echo htmlspecialchars($status); ?>"
                             tabindex="0"
                             role="article"
                             onclick="openDomainViewer('<?php echo htmlspecialchars($system['domain']); ?>')"
                             style="cursor: pointer;">

                            <a href="#" class="logo-link-viewer" aria-label="Open <?php echo htmlspecialchars($system['name']); ?>" onclick="event.stopPropagation();">
                                <?php if (!empty($system['logo']) && file_exists('../' . $system['logo'])): ?>
                                    <img src="../<?php echo htmlspecialchars($system['logo']); ?>"
                                         alt="<?php echo htmlspecialchars($system['name']); ?> logo"
                                         class="system-logo-viewer">
                                <?php else: ?>
                                    <div class="system-logo-placeholder-viewer">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="3" width="7" height="7"></rect>
                                            <rect x="14" y="3" width="7" height="7"></rect>
                                            <rect x="14" y="14" width="7" height="7"></rect>
                                            <rect x="3" y="14" width="7" height="7"></rect>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </a>

                            <div class="status-badge-viewer status-<?php echo htmlspecialchars($status); ?>">
                                <span class="status-indicator-viewer"></span>
                                <?php echo $statusLabel; ?>
                            </div>

                            <h3 class="system-name-viewer"><?php echo htmlspecialchars($system['name']); ?></h3>

                            <span class="system-domain-viewer"><?php echo htmlspecialchars($system['domain']); ?></span>

                            <?php if (!empty($system['updated_at'])): ?>
                                <div class="last-updated-viewer">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <polyline points="12 6 12 12 16 14"></polyline>
                                    </svg>
                                    Updated: <?php echo date('M d, Y', strtotime($system['updated_at'])); ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($system['description'])): ?>
                                <p class="system-description-viewer"><?php echo htmlspecialchars($system['description']); ?></p>
                            <?php endif; ?>

                            <?php if (in_array($status, ['maintenance', 'offline', 'down'])): ?>
                                <div class="system-contact-message">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                    </svg>
                                    Contact <span class="contact-number"><?php echo htmlspecialchars($contactNumber); ?></span> for assistance
                                </div>
                            <?php endif; ?>
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

        function toggleLogin() {
            const loginButton = document.getElementById('loginButton');
            const arrowToggle = document.getElementById('arrowToggle');
            loginButton.classList.toggle('show');
            arrowToggle.classList.toggle('active');
        }
    </script>
    <script src="../assets/js/viewer.js"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>