<?php
/**
 * G-Portal — System Error Landing Page
 * 
 * Usage:
 *   error.php?type=503&domain=youtube.com
 *   error.php?type=404&domain=youtube.com
 *   error.php?type=500&domain=youtube.com
 *   error.php?type=403&domain=youtube.com
 *   error.php?type=maintenance&system_id=5
 *   error.php?type=down&domain=youtube.com
 * 
 * Place this file at: system-directory/pages/error.php
 * The viewer page link points to: viewer.php
 */

require_once '../config/database.php';

$type     = trim($_GET['type']      ?? '503');
$domain   = trim($_GET['domain']    ?? '');
$systemId = intval($_GET['system_id'] ?? 0);

// ── Fetch system info from DB if domain or system_id provided ──
$systemInfo      = null;
$maintenanceInfo = null;

$conn = getDBConnection();

if ($systemId > 0) {
    $stmt = $conn->prepare("SELECT id, name, domain, contact_number, status FROM systems WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $systemId);
    $stmt->execute();
    $systemInfo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} elseif (!empty($domain)) {
    $stmt = $conn->prepare("SELECT id, name, domain, contact_number, status FROM systems WHERE domain = ? LIMIT 1");
    $stmt->bind_param("s", $domain);
    $stmt->execute();
    $systemInfo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// If system found and type is maintenance, fetch active schedule
if ($systemInfo) {
    $systemId = $systemInfo['id'];
    $stmt = $conn->prepare("
        SELECT title, description, start_datetime, end_datetime, status
        FROM maintenance_schedules
        WHERE system_id = ?
          AND status IN ('Scheduled', 'In Progress')
          AND deleted_from_calendar = 0
        ORDER BY start_datetime ASC
        LIMIT 1
    ");
    $stmt->bind_param("i", $systemId);
    $stmt->execute();
    $maintenanceInfo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Auto-detect type from system status if not maintenance/down
    if (in_array($type, ['503', 'down', 'maintenance'])) {
        if ($systemInfo['status'] === 'maintenance' || $maintenanceInfo) {
            $type = 'maintenance';
        } elseif (in_array($systemInfo['status'], ['down', 'offline'])) {
            $type = 'down';
        }
    }
}

$conn->close();

// ── Error config per type ──────────────────────────────────────
$errors = [
    '404' => [
        'code'    => '404',
        'label'   => 'Not Found',
        'title'   => 'Page Not Found',
        'message' => 'The page you\'re looking for doesn\'t exist or has been moved.',
        'color'   => '#6366f1',
        'bg'      => '#EEF2FF',
        'icon'    => 'search',
    ],
    '403' => [
        'code'    => '403',
        'label'   => 'Forbidden',
        'title'   => 'Access Denied',
        'message' => 'You don\'t have permission to access this page.',
        'color'   => '#f59e0b',
        'bg'      => '#FFFBEB',
        'icon'    => 'lock',
    ],
    '500' => [
        'code'    => '500',
        'label'   => 'Server Error',
        'title'   => 'Internal Server Error',
        'message' => 'Something went wrong on our end. Our team has been notified.',
        'color'   => '#ef4444',
        'bg'      => '#FEF2F2',
        'icon'    => 'alert',
    ],
    'down' => [
        'code'    => '',
        'label'   => 'System Down',
        'title'   => 'System Unavailable',
        'message' => 'This system is currently unavailable. Please try again later.',
        'color'   => '#ef4444',
        'bg'      => '#FEF2F2',
        'icon'    => 'offline',
    ],
    'maintenance' => [
        'code'    => '',
        'label'   => 'Under Maintenance',
        'title'   => 'System Under Maintenance',
        'message' => 'This system is currently undergoing scheduled maintenance.',
        'color'   => '#f59e0b',
        'bg'      => '#FFFBEB',
        'icon'    => 'maintenance',
    ],
    'offline' => [
        'code'    => '',
        'label'   => 'Offline',
        'title'   => 'System Offline',
        'message' => 'This system is currently offline.',
        'color'   => '#6b7280',
        'bg'      => '#F9FAFB',
        'icon'    => 'offline',
    ],
];

// Default to 503 if unknown type
$errorConfig = $errors[$type] ?? [
    'code'    => '503',
    'label'   => 'Unavailable',
    'title'   => 'Service Unavailable',
    'message' => 'This service is temporarily unavailable. Please try again later.',
    'color'   => '#ef4444',
    'bg'      => '#FEF2F2',
    'icon'    => 'alert',
];

$systemName    = $systemInfo['name']           ?? ($domain ?: 'This System');
$contactNumber = $systemInfo['contact_number'] ?? null;

// Format datetimes
function fmtDt($dt) {
    if (!$dt) return '—';
    date_default_timezone_set('Asia/Manila');
    return date('F j, Y \a\t g:i A', strtotime($dt));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($errorConfig['title']) ?> — G-Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/error_page.css">
    <style>
        /* Dynamic variables injected per error type */
        :root {
            --error-color: <?= $errorConfig['color'] ?>;
            --error-bg:    <?= $errorConfig['bg'] ?>;
        }
    </style>
</head>
<body>
<div class="page">

    <!-- ═══════════════ LEFT PANEL ═══════════════ -->
    <div class="left-panel">

        <?php if (!empty($errorConfig['code'])): ?>
            <div class="error-code"><?= $errorConfig['code'] ?></div>
        <?php endif; ?>

        <!-- G-Portal mark -->
        <div class="gportal-mark">
            <div class="gportal-mark-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                    <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                    <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                    <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                </svg>
            </div>
            <span class="gportal-mark-text">G-Portal</span>
        </div>

        <!-- Error badge -->
        <div class="error-badge">
            <span class="error-badge-dot"></span>
            <?= htmlspecialchars($errorConfig['label']) ?>
        </div>

        <!-- Heading -->
        <h1 class="error-heading">
            <?php
                $words = explode(' ', $errorConfig['title']);
                $last  = array_pop($words);
                echo implode(' ', $words) . ' <span>' . htmlspecialchars($last) . '</span>';
            ?>
        </h1>

        <!-- System name -->
        <div class="system-name-row">
            <span class="system-name-label">System</span>
            <span class="system-name-value"><?= htmlspecialchars($systemName) ?></span>
        </div>

        <!-- Message -->
        <p class="error-message"><?= htmlspecialchars($errorConfig['message']) ?></p>

        <!-- Maintenance details card -->
        <?php if ($type === 'maintenance' && $maintenanceInfo): ?>
        <div class="maintenance-card">
            <div class="maintenance-card-header">
                <div class="maintenance-card-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                </div>
                <div>
                    <div class="maintenance-card-title">Scheduled Maintenance Details</div>
                    <div class="maintenance-card-subtitle">Information about this maintenance window</div>
                </div>
            </div>
            <div class="maintenance-card-rows">
                <div class="maintenance-row">
                    <span class="maintenance-row-label">Title</span>
                    <span class="maintenance-row-value"><?= htmlspecialchars($maintenanceInfo['title']) ?></span>
                </div>
                <div class="maintenance-row">
                    <span class="maintenance-row-label">Status</span>
                    <span class="maintenance-row-value">
                        <?php $pill = $maintenanceInfo['status'] === 'In Progress' ? 'pill-in-progress' : 'pill-scheduled'; ?>
                        <span class="maintenance-status-pill <?= $pill ?>">
                            <?= htmlspecialchars($maintenanceInfo['status']) ?>
                        </span>
                    </span>
                </div>
                <div class="maintenance-row">
                    <span class="maintenance-row-label">Start</span>
                    <span class="maintenance-row-value"><?= fmtDt($maintenanceInfo['start_datetime']) ?></span>
                </div>
                <div class="maintenance-row">
                    <span class="maintenance-row-label">End</span>
                    <span class="maintenance-row-value"><?= fmtDt($maintenanceInfo['end_datetime']) ?></span>
                </div>
                <?php if (!empty($maintenanceInfo['description'])): ?>
                <div class="maintenance-row">
                    <span class="maintenance-row-label">Details</span>
                    <span class="maintenance-row-value"><?= htmlspecialchars($maintenanceInfo['description']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Contact number -->
        <?php if ($contactNumber): ?>
        <div class="contact-row">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.38 2 2 0 0 1 3.6 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.6a16 16 0 0 0 6.29 6.29l.97-.97a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"></path>
            </svg>
            <span class="contact-row-text">
                Need help? Contact IT Support: <strong><?= htmlspecialchars($contactNumber) ?></strong>
            </span>
        </div>
        <?php endif; ?>

    </div>

    <!-- ═══════════════ RIGHT PANEL ═══════════════ -->
    <div class="right-panel">
        <div class="right-panel-grid"></div>

        <div class="right-content">
            <div class="right-icon-wrap">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                    <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                    <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                    <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                </svg>
            </div>

            <h2 class="right-heading">View System Status<br>on G-Portal</h2>
            <p class="right-sub">Check real-time status, maintenance schedules, and system health across all services.</p>

            <a href="viewer.php" class="cta-btn">
                Go to G-Portal
                <span class="cta-btn-arrow">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </span>
            </a>

            <div class="right-divider"></div>

            <div class="status-indicator">
                <span class="status-dot-live"></span>
                G-Portal is online
            </div>

            <div class="right-timestamp" id="timestamp"></div>
        </div>
    </div>

</div>

<script>
    // Live timestamp
    function updateTimestamp() {
        const now = new Date();
        const opts = { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
        document.getElementById('timestamp').textContent = now.toLocaleDateString('en-US', opts);
    }
    updateTimestamp();
    setInterval(updateTimestamp, 1000);
</script>
</body>
</html>