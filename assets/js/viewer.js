/* ================================
   VIEWER PAGE JAVASCRIPT
   Enhanced with active filter tracking,
   result counting, and 30s auto-refresh
   ================================ */

let currentFilter     = 'all';
let currentSearchTerm = '';

// ================================
// AUTO-REFRESH EVERY 30 SECONDS
// ================================
let autoRefreshInterval = null;
let refreshCountdown    = 30;
let countdownInterval   = null;

function startAutoRefresh() {
    autoRefreshInterval = setInterval(function () {
        location.reload();
    }, 30000);

    countdownInterval = setInterval(function () {
        refreshCountdown--;
        const indicator = document.getElementById('refreshCountdown');
        if (indicator) indicator.textContent = refreshCountdown + 's';
        if (refreshCountdown <= 0) refreshCountdown = 30;
    }, 1000);
}

// ================================
// MAINTENANCE THOUGHT-CLOUD POPOVER
// ================================
function fetchMaintenanceBadges() {
    fetch('../backend/maintenance/get_maintenance.php?action=counts')
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data.success) return;
            renderMaintenancePopover(data.in_progress, data.scheduled);
        })
        .catch(function () {
            // Silently fail — popover just won't appear
        });
}

function renderMaintenancePopover(inProgress, scheduled) {
    const popover = document.getElementById('maintPopover');
    const inner   = document.getElementById('maintPopoverInner');
    const wrapper = document.getElementById('maintBtnWrapper');

    if (!popover || !inner || !wrapper) return;

    // Nothing active — hide popover
    if (inProgress === 0 && scheduled === 0) {
        popover.style.display = 'none';
        return;
    }

    // Build popover rows
    let html = '';

    if (inProgress > 0) {
        html += `
            <div class="maint-popover-row maint-popover-row--ongoing">
                <span class="maint-popover-dot maint-popover-dot--ongoing"></span>
                <div class="maint-popover-text">
                    <span class="maint-popover-count">${inProgress}</span>
                    <span class="maint-popover-label">Ongoing Maintenance</span>
                </div>
            </div>`;
    }

    if (scheduled > 0) {
        html += `
            <div class="maint-popover-row maint-popover-row--scheduled">
                <span class="maint-popover-dot maint-popover-dot--scheduled"></span>
                <div class="maint-popover-text">
                    <span class="maint-popover-count">${scheduled}</span>
                    <span class="maint-popover-label">Scheduled Maintenance</span>
                </div>
            </div>`;
    }

    inner.innerHTML = html;

    // Always visible when data exists
    popover.style.display = 'block';
}

// ================================
// FUNCTION: toggleFilterViewer
// ================================
function toggleFilterViewer(event) {
    event.stopPropagation();
    const dropdown = document.querySelector('.filter-dropdown-viewer');
    dropdown.classList.toggle('show');

    document.addEventListener('click', function closeDropdown(e) {
        if (!dropdown.contains(e.target) && !event.target.closest('.btn-filter-viewer')) {
            dropdown.classList.remove('show');
            document.removeEventListener('click', closeDropdown);
        }
    });
}

// ================================
// FUNCTION: searchSystemsViewer
// ================================
function searchSystemsViewer() {
    const searchBox = document.getElementById('viewerSearchBox');
    const filter    = searchBox.value.toLowerCase();
    currentSearchTerm = filter;
    const cards = document.querySelectorAll('.system-card-viewer');

    let visibleCount = 0;

    cards.forEach(card => {
        const name        = card.querySelector('.system-name-viewer').textContent.toLowerCase();
        const domain      = card.querySelector('.system-domain-viewer').textContent.toLowerCase();
        const description = card.querySelector('.system-description-viewer');
        const descText    = description ? description.textContent.toLowerCase() : '';
        const cardStatus  = card.getAttribute('data-status') || 'online';
        const matchesSearch = name.includes(filter) || domain.includes(filter) || descText.includes(filter);

        if (currentFilter === 'all' && cardStatus === 'archived') {
            card.style.display = 'none';
        } else if (currentFilter === 'archived') {
            if (cardStatus === 'archived' && matchesSearch) {
                card.style.display = 'flex';
                card.style.opacity = '0.5';
                card.style.pointerEvents = 'none';
                card.style.cursor = 'not-allowed';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        } else {
            const matchesFilter = currentFilter === 'all' || cardStatus === currentFilter;
            if (matchesFilter && matchesSearch) {
                card.style.display = 'flex';
                card.style.opacity = '1';
                card.style.pointerEvents = 'auto';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        }
    });

    updateFilterDisplay(visibleCount);
    showEmptyState(visibleCount === 0, 'No systems match your search');
}

// ================================
// FUNCTION: filterSystemsViewer
// ================================
function filterSystemsViewer(status) {
    currentFilter = status;
    const cards       = document.querySelectorAll('.system-card-viewer');
    const filterItems = document.querySelectorAll('.filter-item');

    filterItems.forEach(item => {
        item.classList.remove('active');
        if (item.getAttribute('data-filter') === status) item.classList.add('active');
    });

    let visibleCount = 0;

    cards.forEach(card => {
        const cardStatus    = card.getAttribute('data-status') || 'online';
        const name          = card.querySelector('.system-name-viewer').textContent.toLowerCase();
        const domain        = card.querySelector('.system-domain-viewer').textContent.toLowerCase();
        const description   = card.querySelector('.system-description-viewer');
        const descText      = description ? description.textContent.toLowerCase() : '';
        const matchesSearch = !currentSearchTerm ||
            name.includes(currentSearchTerm) ||
            domain.includes(currentSearchTerm) ||
            descText.includes(currentSearchTerm);

        if (status === 'all') {
            if (cardStatus === 'archived') {
                card.style.display = 'none';
            } else if (matchesSearch) {
                card.style.display = 'flex';
                card.style.opacity = '1';
                card.style.pointerEvents = 'auto';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        } else if (status === 'archived') {
            if (cardStatus === 'archived' && matchesSearch) {
                card.style.display = 'flex';
                card.style.opacity = '0.5';
                card.style.pointerEvents = 'none';
                card.style.cursor = 'not-allowed';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        } else {
            if (cardStatus === status && matchesSearch) {
                card.style.display = 'flex';
                card.style.opacity = '1';
                card.style.pointerEvents = 'auto';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        }
    });

    const dropdown = document.querySelector('.filter-dropdown-viewer');
    if (dropdown) dropdown.classList.remove('show');

    updateFilterDisplay(visibleCount);

    const statusLabels = {
        'all': 'active systems', 'online': 'online systems',
        'offline': 'offline systems', 'maintenance': 'systems under maintenance',
        'down': 'down systems', 'archived': 'archived systems'
    };
    showEmptyState(visibleCount === 0, `No ${statusLabels[status] || 'systems'} found`);
}

// ================================
// FUNCTION: updateFilterDisplay
// ================================
function updateFilterDisplay(visibleCount) {
    const activeFiltersContainer = document.getElementById('activeFilters');
    const filterResultsText      = document.getElementById('filterResultsText');
    const clearFiltersBtn        = document.getElementById('clearFiltersBtn');
    const systemCount            = document.getElementById('systemCount');

    const hasActiveFilters = currentFilter !== 'all' || currentSearchTerm !== '';

    if (hasActiveFilters) {
        activeFiltersContainer.style.display = 'block';

        let filterText = `Showing ${visibleCount} of ${TOTAL_SYSTEMS}`;
        if (currentFilter !== 'all') {
            const labels = { online: 'online', offline: 'offline', maintenance: 'maintenance', down: 'down', archived: 'archived' };
            filterText += ` ${labels[currentFilter]}`;
        }
        filterText += currentSearchTerm ? ' matching your search' : ' systems';

        filterResultsText.textContent = filterText;
        clearFiltersBtn.style.display = 'block';
        systemCount.textContent = `(${visibleCount} of ${TOTAL_SYSTEMS})`;
    } else {
        activeFiltersContainer.style.display = 'none';
        clearFiltersBtn.style.display = 'none';
        systemCount.textContent = `(${TOTAL_SYSTEMS} total)`;
    }
}

// ================================
// FUNCTION: clearAllFilters
// ================================
function clearAllFilters() {
    const searchBox = document.getElementById('viewerSearchBox');
    searchBox.value   = '';
    currentSearchTerm = '';
    currentFilter     = 'all';

    const filterItems = document.querySelectorAll('.filter-item');
    filterItems.forEach(item => {
        item.classList.remove('active');
        if (item.getAttribute('data-filter') === 'all') item.classList.add('active');
    });

    const cards = document.querySelectorAll('.system-card-viewer');
    let activeCount = 0;
    cards.forEach(card => {
        const cardStatus = card.getAttribute('data-status') || 'online';
        if (cardStatus === 'archived') {
            card.style.display = 'none';
        } else {
            card.style.display = 'flex';
            card.style.opacity = '1';
            card.style.pointerEvents = 'auto';
            card.style.cursor = 'pointer';
            activeCount++;
        }
    });

    updateFilterDisplay(activeCount);
    showEmptyState(false);
}

// ================================
// FUNCTION: showEmptyState
// ================================
function showEmptyState(show, message = 'No systems found') {
    const grid = document.querySelector('.systems-grid-viewer');
    let emptyState = grid.querySelector('.filter-empty-state');

    if (show) {
        if (!emptyState) {
            emptyState = document.createElement('div');
            emptyState.className = 'empty-state-viewer filter-empty-state';
            emptyState.style.gridColumn = '1 / -1';
            emptyState.innerHTML = `
                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                </svg>
                <h3>No Systems Found</h3>
                <p>${message}</p>
            `;
            grid.appendChild(emptyState);
        } else {
            emptyState.querySelector('p').textContent = message;
        }
    } else {
        if (emptyState) emptyState.remove();
    }
}

// ================================
// FUNCTION: openDomainViewer
// ================================
function openDomainViewer(domain) {
    let url = domain;
    if (!url.startsWith('http://') && !url.startsWith('https://')) {
        url = 'https://' + url;
    }
    window.open(url, '_blank');
}

// ================================
// KEYBOARD NAVIGATION
// ================================
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        const loginButton = document.querySelector('.login-button-slide');
        const toggleBtn   = document.querySelector('.user-menu-toggle');
        const filterMenu  = document.querySelector('.filter-dropdown-viewer');

        if (loginButton && loginButton.classList.contains('show')) {
            loginButton.classList.remove('show');
            if (toggleBtn) toggleBtn.classList.remove('active');
        }
        if (filterMenu && filterMenu.classList.contains('show')) {
            filterMenu.classList.remove('show');
        }
    }

    if (e.key === 'Enter') {
        const focusedCard = document.activeElement;
        if (focusedCard && focusedCard.classList.contains('system-card-viewer')) {
            const domainLink = focusedCard.querySelector('.system-domain-viewer');
            if (domainLink) domainLink.click();
        }
    }
});

// ================================
// INITIALIZE ON PAGE LOAD
// ================================
document.addEventListener('DOMContentLoaded', function () {
    // Hide archived by default
    const cards = document.querySelectorAll('.system-card-viewer');
    let activeCount = 0;
    cards.forEach(card => {
        const cardStatus = card.getAttribute('data-status') || 'online';
        if (cardStatus === 'archived') {
            card.style.display = 'none';
        } else {
            activeCount++;
        }
    });

    updateFilterDisplay(activeCount);

    // Fetch maintenance popover data
    fetchMaintenanceBadges();

    // Start auto-refresh
    startAutoRefresh();
});

// Stop refresh intervals when page unloads
window.addEventListener('beforeunload', function () {
    if (autoRefreshInterval)  clearInterval(autoRefreshInterval);
    if (countdownInterval)    clearInterval(countdownInterval);
});