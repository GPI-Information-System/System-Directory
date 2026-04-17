const REFRESH_INTERVAL = 30;
let refreshTimer   = null;
let refreshCounter = REFRESH_INTERVAL;
const LS_KEY       = 'gportal_access_logs_autorefresh';

function toggleAutoRefresh() {
    const checkbox  = document.getElementById('refreshToggle');
    const wrap      = document.getElementById('refreshWrap');
    const isEnabled = checkbox.checked;

    wrap.classList.toggle('active', isEnabled);
    localStorage.setItem(LS_KEY, isEnabled ? '1' : '0');

    if (isEnabled) {
        startRefreshTimer();
    } else {
        stopRefreshTimer();
    }
}

function startRefreshTimer() {
    stopRefreshTimer();
    refreshCounter = REFRESH_INTERVAL;
    updateCountdown();

    refreshTimer = setInterval(() => {
        refreshCounter--;
        updateCountdown();
        if (refreshCounter <= 0) {
            location.reload();
        }
    }, 1000);
}

function stopRefreshTimer() {
    if (refreshTimer) {
        clearInterval(refreshTimer);
        refreshTimer = null;
    }
    refreshCounter = REFRESH_INTERVAL;
    updateCountdown();
}

function pauseRefreshTimer() {
    if (refreshTimer) {
        clearInterval(refreshTimer);
        refreshTimer = null;
    }
}

function resumeRefreshTimer() {
    const checkbox = document.getElementById('refreshToggle');
    if (checkbox && checkbox.checked && !refreshTimer) {
        refreshTimer = setInterval(() => {
            refreshCounter--;
            updateCountdown();
            if (refreshCounter <= 0) {
                location.reload();
            }
        }, 1000);
    }
}

function updateCountdown() {
    const el = document.getElementById('refreshCountdown');
    if (el) el.textContent = refreshCounter + 's';
}

let searchTimer;
function debounceSubmit() {
    pauseRefreshTimer();
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        document.getElementById('filterForm').submit();
    }, 1500);
}

function onSearchBlur() {
    clearTimeout(searchTimer);
    resumeRefreshTimer();
}

// Custom Date Picker 
function onCustomDateChange(input) {
    const dateRangeSelect = document.getElementById('dateRangeSelect');
    const label           = document.getElementById('dateLabelText');

    if (input.value !== '') {
        const today     = new Date();
        const todayStr  = today.toISOString().slice(0, 10); 
        const isToday   = input.value === todayStr;

        if (isToday) {
            label.textContent = 'Today';
        } else {
            const d         = new Date(input.value + 'T00:00:00');
            const formatted = d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
            label.textContent = formatted;
        }

        label.classList.add('has-value');
        dateRangeSelect.value = 'all';
        dateRangeSelect.style.display = 'none';
    } else {
        label.textContent = 'Select Date';
        label.classList.remove('has-value');
        dateRangeSelect.style.display = '';
    }

    document.getElementById('filterForm').submit();
}

function onDateRangeChange(select) {
    const customDateInput = document.getElementById('customDateInput');
    if (customDateInput) customDateInput.value = '';
    select.form.submit();
}

// ── Searchable System Dropdown ────────────────────────────────
function toggleSystemDropdown() {
    const menu    = document.getElementById('systemDropdownMenu');
    const trigger = document.getElementById('systemDropdownTrigger');
    const search  = document.getElementById('systemDropdownSearch');
    const isOpen  = menu.classList.contains('show');

    if (isOpen) {
        closeSystemDropdown();
    } else {
        menu.classList.add('show');
        trigger.classList.add('open');
        search.value = '';
        filterSystemOptions('');
        setTimeout(() => search.focus(), 50);
    }
}

function closeSystemDropdown() {
    document.getElementById('systemDropdownMenu').classList.remove('show');
    document.getElementById('systemDropdownTrigger').classList.remove('open');
}

function selectSystem(name) {
    document.getElementById('systemHiddenInput').value = name;
    document.getElementById('systemDropdownLabel').textContent = name || 'All Systems';
    document.querySelectorAll('.al-system-dropdown-option').forEach(opt => opt.classList.remove('selected'));
    event.target.classList.add('selected');
    closeSystemDropdown();
    document.getElementById('filterForm').submit();
}

function filterSystemOptions(query) {
    const list    = document.getElementById('systemDropdownList');
    const options = list.querySelectorAll('.al-system-dropdown-option');
    const q       = query.toLowerCase().trim();
    let   visible = 0;

    options.forEach(opt => {
        const name = opt.getAttribute('data-name') || '';
        if (!opt.hasAttribute('data-name')) { opt.style.display = ''; return; }
        if (!q || name.includes(q)) { opt.style.display = ''; visible++; }
        else { opt.style.display = 'none'; }
    });

    let emptyMsg = list.querySelector('.al-system-dropdown-empty');
    if (visible === 0 && q) {
        if (!emptyMsg) {
            emptyMsg = document.createElement('div');
            emptyMsg.className = 'al-system-dropdown-empty';
            list.appendChild(emptyMsg);
        }
        emptyMsg.textContent = 'No systems found for "' + query + '"';
        emptyMsg.style.display = '';
    } else if (emptyMsg) {
        emptyMsg.style.display = 'none';
    }
}

// Close dropdown 
document.addEventListener('click', function(e) {
    const wrap = document.getElementById('systemDropdownWrap');
    if (wrap && !wrap.contains(e.target)) closeSystemDropdown();
});

// Restore auto-refresh
document.addEventListener('DOMContentLoaded', function() {
    const saved    = localStorage.getItem(LS_KEY);
    const checkbox = document.getElementById('refreshToggle');
    const wrap     = document.getElementById('refreshWrap');

    const shouldBeOn = saved !== '0';

    if (shouldBeOn) {
        checkbox.checked = true;
        wrap.classList.add('active');
        localStorage.setItem(LS_KEY, '1');
        startRefreshTimer();
    }

    
    const searchInput = document.querySelector('.al-search');
    if (searchInput && searchInput.value.trim() !== '') {
        searchInput.focus();
        const len = searchInput.value.length;
        searchInput.setSelectionRange(len, len);
    }
});

// Close confirm modal 
document.getElementById('clearConfirm').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('show');
});

//  Export PDF 
function exportPDF() {
    const params = new URLSearchParams(window.location.search);

    // Show loading state on button
    const btn          = document.querySelector('.al-btn-pdf');
    const originalHTML = btn.innerHTML;
    btn.innerHTML      = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg> Generating...';
    btn.disabled       = true;

    fetch('../backend/export_access_logs.php?' + params.toString())
        .then(res => res.json())
        .then(response => {
            if (!response.success) {
                alert('Export failed. Please try again.');
                btn.innerHTML = originalHTML;
                btn.disabled  = false;
                return;
            }

            buildPDF(response.data, params);

            btn.innerHTML = originalHTML;
            btn.disabled  = false;
        })
        .catch(() => {
            alert('Export failed. Please try again.');
            btn.innerHTML = originalHTML;
            btn.disabled  = false;
        });
}

function buildPDF(rows, params) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

    const navy  = [22, 38, 96];
    const white = [255, 255, 255];
    const gray  = [107, 114, 128];
    const light = [249, 250, 251];
    const total = rows.length;

    //  Column config 
    const headers   = ['#', 'System Name', 'IP Address', 'Browser / Device', 'Lang', 'Source', 'Timestamp'];
    const colWidths = [12, 75, 34, 48, 16, 24, 52];

    // Build filter label 
    const customDate   = params.get('custom_date') || '';
    const dateRange    = params.get('date_range')  || 'all';
    const systemFilter = params.get('system')      || '';
    const sourceFilter = params.get('source')      || 'all';

    let filterParts = [];
    if (customDate) {
        const d = new Date(customDate + 'T00:00:00');
        filterParts.push(d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }));
    } else if (dateRange === 'today') {
        filterParts.push('Today');
    } else if (dateRange === '7days') {
        filterParts.push('Last 7 Days');
    } else if (dateRange === '30days') {
        filterParts.push('Last 30 Days');
    } else {
        const now = new Date();
        const formatted = now.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
        filterParts.push(formatted);
    }

    if (systemFilter) filterParts.push('System: ' + systemFilter);
    if (sourceFilter !== 'all') filterParts.push('Source: ' + sourceFilter);

    const filterLabel = filterParts.join('  |  ');

    // Helper: draw page header 
    function drawPageHeader(isFirstPage) {
        doc.setFillColor(...navy);
        doc.rect(0, 0, 297, 22, 'F');
        doc.setTextColor(...white);
        doc.setFontSize(14);
        doc.setFont('helvetica', 'bold');
        doc.text('G-Portal — Access Logs', 14, 14);
        doc.setFontSize(9);
        doc.setFont('helvetica', 'normal');
        doc.text('Generated: ' + new Date().toLocaleString(), 297 - 14, 14, { align: 'right' });

        doc.setFontSize(8);
        doc.setTextColor(...gray);
        doc.text('Date: ' + filterLabel + '   |   Total records: ' + total, 14, 20);
    }


    function drawColumnHeaders(y) {
        doc.setFillColor(...navy);
        doc.rect(14, y - 6, 261, 9, 'F');
        doc.setTextColor(...white);
        doc.setFontSize(8);
        doc.setFont('helvetica', 'bold');
        let x = 14;
        headers.forEach((h, i) => {
            doc.text(h, x + 2, y);
            x += colWidths[i];
        });
    }

    //  First page 
    drawPageHeader(true);
    let y = 34;
    drawColumnHeaders(y);

    // Table rows 
    let rowY = y + 8;

    rows.forEach((row, idx) => {
        if (rowY > 188) {
            doc.setFontSize(7.5);
            doc.setTextColor(...gray);
            doc.setFont('helvetica', 'italic');
            doc.text('Continued on next page...', 14, 194);

            doc.addPage();
            drawPageHeader(false);
            y    = 34;
            rowY = y + 8;
            drawColumnHeaders(y);
            rowY = y + 8;
        }


        if (idx % 2 === 0) {
            doc.setFillColor(...light);
            doc.rect(14, rowY - 5, 261, 8, 'F');
        }

        const rowData = [
            row.id,
            row.system_name,
            row.ip_address,
            row.browser,
            row.language,
            row.source,
            row.timestamp,
        ];

        doc.setTextColor(...gray);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(7.5);

        let cx = 14;
        rowData.forEach((val, i) => {
            const truncated = doc.splitTextToSize(String(val), colWidths[i] - 3)[0] || '';
            doc.text(truncated, cx + 2, rowY);
            cx += colWidths[i];
        });

        rowY += 8;
    });

    rowY += 4;
    if (rowY > 188) {
        doc.addPage();
        rowY = 30;
    }

    doc.setDrawColor(...gray);
    doc.setLineWidth(0.3);
    doc.line(14, rowY, 275, rowY);
    rowY += 5;

    doc.setFontSize(8);
    doc.setFont('helvetica', 'bold');
    doc.setTextColor(...navy);
    doc.text('Total Records Exported: ' + total, 14, rowY);

    doc.setFont('helvetica', 'normal');
    doc.setTextColor(...gray);
    doc.text('Date: ' + filterLabel, 14, rowY + 5);

    // Footer on every page
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(8);
        doc.setTextColor(...gray);
        doc.setFont('helvetica', 'normal');
        doc.text('G-Portal Access Logs — GPI Information Technology Department', 14, 205);
        doc.text('Page ' + i + ' of ' + pageCount, 297 - 14, 205, { align: 'right' });
    }

    // Save 
    const fileDate = customDate || new Date().toISOString().slice(0, 10);
    doc.save('GPortal_Access_Logs_' + fileDate + '.pdf');
}