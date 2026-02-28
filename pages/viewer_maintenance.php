<?php
/**
 * G-Portal - Viewer Maintenance Schedule Page
 * Public-facing maintenance history board for employees
 */

require_once '../config/database.php';

date_default_timezone_set('Asia/Manila');

$conn   = getDBConnection();
$result = $conn->query("
    SELECT
        ms.id,
        ms.title,
        ms.description,
        ms.start_datetime,
        ms.end_datetime,
        ms.status,
        ms.exceeded_duration,
        ms.updated_at,
        s.name           AS system_name,
        s.logo           AS system_logo,
        s.domain         AS system_domain,
        s.contact_number AS system_contact
    FROM maintenance_schedules ms
    JOIN systems s ON ms.system_id = s.id
    WHERE ms.deleted_from_calendar = 0
       OR ms.status = 'Done'
    ORDER BY
        FIELD(ms.status, 'In Progress', 'Scheduled', 'Done'),
        ms.start_datetime DESC
");

$schedules = [];
while ($row = $result->fetch_assoc()) { $schedules[] = $row; }
$conn->close();

$countActive    = count(array_filter($schedules, fn($s) => $s['status'] === 'In Progress'));
$countScheduled = count(array_filter($schedules, fn($s) => $s['status'] === 'Scheduled'));
$countDone      = count(array_filter($schedules, fn($s) => $s['status'] === 'Done'));
$totalCount     = count($schedules);
$hasActive      = $countActive > 0;

$activeSystems = array_values(array_filter($schedules, fn($s) => $s['status'] === 'In Progress'));

function formatExceededDuration($seconds) {
    if (!$seconds || $seconds <= 0) return null;
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    if ($h > 0 && $m > 0) return "+{$h}h {$m}m over schedule";
    if ($h > 0)            return "+{$h}h over schedule";
    return "+{$m}m over schedule";
}

function fmtDt($dt) { return date('M d, Y · g:i A', strtotime($dt)); }
function isodt($dt) { return date('c', strtotime($dt)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>G-Portal — Maintenance Schedule</title>
    <link rel="stylesheet" href="../assets/css/viewer.css">
    <link rel="stylesheet" href="../assets/css/viewer_maintenance.css">
</head>
<body>

    <header class="viewer-header">
        <div class="header-content">
            <h1>G-Portal</h1>
            <div class="header-right">
                <a href="viewer.php" class="btn-back-viewer">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                    System Directory
                </a>
            </div>
        </div>
    </header>

    <main class="viewer-main">
        <div class="viewer-container">

            <!-- ACTIVE MAINTENANCE ALERT BANNER -->
            <?php if ($hasActive): ?>
            <div class="vm-alert-banner">
                <div class="vm-alert-inner" id="vmAlertToggle" onclick="vmToggleAlertDropdown()" role="button" tabindex="0" aria-expanded="false" aria-controls="vmAlertDropdown">
                    <div class="vm-alert-left">
                        <span class="vm-alert-pulse"></span>
                        <div class="vm-alert-text">
                            <strong>Active Maintenance In Progress</strong>
                            <span>
                                Systems are currently under maintenance.
                                <span class="vm-alert-view-here">
                                    View here
                                    <svg class="vm-alert-chevron" id="vmAlertChevron" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="6 9 12 15 18 9"></polyline>
                                    </svg>
                                </span>
                            </span>
                        </div>
                    </div>
                    <div class="vm-alert-badge">
                        <?php echo $countActive; ?> system<?php echo $countActive > 1 ? 's' : ''; ?> affected
                    </div>
                </div>

                <div class="vm-alert-dropdown" id="vmAlertDropdown" aria-hidden="true">
                    <div class="vm-alert-dropdown-inner">
                        <?php foreach ($activeSystems as $sys):
                            $logoPath = !empty($sys['system_logo']) ? '../' . $sys['system_logo'] : null;
                            $hasLogo  = $logoPath && file_exists('../' . $sys['system_logo']);
                        ?>
                        <div class="vm-alert-system-row">
                            <?php if ($hasLogo): ?>
                                <img src="<?php echo htmlspecialchars($logoPath); ?>"
                                     alt="<?php echo htmlspecialchars($sys['system_name']); ?>"
                                     class="vm-alert-system-logo">
                            <?php else: ?>
                                <div class="vm-alert-system-logo-placeholder">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="3" width="7" height="7"></rect>
                                        <rect x="14" y="3" width="7" height="7"></rect>
                                        <rect x="14" y="14" width="7" height="7"></rect>
                                        <rect x="3" y="14" width="7" height="7"></rect>
                                    </svg>
                                </div>
                            <?php endif; ?>
                            <div class="vm-alert-system-info">
                                <span class="vm-alert-system-name"><?php echo htmlspecialchars($sys['system_name']); ?></span>
                                <span class="vm-alert-system-domain"><?php echo htmlspecialchars($sys['system_domain']); ?></span>
                            </div>
                            <div class="vm-alert-system-live">
                                <span class="vm-live-dot-small"></span>
                                Live
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- PAGE HEADER -->
            <div class="vm-page-header">
                <div class="vm-title-block">
                    <div class="vm-title-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                    </div>
                    <div>
                        <h2 class="vm-title">Maintenance Schedule</h2>
                        <p class="vm-subtitle">Scheduled, ongoing, and completed maintenance windows</p>
                    </div>
                </div>

                <div class="vm-header-right">
                    <div class="vm-summary-pills">
                        <?php if ($countActive > 0): ?>
                        <div class="vm-pill vm-pill-inprogress">
                            <span class="vm-pill-dot"></span>
                            <?php echo $countActive; ?> In Progress
                        </div>
                        <?php endif; ?>
                        <?php if ($countScheduled > 0): ?>
                        <div class="vm-pill vm-pill-scheduled">
                            <span class="vm-pill-dot"></span>
                            <?php echo $countScheduled; ?> Scheduled
                        </div>
                        <?php endif; ?>
                        <div class="vm-pill vm-pill-done">
                            <span class="vm-pill-dot"></span>
                            <?php echo $countDone; ?> Completed
                        </div>
                    </div>

                    <div class="vm-refresh-row">
                        <span class="vm-last-updated" id="vmLastUpdated">
                            Updated <?php echo date('g:i A'); ?>
                        </span>
                        <div class="vm-refresh-ring" id="vmRefreshRing" title="Auto-refreshes every 60 seconds">
                            <svg class="vm-refresh-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="23 4 23 10 17 10"></polyline>
                                <polyline points="1 20 1 14 7 14"></polyline>
                                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════
                 STICKY FILTER BAR (Feature 5)
                 ══════════════════════════════════ -->
            <div class="vm-sticky-controls" id="vmStickyControls">
                <div class="vm-controls">
                    <div class="vm-search-wrap">
                        <svg class="vm-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                        <input type="text" id="vmSearch" class="vm-search"
                               placeholder="Search by system or title..."
                               oninput="vmFilter()"
                               aria-label="Search maintenance schedules">
                    </div>
                    <!-- Desktop filter buttons -->
                    <div class="vm-filter-group">
                        <button class="vm-filter-btn active" data-status="all"         onclick="vmSetFilter('all', this)">All</button>
                        <button class="vm-filter-btn"        data-status="In Progress" onclick="vmSetFilter('In Progress', this)">
                            <span class="vm-btn-dot inprogress"></span>In Progress
                        </button>
                        <button class="vm-filter-btn"        data-status="Scheduled"   onclick="vmSetFilter('Scheduled', this)">
                            <span class="vm-btn-dot scheduled"></span>Scheduled
                        </button>
                        <button class="vm-filter-btn"        data-status="Done"        onclick="vmSetFilter('Done', this)">
                            <span class="vm-btn-dot done"></span>Done
                        </button>
                    </div>
                    <!-- Mobile filter trigger (Feature 6) -->
                    <button class="vm-filter-sheet-btn" id="vmFilterSheetBtn" onclick="vmOpenFilterSheet()" aria-label="Open filter options">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                        </svg>
                        Filter
                        <span class="vm-filter-sheet-badge" id="vmFilterSheetBadge" style="display:none;"></span>
                    </button>
                </div>
            </div>

            <p class="vm-result-count" id="vmResultCount">
                Showing <strong><?php echo $totalCount; ?></strong> maintenance records
            </p>

            <!-- CARDS -->
            <?php if (empty($schedules)): ?>
                <div class="vm-allclear">
                    <div class="vm-allclear-icon">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                    <h3>All Systems Running Normally</h3>
                    <p>No scheduled or active maintenance windows at this time.<br>All systems are operating as expected.</p>
                </div>

            <?php else: ?>

                <?php if ($countActive === 0 && $countScheduled === 0): ?>
                <div class="vm-allclear-bar" id="vmAllClearBar">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    All systems running normally — <?php echo $countDone; ?> completed record<?php echo $countDone !== 1 ? 's' : ''; ?> in history
                </div>
                <?php endif; ?>

                <div class="vm-grid" id="vmGrid">
                    <?php foreach ($schedules as $idx => $sched):
                        $status     = $sched['status'];
                        $exceeded   = formatExceededDuration($sched['exceeded_duration']);
                        $logoPath   = !empty($sched['system_logo']) ? '../' . $sched['system_logo'] : null;
                        $hasLogo    = $logoPath && file_exists('../' . $sched['system_logo']);
                        $contact    = !empty($sched['system_contact']) ? htmlspecialchars($sched['system_contact']) : null;
                        $fullName   = htmlspecialchars($sched['system_name']);
                        $updatedIso = isodt($sched['updated_at']);
                    ?>
                    <!-- Feature 2: staggered entrance animation via CSS custom property -->
                    <div class="vm-card vm-card-animate"
                         style="--vm-card-delay: <?php echo min($idx * 60, 600); ?>ms"
                         data-status="<?php echo htmlspecialchars($status); ?>"
                         data-search="<?php echo strtolower(htmlspecialchars($sched['system_name'] . ' ' . $sched['title'])); ?>">

                        <div class="vm-card-top">
                            <div class="vm-system-identity">
                                <?php if ($hasLogo): ?>
                                    <img src="<?php echo htmlspecialchars($logoPath); ?>"
                                         alt="<?php echo $fullName; ?>"
                                         class="vm-system-logo">
                                <?php else: ?>
                                    <div class="vm-system-logo-placeholder">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="3" width="7" height="7"></rect>
                                            <rect x="14" y="3" width="7" height="7"></rect>
                                            <rect x="14" y="14" width="7" height="7"></rect>
                                            <rect x="3" y="14" width="7" height="7"></rect>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                                <div class="vm-system-name-wrap">
                                    <!-- Feature 1: native tooltip via title attr -->
                                    <span class="vm-system-name"
                                          title="<?php echo $fullName; ?>"><?php echo $fullName; ?></span>
                                    <span class="vm-system-domain"><?php echo htmlspecialchars($sched['system_domain']); ?></span>
                                </div>
                            </div>
                            <div class="vm-status-badge vm-status-<?php echo strtolower(str_replace(' ', '-', $status)); ?>">
                                <span class="vm-status-dot"></span>
                                <?php echo htmlspecialchars($status); ?>
                            </div>
                        </div>

                        <div class="vm-card-divider"></div>

                        <div class="vm-card-body">
                            <!-- Feature 4: data-title used by JS for search highlight -->
                            <h3 class="vm-maint-title" data-original="<?php echo htmlspecialchars($sched['title']); ?>"><?php echo htmlspecialchars($sched['title']); ?></h3>
                            <?php if (!empty($sched['description'])): ?>
                                <p class="vm-maint-desc"><?php echo htmlspecialchars($sched['description']); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="vm-card-times">
                            <div class="vm-time-row">
                                <span class="vm-time-label">Start</span>
                                <span class="vm-time-value"><?php echo fmtDt($sched['start_datetime']); ?></span>
                            </div>
                            <div class="vm-time-row">
                                <span class="vm-time-label">End</span>
                                <span class="vm-time-value"><?php echo fmtDt($sched['end_datetime']); ?></span>
                            </div>
                        </div>

                        <!-- Footer -->
                        <?php if ($status === 'In Progress'): ?>
                            <div class="vm-card-footer vm-card-footer-inprogress">
                                <div class="vm-inprogress-indicator">
                                    <span class="vm-live-dot"></span>
                                    Maintenance in progress
                                </div>
                                <div class="vm-countdown"
                                     data-end="<?php echo isodt($sched['end_datetime']); ?>"
                                     data-contact="<?php echo $contact ?? ''; ?>"
                                     id="vmCountdown_<?php echo $sched['id']; ?>">
                                </div>
                            </div>

                        <?php elseif ($status === 'Scheduled'): ?>
                            <div class="vm-card-footer">
                                <div class="vm-scheduled-indicator">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <polyline points="12 6 12 12 16 14"></polyline>
                                    </svg>
                                    Starts <?php echo fmtDt($sched['start_datetime']); ?>
                                </div>
                            </div>

                        <?php elseif ($status === 'Done'): ?>
                            <div class="vm-card-footer vm-card-footer-done">
                                <?php if ($exceeded): ?>
                                    <div class="vm-exceeded-badge">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <line x1="12" y1="8" x2="12" y2="12"></line>
                                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                        </svg>
                                        <?php echo htmlspecialchars($exceeded); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="vm-ontime-badge">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                        Completed on time
                                    </div>
                                <?php endif; ?>
                                <!-- Feature 3: relative time -->
                                <span class="vm-relative-time"
                                      data-updated="<?php echo $updatedIso; ?>"
                                      title="<?php echo fmtDt($sched['updated_at']); ?>">
                                </span>
                            </div>
                        <?php endif; ?>

                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="vm-show-more-wrap" id="vmShowMoreWrap" style="display:none;">
                    <button class="vm-show-more-btn" id="vmShowMoreDone" onclick="vmToggleDoneHistory()">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                        Show older records
                    </button>
                </div>

                <div class="vm-empty vm-filter-empty" id="vmFilterEmpty" style="display:none;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                    </svg>
                    <h3 id="vmFilterEmptyTitle">No Results Found</h3>
                    <p id="vmFilterEmptyMsg">No maintenance records match your filter.</p>
                </div>

            <?php endif; ?>

        </div>
    </main>

    <!-- ══════════════════════════════════
         MOBILE BOTTOM SHEET (Feature 6)
         ══════════════════════════════════ -->
    <div class="vm-sheet-overlay" id="vmSheetOverlay" onclick="vmCloseFilterSheet()"></div>
    <div class="vm-bottom-sheet" id="vmBottomSheet" role="dialog" aria-modal="true" aria-label="Filter options">
        <div class="vm-sheet-handle"></div>
        <div class="vm-sheet-header">
            <span class="vm-sheet-title">Filter by Status</span>
            <button class="vm-sheet-close" onclick="vmCloseFilterSheet()" aria-label="Close">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="vm-sheet-options">
            <button class="vm-sheet-option active" data-status="all" onclick="vmSheetFilter('all', this)">
                <span class="vm-sheet-option-dot" style="background:#9ca3af"></span>
                All Records
                <svg class="vm-sheet-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </button>
            <button class="vm-sheet-option" data-status="In Progress" onclick="vmSheetFilter('In Progress', this)">
                <span class="vm-sheet-option-dot" style="background:#f59e0b"></span>
                In Progress
                <svg class="vm-sheet-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </button>
            <button class="vm-sheet-option" data-status="Scheduled" onclick="vmSheetFilter('Scheduled', this)">
                <span class="vm-sheet-option-dot" style="background:#3b82f6"></span>
                Scheduled
                <svg class="vm-sheet-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </button>
            <button class="vm-sheet-option" data-status="Done" onclick="vmSheetFilter('Done', this)">
                <span class="vm-sheet-option-dot" style="background:#10b981"></span>
                Done
                <svg class="vm-sheet-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </button>
        </div>
    </div>

    <footer class="viewer-footer">
        <p>&copy; <?php echo date('Y'); ?> G-Portal. All rights reserved.</p>
    </footer>

    <script>
        const VM_TOTAL      = <?php echo $totalCount; ?>;
        const VM_HAS_ACTIVE = <?php echo $hasActive ? 'true' : 'false'; ?>;
        let vmActiveFilter  = 'all';
        let vmActiveSearch  = '';
    </script>
    <script src="../assets/js/viewer_maintenance.js"></script>
</body>
</html>