/* ============================================================
   G-Portal Analytics Page JavaScript
   Features: Uptime, Patch Logs, Completed Maintenance,
             Monthly Reports, Status Trends
   ============================================================ */

// Global variables
let uptimeChart       = null;
let statusTrendsChart = null;
let currentUptimeView = 'overall';
let currentHistoryDays = 30;

// Patch logs pagination
let allStatusLogs = [];
let currentPage   = 1;
let itemsPerPage  = 5; // Changed from 10 to 5

// Log data map — avoids quote-escaping issues in onclick handlers
let logDataMap = {};

// Completed maintenance pagination
let allCompletedMaint   = [];
let maintCurrentPage    = 1;
let maintItemsPerPage   = 5;
let currentMaintDays    = 30;

// ============================================================
// INITIALIZATION
// ============================================================

document.addEventListener('DOMContentLoaded', function() {
    loadStatusHistory(30);
    loadUptimeData();
    loadStatusTrends();
    loadCompletedMaintenance(30);
});

// ============================================================
// SECTION 1: UPTIME STATISTICS
// ============================================================

function switchUptimeView(view) {
    currentUptimeView = view;
    document.querySelectorAll('.toggle-btn[data-view]').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.view === view);
    });
    const systemSelect = document.getElementById('uptimeSystemSelect');
    if (view === 'per-system') {
        systemSelect.style.display = 'block';
    } else {
        systemSelect.style.display = 'none';
        systemSelect.value = '';
    }
    loadUptimeData();
}

function loadUptimeData() {
    const systemId = currentUptimeView === 'per-system'
        ? document.getElementById('uptimeSystemSelect').value
        : 0;

    if (currentUptimeView === 'per-system' && !systemId) {
        if (uptimeChart) { uptimeChart.destroy(); uptimeChart = null; }
        updateUptimeStats(null);
        return;
    }

    fetch(`../backend/get_analytics_data.php?action=uptime_stats&system_id=${systemId}&days=30`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderUptimeChart(data.data, systemId);
                calculateUptimeStats(data.data);
            }
        })
        .catch(err => console.error('Error loading uptime data:', err));
}

function renderUptimeChart(data, systemId) {
    const ctx = document.getElementById('uptimeChart').getContext('2d');
    const dates = [];
    const uptimePercentages = [];

    if (systemId > 0) {
        const dailyData = {};
        data.forEach(entry => {
            const date = entry.date;
            if (!dailyData[date]) dailyData[date] = { online: 0, total: 0 };
            dailyData[date].total++;
            if (entry.status === 'online') dailyData[date].online++;
        });
        Object.keys(dailyData).sort().forEach(date => {
            dates.push(date);
            uptimePercentages.push(((dailyData[date].online / dailyData[date].total) * 100).toFixed(2));
        });
    } else {
        const dailyData = {};
        data.forEach(entry => {
            const date = entry.date;
            if (!dailyData[date]) dailyData[date] = { online: 0, total: 0 };
            dailyData[date].total += parseInt(entry.count);
            if (entry.status === 'online') dailyData[date].online += parseInt(entry.count);
        });
        Object.keys(dailyData).sort().forEach(date => {
            dates.push(date);
            uptimePercentages.push(((dailyData[date].online / dailyData[date].total) * 100).toFixed(2));
        });
    }

    if (uptimeChart) uptimeChart.destroy();

    uptimeChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: dates,
            datasets: [{
                label: 'Uptime %',
                data: uptimePercentages,
                backgroundColor: 'rgba(30, 58, 138, 0.8)',
                borderColor: 'rgba(30, 58, 138, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: systemId > 0 ? 'Daily Uptime Percentage' : 'Overall System Uptime',
                    font: { size: 16, weight: 'bold' }
                },
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 130,
                    ticks: { callback: value => value + '%' }
                }
            }
        }
    });
}

function calculateUptimeStats(data) {
    let totalUptime = 0;
    let incidents   = 0;
    data.forEach(entry => {
        if (entry.status) {
            if (entry.status === 'online') totalUptime += 100;
            if (entry.status === 'down' || entry.status === 'offline') incidents++;
        }
    });
    const avgUptime = data.length > 0 ? (totalUptime / data.length).toFixed(1) : 0;
    updateUptimeStats({
        current: data.length > 0 && data[data.length - 1]?.status === 'online' ? '100%' : '0%',
        average: avgUptime + '%',
        incidents: incidents
    });
}

function updateUptimeStats(stats) {
    if (!stats) {
        document.getElementById('currentUptime').textContent  = '--';
        document.getElementById('avgUptime').textContent      = '--';
        document.getElementById('totalIncidents').textContent = '--';
        return;
    }
    document.getElementById('currentUptime').textContent  = stats.current;
    document.getElementById('avgUptime').textContent      = stats.average;
    document.getElementById('totalIncidents').textContent = stats.incidents;
}

// ============================================================
// SECTION 2: STATUS CHANGE HISTORY (PATCH LOGS) — 5 per page
// ============================================================

function loadStatusHistory(days) {
    currentHistoryDays = days;
    currentPage = 1;

    document.querySelectorAll('.toggle-btn[data-days]').forEach(btn => {
        btn.classList.toggle('active', parseInt(btn.dataset.days) === days);
    });

    const tbody = document.getElementById('statusHistoryBody');
    tbody.innerHTML = '<tr><td colspan="5" class="loading-cell">Loading...</td></tr>';

    logDataMap = {}; // Reset map on new load
    fetch(`../backend/get_analytics_data.php?action=status_history&days=${days}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                allStatusLogs = data.data;
                renderStatusHistory();
            }
        })
        .catch(err => {
            console.error('Error loading status history:', err);
            tbody.innerHTML = '<tr><td colspan="5" class="loading-cell">Error loading data</td></tr>';
        });
}

function renderStatusHistory() {
    const tbody = document.getElementById('statusHistoryBody');

    if (allStatusLogs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="loading-cell">No status changes found</td></tr>';
        updatePaginationInfo();
        return;
    }

    const totalPages = Math.ceil(allStatusLogs.length / itemsPerPage);
    const startIndex = (currentPage - 1) * itemsPerPage;
    const logsToDisplay = allStatusLogs.slice(startIndex, startIndex + itemsPerPage);

    // Store log data in a map so onclick can safely retrieve it without escaping issues
    logsToDisplay.forEach(log => { logDataMap[log.id] = log; });

    tbody.innerHTML = logsToDisplay.map(log => {
        const noteText = log.change_note ? escapeHtml(log.change_note) : '';
        return `
        <tr>
            <td class="col-date">${formatDateTime(log.changed_at)}</td>
            <td class="col-system"><strong class="truncate-cell" title="${escapeHtml(log.system_name)}">${escapeHtml(log.system_name)}</strong></td>
            <td class="col-status">
                <div class="status-change">
                    <span class="status-badge ${log.old_status}">${capitalize(log.old_status)}</span>
                    <span class="arrow">→</span>
                    <span class="status-badge ${log.new_status}">${capitalize(log.new_status)}</span>
                </div>
            </td>
            <td class="col-by"><span class="truncate-cell" title="${escapeHtml(log.changed_by)}">${escapeHtml(log.changed_by)}</span></td>
            <td class="col-note">
                <div class="note-cell">
                    <button class="btn-view-details" onclick="openViewDetailsModal(${log.id})" title="View full details">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                        </svg>
                    </button>
                    <span class="truncate-cell note-text" title="${noteText}">${noteText || '<em>No note</em>'}</span>
                    <button class="btn-edit-note" onclick="openEditNoteModal_byId(${log.id})" title="Edit note">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        Edit
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');

    updatePaginationInfo();
    renderPaginationControls(totalPages, currentPage, 'paginationControls', changePage);
}

function updatePaginationInfo() {
    const el = document.getElementById('paginationInfo');
    if (!el) return;
    const total      = allStatusLogs.length;
    const startItem  = total === 0 ? 0 : (currentPage - 1) * itemsPerPage + 1;
    const endItem    = Math.min(currentPage * itemsPerPage, total);
    el.textContent   = `Showing ${startItem}-${endItem} of ${total} entries`;
}

function changePage(pageNumber) {
    const totalPages = Math.ceil(allStatusLogs.length / itemsPerPage);
    if (pageNumber < 1 || pageNumber > totalPages) return;
    currentPage = pageNumber;
    renderStatusHistory();
}

// ============================================================
// SECTION 3: COMPLETED MAINTENANCE TABLE
// ============================================================

function loadCompletedMaintenance(days) {
    currentMaintDays  = days;
    maintCurrentPage  = 1;

    document.querySelectorAll('.toggle-btn[data-mdays]').forEach(btn => {
        btn.classList.toggle('active', parseInt(btn.dataset.mdays) === days);
    });

    const tbody = document.getElementById('completedMaintenanceBody');
    tbody.innerHTML = '<tr><td colspan="6" class="loading-cell">Loading...</td></tr>';

    fetch(`../backend/get_analytics_data.php?action=completed_maintenance&days=${days}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                allCompletedMaint = data.data;
                renderCompletedMaintenance();
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="loading-cell">Error loading data</td></tr>';
            }
        })
        .catch(err => {
            console.error('Error loading completed maintenance:', err);
            tbody.innerHTML = '<tr><td colspan="6" class="loading-cell">Error loading data</td></tr>';
        });
}

function renderCompletedMaintenance() {
    const tbody = document.getElementById('completedMaintenanceBody');

    if (allCompletedMaint.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="loading-cell">No completed maintenance records found</td></tr>';
        updateMaintPaginationInfo();
        return;
    }

    const totalPages   = Math.ceil(allCompletedMaint.length / maintItemsPerPage);
    const startIndex   = (maintCurrentPage - 1) * maintItemsPerPage;
    const toDisplay    = allCompletedMaint.slice(startIndex, startIndex + maintItemsPerPage);

    tbody.innerHTML = toDisplay.map(m => {
        const exceeded = m.exceeded_duration
            ? formatExceeded(parseInt(m.exceeded_duration))
            : '<span class="no-exceeded">Within schedule</span>';

        const exceededClass = m.exceeded_duration && parseInt(m.exceeded_duration) > 0
            ? 'exceeded-yes' : 'exceeded-no';

        return `
        <tr>
            <td class="col-system"><strong class="truncate-cell" title="${escapeHtml(m.system_name)}">${escapeHtml(m.system_name)}</strong></td>
            <td class="col-title"><span class="truncate-cell" title="${escapeHtml(m.title)}">${escapeHtml(m.title)}</span></td>
            <td class="col-date">${formatDateTime(m.start_datetime)}</td>
            <td class="col-date">${formatDateTime(m.end_datetime)}</td>
            <td class="col-exceeded"><span class="${exceededClass}">${exceeded}</span></td>
            <td class="col-by"><span class="truncate-cell" title="${escapeHtml(m.done_by || 'System')}">${escapeHtml(m.done_by || 'System')}</span></td>
        </tr>`;
    }).join('');

    updateMaintPaginationInfo();
    renderPaginationControls(totalPages, maintCurrentPage, 'maintPaginationControls', changeMaintPage);
}

function formatExceeded(seconds) {
    if (!seconds || seconds <= 0) return '<span class="no-exceeded">Within schedule</span>';
    if (seconds < 60)   return `+${seconds}s`;
    if (seconds < 3600) return `+${Math.floor(seconds / 60)}m ${seconds % 60}s`;
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return `+${h}h ${m}m`;
}

function updateMaintPaginationInfo() {
    const el = document.getElementById('maintPaginationInfo');
    if (!el) return;
    const total     = allCompletedMaint.length;
    const startItem = total === 0 ? 0 : (maintCurrentPage - 1) * maintItemsPerPage + 1;
    const endItem   = Math.min(maintCurrentPage * maintItemsPerPage, total);
    el.textContent  = `Showing ${startItem}-${endItem} of ${total} entries`;
}

function changeMaintPage(pageNumber) {
    const totalPages = Math.ceil(allCompletedMaint.length / maintItemsPerPage);
    if (pageNumber < 1 || pageNumber > totalPages) return;
    maintCurrentPage = pageNumber;
    renderCompletedMaintenance();
}

// ============================================================
// SHARED PAGINATION RENDERER
// ============================================================

function renderPaginationControls(totalPages, activePage, containerId, changePageFn) {
    const container = document.getElementById(containerId);
    if (!container) return;

    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = '';

    // Previous
    html += `<button class="pagination-btn pagination-prev" onclick="${changePageFn.name}(${activePage - 1})" ${activePage === 1 ? 'disabled' : ''}>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
        Previous
    </button>`;

    html += '<div class="pagination-numbers">';

    // Page 1
    html += `<button class="pagination-number ${activePage === 1 ? 'active' : ''}" onclick="${changePageFn.name}(1)">1</button>`;

    if (activePage > 3) html += '<span class="pagination-dots">...</span>';

    for (let i = Math.max(2, activePage - 1); i <= Math.min(totalPages - 1, activePage + 1); i++) {
        html += `<button class="pagination-number ${activePage === i ? 'active' : ''}" onclick="${changePageFn.name}(${i})">${i}</button>`;
    }

    if (activePage < totalPages - 2) html += '<span class="pagination-dots">...</span>';

    if (totalPages > 1) {
        html += `<button class="pagination-number ${activePage === totalPages ? 'active' : ''}" onclick="${changePageFn.name}(${totalPages})">${totalPages}</button>`;
    }

    html += '</div>';

    // Next
    html += `<button class="pagination-btn pagination-next" onclick="${changePageFn.name}(${activePage + 1})" ${activePage === totalPages ? 'disabled' : ''}>
        Next
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
    </button>`;

    container.innerHTML = html;
}

// ============================================================
// SECTION 4: MONTHLY REPORTS
// ============================================================

function loadMonthlyReport() {
    const systemId  = document.getElementById('reportSystemSelect').value;
    const month     = document.getElementById('reportMonthSelect').value;
    const container = document.getElementById('monthlyReportContainer');
    const exportBtn = document.getElementById('exportPdfBtn');

    if (!systemId) {
        container.innerHTML = `
            <div class="empty-state">
                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <line x1="12" y1="20" x2="12" y2="10"></line>
                    <line x1="18" y1="20" x2="18" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="16"></line>
                </svg>
                <h4>Select a System and Month</h4>
                <p>Choose a system and time period to generate a detailed monthly report</p>
            </div>`;
        exportBtn.disabled = true;
        return;
    }

    container.innerHTML = '<div class="loading-cell" style="padding: 60px;">Loading report...</div>';
    exportBtn.disabled  = true;

    fetch(`../backend/get_analytics_data.php?action=monthly_report&system_id=${systemId}&month=${month}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderMonthlyReport(data.data);
                exportBtn.disabled = false;
            } else {
                container.innerHTML = '<div class="empty-state"><p>Error loading report</p></div>';
            }
        })
        .catch(err => {
            console.error('Error loading monthly report:', err);
            container.innerHTML = '<div class="empty-state"><p>Error loading report</p></div>';
        });
}

function renderMonthlyReport(reportData) {
    const container = document.getElementById('monthlyReportContainer');
    const monthName = new Date(reportData.month + '-01').toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

    container.innerHTML = `
        <div class="report-content" id="reportContent">
            <div class="report-header">
                <h4>${escapeHtml(reportData.system.name)} - Monthly Report</h4>
                <p class="report-period">${monthName}</p>
            </div>
            <div class="report-stats">
                <div class="report-stat-card">
                    <div class="label">Uptime Percentage</div>
                    <div class="value">${reportData.uptime_percentage}%</div>
                </div>
                <div class="report-stat-card">
                    <div class="label">Status Changes</div>
                    <div class="value">${reportData.status_changes_count}</div>
                </div>
                <div class="report-stat-card">
                    <div class="label">Downtime Incidents</div>
                    <div class="value">${reportData.downtime_incidents}</div>
                </div>
            </div>
            <div class="report-section">
                <h5>Time Spent in Each Status</h5>
                <div class="time-breakdown">
                    ${Object.keys(reportData.time_in_status).map(status => `
                        <div class="time-breakdown-item">
                            <div class="status-label">
                                <span class="status-dot ${status}"></span>
                                ${capitalize(status)}
                            </div>
                            <div class="time-value">${reportData.time_in_status[status].formatted}</div>
                        </div>`).join('')}
                </div>
            </div>
            ${reportData.status_changes.length > 0 ? `
                <div class="report-section">
                    <h5>Status Change Log</h5>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Change</th>
                                    <th>Changed By</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${reportData.status_changes.map(change => `
                                    <tr>
                                        <td>${formatDateTime(change.changed_at)}</td>
                                        <td>
                                            <span class="status-badge ${change.old_status}">${capitalize(change.old_status)}</span>
                                            → 
                                            <span class="status-badge ${change.new_status}">${capitalize(change.new_status)}</span>
                                        </td>
                                        <td>${escapeHtml(change.changed_by)}</td>
                                        <td>${change.change_note ? escapeHtml(change.change_note) : '<em>No note</em>'}</td>
                                    </tr>`).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>` : ''}
        </div>`;
}

// ============================================================
// SECTION 5: STATUS TRENDS CHART
// ============================================================

function loadStatusTrends() {
    fetch('../backend/get_analytics_data.php?action=status_history&days=30')
        .then(r => r.json())
        .then(data => {
            if (data.success) renderStatusTrendsChart(data.data);
        })
        .catch(err => console.error('Error loading status trends:', err));
}

function renderStatusTrendsChart(logs) {
    const ctx = document.getElementById('statusTrendsChart').getContext('2d');
    const dailyCounts = {};
    logs.forEach(log => {
        const date = log.changed_at.split(' ')[0];
        dailyCounts[date] = (dailyCounts[date] || 0) + 1;
    });
    const dates  = Object.keys(dailyCounts).sort();
    const counts = dates.map(d => dailyCounts[d]);

    if (statusTrendsChart) statusTrendsChart.destroy();

    statusTrendsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: dates,
            datasets: [{
                label: 'Status Changes',
                data: counts,
                backgroundColor: 'rgba(30, 58, 138, 0.8)',
                borderColor: 'rgba(30, 58, 138, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: { display: true, text: 'Status Changes Over Time (Last 30 Days)', font: { size: 16, weight: 'bold' } },
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, suggestedMax: 20, ticks: { stepSize: 1 } }
            }
        }
    });
}

// ============================================================
// PDF EXPORT
// ============================================================

function exportReportToPDF() {
    const reportContent = document.getElementById('reportContent');
    if (!reportContent) { alert('No report to export.'); return; }

    const exportBtn  = document.getElementById('exportPdfBtn');
    const origText   = exportBtn.innerHTML;
    exportBtn.innerHTML = 'Generating...';
    exportBtn.disabled  = true;

    html2canvas(reportContent, { scale: 2, useCORS: true, logging: false }).then(canvas => {
        const imgData   = canvas.toDataURL('image/png');
        const { jsPDF } = window.jspdf;
        const pdf       = new jsPDF('p', 'mm', 'a4');
        const imgWidth  = 210;
        const pageHeight = 297;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        let heightLeft  = imgHeight;
        let position    = 0;

        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;

        while (heightLeft >= 0) {
            position = heightLeft - imgHeight;
            pdf.addPage();
            pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
        }

        const systemName = document.getElementById('reportSystemSelect').selectedOptions[0].text;
        const month      = document.getElementById('reportMonthSelect').value;
        pdf.save(`${systemName}-Report-${month}.pdf`);

        exportBtn.innerHTML = origText;
        exportBtn.disabled  = false;
    }).catch(err => {
        console.error('PDF error:', err);
        alert('Error generating PDF.');
        exportBtn.innerHTML = origText;
        exportBtn.disabled  = false;
    });
}

// ============================================================
// VIEW DETAILS MODAL
// ============================================================

function openViewDetailsModal(id) {
    const log = logDataMap[id];
    if (!log) return;

    const systemName = log.system_name;
    const datetime   = log.changed_at;
    const oldStatus  = log.old_status;
    const newStatus  = log.new_status;
    const changedBy  = log.changed_by;
    const note       = log.change_note || '';

    document.getElementById('vdSystemName').textContent = systemName;
    document.getElementById('vdSystem').textContent     = systemName;
    document.getElementById('vdDateTime').textContent   = formatDateTime(datetime);
    document.getElementById('vdChangedBy').textContent  = changedBy;

    // Status change badges
    document.getElementById('vdStatusChange').innerHTML = `
        <span class="status-badge ${oldStatus}">${capitalize(oldStatus)}</span>
        <span style="margin: 0 6px; color: #9ca3af; font-weight: 600;">→</span>
        <span class="status-badge ${newStatus}">${capitalize(newStatus)}</span>
    `;

    // Note — show full text, support line breaks
    const noteEl = document.getElementById('vdNote');
    if (note && note.trim()) {
        noteEl.textContent = note; // safe — no XSS
        noteEl.style.whiteSpace = 'pre-wrap';
        noteEl.classList.remove('vd-note-empty');
    } else {
        noteEl.innerHTML = '<em style="color:#9ca3af;">No note provided</em>';
        noteEl.classList.add('vd-note-empty');
    }

    document.getElementById('viewDetailsModal').classList.add('show');
}

function closeViewDetailsModal() {
    document.getElementById('viewDetailsModal').classList.remove('show');
}

// ============================================================
// EDIT NOTE MODAL
// ============================================================

let currentEditLogId = null;

// Safe wrapper — retrieves data from logDataMap instead of inline params
function openEditNoteModal_byId(id) {
    const log = logDataMap[id];
    if (!log) return;
    openEditNoteModal(log.id, log.change_note || '', log.system_name);
}

function openEditNoteModal(logId, currentNote, systemName) {
    currentEditLogId = logId;
    document.getElementById('editNoteSystemName').textContent = systemName;
    document.getElementById('editNoteTextarea').value = currentNote === 'No note' ? '' : currentNote;
    document.getElementById('editNoteModal').classList.add('show');
    document.getElementById('editNoteTextarea').focus();
}

function closeEditNoteModal() {
    document.getElementById('editNoteModal').classList.remove('show');
    document.getElementById('editNoteTextarea').value = '';
    currentEditLogId = null;
}

function saveEditedNote(event) {
    event.preventDefault();
    if (!currentEditLogId) return;

    const newNote  = document.getElementById('editNoteTextarea').value.trim();
    const formData = new FormData();
    formData.append('log_id', currentEditLogId);
    formData.append('note', newNote);

    const saveBtn  = event.target.querySelector('button[type="submit"]');
    const origText = saveBtn.innerHTML;
    saveBtn.innerHTML = 'Saving...';
    saveBtn.disabled  = true;

    fetch('../backend/update_log_note.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                loadStatusHistory(currentHistoryDays);
                closeEditNoteModal();
                showSuccessMessage('Note updated successfully!');
            } else {
                alert(data.message || 'Error updating note');
            }
        })
        .catch(err => { console.error('Error:', err); alert('Error updating note'); })
        .finally(() => { saveBtn.innerHTML = origText; saveBtn.disabled = false; });
}

function showSuccessMessage(message) {
    const msgDiv = document.createElement('div');
    msgDiv.className = 'success-toast';
    msgDiv.textContent = message;
    msgDiv.style.cssText = `position:fixed;top:20px;right:20px;background:#10b981;color:white;padding:16px 24px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:10000;animation:slideIn 0.3s ease;`;
    document.body.appendChild(msgDiv);
    setTimeout(() => { msgDiv.style.animation = 'slideOut 0.3s ease'; setTimeout(() => msgDiv.remove(), 300); }, 3000);
}

window.addEventListener('click', function(event) {
    if (event.target === document.getElementById('editNoteModal')) closeEditNoteModal();
    if (event.target === document.getElementById('viewDetailsModal')) closeViewDetailsModal();
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeEditNoteModal();
        closeViewDetailsModal();
    }
});

// Animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
`;
document.head.appendChild(style);

// ============================================================
// UTILITY FUNCTIONS
// ============================================================

function formatDateTime(datetime) {
    return new Date(datetime).toLocaleString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function loadSystemsByStatus() {
    fetch('../backend/get_analytics_data.php?action=systems_by_status')
        .then(r => r.json())
        .then(data => {
            if (data.success && window.renderDashboardChart) window.renderDashboardChart(data.data);
        })
        .catch(err => console.error('Error:', err));
}