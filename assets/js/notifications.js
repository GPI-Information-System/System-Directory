/* ============================================================
   PHASE 3: NOTIFICATION BELL JAVASCRIPT
   Handles fetching and displaying recent status changes
   ============================================================ */

// Global variables
let notificationCheckInterval = null;
let lastNotificationCount = 0;

// ============================================================
// INITIALIZATION
// ============================================================

document.addEventListener('DOMContentLoaded', function() {
    initializeNotifications();
});

function initializeNotifications() {
    // Load notifications immediately
    loadNotifications();
    
    // Set up auto-refresh every 30 seconds
    notificationCheckInterval = setInterval(loadNotifications, 30000);
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const bellContainer = document.querySelector('.notification-bell');
        const dropdown = document.getElementById('notificationDropdown');
        
        if (bellContainer && !bellContainer.contains(event.target)) {
            dropdown?.classList.remove('show');
        }
    });
}

// ============================================================
// TOGGLE NOTIFICATION DROPDOWN
// ============================================================

function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    const isShowing = dropdown.classList.contains('show');
    
    if (isShowing) {
        dropdown.classList.remove('show');
    } else {
        dropdown.classList.add('show');
        // Refresh notifications when opening
        loadNotifications();
    }
}

// ============================================================
// LOAD NOTIFICATIONS
// ============================================================

function loadNotifications() {
    fetch('../backend/get_notifications.php?hours=24')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationBadge(data.count);
                renderNotifications(data.notifications);
                lastNotificationCount = data.count;
            }
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
        });
}

// ============================================================
// UPDATE NOTIFICATION BADGE
// ============================================================

function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationBadge');
    const countElement = document.getElementById('notificationCount');
    
    if (count > 0) {
        badge.style.display = 'block';
        if (countElement) {
            countElement.textContent = count;
        }
    } else {
        badge.style.display = 'none';
    }
}

// ============================================================
// RENDER NOTIFICATIONS
// ============================================================

function renderNotifications(notifications) {
    const container = document.getElementById('notificationList');
    
    if (!notifications || notifications.length === 0) {
        container.innerHTML = `
            <div class="notification-empty">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <p>No recent notifications</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = notifications.map(notif => {
        const timeAgo = getTimeAgo(notif.changed_at);
        
        return `
            <div class="notification-item" onclick="viewSystem(${notif.system_id})">
                <div class="notification-item-header">
                    <div class="notification-system-name">${escapeHtml(notif.system_name)}</div>
                    <div class="notification-time">${timeAgo}</div>
                </div>
                <div class="notification-status-change">
                    <span class="notification-status-badge ${notif.old_status}">${capitalize(notif.old_status)}</span>
                    →
                    <span class="notification-status-badge ${notif.new_status}">${capitalize(notif.new_status)}</span>
                </div>
            </div>
        `;
    }).join('');
}

// ============================================================
// VIEW SYSTEM (Navigate to system)
// ============================================================

function viewSystem(systemId) {
    // Close dropdown
    document.getElementById('notificationDropdown').classList.remove('show');
    
    // Scroll to system card
    const systemCard = document.querySelector(`[data-system-id="${systemId}"]`);
    if (systemCard) {
        systemCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Highlight the card briefly
        systemCard.style.transition = 'all 0.3s';
        systemCard.style.transform = 'scale(1.02)';
        systemCard.style.boxShadow = '0 8px 24px rgba(30, 58, 138, 0.3)';
        
        setTimeout(() => {
            systemCard.style.transform = '';
            systemCard.style.boxShadow = '';
        }, 1000);
    }
}

// ============================================================
// UTILITY FUNCTIONS
// ============================================================

function getTimeAgo(datetime) {
    const now = new Date();
    const then = new Date(datetime);
    const seconds = Math.floor((now - then) / 1000);
    
    if (seconds < 60) return 'Just now';
    
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d ago`;
    
    return then.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================================
// CLEANUP ON PAGE UNLOAD
// ============================================================

window.addEventListener('beforeunload', function() {
    if (notificationCheckInterval) {
        clearInterval(notificationCheckInterval);
    }
});