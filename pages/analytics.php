<?php
/**
 * G-Portal Analytics & Reports Page
 * Features: Uptime stats, Patch logs, Completed Maintenance, Monthly Reports, Trends
 */

require_once '../config/session.php';
require_once '../config/database.php';

requireLogin();
$currentUser = getCurrentUser();

$conn = getDBConnection();
$systemsResult = $conn->query("SELECT id, name, status FROM systems ORDER BY name ASC");
$systems = [];
while ($row = $systemsResult->fetch_assoc()) {
    $systems[] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>G-Portal - Analytics & Reports</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/analytics.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>
<body>
    <!-- Hamburger button for tablet/mobile -->
<button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Open menu">
    <span></span><span></span><span></span>
</button>
<!-- Sidebar overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

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
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="7" height="7"></rect>
                                <rect x="14" y="3" width="7" height="7"></rect>
                                <rect x="14" y="14" width="7" height="7"></rect>
                                <rect x="3" y="14" width="7" height="7"></rect>
                            </svg>
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="analytics.php" class="active">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="20" x2="12" y2="10"></line>
                                <line x1="18" y1="20" x2="18" y2="4"></line>
                                <line x1="6" y1="20" x2="6" y2="16"></line>
                            </svg>
                            Analytics
                        </a>
                    </li>
                </ul>
                <div class="user-profile">
                    <div class="user-avatar-large">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
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

            <!-- Page Header -->
            <div class="page-header-analytics">
                <div>
                    <h2 class="page-title">Analytics & Reports</h2>
                    <p class="page-subtitle">Monitor system performance and generate reports</p>
                </div>
            </div>

            <!-- ========================================
                 SECTION 1: UPTIME STATISTICS
                 ======================================== -->
            <section class="analytics-section">
                <div class="section-header">
                    <h3>System Uptime Statistics</h3>
                    <div class="section-controls">
                        <div class="toggle-group">
                            <button class="toggle-btn active" data-view="overall" onclick="switchUptimeView('overall')">Overall</button>
                            <button class="toggle-btn" data-view="per-system" onclick="switchUptimeView('per-system')">Per System</button>
                        </div>
                        <select id="uptimeSystemSelect" class="select-system" style="display: none;" onchange="loadUptimeData()">
                            <option value="">Select a system...</option>
                            <?php foreach ($systems as $system): ?>
                                <option value="<?php echo $system['id']; ?>"><?php echo htmlspecialchars($system['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="uptimeChart"></canvas>
                </div>
                <div class="stats-grid" id="uptimeStats">
                    <div class="stat-card">
                        <div class="stat-value" id="currentUptime">--</div>
                        <div class="stat-label">Current Uptime</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="avgUptime">--</div>
                        <div class="stat-label">30-Day Average</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="totalIncidents">--</div>
                        <div class="stat-label">Incidents This Month</div>
                    </div>
                </div>
            </section>

            <!-- ========================================
                 SECTION 2: STATUS CHANGE HISTORY (PATCH LOGS)
                 ======================================== -->
            <section class="analytics-section">
                <div class="section-header">
                    <h3>Status Change History (Patch Logs)</h3>
                    <div class="section-controls">
                        <div class="toggle-group">
                            <button class="toggle-btn active" data-days="30" onclick="loadStatusHistory(30)">Last 30 Days</button>
                            <button class="toggle-btn" data-days="0" onclick="loadStatusHistory(0)">All Time</button>
                        </div>
                    </div>
                </div>
                <div class="table-container">
                    <table class="data-table" id="statusHistoryTable">
                        <thead>
                            <tr>
                                <th class="col-date">Date & Time</th>
                                <th class="col-system">System</th>
                                <th class="col-status">Status Change</th>
                                <th class="col-by">Changed By</th>
                                <th class="col-note">Note</th>
                            </tr>
                        </thead>
                        <tbody id="statusHistoryBody">
                            <tr><td colspan="5" class="loading-cell">Loading...</td></tr>
                        </tbody>
                    </table>
                    <div class="pagination-container">
                        <div class="pagination-info" id="paginationInfo">Showing 0-0 of 0 entries</div>
                        <div class="pagination-controls" id="paginationControls"></div>
                    </div>
                </div>
            </section>

            <!-- ========================================
                 SECTION 3: COMPLETED MAINTENANCE
                 ======================================== -->
            <section class="analytics-section">
                <div class="section-header">
                    <h3>Completed Maintenance</h3>
                    <div class="section-controls">
                        <div class="toggle-group">
                            <button class="toggle-btn active" data-mdays="30" onclick="loadCompletedMaintenance(30)">Last 30 Days</button>
                            <button class="toggle-btn" data-mdays="0" onclick="loadCompletedMaintenance(0)">All Time</button>
                        </div>
                    </div>
                </div>
                <div class="table-container">
                    <table class="data-table" id="completedMaintenanceTable">
                        <thead>
                            <tr>
                                <th class="col-system">System</th>
                                <th class="col-title">Title</th>
                                <th class="col-date">Start</th>
                                <th class="col-date">End</th>
                                <th class="col-exceeded">Exceeded Duration</th>
                                <th class="col-by">Done By</th>
                            </tr>
                        </thead>
                        <tbody id="completedMaintenanceBody">
                            <tr><td colspan="6" class="loading-cell">Loading...</td></tr>
                        </tbody>
                    </table>
                    <div class="pagination-container">
                        <div class="pagination-info" id="maintPaginationInfo">Showing 0-0 of 0 entries</div>
                        <div class="pagination-controls" id="maintPaginationControls"></div>
                    </div>
                </div>
            </section>

            <!-- ========================================
                 SECTION 4: MONTHLY SYSTEM REPORTS
                 ======================================== -->
            <section class="analytics-section">
                <div class="section-header">
                    <h3>Monthly System Reports</h3>
                    <div class="section-controls">
                        <select id="reportSystemSelect" class="select-system" onchange="loadMonthlyReport()">
                            <option value="">Select a system...</option>
                            <?php foreach ($systems as $system): ?>
                                <option value="<?php echo $system['id']; ?>"><?php echo htmlspecialchars($system['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="month" id="reportMonthSelect" class="month-picker"
                               value="<?php echo date('Y-m'); ?>"
                               max="<?php echo date('Y-m'); ?>"
                               onchange="loadMonthlyReport()">
                        <button class="btn-export" id="exportPdfBtn" onclick="exportReportToPDF()" disabled>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="7 10 12 15 17 10"></polyline>
                                <line x1="12" y1="15" x2="12" y2="3"></line>
                            </svg>
                            Export PDF
                        </button>
                    </div>
                </div>
                <div class="report-container" id="monthlyReportContainer">
                    <div class="empty-state">
                        <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <line x1="12" y1="20" x2="12" y2="10"></line>
                            <line x1="18" y1="20" x2="18" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="16"></line>
                        </svg>
                        <h4>Select a System and Month</h4>
                        <p>Choose a system and time period to generate a detailed monthly report</p>
                    </div>
                </div>
            </section>

            <!-- ========================================
                 SECTION 5: STATUS CHANGE TRENDS
                 ======================================== -->
            <section class="analytics-section">
                <div class="section-header">
                    <h3>Status Change Trends</h3>
                </div>
                <div class="chart-container">
                    <canvas id="statusTrendsChart"></canvas>
                </div>
            </section>

        </main>
    </div>

    <!-- VIEW DETAILS MODAL -->
    <div id="viewDetailsModal" class="view-details-modal">
        <div class="view-details-modal-content">
            <div class="view-details-modal-header">
                <div class="view-details-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    <div>
                        <h3>Status Change Details</h3>
                        <span class="view-details-system-name" id="vdSystemName"></span>
                    </div>
                </div>
                <button class="view-details-close" onclick="closeViewDetailsModal()">&times;</button>
            </div>
            <div class="view-details-modal-body">
                <div class="vd-row">
                    <div class="vd-label">System</div>
                    <div class="vd-value" id="vdSystem"></div>
                </div>
                <div class="vd-row">
                    <div class="vd-label">Date & Time</div>
                    <div class="vd-value" id="vdDateTime"></div>
                </div>
                <div class="vd-row">
                    <div class="vd-label">Status Change</div>
                    <div class="vd-value" id="vdStatusChange"></div>
                </div>
                <div class="vd-row">
                    <div class="vd-label">Changed By</div>
                    <div class="vd-value" id="vdChangedBy"></div>
                </div>
                <div class="vd-row vd-row-note">
                    <div class="vd-label">Note</div>
                    <div class="vd-value vd-note-text" id="vdNote"></div>
                </div>
            </div>
            <div class="view-details-modal-footer">
                <button class="btn-vd-close" onclick="closeViewDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- EDIT NOTE MODAL -->
    <div id="editNoteModal" class="edit-note-modal">
        <div class="edit-note-modal-content">
            <div class="edit-note-modal-header">
                <div>
                    <h3>Edit Note</h3>
                    <div class="system-name" id="editNoteSystemName"></div>
                </div>
                <button class="close-edit-modal" onclick="closeEditNoteModal()">&times;</button>
            </div>
            <form onsubmit="saveEditedNote(event)">
                <div class="edit-note-modal-body">
                    <div class="edit-note-form-group">
                        <label for="editNoteTextarea">Change Note</label>
                        <textarea id="editNoteTextarea" class="edit-note-textarea"
                            placeholder="e.g., Scheduled maintenance, Server upgrade..."
                            rows="4"></textarea>
                        <div class="edit-note-helper">💡 This will update the note and change the timestamp to now</div>
                    </div>
                </div>
                <div class="edit-note-modal-footer">
                    <button type="button" class="btn-edit-cancel" onclick="closeEditNoteModal()">Cancel</button>
                    <button type="submit" class="btn-edit-save">Save Note</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/analytics.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>