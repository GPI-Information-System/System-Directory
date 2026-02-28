// Global variables
let currentEditId = null;
let deleteSystemId = null;
let deleteSystemName = null;

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.card-menu')) {
        closeAllDropdowns();
    }
});

// Toggle dropdown menu
function toggleDropdown(event, cardId) {
    event.stopPropagation();
    
    const dropdown = event.currentTarget.nextElementSibling;
    const isCurrentlyOpen = dropdown.classList.contains('show');
    
    // Close all dropdowns
    closeAllDropdowns();
    
    // Toggle current dropdown
    if (!isCurrentlyOpen) {
        dropdown.classList.add('show');
    }
}

// Close all dropdowns
function closeAllDropdowns() {
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.classList.remove('show');
    });
}

// Open Add System Modal
function openAddModal() {
    document.getElementById('addModal').classList.add('show');
    document.getElementById('addSystemForm').reset();
}

// Close Add System Modal
function closeAddModal() {
    document.getElementById('addModal').classList.remove('show');
    document.getElementById('addSystemForm').reset();
}

// ================================
// UPDATED: Open Edit System Modal
// Now includes excludeHealthCheck parameter (7th parameter)
// ================================
function openEditModal(id, name, domain, description, status, contactNumber, excludeHealthCheck = 0) {
    currentEditId = id;
    document.getElementById('editModal').classList.add('show');
    document.getElementById('editSystemName').value = name;
    document.getElementById('editSystemDomain').value = domain;
    document.getElementById('editSystemDescription').value = description;
    document.getElementById('editSystemStatus').value = status || 'online';
    document.getElementById('editSystemContact').value = contactNumber || '123';
    // Set exclude health check toggle
    document.getElementById('editExcludeHealthCheck').checked = excludeHealthCheck == 1;
}

// Close Edit System Modal
function closeEditModal() {
    document.getElementById('editModal').classList.remove('show');
    document.getElementById('editSystemForm').reset();
    currentEditId = null;
}

// ================================
// UPDATED: Add System
// Now includes contact_number in FormData
// ================================
function addSystem(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    // Ensure contact_number has a default value if empty
    if (!formData.get('contact_number')) {
        formData.set('contact_number', '123');
    }
    
    fetch('../backend/add_system.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAddModal();
            location.reload();
        } else {
            alert(data.message || 'Error adding system');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error adding system');
    });
}

// ================================
// UPDATED: Edit System
// Now includes contact_number in FormData
// ================================
function editSystem(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    formData.append('id', currentEditId);
    
    // Ensure contact_number has a default value if empty
    if (!formData.get('contact_number')) {
        formData.set('contact_number', '123');
    }
    
    fetch('../backend/edit_system.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeEditModal();
            location.reload();
        } else {
            alert(data.message || 'Error updating system');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating system');
    });
}

// Preview logo image
function previewLogo(input, previewId) {
    const preview = document.getElementById(previewId);
    const file = input.files[0];
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(file);
    }
}

// Delete System - Show custom confirmation modal
function deleteSystem(id, name) {
    deleteSystemId = id;
    deleteSystemName = name;
    
    // Update modal content
    document.getElementById('deleteSystemName').textContent = name;
    
    // Show delete modal
    document.getElementById('deleteModal').classList.add('show');
}

// Confirm Delete
function confirmDelete() {
    if (!deleteSystemId) return;
    
    const formData = new FormData();
    formData.append('id', deleteSystemId);
    
    fetch('../backend/delete_system.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteModal();
            location.reload();
        } else {
            alert(data.message || 'Error deleting system');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting system');
    });
}

// Close Delete Modal
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    deleteSystemId = null;
    deleteSystemName = null;
}

// Search Systems
function searchSystems() {
    const searchTerm = document.getElementById('searchBox').value.toLowerCase();
    const cards = document.querySelectorAll('.system-card');
    
    cards.forEach(card => {
        const name = card.querySelector('.card-title').textContent.toLowerCase();
        const domain = card.querySelector('.card-domain').textContent.toLowerCase();
        const description = card.querySelector('.card-description')?.textContent.toLowerCase() || '';
        
        if (name.includes(searchTerm) || domain.includes(searchTerm) || description.includes(searchTerm)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Open system domain in new tab
function openDomain(domain) {
    if (domain) {
        let url = domain;
        if (!domain.startsWith('http://') && !domain.startsWith('https://')) {
            url = 'https://' + domain;
        }
        window.open(url, '_blank');
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const addModal = document.getElementById('addModal');
    const editModal = document.getElementById('editModal');
    const deleteModal = document.getElementById('deleteModal');
    
    if (event.target === addModal) {
        closeAddModal();
    }
    if (event.target === editModal) {
        closeEditModal();
    }
    if (event.target === deleteModal) {
        closeDeleteModal();
    }
}


/* ================================
   JAVASCRIPT FOR FILTER FUNCTIONALITY
   ================================ */

function toggleFilterDropdown(event) {
    event.stopPropagation();
    const dropdown = event.currentTarget.nextElementSibling;
    dropdown.classList.toggle('show');
    
    document.addEventListener('click', function closeDropdown(e) {
        if (!dropdown.contains(e.target) && !event.currentTarget.contains(e.target)) {
            dropdown.classList.remove('show');
            document.removeEventListener('click', closeDropdown);
        }
    });
}

function filterSystems(status) {
    const cards = document.querySelectorAll('.system-card');
    
    const filterOptions = document.querySelectorAll('.filter-option');
    filterOptions.forEach(option => {
        option.classList.remove('active');
        if (option.getAttribute('data-filter') === status) {
            option.classList.add('active');
        }
    });
    
    cards.forEach(card => {
        const cardStatus = card.getAttribute('data-status') || 'online';
        
        if (status === 'all') {
            card.style.display = 'block';
        } else {
            card.style.display = cardStatus === status ? 'block' : 'none';
        }
    });
    
    const dropdown = document.querySelector('.filter-dropdown-menu');
    if (dropdown) {
        dropdown.classList.remove('show');
    }
    
    const visibleCards = Array.from(cards).filter(card => card.style.display !== 'none');
    const cardsGrid = document.querySelector('.cards-grid');
    
    const existingEmptyState = cardsGrid.querySelector('.filter-empty-state');
    if (existingEmptyState) {
        existingEmptyState.remove();
    }
    
    if (visibleCards.length === 0) {
        const emptyState = document.createElement('div');
        emptyState.className = 'empty-state filter-empty-state';
        emptyState.style.gridColumn = '1/-1';
        emptyState.innerHTML = `
            <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
            </svg>
            <h3>No Systems Found</h3>
            <p>No systems match the selected filter: ${status}</p>
        `;
        cardsGrid.appendChild(emptyState);
    }
}


/* ============================================================
   PHASE 2: Dashboard Chart - Systems by Status
   ============================================================ */

let dashboardChart = null;

document.addEventListener('DOMContentLoaded', function() {
    loadDashboardChart();
});

function loadDashboardChart() {
    fetch('../backend/get_analytics_data.php?action=systems_by_status')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderDashboardChart(data.data);
            }
        })
        .catch(error => console.error('Error loading dashboard chart:', error));
}

function renderDashboardChart(data) {
    const ctx = document.getElementById('systemsStatusChart');
    
    if (!ctx) return;
    
    const labels = [];
    const counts = [];
    const colors = [];
    
    const statusColors = {
        'online': 'rgba(16, 185, 129, 0.8)',
        'maintenance': 'rgba(245, 158, 11, 0.8)',
        'down': 'rgba(239, 68, 68, 0.8)',
        'offline': 'rgba(107, 114, 128, 0.8)',
        'archived': 'rgba(156, 163, 175, 0.8)'
    };
    
    data.forEach(item => {
        labels.push(item.status.charAt(0).toUpperCase() + item.status.slice(1));
        counts.push(item.count);
        colors.push(statusColors[item.status] || 'rgba(30, 58, 138, 0.8)');
    });
    
    if (dashboardChart) {
        dashboardChart.destroy();
    }
    
    const maxValue = Math.max(...counts);
    const suggestedMax = maxValue + Math.ceil(maxValue * 0.9);
    
    dashboardChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Number of Systems',
                data: counts,
                backgroundColor: colors,
                borderColor: colors.map(c => c.replace('0.8', '1')),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 },
                    suggestedMax: suggestedMax < 2 ? 2 : suggestedMax
                }
            }
        }
    });
}


/* ============================================================
   RESPONSIVE HAMBURGER MENU
   ============================================================ */

function toggleSidebar() {
    document.body.classList.toggle('sidebar-open');
    const overlay = document.querySelector('.sidebar-overlay');
    if (overlay) overlay.classList.toggle('show');
}

function closeSidebar() {
    document.body.classList.remove('sidebar-open');
    const overlay = document.querySelector('.sidebar-overlay');
    if (overlay) overlay.classList.remove('show');
}

function handleNavLinkClick() {
    if (window.innerWidth <= 1024) closeSidebar();
}

function initializeResponsiveMenu() {
    if (!document.querySelector('.sidebar-overlay')) {
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        overlay.onclick = closeSidebar;
        document.body.appendChild(overlay);
    }
    
    if (!document.querySelector('.hamburger-menu')) {
        const hamburger = document.createElement('button');
        hamburger.className = 'hamburger-menu';
        hamburger.setAttribute('aria-label', 'Toggle navigation menu');
        hamburger.onclick = toggleSidebar;
        hamburger.innerHTML = `
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        `;
        document.body.appendChild(hamburger);
    }
    
    const navLinks = document.querySelectorAll('.nav-menu a');
    navLinks.forEach(link => {
        link.addEventListener('click', handleNavLinkClick);
    });
    
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 1024) closeSidebar();
        }, 250);
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
            closeSidebar();
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initializeResponsiveMenu();
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeResponsiveMenu);
} else {
    initializeResponsiveMenu();
}