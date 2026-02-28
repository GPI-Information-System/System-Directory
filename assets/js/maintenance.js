/* ============================================================
   G-Portal - Schedule Maintenance JavaScript
   Handles: Calendar, Maintenance Modal, Side Panel
   ============================================================ */

// ============================================================
// STATE
// ============================================================

const MaintenanceApp = {
    calendarYear: new Date().getFullYear(),
    calendarMonth: new Date().getMonth(),
    calendarSchedules: [],

    currentSystemId: null,
    currentSystemName: null,
    editingScheduleId: null,

    sidePanelDate: null,

    deletingScheduleId: null,
    deletingScheduleName: null,

    _panelCountdownInterval: null,
    _detailCountdownInterval: null,
};

// ============================================================
// INIT
// ============================================================

document.addEventListener('DOMContentLoaded', function () {
    initCalendar();
});

// ============================================================
// CALENDAR
// ============================================================

function initCalendar() {
    renderCalendar();
    loadCalendarSchedules();
}

function renderCalendar() {
    const year  = MaintenanceApp.calendarYear;
    const month = MaintenanceApp.calendarMonth;

    const label = new Date(year, month, 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    document.getElementById('calMonthLabel').textContent = label;

    const daysContainer = document.getElementById('calendarDays');
    daysContainer.innerHTML = '';

    const firstDay    = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    const today  = new Date();
    const todayY = today.getFullYear();
    const todayM = today.getMonth();
    const todayD = today.getDate();

    for (let i = 0; i < firstDay; i++) {
        const empty = document.createElement('div');
        empty.className = 'cal-day cal-empty';
        daysContainer.appendChild(empty);
    }

    for (let d = 1; d <= daysInMonth; d++) {
        const cell    = document.createElement('div');
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        cell.className    = 'cal-day';
        cell.dataset.date = dateStr;

        if (year === todayY && month === todayM && d === todayD) {
            cell.classList.add('cal-today');
        }

        const matchingSchedules = getSchedulesForDate(dateStr);

        if (matchingSchedules.length > 0) {
            cell.classList.add('cal-has-maintenance');
            cell.appendChild(document.createTextNode(d));
            const dot = document.createElement('span');
            dot.className = 'cal-dot';
            cell.appendChild(dot);
            if (matchingSchedules.length > 1) {
                const count = document.createElement('span');
                count.className = 'cal-count';
                count.textContent = `+${matchingSchedules.length}`;
                cell.appendChild(count);
            }
        } else {
            cell.textContent = d;
        }

        cell.addEventListener('click', () => openSidePanelForDate(dateStr));
        daysContainer.appendChild(cell);
    }
}

function loadCalendarSchedules() {
    const year     = MaintenanceApp.calendarYear;
    const month    = MaintenanceApp.calendarMonth;
    const monthStr = `${year}-${String(month + 1).padStart(2, '0')}`;

    fetch(`../backend/maintenance/get_maintenance.php?action=calendar&month=${monthStr}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                MaintenanceApp.calendarSchedules = data.data || [];
                renderCalendar();
            }
        })
        .catch(err => console.error('Calendar load error:', err));
}

function getSchedulesForDate(dateStr) {
    return MaintenanceApp.calendarSchedules.filter(s => {
        const start = s.start_datetime.substring(0, 10);
        const end   = s.end_datetime.substring(0, 10);
        return dateStr >= start && dateStr <= end;
    });
}

function changeCalendarMonth(delta) {
    MaintenanceApp.calendarMonth += delta;
    if (MaintenanceApp.calendarMonth > 11) {
        MaintenanceApp.calendarMonth = 0;
        MaintenanceApp.calendarYear++;
    } else if (MaintenanceApp.calendarMonth < 0) {
        MaintenanceApp.calendarMonth = 11;
        MaintenanceApp.calendarYear--;
    }
    loadCalendarSchedules();
}

// ============================================================
// SCHEDULE MAINTENANCE MODAL — OPEN (CREATE mode)
// ============================================================

function openMaintenanceModal(systemId, systemName) {
    document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));

    MaintenanceApp.currentSystemId   = systemId;
    MaintenanceApp.currentSystemName = systemName;
    MaintenanceApp.editingScheduleId = null;

    document.getElementById('maintenanceForm').reset();

    document.getElementById('maintenanceAction').value   = 'create';
    document.getElementById('maintenanceId').value       = '';
    document.getElementById('maintenanceSystemId').value = systemId;
    document.getElementById('maintenanceModalTitle').textContent = 'Schedule Maintenance';
    document.getElementById('maintenanceSystemName').textContent = systemName;

    document.getElementById('maintenanceStatus').value = 'Scheduled';
    onMaintenanceStatusChange('Scheduled');

    const now           = new Date();
    const twoHoursLater = new Date(now.getTime() + 2 * 60 * 60 * 1000);
    document.getElementById('maintenanceStart').value = formatDatetimeLocal(now);
    document.getElementById('maintenanceEnd').value   = formatDatetimeLocal(twoHoursLater);

    setSaveButtonMode('save');

    const emailToggle = document.getElementById('emailNotifyToggle');
    if (emailToggle) emailToggle.checked = false;
    const emailArea = document.getElementById('emailRecipientArea');
    if (emailArea) emailArea.style.display = 'none';
    clearEmailTags('emailTagWrapper', 'emailTags');

    document.getElementById('maintenanceModal').classList.add('show');

    loadExistingSchedules(systemId);
}

function closeMaintenanceModal() {
    document.getElementById('maintenanceModal').classList.remove('show');
    MaintenanceApp.editingScheduleId = null;
    const group = document.getElementById('changeToOnlineGroup');
    if (group) {
        group.style.display = 'none';
        const noRadio = group.querySelector('input[value="no"]');
        if (noRadio) noRadio.checked = true;
    }
    const emailToggle = document.getElementById('emailNotifyToggle');
    if (emailToggle) emailToggle.checked = false;
    const emailArea = document.getElementById('emailRecipientArea');
    if (emailArea) emailArea.style.display = 'none';
    clearEmailTags('emailTagWrapper', 'emailTags');
}

function onMaintenanceStatusChange(value) {
    const group = document.getElementById('changeToOnlineGroup');
    if (!group) return;
    if (value === 'Done') {
        group.style.display = 'none';
        void group.offsetHeight;
        group.style.display = 'block';
    } else {
        group.style.display = 'none';
        const noRadio = group.querySelector('input[value="no"]');
        if (noRadio) noRadio.checked = true;
    }
}

// ============================================================
// LOAD EXISTING SCHEDULES LIST (inside modal)
// ============================================================

function loadExistingSchedules(systemId) {
    const list = document.getElementById('existingSchedulesList');
    list.innerHTML = '<div class="schedules-loading">Loading...</div>';

    fetch(`../backend/maintenance/get_maintenance.php?action=system&system_id=${systemId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderExistingSchedules(data.data);
            } else {
                list.innerHTML = '<div class="schedules-empty">Could not load schedules.</div>';
            }
        })
        .catch(() => {
            list.innerHTML = '<div class="schedules-empty">Could not load schedules.</div>';
        });
}

function renderExistingSchedules(schedules) {
    const list = document.getElementById('existingSchedulesList');

    if (!schedules || schedules.length === 0) {
        list.innerHTML = '<div class="schedules-empty">No existing schedules for this system.</div>';
        return;
    }

    list.innerHTML = schedules.map(s => `
        <div class="existing-schedule-item">
            <div class="existing-schedule-info">
                <div class="existing-schedule-title">${escapeHtml(s.title)}</div>
                <div class="existing-schedule-dates">
                    ${formatDateDisplay(s.start_datetime)} → ${formatDateDisplay(s.end_datetime)}
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:6px;">
                <span class="maint-status-badge ${getStatusClass(s.status)}">${escapeHtml(s.status)}</span>
                <div class="existing-schedule-actions">
                    <button class="btn-sched-edit"
                            data-action="edit-schedule"
                            data-id="${s.id}"
                            title="Edit">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                    </button>
                    <button class="btn-sched-delete"
                            data-action="delete-schedule"
                            data-id="${s.id}"
                            data-title="${escapeHtml(s.title)}"
                            title="Delete">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

// ============================================================
// EDIT EXISTING SCHEDULE (load into form)
// ============================================================

function editExistingSchedule(scheduleId) {
    fetch(`../backend/maintenance/get_maintenance.php?action=single&id=${scheduleId}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                showToast('Could not load schedule.', 'error');
                return;
            }
            const s = data.data;

            MaintenanceApp.editingScheduleId = scheduleId;

            document.getElementById('maintenanceAction').value   = 'update';
            document.getElementById('maintenanceId').value       = scheduleId;
            document.getElementById('maintenanceSystemId').value = s.system_id;

            document.getElementById('maintenanceModalTitle').textContent  = 'Edit Maintenance Schedule';
            document.getElementById('maintenanceTitle').value             = s.title;
            document.getElementById('maintenanceStart').value             = formatDatetimeLocal(parseDbDatetime(s.start_datetime));
            document.getElementById('maintenanceEnd').value               = formatDatetimeLocal(parseDbDatetime(s.end_datetime));
            document.getElementById('maintenanceStatus').value            = s.status;
            onMaintenanceStatusChange(s.status);
            document.getElementById('maintenanceDescription').value       = s.description || '';

            setSaveButtonMode('update');
        })
        .catch(() => showToast('Error loading schedule.', 'error'));
}

// ============================================================
// SAVE SCHEDULE (create or update)
// ============================================================

function saveMaintenanceSchedule(event) {
    event.preventDefault();

    const form     = document.getElementById('maintenanceForm');
    const formData = new FormData(form);
    const action   = formData.get('action');

    if (!formData.get('system_id') || formData.get('system_id') === '0') {
        formData.set('system_id', MaintenanceApp.currentSystemId);
    }

    const start = new Date(formData.get('start_datetime'));
    const end   = new Date(formData.get('end_datetime'));

    if (end <= start) {
        showToast('End date/time must be after start date/time.', 'error');
        return;
    }

    if (action === 'create') {
        const existingItems = document.querySelectorAll('#existingSchedulesList .existing-schedule-item');
        for (const item of existingItems) {
            const badge = item.querySelector('.maint-status-badge');
            if (badge) {
                const badgeText = badge.textContent.trim();
                if (badgeText === 'Scheduled' || badgeText === 'In Progress') {
                    showToast('This system already has an active maintenance schedule. Please complete or delete it before creating a new one.', 'error');
                    return;
                }
            }
        }
    }

    const emailToggle = document.getElementById('emailNotifyToggle');
    if (emailToggle && emailToggle.checked) {
        const emailList = getEmailTags('emailTagWrapper');
        if (emailList.length > 0) {
            formData.append('send_email', 'yes');
            formData.append('email_recipients', JSON.stringify(emailList));
        }
    }

    const saveBtn  = document.getElementById('maintenanceSaveBtn');
    const origHTML = saveBtn.innerHTML;
    saveBtn.disabled  = true;
    saveBtn.innerHTML = '<span class="loading-spinner" style="width:16px;height:16px;border-width:2px;display:inline-block;"></span> Saving...';

    fetch('../backend/maintenance/save_maintenance.php', {
        method: 'POST',
        body: formData,
    })
    .then(r => r.json())
    .then(data => {
        saveBtn.disabled  = false;
        saveBtn.innerHTML = origHTML;

        if (data.success) {
            showToast(data.message || 'Schedule saved!', 'success');
            closeMaintenanceModal();
            loadCalendarSchedules();
            if (data.system_switched) {
                setTimeout(() => location.reload(), 800);
            }
        } else {
            showToast(data.message || 'Error saving schedule.', 'error');
        }
    })
    .catch(() => {
        saveBtn.disabled  = false;
        saveBtn.innerHTML = origHTML;
        showToast('Network error. Please try again.', 'error');
    });
}

// ============================================================
// DELETE MAINTENANCE
// ============================================================

function promptDeleteMaintenance(id, name) {
    MaintenanceApp.deletingScheduleId   = id;
    MaintenanceApp.deletingScheduleName = name;
    document.getElementById('deleteMaintenanceName').textContent = name;
    document.getElementById('deleteMaintenanceModal').classList.add('show');
}

function closeDeleteMaintenanceModal() {
    document.getElementById('deleteMaintenanceModal').classList.remove('show');
    MaintenanceApp.deletingScheduleId   = null;
    MaintenanceApp.deletingScheduleName = null;
}

function confirmDeleteMaintenance() {
    const id = MaintenanceApp.deletingScheduleId;
    if (!id) return;

    const formData = new FormData();
    formData.append('id', id);

    fetch('../backend/maintenance/delete_maintenance.php', {
        method: 'POST',
        body: formData,
    })
    .then(r => r.json())
    .then(data => {
        closeDeleteMaintenanceModal();
        if (data.success) {
            showToast('Schedule deleted.', 'success');
            loadCalendarSchedules();

            if (MaintenanceApp.sidePanelDate) {
                loadSidePanelSchedules(MaintenanceApp.sidePanelDate);
            }

            if (MaintenanceApp.currentSystemId) {
                loadExistingSchedules(MaintenanceApp.currentSystemId);
            }
        } else {
            showToast(data.message || 'Error deleting schedule.', 'error');
        }
    })
    .catch(() => {
        closeDeleteMaintenanceModal();
        showToast('Network error.', 'error');
    });
}

// ============================================================
// SIDE PANEL
// ============================================================

function openSidePanelForDate(dateStr) {
    MaintenanceApp.sidePanelDate = dateStr;

    const d     = new Date(dateStr + 'T00:00:00');
    const label = d.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    document.getElementById('sidePanelDate').textContent = label;

    document.getElementById('maintenanceSidePanel').classList.add('open');
    loadSidePanelSchedules(dateStr);
}

function loadSidePanelSchedules(dateStr) {
    const body = document.getElementById('sidePanelBody');
    body.innerHTML = `
        <div class="side-panel-loading">
            <div class="loading-spinner"></div>
            <span>Loading schedules...</span>
        </div>
    `;

    fetch(`../backend/maintenance/get_maintenance.php?action=day&date=${dateStr}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderSidePanelSchedules(data.data);
            } else {
                body.innerHTML = '<div class="side-panel-empty"><p>Could not load schedules.</p></div>';
            }
        })
        .catch(() => {
            body.innerHTML = '<div class="side-panel-empty"><p>Network error.</p></div>';
        });
}

function renderSidePanelSchedules(schedules) {
    const body = document.getElementById('sidePanelBody');

    if (!schedules || schedules.length === 0) {
        body.innerHTML = `
            <div class="side-panel-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <p>No maintenance scheduled for this date.</p>
            </div>
        `;
        return;
    }

    body.innerHTML = schedules.map(s => {
        const statusClean = (s.status || '').trim();
        const isFrozen    = statusClean === 'Done';

        const isExceeded  = isFrozen
            ? (s.exceeded_duration !== null && s.exceeded_duration !== undefined)
            : (statusClean === 'In Progress' && new Date() > parseDbDatetime(s.end_datetime));

        const exceededText = isExceeded
            ? (isFrozen ? formatStoredDuration(s.exceeded_duration) : '')
            : '';

        return `
        <div class="side-panel-schedule-card ${isExceeded ? 'spc-exceeded' : ''}"
             data-action="open-detail-modal"
             data-schedule='${JSON.stringify(s).replace(/'/g, "&#39;")}'>
            <div class="spc-top">
                <div class="spc-title">${escapeHtml(s.title)}</div>
                <span class="maint-status-badge ${getStatusClass(s.status)}">${escapeHtml(s.status)}</span>
            </div>

            <div class="spc-system">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                ${escapeHtml(s.system_name)}
            </div>

            <div class="spc-time">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <span>${formatDateDisplay(s.start_datetime)}</span>
                <span>→</span>
                <span>${formatDateDisplay(s.end_datetime)}</span>
            </div>

            ${isExceeded ? `
            <div class="spc-exceeded-banner">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <span>Exceeded by <span class="spc-countdown" data-end="${s.end_datetime}" data-frozen="${isFrozen ? 'true' : 'false'}">${exceededText}</span></span>
            </div>` : ''}

            ${s.description ? `<div class="spc-description">${escapeHtml(s.description)}</div>` : ''}

            <div class="spc-footer">
                <div class="spc-created-by">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    ${escapeHtml(s.created_by_name)}
                </div>
                <div class="spc-actions">
                    <button class="spc-btn-edit"
                            data-action="edit-from-panel"
                            data-id="${s.id}"
                            data-system-id="${s.system_id}"
                            data-system-name="${escapeHtml(s.system_name)}">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        Edit
                    </button>
                    <button class="spc-btn-delete"
                            data-action="delete-schedule"
                            data-id="${s.id}"
                            data-title="${escapeHtml(s.title)}">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                        Delete
                    </button>
                </div>
            </div>
        </div>
        `;
    }).join('');

    startExceededCountdowns();
}

function editFromSidePanel(scheduleId, systemId, systemName) {
    MaintenanceApp.currentSystemId   = systemId;
    MaintenanceApp.currentSystemName = systemName;

    document.getElementById('maintenanceForm').reset();
    document.getElementById('maintenanceSystemId').value        = systemId;
    document.getElementById('maintenanceSystemName').textContent = systemName;

    document.getElementById('maintenanceModal').classList.add('show');
    loadExistingSchedules(systemId);
    editExistingSchedule(scheduleId);
}

function closeSidePanel() {
    document.getElementById('maintenanceSidePanel').classList.remove('open');
    MaintenanceApp.sidePanelDate = null;
    if (MaintenanceApp._panelCountdownInterval) {
        clearInterval(MaintenanceApp._panelCountdownInterval);
        MaintenanceApp._panelCountdownInterval = null;
    }
}

// ============================================================
// EVENT DELEGATION
// ============================================================

document.addEventListener('click', function (e) {
    const cardBtn = e.target.closest('[data-action="open-detail-modal"]');
    if (cardBtn && !e.target.closest('button')) {
        const schedule = JSON.parse(cardBtn.dataset.schedule.replace(/&#39;/g, "'"));
        fetch(`../backend/maintenance/get_maintenance.php?action=single&id=${schedule.id}`)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data) {
                    openScheduleDetailModal(data.data);
                } else {
                    openScheduleDetailModal(schedule);
                }
            })
            .catch(() => openScheduleDetailModal(schedule));
        return;
    }

    const editBtn = e.target.closest('[data-action="edit-schedule"]');
    if (editBtn) {
        editExistingSchedule(editBtn.dataset.id);
        return;
    }

    const deleteBtn = e.target.closest('[data-action="delete-schedule"]');
    if (deleteBtn) {
        promptDeleteMaintenance(deleteBtn.dataset.id, deleteBtn.dataset.title);
        return;
    }

    const editPanelBtn = e.target.closest('[data-action="edit-from-panel"]');
    if (editPanelBtn) {
        editFromSidePanel(editPanelBtn.dataset.id, editPanelBtn.dataset.systemId, editPanelBtn.dataset.systemName);
        return;
    }
});

// ============================================================
// SCHEDULE DETAIL MODAL
// ============================================================

function openScheduleDetailModal(s) {
    if (MaintenanceApp._detailCountdownInterval) {
        clearInterval(MaintenanceApp._detailCountdownInterval);
        MaintenanceApp._detailCountdownInterval = null;
    }

    const statusClean = (s.status || '').trim();
    const isFrozen    = statusClean === 'Done';

    const isExceeded  = isFrozen
        ? (s.exceeded_duration !== null && s.exceeded_duration !== undefined)
        : (statusClean === 'In Progress' && new Date() > parseDbDatetime(s.end_datetime));

    let exceededHtml = '';
    if (isExceeded) {
        if (isFrozen) {
            const frozenText = formatStoredDuration(s.exceeded_duration);
            exceededHtml = `
                <div class="sdm-exceeded-block">
                    <div class="sdm-exceeded-label">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                            <line x1="12" y1="9" x2="12" y2="13"></line>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                        Exceeded Downtime
                    </div>
                    <div class="sdm-countdown sdm-countdown-frozen">${frozenText}</div>
                </div>`;
        } else {
            exceededHtml = `
                <div class="sdm-exceeded-block">
                    <div class="sdm-exceeded-label">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                            <line x1="12" y1="9" x2="12" y2="13"></line>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                        Exceeded Downtime
                    </div>
                    <div class="sdm-countdown" id="sdmCountdown">${formatExceededDuration(s.end_datetime)}</div>
                </div>`;
        }
    }

    const html = `
        <div class="sdm-row">
            <div class="sdm-label">System</div>
            <div class="sdm-value sdm-system-name">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                <span>${escapeHtml(s.system_name)}</span>
            </div>
        </div>
        <div class="sdm-row">
            <div class="sdm-label">Status</div>
            <div class="sdm-value"><span class="maint-status-badge ${getStatusClass(s.status)}">${escapeHtml(s.status)}</span></div>
        </div>
        <div class="sdm-row">
            <div class="sdm-label">Start Time</div>
            <div class="sdm-value">${formatDateDisplay(s.start_datetime)}</div>
        </div>
        <div class="sdm-row">
            <div class="sdm-label">End Time</div>
            <div class="sdm-value ${isExceeded ? 'sdm-value-danger' : ''}">${formatDateDisplay(s.end_datetime)}</div>
        </div>
        ${s.description ? `
        <div class="sdm-row">
            <div class="sdm-label">Description</div>
            <div class="sdm-value">${escapeHtml(s.description)}</div>
        </div>` : ''}
        <div class="sdm-row">
            <div class="sdm-label">Scheduled By</div>
            <div class="sdm-value">${escapeHtml(s.created_by_name)}</div>
        </div>
        ${exceededHtml}
    `;

    document.getElementById('scheduleDetailTitle').textContent = s.title;
    document.getElementById('scheduleDetailBody').innerHTML    = html;
    document.getElementById('scheduleDetailModal').classList.add('show');

    if (isExceeded && !isFrozen) {
        MaintenanceApp._detailCountdownInterval = setInterval(() => {
            const el = document.getElementById('sdmCountdown');
            if (!el) {
                clearInterval(MaintenanceApp._detailCountdownInterval);
                MaintenanceApp._detailCountdownInterval = null;
                return;
            }
            el.textContent = formatExceededDuration(s.end_datetime);
        }, 1000);
    }
}

function closeScheduleDetailModal() {
    document.getElementById('scheduleDetailModal').classList.remove('show');
    if (MaintenanceApp._detailCountdownInterval) {
        clearInterval(MaintenanceApp._detailCountdownInterval);
        MaintenanceApp._detailCountdownInterval = null;
    }
}

function startExceededCountdowns() {
    if (MaintenanceApp._panelCountdownInterval) {
        clearInterval(MaintenanceApp._panelCountdownInterval);
        MaintenanceApp._panelCountdownInterval = null;
    }

    function getLiveEls() {
        return Array.from(document.querySelectorAll('.spc-countdown'))
            .filter(el => el.dataset.frozen === 'false');
    }

    if (getLiveEls().length === 0) return;

    function tick() {
        const els = getLiveEls();
        if (els.length === 0) {
            clearInterval(MaintenanceApp._panelCountdownInterval);
            MaintenanceApp._panelCountdownInterval = null;
            return;
        }
        els.forEach(el => {
            el.textContent = formatExceededDuration(el.dataset.end);
        });
    }
    tick();
    MaintenanceApp._panelCountdownInterval = setInterval(tick, 1000);
}

// ============================================================
// DATE HELPERS
// ============================================================

function parseDbDatetime(dtStr) {
    if (!dtStr) return new Date(NaN);
    return new Date(dtStr.replace(' ', 'T'));
}

function formatExceededDuration(endDatetime) {
    const end  = parseDbDatetime(endDatetime);
    const now  = new Date();
    const diff = Math.floor((now - end) / 1000);

    if (diff <= 0) return '0s';

    const h = Math.floor(diff / 3600);
    const m = Math.floor((diff % 3600) / 60);
    const sec = diff % 60;

    if (h > 0) return `${h}h ${m}m ${sec}s`;
    if (m > 0) return `${m}m ${sec}s`;
    return `${sec}s`;
}

function formatStoredDuration(seconds) {
    const s = parseInt(seconds, 10);
    if (isNaN(s) || s <= 0) return '0s';

    const h   = Math.floor(s / 3600);
    const m   = Math.floor((s % 3600) / 60);
    const sec = s % 60;

    if (h > 0) return `${h}h ${m}m ${sec}s`;
    if (m > 0) return `${m}m ${sec}s`;
    return `${sec}s`;
}

// ============================================================
// HELPERS
// ============================================================

function setSaveButtonMode(mode) {
    const btn = document.getElementById('maintenanceSaveBtn');
    if (mode === 'update') {
        btn.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
            Update Schedule
        `;
    } else {
        btn.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                <polyline points="7 3 7 8 15 8"></polyline>
            </svg>
            Save Schedule
        `;
    }
}

function formatDatetimeLocal(date) {
    const pad = n => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function formatDateDisplay(datetimeStr) {
    const d = parseDbDatetime(datetimeStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ' ' +
           d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

function getStatusClass(status) {
    const map = {
        'Scheduled':   'maint-status-scheduled',
        'In Progress': 'maint-status-in-progress',
        'Done':        'maint-status-done',
    };
    return map[status] || 'maint-status-scheduled';
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// ============================================================
// TOAST NOTIFICATIONS
// ============================================================

function showToast(message, type = 'success') {
    document.querySelectorAll('.maintenance-toast').forEach(t => t.remove());

    const icon = type === 'success'
        ? `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>`
        : `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>`;

    const toast = document.createElement('div');
    toast.className = `maintenance-toast toast-${type}`;
    toast.innerHTML = `${icon}<span>${escapeHtml(message)}</span>`;
    document.body.appendChild(toast);

    setTimeout(() => toast.remove(), 3200);
}

// ============================================================
// CLOSE MODALS ON OUTSIDE CLICK
// ============================================================

const _originalWindowOnClick = window.onclick;
window.onclick = function (event) {
    if (_originalWindowOnClick) _originalWindowOnClick(event);

    const maintenanceModal = document.getElementById('maintenanceModal');
    if (maintenanceModal && event.target === maintenanceModal) {
        closeMaintenanceModal();
    }

    const deleteMaintenanceModal = document.getElementById('deleteMaintenanceModal');
    if (deleteMaintenanceModal && event.target === deleteMaintenanceModal) {
        closeDeleteMaintenanceModal();
    }

    const bulkModal = document.getElementById('bulkMaintenanceModal');
    if (bulkModal && event.target === bulkModal) {
        requestCloseBulkModal();
    }
};

/* ============================================================
   BULK MAINTENANCE SCHEDULING
   ============================================================ */

// ============================================================
// BULK SCHEDULE STATE
// ============================================================

const BulkSchedule = {
    active: false,
    selectedIds: new Set(),
    selectedNames: {},
    modalSnapshot: null,
};

// ============================================================
// TOGGLE BULK MODE
// ============================================================

function toggleBulkMode() {
    BulkSchedule.active = !BulkSchedule.active;

    const btn   = document.getElementById('btnBulkSchedule');
    const label = document.getElementById('btnBulkScheduleLabel');
    const bar   = document.getElementById('bulkActionBar');

    if (BulkSchedule.active) {
        document.body.classList.add('bulk-mode');
        btn.classList.add('bulk-active');
        label.textContent = 'Exit Selection';
        bar.classList.add('visible');
        clearBulkSelection();
    } else {
        document.body.classList.remove('bulk-mode');
        btn.classList.remove('bulk-active');
        label.textContent = 'Schedule Multiple';
        bar.classList.remove('visible');
        clearBulkSelection();
    }
}

// ============================================================
// CARD CLICK — Toggle selection
// ============================================================

function toggleCardSelection(event, systemId, systemName) {
    if (!BulkSchedule.active) return;
    event.stopPropagation();

    const card     = document.querySelector(`.system-card[data-system-id="${systemId}"]`);
    const checkbox = document.getElementById(`bulk-check-${systemId}`);

    if (!card || !checkbox) return;

    if (BulkSchedule.selectedIds.has(systemId)) {
        BulkSchedule.selectedIds.delete(systemId);
        delete BulkSchedule.selectedNames[String(systemId)];
        card.classList.remove('bulk-selected');
        checkbox.classList.remove('checked');
    } else {
        BulkSchedule.selectedIds.add(systemId);
        BulkSchedule.selectedNames[String(systemId)] = systemName;
        card.classList.add('bulk-selected');
        checkbox.classList.add('checked');
    }

    updateBulkBar();
}

document.addEventListener('click', function(e) {
    if (!BulkSchedule.active) return;

    const card = e.target.closest('.system-card:not(.bulk-excluded)');
    if (!card) return;

    if (e.target.closest('.bulk-checkbox-overlay')) return;
    if (e.target.closest('button') || e.target.closest('a')) return;

    const systemId   = parseInt(card.dataset.systemId);
    const systemName = card.dataset.systemName;
    if (!systemId) return;

    toggleCardSelection(e, systemId, systemName);
});

// ============================================================
// UPDATE FLOATING BAR
// ============================================================

function updateBulkBar() {
    const count = BulkSchedule.selectedIds.size;

    document.getElementById('bulkSelectedCount').textContent  = count;
    document.getElementById('bulkSelectedPlural').textContent = count === 1 ? '' : 's';
    document.getElementById('bulkProceedCount').textContent   = count;

    const proceedBtn = document.getElementById('bulkProceedBtn');
    if (proceedBtn) proceedBtn.disabled = count === 0;

    const btn   = document.getElementById('bulkSelectAllBtn');
    const label = document.getElementById('bulkSelectAllLabel');
    if (btn && label) {
        const totalSelectable = document.querySelectorAll('.system-card:not(.bulk-excluded)').length;
        const allSelected     = count >= totalSelectable && totalSelectable > 0;
        label.textContent     = allSelected ? 'Deselect All' : 'Select All';
        btn.classList.toggle('all-selected', allSelected);
    }
}

// ============================================================
// CLEAR SELECTIONS
// ============================================================

function clearBulkSelection() {
    BulkSchedule.selectedIds.clear();
    BulkSchedule.selectedNames = {};

    document.querySelectorAll('.system-card.bulk-selected').forEach(c => c.classList.remove('bulk-selected'));
    document.querySelectorAll('.bulk-checkbox.checked').forEach(cb => cb.classList.remove('checked'));

    updateBulkBar();
}

// ============================================================
// OPEN BULK MODAL
// ============================================================

async function openBulkModal() {
    if (BulkSchedule.selectedIds.size === 0) {
        showToast('Please select at least one system.', 'error');
        return;
    }

    const modal = document.getElementById('bulkMaintenanceModal');
    const bar   = document.getElementById('bulkActionBar');

    BulkSchedule.modalSnapshot = {
        ids:            Array.from(BulkSchedule.selectedIds),
        names:          Object.assign({}, BulkSchedule.selectedNames),
        conflictIds:    new Set(),
        conflictTitles: {},
    };

    function updateSystemsDisplay() {
        document.getElementById('bulkModalCount').textContent = BulkSchedule.modalSnapshot.ids.length;
        const chipContainer = document.getElementById('bulkModalSystemsList');
        chipContainer.innerHTML = BulkSchedule.modalSnapshot.ids.map(id => {
            const name        = BulkSchedule.modalSnapshot.names[String(id)] || `System #${id}`;
            const hasConflict = BulkSchedule.modalSnapshot.conflictIds.has(id);
            const conflictClass = hasConflict ? ' conflict' : '';
            const conflictTitle = hasConflict
                ? `title="⚠ Already has active schedule: ${escapeHtml(BulkSchedule.modalSnapshot.conflictTitles[id] || '')}"`
                : `title="${escapeHtml(name)}"`;
            const warnIcon = hasConflict
                ? `<span class="chip-warn-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L1 21h22L12 2zm0 3.5L20.5 19h-17L12 5.5zM11 10v4h2v-4h-2zm0 6v2h2v-2h-2z"/></svg></span>`
                : '';
            return `<span class="bulk-system-chip${conflictClass}" ${conflictTitle}>
                        <span class="bulk-system-chip-dot"></span>
                        ${escapeHtml(name)}${warnIcon}
                    </span>`;
        }).join('');
    }

    if (modal.classList.contains('show')) {
        updateSystemsDisplay();
        return;
    }

    if (bar) bar.classList.remove('visible');

    document.getElementById('bulkMaintenanceForm').reset();
    document.getElementById('bulkResults').style.display        = 'none';
    document.getElementById('bulkConflictBanner').style.display = 'none';
    document.getElementById('bulkSaveBtn').disabled             = false;
    document.getElementById('bulkMaintenanceForm').classList.remove('bulk-form-blocked');

    const now           = new Date();
    const twoHoursLater = new Date(now.getTime() + 2 * 60 * 60 * 1000);
    document.getElementById('bulkStart').value = formatDatetimeLocal(now);
    document.getElementById('bulkEnd').value   = formatDatetimeLocal(twoHoursLater);

    updateSystemsDisplay();
    modal.classList.add('show');

    await checkBulkConflicts(updateSystemsDisplay);
}

async function checkBulkConflicts(onComplete) {
    const snapshot = BulkSchedule.modalSnapshot;
    if (!snapshot) return;

    for (const systemId of snapshot.ids) {
        const formData = new FormData();
        formData.append('action', 'create');
        formData.append('system_id', systemId);
        formData.append('title', '__conflict_check__');
        formData.append('start_datetime', document.getElementById('bulkStart').value);
        formData.append('end_datetime', document.getElementById('bulkEnd').value);
        formData.append('status', 'Scheduled');
        formData.append('dry_run', '1');

        try {
            const response = await fetch('../backend/maintenance/save_maintenance.php', {
                method: 'POST', body: formData,
            });
            const data = await response.json();

            if (!data.success && data.message && data.message.toLowerCase().includes('active')) {
                snapshot.conflictIds.add(systemId);
                const match = data.message.match(/"([^"]+)"/);
                snapshot.conflictTitles[systemId] = match ? match[1] : 'existing schedule';
            }
        } catch (e) { /* skip */ }
    }

    if (onComplete) onComplete();
    showBulkConflictBanner();
}

function showBulkConflictBanner() {
    const snapshot = BulkSchedule.modalSnapshot;
    const banner   = document.getElementById('bulkConflictBanner');
    const titleEl  = document.getElementById('bulkConflictTitle');
    const detailEl = document.getElementById('bulkConflictDetail');
    const saveBtn  = document.getElementById('bulkSaveBtn');
    const form     = document.getElementById('bulkMaintenanceForm');

    if (!snapshot || snapshot.conflictIds.size === 0) {
        if (banner) banner.style.display = 'none';
        return;
    }

    const conflictCount = snapshot.conflictIds.size;
    const totalCount    = snapshot.ids.length;
    const allConflict   = conflictCount === totalCount;

    const conflictTags = Array.from(snapshot.conflictIds).map(id => {
        const name  = snapshot.names[String(id)] || `System #${id}`;
        const sched = snapshot.conflictTitles[id] ? ` · "${escapeHtml(snapshot.conflictTitles[id])}"` : '';
        return `<span class="bulk-conflict-system-tag">${escapeHtml(name)}${sched}</span>`;
    }).join(' ');

    banner.className = `bulk-conflict-banner ${allConflict ? 'all-conflict' : 'partial-conflict'}`;

    if (allConflict) {
        titleEl.textContent = 'Cannot schedule — all selected systems have active maintenance';
        detailEl.innerHTML  = `Complete or delete the existing schedule before adding a new one: ${conflictTags}`;
        saveBtn.disabled    = true;
        form.classList.add('bulk-form-blocked');
    } else {
        titleEl.textContent = `${conflictCount} of ${totalCount} system${conflictCount > 1 ? 's' : ''} will be skipped`;
        detailEl.innerHTML  = `These systems already have active schedules and will be skipped automatically: ${conflictTags}`;
        saveBtn.disabled    = false;
        form.classList.remove('bulk-form-blocked');
    }

    banner.style.display = 'block';
}

// ============================================================
// CLOSE BULK MODAL
// ============================================================

function closeBulkModal() {
    document.getElementById('bulkMaintenanceModal').classList.remove('show');

    // Reset email fields
    const toggle = document.getElementById('bulkEmailNotifyToggle');
    if (toggle) toggle.checked = false;
    const area = document.getElementById('bulkEmailRecipientArea');
    if (area) area.style.display = 'none';
    clearEmailTags('bulkEmailTagWrapper', 'bulkEmailTags');

    // Restore floating bar if still in bulk mode with selections
    if (BulkSchedule.active && BulkSchedule.selectedIds.size > 0) {
        const bar = document.getElementById('bulkActionBar');
        if (bar) bar.classList.add('visible');
    }
}

// ============================================================
// SAVE BULK — FIX: close modal + exit bulk mode + reload on success
// ============================================================

async function saveBulkMaintenance(event) {
    if (event) event.preventDefault();

    const title  = document.getElementById('bulkTitle').value.trim();
    const start  = document.getElementById('bulkStart').value;
    const end    = document.getElementById('bulkEnd').value;
    const status = document.getElementById('bulkStatus').value;
    const desc   = document.getElementById('bulkDescription').value.trim();

    if (!title) {
        showToast('Please enter a maintenance title.', 'error');
        return;
    }

    if (new Date(end) <= new Date(start)) {
        showToast('End date/time must be after start date/time.', 'error');
        return;
    }

    const snapshot = BulkSchedule.modalSnapshot || {
        ids:   Array.from(BulkSchedule.selectedIds),
        names: Object.assign({}, BulkSchedule.selectedNames),
    };

    if (snapshot.ids.length === 0) {
        showToast('No systems selected.', 'error');
        return;
    }

    const saveBtn  = document.getElementById('bulkSaveBtn');
    const origHTML = saveBtn.innerHTML;
    saveBtn.disabled  = true;
    saveBtn.innerHTML = '<span class="loading-spinner" style="width:16px;height:16px;border-width:2px;display:inline-block;margin-right:8px;vertical-align:middle;"></span> Saving...';

    const results = [];

    for (const systemId of snapshot.ids) {
        const systemName = snapshot.names[String(systemId)] || `System #${systemId}`;

        const formData = new FormData();
        formData.append('action', 'create');
        formData.append('system_id', systemId);
        formData.append('title', title);
        formData.append('start_datetime', start);
        formData.append('end_datetime', end);
        formData.append('status', status);
        formData.append('description', desc);

        const bulkEmailToggle = document.getElementById('bulkEmailNotifyToggle');
        if (bulkEmailToggle && bulkEmailToggle.checked) {
            const emailList = getEmailTags('bulkEmailTagWrapper');
            if (emailList.length > 0) {
                formData.append('send_email', 'yes');
                formData.append('email_recipients', JSON.stringify(emailList));
            }
        }

        try {
            const response = await fetch('../backend/maintenance/save_maintenance.php', {
                method: 'POST',
                body: formData,
            });
            const data = await response.json();

            results.push({
                systemId,
                systemName,
                success: data.success,
                message: data.message || (data.success ? 'Scheduled successfully' : 'Error occurred'),
            });
        } catch (err) {
            results.push({ systemId, systemName, success: false, message: 'Network error' });
        }
    }

    // Restore button
    saveBtn.disabled  = false;
    saveBtn.innerHTML = origHTML;

    const successCount = results.filter(r => r.success).length;
    const skippedCount = results.filter(r => !r.success && r.message.toLowerCase().includes('active')).length;
    const errorCount   = results.length - successCount - skippedCount;

    if (successCount > 0) {
        // Refresh calendar immediately
        loadCalendarSchedules();

        if (successCount === results.length) {
            // ALL succeeded — show toast, close modal, exit bulk mode, reload page
            showToast(`${successCount} schedule(s) saved successfully!`, 'success');
            closeBulkModal();
            if (BulkSchedule.active) toggleBulkMode();
            setTimeout(() => location.reload(), 1000);
        } else {
            // Partial success — show results so user can see what was skipped/failed
            showBulkResultsInModal(results);
            if (skippedCount > 0 && errorCount === 0) {
                showToast(`${successCount} saved, ${skippedCount} skipped (already have active schedules).`, 'success');
            } else {
                showToast(`${successCount} saved, ${results.length - successCount} skipped/failed.`, 'success');
            }
            // Auto-close after delay even on partial success
            setTimeout(() => {
                closeBulkModal();
                if (BulkSchedule.active) toggleBulkMode();
                location.reload();
            }, 3000);
        }
    } else {
        // Nothing saved — show results and keep modal open
        showBulkResultsInModal(results);
        showToast('No schedules were saved. See results below.', 'error');
    }
}

// ============================================================
// RENDER BULK RESULTS (renamed from renderBulkResults to avoid conflicts)
// ============================================================

function showBulkResultsInModal(results) {
    const container = document.getElementById('bulkResults');
    const list      = document.getElementById('bulkResultsList');

    list.innerHTML = results.map(r => {
        const isSkipped = !r.success && r.message.toLowerCase().includes('active');
        let iconClass, msgClass, statusText;

        if (r.success) {
            iconClass  = 'success';
            msgClass   = 'success';
            statusText = 'Saved';
        } else if (isSkipped) {
            iconClass  = 'skipped';
            msgClass   = 'skipped';
            statusText = 'Skipped — has active schedule';
        } else {
            iconClass  = 'error';
            msgClass   = 'error';
            statusText = 'Error';
        }

        const successIcon = `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>`;
        const skipIcon    = `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line></svg>`;
        const errorIcon   = `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>`;

        const icon = r.success ? successIcon : (isSkipped ? skipIcon : errorIcon);

        return `
            <div class="bulk-result-item">
                <div class="bulk-result-icon ${iconClass}">${icon}</div>
                <span class="bulk-result-name" title="${escapeHtml(r.systemName)}">${escapeHtml(r.systemName)}</span>
                <span class="bulk-result-msg ${msgClass}">${statusText}</span>
            </div>
        `;
    }).join('');

    container.style.display = 'block';

    const modalBody = container.closest('.modal-body');
    if (modalBody) {
        setTimeout(() => {
            container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 100);
    }
}

// ============================================================
// SELECT ALL / DESELECT ALL
// ============================================================

function toggleSelectAll() {
    const allCards = Array.from(
        document.querySelectorAll('.system-card:not(.bulk-excluded)')
    ).filter(card => card.offsetParent !== null);

    const totalSelectable = allCards.length;
    const allSelected     = BulkSchedule.selectedIds.size >= totalSelectable && totalSelectable > 0;

    if (allSelected) {
        clearBulkSelection();
    } else {
        allCards.forEach(card => {
            const systemId   = parseInt(card.dataset.systemId);
            const systemName = card.dataset.systemName || `System #${systemId}`;
            if (!systemId) return;

            BulkSchedule.selectedIds.add(systemId);
            BulkSchedule.selectedNames[String(systemId)] = systemName;
            card.classList.add('bulk-selected');

            const checkbox = document.getElementById(`bulk-check-${systemId}`);
            if (checkbox) checkbox.classList.add('checked');
        });
        updateBulkBar();
    }
}

// ============================================================
// CONFIRM BEFORE CLOSING BULK MODAL
// ============================================================

function bulkModalHasData() {
    const title = document.getElementById('bulkTitle');
    const desc  = document.getElementById('bulkDescription');
    return (title && title.value.trim() !== '') || (desc && desc.value.trim() !== '');
}

function requestCloseBulkModal() {
    if (bulkModalHasData()) {
        document.getElementById('bulkCancelConfirm').style.display = 'flex';
    } else {
        closeBulkModal();
    }
}

function dismissBulkConfirm() {
    document.getElementById('bulkCancelConfirm').style.display = 'none';
}

function confirmCloseBulkModal() {
    dismissBulkConfirm();
    closeBulkModal();
}

document.addEventListener('click', function(e) {
    const overlay = document.getElementById('bulkCancelConfirm');
    if (overlay && e.target === overlay) {
        dismissBulkConfirm();
    }
});

// ============================================================
// EMAIL TAG INPUT — shared utility functions
// ============================================================

const _emailTagState = {};

function _getTagState(wrapperId) {
    if (!_emailTagState[wrapperId]) {
        _emailTagState[wrapperId] = { tags: [], suggestionIndex: -1 };
    }
    return _emailTagState[wrapperId];
}

function toggleEmailNotify(areaId) {
    const area = document.getElementById(areaId);
    if (!area) return;
    const isVisible = area.style.display !== 'none';
    area.style.display = isVisible ? 'none' : 'block';
    if (!isVisible) {
        const input = area.querySelector('.email-tag-text-input');
        if (input) setTimeout(() => input.focus(), 50);
    }
}

function addEmailTag(email, wrapperId, tagsId) {
    const state   = _getTagState(wrapperId);
    const tagsEl  = document.getElementById(tagsId);
    email = email.trim().toLowerCase();

    if (!email) return;
    if (state.tags.includes(email)) return;

    const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    state.tags.push(email);

    const tag = document.createElement('span');
    tag.className = 'email-tag' + (isValid ? '' : ' invalid');
    tag.title     = isValid ? email : 'Invalid email address';
    tag.innerHTML = `
        ${escapeHtml(email)}
        <button type="button" class="email-tag-remove" onclick="removeEmailTag('${escapeHtml(email)}', '${wrapperId}', '${tagsId}')" title="Remove">×</button>
    `;
    tagsEl.appendChild(tag);
}

function removeEmailTag(email, wrapperId, tagsId) {
    const state  = _getTagState(wrapperId);
    const tagsEl = document.getElementById(tagsId);
    state.tags   = state.tags.filter(e => e !== email);

    Array.from(tagsEl.querySelectorAll('.email-tag')).forEach(el => {
        if (el.title === email || el.textContent.trim().startsWith(email)) {
            el.remove();
        }
    });
}

function getEmailTags(wrapperId) {
    return [..._getTagState(wrapperId).tags];
}

function clearEmailTags(wrapperId, tagsId) {
    const state  = _getTagState(wrapperId);
    state.tags   = [];
    const tagsEl = document.getElementById(tagsId);
    if (tagsEl) tagsEl.innerHTML = '';
}

let _emailSuggestTimer = null;

async function fetchEmailSuggestions(q) {
    try {
        const res  = await fetch(`../backend/maintenance/email_recipients_api.php?action=suggest&q=${encodeURIComponent(q)}`);
        const data = await res.json();
        return data.success ? (data.suggestions || []) : [];
    } catch (e) {
        return [];
    }
}

function renderSuggestions(suggestions, input, wrapperId, tagsId, suggestionsId) {
    const box   = document.getElementById(suggestionsId);
    const state = _getTagState(wrapperId);

    if (!suggestions.length) {
        box.style.display = 'none';
        return;
    }

    state.suggestionIndex = -1;
    box.innerHTML = suggestions.map((s, i) => {
        const initial = (s.name || s.email).charAt(0).toUpperCase();
        const name    = s.name ? `<div class="email-suggestion-name">${escapeHtml(s.name)}</div>` : '';
        const count   = s.use_count > 0 ? `<span class="email-suggestion-count">Used ${s.use_count}×</span>` : '';
        return `
            <div class="email-suggestion-item" data-email="${escapeHtml(s.email)}" data-index="${i}"
                 onmousedown="selectEmailSuggestion(event, '${escapeHtml(s.email)}', '${wrapperId}', '${tagsId}', '${suggestionsId}')">
                <div class="email-suggestion-avatar">${initial}</div>
                <div class="email-suggestion-info">
                    <div class="email-suggestion-email">${escapeHtml(s.email)}</div>
                    ${name}
                </div>
                ${count}
            </div>`;
    }).join('');

    box.style.display = 'block';
}

function selectEmailSuggestion(event, email, wrapperId, tagsId, suggestionsId) {
    event.preventDefault();
    const input = document.getElementById(
        wrapperId === 'emailTagWrapper' ? 'emailRecipientInput' : 'bulkEmailRecipientInput'
    );
    addEmailTag(email, wrapperId, tagsId);
    if (input) input.value = '';
    document.getElementById(suggestionsId).style.display = 'none';
}

function onEmailInput(input, wrapperId, tagsId, suggestionsId) {
    const q = input.value.trim();

    if (q.endsWith(',') || q.endsWith(';')) {
        const email = q.slice(0, -1).trim();
        if (email) addEmailTag(email, wrapperId, tagsId);
        input.value = '';
        document.getElementById(suggestionsId).style.display = 'none';
        return;
    }

    clearTimeout(_emailSuggestTimer);
    if (q.length < 1) {
        document.getElementById(suggestionsId).style.display = 'none';
        return;
    }

    _emailSuggestTimer = setTimeout(async () => {
        const suggestions = await fetchEmailSuggestions(q);
        renderSuggestions(suggestions, input, wrapperId, tagsId, suggestionsId);
    }, 200);
}

function onEmailKeydown(event, input, wrapperId, tagsId, suggestionsId) {
    const box   = document.getElementById(suggestionsId);
    const state = _getTagState(wrapperId);
    const items = box ? box.querySelectorAll('.email-suggestion-item') : [];

    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        const active = box ? box.querySelector('.email-suggestion-item.active') : null;
        if (active) {
            addEmailTag(active.dataset.email, wrapperId, tagsId);
            input.value = '';
            box.style.display = 'none';
        } else if (input.value.trim()) {
            addEmailTag(input.value.trim(), wrapperId, tagsId);
            input.value = '';
            if (box) box.style.display = 'none';
        }
        return;
    }

    if (event.key === 'Backspace' && !input.value) {
        const tagState = _getTagState(wrapperId);
        if (tagState.tags.length > 0) {
            const last = tagState.tags[tagState.tags.length - 1];
            removeEmailTag(last, wrapperId, tagsId);
        }
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        state.suggestionIndex = Math.min(state.suggestionIndex + 1, items.length - 1);
        items.forEach((el, i) => el.classList.toggle('active', i === state.suggestionIndex));
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        state.suggestionIndex = Math.max(state.suggestionIndex - 1, -1);
        items.forEach((el, i) => el.classList.toggle('active', i === state.suggestionIndex));
        return;
    }

    if (event.key === 'Escape') {
        if (box) box.style.display = 'none';
    }
}

function onEmailBlur(input, wrapperId, tagsId, suggestionsId) {
    setTimeout(() => {
        const v = input.value.trim();
        if (v) {
            addEmailTag(v, wrapperId, tagsId);
            input.value = '';
        }
        const box = document.getElementById(suggestionsId);
        if (box) box.style.display = 'none';
    }, 180);
}