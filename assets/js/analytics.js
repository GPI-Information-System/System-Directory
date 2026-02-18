/* ============================================================
   PHASE 2: ANALYTICS PAGE JAVASCRIPT
   WITH PAGINATION FOR STATUS HISTORY
   Handles charts, data fetching, and PDF export
   ============================================================ */

// Global variables
let uptimeChart = null;
let statusTrendsChart = null;
let currentUptimeView = 'overall';
let currentHistoryDays = 30;

// Pagination variables
let allStatusLogs = []; // Store all logs for pagination
let currentPage = 1;
let itemsPerPage = 10;

// ============================================================
// INITIALIZATION
// ============================================================

document.addEventListener('DOMContentLoaded', function() {
    // Load initial data
    loadSystemsByStatus(); // For dashboard chart
    loadStatusHistory(30);  // Load last 30 days of history
    loadUptimeData();      // Load overall uptime
    loadStatusTrends();    // Load status trends chart
});

// ============================================================
// SECTION 1: UPTIME STATISTICS
// ============================================================

function switchUptimeView(view) {
    currentUptimeView = view;
    
    // Update toggle buttons
    document.querySelectorAll('.toggle-btn[data-view]').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.view === view);
    });
    
    // Show/hide system selector
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
        // Clear chart if no system selected
        if (uptimeChart) {
            uptimeChart.destroy();
            uptimeChart = null;
        }
        updateUptimeStats(null);
        return;
    }
    
    fetch(`../backend/get_analytics_data.php?action=uptime_stats&system_id=${systemId}&days=30`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderUptimeChart(data.data, systemId);
                calculateUptimeStats(data.data);
            }
        })
        .catch(error => console.error('Error loading uptime data:', error));
}

function renderUptimeChart(data, systemId) {
    const ctx = document.getElementById('uptimeChart').getContext('2d');
    
    // Process data for chart
    const dates = [];
    const uptimePercentages = [];
    
    if (systemId > 0) {
        // Per-system: Calculate daily uptime
        const dailyData = {};
        
        data.forEach(entry => {
            const date = entry.date;
            if (!dailyData[date]) {
                dailyData[date] = { online: 0, total: 0 };
            }
            dailyData[date].total++;
            if (entry.status === 'online') {
                dailyData[date].online++;
            }
        });
        
        Object.keys(dailyData).sort().forEach(date => {
            dates.push(date);
            const uptime = (dailyData[date].online / dailyData[date].total) * 100;
            uptimePercentages.push(uptime.toFixed(2));
        });
    } else {
        // Overall: Group by date and status
        const dailyData = {};
        
        data.forEach(entry => {
            const date = entry.date;
            if (!dailyData[date]) {
                dailyData[date] = { online: 0, total: 0 };
            }
            dailyData[date].total += parseInt(entry.count);
            if (entry.status === 'online') {
                dailyData[date].online += parseInt(entry.count);
            }
        });
        
        Object.keys(dailyData).sort().forEach(date => {
            dates.push(date);
            const uptime = (dailyData[date].online / dailyData[date].total) * 100;
            uptimePercentages.push(uptime.toFixed(2));
        });
    }
    
    // Destroy existing chart
    if (uptimeChart) {
        uptimeChart.destroy();
    }
    
    // Create new chart
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
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 130,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            }
        }
    });
}

function calculateUptimeStats(data) {
    // Calculate current uptime, average, and incidents
    let totalUptime = 0;
    let incidents = 0;
    
    data.forEach(entry => {
        if (entry.status) {
            if (entry.status === 'online') {
                totalUptime += 100;
            }
            if (entry.status === 'down' || entry.status === 'offline') {
                incidents++;
            }
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
        document.getElementById('currentUptime').textContent = '--';
        document.getElementById('avgUptime').textContent = '--';
        document.getElementById('totalIncidents').textContent = '--';
        return;
    }
    
    document.getElementById('currentUptime').textContent = stats.current;
    document.getElementById('avgUptime').textContent = stats.average;
    document.getElementById('totalIncidents').textContent = stats.incidents;
}

// ============================================================
// SECTION 2: STATUS CHANGE HISTORY WITH PAGINATION
// ============================================================

function loadStatusHistory(days) {
    currentHistoryDays = days;
    
    // Reset to page 1 when changing filters
    currentPage = 1;
    
    // Update toggle buttons
    document.querySelectorAll('.toggle-btn[data-days]').forEach(btn => {
        btn.classList.toggle('active', parseInt(btn.dataset.days) === days);
    });
    
    const tbody = document.getElementById('statusHistoryBody');
    tbody.innerHTML = '<tr><td colspan="5" class="loading-cell">Loading...</td></tr>';
    
    fetch(`../backend/get_analytics_data.php?action=status_history&days=${days}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allStatusLogs = data.data; // Store all logs
                renderStatusHistory(); // Render with pagination
            }
        })
        .catch(error => {
            console.error('Error loading status history:', error);
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
    
    // Calculate pagination
    const totalPages = Math.ceil(allStatusLogs.length / itemsPerPage);
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const logsToDisplay = allStatusLogs.slice(startIndex, endIndex);
    
    // Render current page rows
    tbody.innerHTML = logsToDisplay.map(log => `
        <tr>
            <td>${formatDateTime(log.changed_at)}</td>
            <td><strong>${escapeHtml(log.system_name)}</strong></td>
            <td>
                <div class="status-change">
                    <span class="status-badge ${log.old_status}">${capitalize(log.old_status)}</span>
                    →
                    <span class="status-badge ${log.new_status}">${capitalize(log.new_status)}</span>
                </div>
            </td>
            <td>${escapeHtml(log.changed_by)}</td>
            <td>
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                    <span class="note-text">${log.change_note ? escapeHtml(log.change_note) : '<em>No note</em>'}</span>
                    <button 
                        class="btn-edit-note" 
                        onclick="openEditNoteModal(${log.id}, '${(log.change_note || 'No note').replace(/'/g, "\\'")}', '${log.system_name.replace(/'/g, "\\'")}')"
                        title="Edit note"
                    >
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        Edit
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
    
    // Update pagination controls
    updatePaginationInfo();
    renderPaginationControls(totalPages);
}

function updatePaginationInfo() {
    const paginationInfo = document.getElementById('paginationInfo');
    if (!paginationInfo) return;
    
    const totalItems = allStatusLogs.length;
    const startItem = totalItems === 0 ? 0 : (currentPage - 1) * itemsPerPage + 1;
    const endItem = Math.min(currentPage * itemsPerPage, totalItems);
    
    paginationInfo.textContent = `Showing ${startItem}-${endItem} of ${totalItems} entries`;
}

function renderPaginationControls(totalPages) {
    const paginationControls = document.getElementById('paginationControls');
    if (!paginationControls) return;
    
    if (totalPages <= 1) {
        paginationControls.innerHTML = '';
        return;
    }
    
    let controlsHTML = '';
    
    // Previous button
    controlsHTML += `
        <button 
            class="pagination-btn pagination-prev" 
            onclick="changePage(${currentPage - 1})"
            ${currentPage === 1 ? 'disabled' : ''}
        >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
            Previous
        </button>
    `;
    
    // Page numbers
    controlsHTML += '<div class="pagination-numbers">';
    
    // Always show first page
    controlsHTML += `
        <button 
            class="pagination-number ${currentPage === 1 ? 'active' : ''}" 
            onclick="changePage(1)"
        >1</button>
    `;
    
    // Show dots if needed
    if (currentPage > 3) {
        controlsHTML += '<span class="pagination-dots">...</span>';
    }
    
    // Show pages around current page
    for (let i = Math.max(2, currentPage - 1); i <= Math.min(totalPages - 1, currentPage + 1); i++) {
        controlsHTML += `
            <button 
                class="pagination-number ${currentPage === i ? 'active' : ''}" 
                onclick="changePage(${i})"
            >${i}</button>
        `;
    }
    
    // Show dots if needed
    if (currentPage < totalPages - 2) {
        controlsHTML += '<span class="pagination-dots">...</span>';
    }
    
    // Always show last page (if more than 1 page)
    if (totalPages > 1) {
        controlsHTML += `
            <button 
                class="pagination-number ${currentPage === totalPages ? 'active' : ''}" 
                onclick="changePage(${totalPages})"
            >${totalPages}</button>
        `;
    }
    
    controlsHTML += '</div>';
    
    // Next button
    controlsHTML += `
        <button 
            class="pagination-btn pagination-next" 
            onclick="changePage(${currentPage + 1})"
            ${currentPage === totalPages ? 'disabled' : ''}
        >
            Next
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
        </button>
    `;
    
    paginationControls.innerHTML = controlsHTML;
}

function changePage(pageNumber) {
    const totalPages = Math.ceil(allStatusLogs.length / itemsPerPage);
    
    if (pageNumber < 1 || pageNumber > totalPages) return;
    
    currentPage = pageNumber;
    renderStatusHistory();
    
    // No automatic scrolling - page stays in place
}

// ============================================================
// SECTION 3: MONTHLY REPORTS
// ============================================================

function loadMonthlyReport() {
    const systemId = document.getElementById('reportSystemSelect').value;
    const month = document.getElementById('reportMonthSelect').value;
    
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
            </div>
        `;
        exportBtn.disabled = true;
        return;
    }
    
    container.innerHTML = '<div class="loading-cell" style="padding: 60px;">Loading report...</div>';
    exportBtn.disabled = true;
    
    fetch(`../backend/get_analytics_data.php?action=monthly_report&system_id=${systemId}&month=${month}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderMonthlyReport(data.data);
                exportBtn.disabled = false;
            } else {
                container.innerHTML = '<div class="empty-state"><p>Error loading report</p></div>';
            }
        })
        .catch(error => {
            console.error('Error loading monthly report:', error);
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
                        </div>
                    `).join('')}
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
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            ` : ''}
        </div>
    `;
}

// ============================================================
// SECTION 4: STATUS TRENDS CHART
// ============================================================

function loadStatusTrends() {
    fetch('../backend/get_analytics_data.php?action=status_history&days=30')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderStatusTrendsChart(data.data);
            }
        })
        .catch(error => console.error('Error loading status trends:', error));
}

function renderStatusTrendsChart(logs) {
    const ctx = document.getElementById('statusTrendsChart').getContext('2d');
    
    // Count status changes by day
    const dailyCounts = {};
    
    logs.forEach(log => {
        const date = log.changed_at.split(' ')[0];
        if (!dailyCounts[date]) {
            dailyCounts[date] = 0;
        }
        dailyCounts[date]++;
    });
    
    const dates = Object.keys(dailyCounts).sort();
    const counts = dates.map(date => dailyCounts[date]);
    
    if (statusTrendsChart) {
        statusTrendsChart.destroy();
    }
    
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
                title: {
                    display: true,
                    text: 'Status Changes Over Time (Last 30 Days)',
                    font: { size: 16, weight: 'bold' }
                },
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                     suggestedMax: 20,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// ============================================================
// PDF EXPORT FUNCTIONALITY
// ============================================================

function exportReportToPDF() {
    const reportContent = document.getElementById('reportContent');
    
    if (!reportContent) {
        alert('No report to export. Please generate a report first.');
        return;
    }
    
    // Show loading state
    const exportBtn = document.getElementById('exportPdfBtn');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle></svg> Generating...';
    exportBtn.disabled = true;
    
    // Use html2canvas to capture the report
    html2canvas(reportContent, {
        scale: 2,
        useCORS: true,
        logging: false
    }).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('p', 'mm', 'a4');
        
        const imgWidth = 210; // A4 width in mm
        const pageHeight = 297; // A4 height in mm
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        let heightLeft = imgHeight;
        let position = 0;
        
        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;
        
        while (heightLeft >= 0) {
            position = heightLeft - imgHeight;
            pdf.addPage();
            pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
        }
        
        const systemName = document.getElementById('reportSystemSelect').selectedOptions[0].text;
        const month = document.getElementById('reportMonthSelect').value;
        const filename = `${systemName}-Report-${month}.pdf`;
        
        pdf.save(filename);
        
        // Restore button
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
    }).catch(error => {
        console.error('Error generating PDF:', error);
        alert('Error generating PDF. Please try again.');
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
    });
}

// ============================================================
// DASHBOARD INTEGRATION: Systems by Status Chart
// (This function is called from dashboard.php)
// ============================================================

function loadSystemsByStatus() {
    // This will be used in Phase 2b when we update the dashboard
    fetch('../backend/get_analytics_data.php?action=systems_by_status')
        .then(response => response.json())
        .then(data => {
            if (data.success && window.renderDashboardChart) {
                window.renderDashboardChart(data.data);
            }
        })
        .catch(error => console.error('Error loading systems by status:', error));
}

// ============================================================
// UTILITY FUNCTIONS
// ============================================================

function formatDateTime(datetime) {
    const date = new Date(datetime);
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
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



/* ============================================================
   PHASE 2 ENHANCEMENT: Edit Note Modal JavaScript
   ============================================================ */

// ============================================================
// EDIT NOTE FUNCTIONALITY
// ============================================================

let currentEditLogId = null;

function openEditNoteModal(logId, currentNote, systemName) {
    currentEditLogId = logId;
    
    // Set modal content
    document.getElementById('editNoteSystemName').textContent = systemName;
    document.getElementById('editNoteTextarea').value = currentNote === 'No note' ? '' : currentNote;
    
    // Show modal
    document.getElementById('editNoteModal').classList.add('show');
    
    // Focus on textarea
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
    
    const newNote = document.getElementById('editNoteTextarea').value.trim();
    
    // Create FormData
    const formData = new FormData();
    formData.append('log_id', currentEditLogId);
    formData.append('note', newNote);
    
    // Show loading state
    const saveBtn = event.target.querySelector('button[type="submit"]');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = 'Saving...';
    saveBtn.disabled = true;
    
    // Send to backend
    fetch('../backend/update_log_note.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload the current page data to reflect changes
            loadStatusHistory(currentHistoryDays);
            
            // Close modal
            closeEditNoteModal();
            
            // Show success message
            showSuccessMessage('Note updated successfully!');
        } else {
            alert(data.message || 'Error updating note');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating note');
    })
    .finally(() => {
        // Restore button
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
}

function showSuccessMessage(message) {
    // Simple success message
    const msgDiv = document.createElement('div');
    msgDiv.className = 'success-toast';
    msgDiv.textContent = message;
    msgDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #10b981;
        color: white;
        padding: 16px 24px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(msgDiv);
    
    setTimeout(() => {
        msgDiv.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => msgDiv.remove(), 300);
    }, 3000);
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('editNoteModal');
    if (event.target === modal) {
        closeEditNoteModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeEditNoteModal();
    }
});

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);