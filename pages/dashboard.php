<?php
/**
 * G-Portal Admin Dashboard
 * Main interface for managing systems in the directory
 * 
 * Access Levels:
 * - Super Admin: Full CRUD (Create, Read, Update, Delete)
 * - Admin: Read and Update only
 * - Regular Users: Redirected (handled by requireLogin)
 * 
 * Features:
 * - System listing with search and filter
 * - Add/Edit/Delete systems (permission-based)
 * - Status management (Online, Offline, Maintenance, Down, Archived)
 * - Logo upload support
 * - Contact number management for unavailable systems
 * - PHASE 2: Change note tracking for status changes
 */

require_once '../config/session.php';
require_once '../config/database.php';

// ============================================================
// AUTHENTICATION & DATA LOADING
// ============================================================

requireLogin();
$currentUser = getCurrentUser();

// Fetch all systems from database
$conn = getDBConnection();
$result = $conn->query("SELECT * FROM systems ORDER BY created_at DESC");

$systems = [];
while ($row = $result->fetch_assoc()) {
    $systems[] = $row;
}

$conn->close();

// ============================================================
// SORT SYSTEMS BY STATUS PRIORITY
// Priority: Online > Maintenance > Down > Offline > Archived
// ============================================================

// Define status priority (lower number = higher priority)
$statusPriority = [
    'online' => 1,
    'maintenance' => 2,
    'down' => 3,
    'offline' => 4,
    'archived' => 5
];

// Sort systems by status priority
usort($systems, function($a, $b) use ($statusPriority) {
    $statusA = $a['status'] ?? 'online';
    $statusB = $b['status'] ?? 'online';
    
    $priorityA = $statusPriority[$statusA] ?? 999;
    $priorityB = $statusPriority[$statusB] ?? 999;
    
    return $priorityA - $priorityB;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>G-Portal - Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="container">
        <!-- ========================================
             SIDEBAR NAVIGATION
             ======================================== -->
        <aside class="sidebar">
            <!-- Sidebar Header -->
            <div class="sidebar-header">
                <h1>G-Portal</h1>
                <div class="user-info">
                    Welcome, <strong><?php echo htmlspecialchars($currentUser['username']); ?></strong>
                    <span class="user-role"><?php echo htmlspecialchars($currentUser['role']); ?></span>
                </div>
            </div>
            
            <nav>
                <!-- Navigation Menu -->
                <ul class="nav-menu">
                    <li>
                        <a href="dashboard.php" class="active">
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
                        <a href="analytics.php">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="20" x2="12" y2="10"></line>
                                <line x1="18" y1="20" x2="18" y2="4"></line>
                                <line x1="6" y1="20" x2="6" y2="16"></line>
                            </svg>
                            Analytics
                        </a>
                    </li>
                </ul>
                
                <!-- User Profile Section -->
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
                
                <!-- Logout Button -->
                <form action="../backend/logout.php" method="POST">
                    <button type="submit" class="btn-logout">Logout</button>
                </form>
            </nav>
        </aside>
        
        <!-- ========================================
             MAIN CONTENT AREA
             ======================================== -->
        <main class="main-content">
            <!-- Top Section: Header, Search, and Add Button -->
            <div class="top-section">
                <!-- Left: Header and Search/Filter -->
                <div class="header-with-search">
                    <div class="content-header">
                        <h2>All Systems</h2>
                    </div>
                    
                    <!-- Search Bar with Status Filter -->
                    <div class="search-container">
                        <div class="search-wrapper">
                            <!-- Search Input -->
                            <input 
                                type="text" 
                                id="searchBox" 
                                class="search-box" 
                                placeholder="Search systems..." 
                                onkeyup="searchSystems()"
                            >
                            
                            <!-- Status Filter Dropdown -->
                            <div class="filter-dropdown-container">
                                <button type="button" class="btn-filter" onclick="toggleFilterDropdown(event)">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                                    </svg>
                                    Status Filter
                                </button>
                                
                                <!-- Filter Options -->
                                <div class="filter-dropdown-menu">
                                    <button type="button" onclick="filterSystems('all')" class="filter-option active" data-filter="all">
                                        <span class="filter-dot filter-all"></span>
                                        All Systems
                                    </button>
                                    <button type="button" onclick="filterSystems('online')" class="filter-option" data-filter="online">
                                        <span class="filter-dot filter-online"></span>
                                        Online
                                    </button>
                                    <button type="button" onclick="filterSystems('maintenance')" class="filter-option" data-filter="maintenance">
                                        <span class="filter-dot filter-maintenance"></span>
                                        Maintenance
                                    </button>
                                    <button type="button" onclick="filterSystems('down')" class="filter-option" data-filter="down">
                                        <span class="filter-dot filter-down"></span>
                                        Down
                                    </button>
                                    <button type="button" onclick="filterSystems('offline')" class="filter-option" data-filter="offline">
                                        <span class="filter-dot filter-offline"></span>
                                        Offline
                                    </button>
                                    <button type="button" onclick="filterSystems('archived')" class="filter-option" data-filter="archived">
                                        <span class="filter-dot filter-archived"></span>
                                        Archived
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right: Add System Button (Super Admin Only) -->
                <?php if (isSuperAdmin()): ?>
                <button class="btn-add-top" onclick="openAddModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Add System
                </button>
                <?php endif; ?>
            </div>
            
            <!-- PHASE 2: Analytics Chart Section -->
            <div class="analytics-chart-section">
                <div class="chart-header">
                    <h3>Systems by Status</h3>
                    <a href="analytics.php" class="link-to-analytics">
                        View Full Analytics
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </a>
                </div>
                <div class="dashboard-chart-container">
                    <canvas id="systemsStatusChart"></canvas>
                </div>
            </div>

            <!-- ========================================
                 SYSTEMS GRID
                 ======================================== -->
            <div class="cards-grid">
                <?php if (empty($systems)): ?>
                    <!-- Empty State -->
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                        </svg>
                        <h3>No Systems Found</h3>
                        <p>Start by adding your first system</p>
                    </div>
                <?php else: ?>
                    <!-- System Cards -->
                    <?php foreach ($systems as $system): ?>
                        <?php
                        // Get status information
                        $status = $system['status'] ?? 'online';
                        $statusLabels = [
                            'online' => 'Online',
                            'offline' => 'Offline',
                            'maintenance' => 'Maintenance',
                            'down' => 'Down',
                            'archived' => 'Archived'
                        ];
                        $statusLabel = $statusLabels[$status] ?? 'Online';
                        $contactNumber = $system['contact_number'] ?? '123';
                        ?>
                        
                        <div class="system-card" data-status="<?php echo htmlspecialchars($status); ?>">
                            <div class="card-header">
                                <div style="flex: 1;">
                                    <!-- System Logo (Clickable) -->
                                    <?php if (!empty($system['logo']) && file_exists('../' . $system['logo'])): ?>
                                        <a href="#" onclick="openDomain('<?php echo htmlspecialchars($system['domain']); ?>'); return false;" style="display: block;">
                                            <img src="../<?php echo htmlspecialchars($system['logo']); ?>" 
                                                 alt="<?php echo htmlspecialchars($system['name']); ?>" 
                                                 class="system-logo system-logo-clickable">
                                        </a>
                                    <?php else: ?>
                                        <a href="#" onclick="openDomain('<?php echo htmlspecialchars($system['domain']); ?>'); return false;" style="display: block;">
                                            <div class="system-logo-placeholder system-logo-clickable">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="3" y="3" width="7" height="7"></rect>
                                                    <rect x="14" y="3" width="7" height="7"></rect>
                                                    <rect x="14" y="14" width="7" height="7"></rect>
                                                    <rect x="3" y="14" width="7" height="7"></rect>
                                                </svg>
                                            </div>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- Status Badge -->
                                    <div class="card-status-badge status-<?php echo htmlspecialchars($status); ?>">
                                        <span class="status-indicator"></span>
                                        <?php echo $statusLabel; ?>
                                    </div>
                                    
                                    <!-- System Name -->
                                    <h3 class="card-title"><?php echo htmlspecialchars($system['name']); ?></h3>
                                    
                                    <!-- Domain Link (Clickable) -->
                                    <a href="#" class="card-domain" onclick="openDomain('<?php echo htmlspecialchars($system['domain']); ?>'); return false;">
                                        <?php echo htmlspecialchars($system['domain']); ?>
                                    </a>
                                    
                                    <!-- Last Updated Timestamp -->
                                    <?php if (!empty($system['updated_at'])): ?>
                                        <div class="card-last-updated">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <polyline points="12 6 12 12 16 14"></polyline>
                                            </svg>
                                            Last updated: <?php echo date('h:i A - m/d/y', strtotime($system['updated_at'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Card Menu (Edit/Delete) -->
                                <div class="card-menu">
                                    <button class="menu-toggle" onclick="toggleDropdown(event, <?php echo $system['id']; ?>)">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                            <circle cx="12" cy="5" r="2"></circle>
                                            <circle cx="12" cy="12" r="2"></circle>
                                            <circle cx="12" cy="19" r="2"></circle>
                                        </svg>
                                    </button>
                                    
                                    <div class="dropdown-menu">
                                        <!-- Edit Button (Admin & Super Admin) -->
                                        <?php if (isSuperAdmin() || isAdmin()): ?>
                                        <button onclick="openEditModal(
                                            <?php echo $system['id']; ?>, 
                                            '<?php echo addslashes($system['name']); ?>', 
                                            '<?php echo addslashes($system['domain']); ?>', 
                                            '<?php echo addslashes($system['description']); ?>',
                                            '<?php echo addslashes($status); ?>',
                                            '<?php echo addslashes($contactNumber); ?>'
                                        )">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                            Edit
                                        </button>
                                        <?php endif; ?>
                                        
                                        <!-- Delete Button (Super Admin Only) -->
                                        <?php if (isSuperAdmin()): ?>
                                        <button class="delete-btn" onclick="deleteSystem(<?php echo $system['id']; ?>, '<?php echo addslashes($system['name']); ?>')">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            </svg>
                                            Delete
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- System Description -->
                            <?php if (!empty($system['description'])): ?>
                            <p class="card-description"><?php echo htmlspecialchars($system['description']); ?></p>
                            <?php endif; ?>
                            
                            <!-- Contact Message for Unavailable Systems -->
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
        </main>
    </div>
    
    <!-- ========================================
         MODALS
         ======================================== -->
    
    <!-- Add System Modal (Super Admin Only) -->
    <?php if (isSuperAdmin()): ?>
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New System</h3>
                <button class="close-modal" onclick="closeAddModal()">&times;</button>
            </div>
            
            <form id="addSystemForm" onsubmit="addSystem(event)" enctype="multipart/form-data">
                <div class="modal-body">
                    <!-- Logo Upload -->
                    <div class="form-group">
                        <label for="systemLogo">System Logo (Optional)</label>
                        <input type="file" id="systemLogo" name="logo" accept="image/*" onchange="previewLogo(this, 'logoPreview')">
                        <img id="logoPreview" style="display: none; width: 56px; height: 56px; margin-top: 10px; border-radius: 8px; object-fit: cover; border: 1px solid var(--gray-200);">
                    </div>
                    
                    <!-- System Name -->
                    <div class="form-group">
                        <label for="systemName">System Name *</label>
                        <input type="text" id="systemName" name="name" required placeholder="e.g., Asset Management System">
                    </div>
                    
                    <!-- Domain -->
                    <div class="form-group">
                        <label for="systemDomain">Domain *</label>
                        <input type="text" id="systemDomain" name="domain" required placeholder="e.g., glory.canteen.com.ph">
                    </div>
                    
                    <!-- Contact Number -->
                    <div class="form-group">
                        <label for="systemContact">Contact Number</label>
                        <input 
                            type="text" 
                            id="systemContact" 
                            name="contact_number" 
                            placeholder="e.g., 123 or 456" 
                            value="123"
                            pattern="[0-9]*"
                            inputmode="numeric"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                        >
                        <small style="color: var(--gray-500); font-size: 12px; margin-top: 4px; display: block;">
                            Displayed when system is Offline, Maintenance, or Down (Numbers only)
                        </small>
                    </div>
                    
                    <!-- Description -->
                    <div class="form-group">
                        <label for="systemDescription">Description</label>
                        <textarea id="systemDescription" name="description" placeholder="Brief description of the system"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add System</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Edit System Modal (Admin & Super Admin) -->
    <?php if (isSuperAdmin() || isAdmin()): ?>
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit System</h3>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            
            <form id="editSystemForm" onsubmit="editSystem(event)" enctype="multipart/form-data">
                <div class="modal-body">
                    <!-- Logo Upload -->
                    <div class="form-group">
                        <label for="editSystemLogo">System Logo (Optional)</label>
                        <input type="file" id="editSystemLogo" name="logo" accept="image/*" onchange="previewLogo(this, 'editLogoPreview')">
                        <img id="editLogoPreview" style="display: none; width: 56px; height: 56px; margin-top: 10px; border-radius: 8px; object-fit: cover; border: 1px solid var(--gray-200);">
                    </div>
                    
                    <!-- System Name -->
                    <div class="form-group">
                        <label for="editSystemName">System Name *</label>
                        <input type="text" id="editSystemName" name="name" required>
                    </div>
                    
                    <!-- Domain -->
                    <div class="form-group">
                        <label for="editSystemDomain">Domain *</label>
                        <input type="text" id="editSystemDomain" name="domain" required>
                    </div>
                    
                    <!-- Status Dropdown -->
                    <div class="form-group">
                        <label for="editSystemStatus">Status *</label>
                        <select id="editSystemStatus" name="status" required>
                            <option value="online">Online</option>
                            <option value="offline">Offline</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="down">Down</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                    
                    <!-- Contact Number -->
                    <div class="form-group">
                        <label for="editSystemContact">Contact Number</label>
                        <input 
                            type="text" 
                            id="editSystemContact" 
                            name="contact_number" 
                            placeholder="e.g., 123 or 456"
                            pattern="[0-9]*"
                            inputmode="numeric"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                        >
                        <small style="color: var(--gray-500); font-size: 12px; margin-top: 4px; display: block;">
                            Displayed when system is Offline, Maintenance, or Down (Numbers only)
                        </small>
                    </div>
                    
                    <!-- Description -->
                    <div class="form-group">
                        <label for="editSystemDescription">Description</label>
                        <textarea id="editSystemDescription" name="description"></textarea>
                    </div>
                    
                    <!-- PHASE 2: Change Note (Optional reason for status change) -->
                    <div class="form-group">
                        <label for="editChangeNote">
                            Change Note (Optional)
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; opacity: 0.6;">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                                <line x1="12" y1="17" x2="12.01" y2="17"></line>
                            </svg>
                        </label>
                        <textarea 
                            id="editChangeNote" 
                            name="change_note" 
                            placeholder="e.g., Scheduled maintenance, Server upgrade, Fixing authentication bug..."
                            rows="2"
                        ></textarea>
                        <small style="color: var(--gray-500); font-size: 12px; margin-top: 4px; display: block;">
                            Add a reason when changing status (helps track why changes were made)
                        </small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update System</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="delete-modal">
        <div class="delete-modal-content">
            <div class="delete-modal-header">
                <div class="delete-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 6h18"></path>
                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                        <line x1="10" y1="11" x2="10" y2="17"></line>
                        <line x1="14" y1="11" x2="14" y2="17"></line>
                    </svg>
                </div>
                <div class="delete-modal-title">
                    <h3>Delete System</h3>
                    <p>This action cannot be undone</p>
                </div>
            </div>
            
            <div class="delete-modal-body">
                <div class="delete-system-name">
                    <strong id="deleteSystemName"></strong>
                </div>
                
                <div class="delete-warning">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <p>Are you sure you want to delete this system? This will permanently remove all associated data.</p>
                </div>
            </div>
            
            <div class="delete-modal-footer">
                <button type="button" class="btn-delete-cancel" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" class="btn-delete-confirm" onclick="confirmDelete()">Delete System</button>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>