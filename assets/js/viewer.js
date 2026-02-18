/* ================================
   VIEWER PAGE JAVASCRIPT
   Enhanced with active filter tracking
   and result counting
   ================================ */

// Track current filter state
let currentFilter = 'all';
let currentSearchTerm = '';

// ================================
// FUNCTION: toggleLoginSlide
// PURPOSE: Toggle login button sliding effect
// ================================
function toggleLoginSlide(event) {
    event.stopPropagation();
    const loginButton = document.querySelector('.login-button-slide');
    const toggleBtn = document.querySelector('.user-menu-toggle');
    
    loginButton.classList.toggle('show');
    toggleBtn.classList.toggle('active');
    
    // Close when clicking outside
    if (loginButton.classList.contains('show')) {
        document.addEventListener('click', function closeSlide(e) {
            if (!e.target.closest('.user-menu-container')) {
                loginButton.classList.remove('show');
                toggleBtn.classList.remove('active');
                document.removeEventListener('click', closeSlide);
            }
        });
    }
}

// ================================
// FUNCTION: toggleFilterViewer
// PURPOSE: Toggle filter dropdown
// ================================
function toggleFilterViewer(event) {
    event.stopPropagation();
    const dropdown = document.querySelector('.filter-dropdown-viewer');
    dropdown.classList.toggle('show');
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function closeDropdown(e) {
        if (!dropdown.contains(e.target) && !event.target.closest('.btn-filter-viewer')) {
            dropdown.classList.remove('show');
            document.removeEventListener('click', closeDropdown);
        }
    });
}

// ================================
// FUNCTION: searchSystemsViewer
// PURPOSE: Search systems by name, domain, or description
// ================================
function searchSystemsViewer() {
    const searchBox = document.getElementById('viewerSearchBox');
    const filter = searchBox.value.toLowerCase();
    currentSearchTerm = filter;
    const cards = document.querySelectorAll('.system-card-viewer');
    
    let visibleCount = 0;
    
    cards.forEach(card => {
        const name = card.querySelector('.system-name-viewer').textContent.toLowerCase();
        const domain = card.querySelector('.system-domain-viewer').textContent.toLowerCase();
        const description = card.querySelector('.system-description-viewer');
        const descText = description ? description.textContent.toLowerCase() : '';
        
        // Check if card matches current filter
        const cardStatus = card.getAttribute('data-status') || 'online';
        
        // Check if card matches search
        const matchesSearch = name.includes(filter) || domain.includes(filter) || descText.includes(filter);
        
        // Handle archived status
        if (currentFilter === 'all' && cardStatus === 'archived') {
            // Hide archived when 'all' is selected
            card.style.display = 'none';
        } else if (currentFilter === 'archived') {
            // Show archived but grey and unclickable
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
            // Normal filtering
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
    
    // Update results display
    updateFilterDisplay(visibleCount);
    
    // Show/hide empty state
    showEmptyState(visibleCount === 0, 'No systems match your search');
}

// ================================
// FUNCTION: filterSystemsViewer
// PURPOSE: Filter systems by status
// ================================
function filterSystemsViewer(status) {
    currentFilter = status;
    const cards = document.querySelectorAll('.system-card-viewer');
    const filterItems = document.querySelectorAll('.filter-item');
    
    // Update active state on filter items
    filterItems.forEach(item => {
        item.classList.remove('active');
        if (item.getAttribute('data-filter') === status) {
            item.classList.add('active');
        }
    });
    
    let visibleCount = 0;
    
    // Filter cards
    cards.forEach(card => {
        const cardStatus = card.getAttribute('data-status') || 'online';
        const name = card.querySelector('.system-name-viewer').textContent.toLowerCase();
        const domain = card.querySelector('.system-domain-viewer').textContent.toLowerCase();
        const description = card.querySelector('.system-description-viewer');
        const descText = description ? description.textContent.toLowerCase() : '';
        
        // Check if card matches search
        const matchesSearch = !currentSearchTerm || 
            name.includes(currentSearchTerm) || 
            domain.includes(currentSearchTerm) || 
            descText.includes(currentSearchTerm);
        
        // Special handling for 'all' - hide archived
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
            // Show archived but make them grey and unclickable
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
            // Normal filtering for other statuses
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
    
    // Close dropdown after selection
    const dropdown = document.querySelector('.filter-dropdown-viewer');
    if (dropdown) {
        dropdown.classList.remove('show');
    }
    
    // Update results display
    updateFilterDisplay(visibleCount);
    
    // Show/hide empty state
    const statusLabels = {
        'all': 'active systems',
        'online': 'online systems',
        'offline': 'offline systems',
        'maintenance': 'systems under maintenance',
        'down': 'down systems',
        'archived': 'archived systems'
    };
    const statusLabel = statusLabels[status] || 'systems';
    showEmptyState(visibleCount === 0, `No ${statusLabel} found`);
}

// ================================
// FUNCTION: updateFilterDisplay
// PURPOSE: Update active filters display
// ================================
function updateFilterDisplay(visibleCount) {
    const activeFiltersContainer = document.getElementById('activeFilters');
    const filterResultsText = document.getElementById('filterResultsText');
    const clearFiltersBtn = document.getElementById('clearFiltersBtn');
    const systemCount = document.getElementById('systemCount');
    
    const hasActiveFilters = currentFilter !== 'all' || currentSearchTerm !== '';
    
    if (hasActiveFilters) {
        // Show active filters container
        activeFiltersContainer.style.display = 'block';
        
        // Build filter text
        let filterText = `Showing ${visibleCount} of ${TOTAL_SYSTEMS}`;
        
        if (currentFilter !== 'all') {
            const statusLabels = {
                'online': 'online',
                'offline': 'offline',
                'maintenance': 'maintenance',
                'down': 'down',
                'archived': 'archived'
            };
            filterText += ` ${statusLabels[currentFilter]}`;
        }
        
        filterText += currentSearchTerm ? ' matching your search' : ' systems';
        
        filterResultsText.textContent = filterText;
        clearFiltersBtn.style.display = 'block';
        
        // Update system count in header
        systemCount.textContent = `(${visibleCount} of ${TOTAL_SYSTEMS})`;
    } else {
        // Hide active filters
        activeFiltersContainer.style.display = 'none';
        clearFiltersBtn.style.display = 'none';
        
        // Reset system count
        systemCount.textContent = `(${TOTAL_SYSTEMS} total)`;
    }
}

// ================================
// FUNCTION: clearAllFilters
// PURPOSE: Reset all filters and search
// ================================
function clearAllFilters() {
    // Clear search
    const searchBox = document.getElementById('viewerSearchBox');
    searchBox.value = '';
    currentSearchTerm = '';
    
    // Reset filter to 'all'
    currentFilter = 'all';
    
    // Reset filter buttons
    const filterItems = document.querySelectorAll('.filter-item');
    filterItems.forEach(item => {
        item.classList.remove('active');
        if (item.getAttribute('data-filter') === 'all') {
            item.classList.add('active');
        }
    });
    
    // Show all cards except archived
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
    
    // Update display
    updateFilterDisplay(activeCount);
    
    // Hide empty state
    showEmptyState(false);
}

// ================================
// FUNCTION: showEmptyState
// PURPOSE: Show/hide empty state message
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
        if (emptyState) {
            emptyState.remove();
        }
    }
}

// ================================
// FUNCTION: openDomainViewer
// PURPOSE: Open system domain in new tab
// ================================
function openDomainViewer(domain) {
    // Add protocol if not present
    let url = domain;
    if (!url.startsWith('http://') && !url.startsWith('https://')) {
        url = 'https://' + url;
    }
    
    // Open in new tab
    window.open(url, '_blank');
}

// ================================
// KEYBOARD NAVIGATION
// ================================
document.addEventListener('keydown', function(e) {
    // ESC: Close dropdowns and clear filters
    if (e.key === 'Escape') {
        const loginButton = document.querySelector('.login-button-slide');
        const toggleBtn = document.querySelector('.user-menu-toggle');
        const filterMenu = document.querySelector('.filter-dropdown-viewer');
        
        if (loginButton && loginButton.classList.contains('show')) {
            loginButton.classList.remove('show');
            if (toggleBtn) toggleBtn.classList.remove('active');
        }
        if (filterMenu && filterMenu.classList.contains('show')) {
            filterMenu.classList.remove('show');
        }
    }
    
    // Enter: Open domain when card is focused
    if (e.key === 'Enter') {
        const focusedCard = document.activeElement;
        if (focusedCard && focusedCard.classList.contains('system-card-viewer')) {
            const domainLink = focusedCard.querySelector('.system-domain-viewer');
            if (domainLink) {
                domainLink.click();
            }
        }
    }
});

// ================================
// INITIALIZE ON PAGE LOAD
// ================================
document.addEventListener('DOMContentLoaded', function() {
    // Hide archived systems by default
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
    
    // Set initial filter display
    updateFilterDisplay(activeCount);
});