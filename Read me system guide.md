

Folder Structure
System-Directory/
├── assets/
│   ├── css/
│   │   ├── style.css          — Main dashboard styles
│   │   ├── maintenance.css    — Maintenance modal styles
│   │   ├── analytics.css      — Analytics page styles
│   │   └── users.css          — User management styles
│   └── js/
│       ├── main.js            — Dashboard logic, chart, filters, CRUD
│       ├── maintenance.js     — Calendar, maintenance modal, bulk scheduling
│       ├── health_check.js    — Polling logic (every 10s), toast notifications
│       ├── analytics.js       — Analytics page JS (search, pagination, charts)
│       ├── viewer.js          — Viewer page JS (JP translation, filters, popover)
│       ├── viewer_maintenance.js — Maintenance viewer JS (JP translation, filters, countdown)
│       ├── notifications.js   — Notification bell and panel
│       └── users.js           — User management CRUD
├── backend/
│   ├── logs/
│   │   ├── health_check.log   — Auto-rotating health check log (max 500 lines)
│   │   ├── emails.log         — Auto-rotating email log (max 500 lines)
│   │   └── maintenance_emails.log
│   ├── add_system.php
│   ├── edit_system.php
│   ├── delete_system.php
│   ├── add_user.php
│   ├── edit_user.php
│   ├── delete_user.php
│   ├── save_maintenance.php
│   ├── trigger_health_check.php   — Main health check engine (badge + domain fallback)
│   ├── check_systems_health.php   — Cron version of health check
│   ├── get_analytics_data.php     — Analytics data endpoints
│   ├── get_notifications.php      — Notification bell data
│   ├── get_systems_status.php     — Live status for cards
│   ├── send_email_notification.php — Email alerts (PHPMailer or file log)
│   ├── update_log_note.php
│   └── logout.php
├── config/
│   ├── database.php           — DB connection, helper functions
│   ├── session.php            — Session management, role helpers
│   └── email_config.php       — SMTP configuration
├── pages/
│   ├── dashboard.php          — Admin dashboard (system cards, calendar, chart)
│   ├── analytics.php          — Analytics & Reports page
│   ├── viewer.php             — Public viewer page (no login)
│   ├── viewer_maintenance.php — Public maintenance schedule viewer
│   └── users.php              — User management page (Super Admin only)
├── sessions/                  — PHP session storage (local folder)
├── uploads/
│   └── logos/                 — Uploaded system logos
├── vendor/                    — PHPMailer (composer)
└── index.php                  — Login page





Features Summary
Core Features

Login & Session Management — Role-based access (Super Admin / Admin / Viewer)
System Dashboard — Add, edit, delete, and monitor all systems
Analytics — Charts, uptime reports, PDF export
Viewer Page — Public read-only system status page
Notification Bell — Real-time status change alerts

Maintenance Scheduling 

Single-system scheduling — Create, edit, delete schedules per system
Bulk scheduling — Schedule maintenance for multiple systems at once
Conflict detection — Pre-checks for active schedule conflicts before saving
Calendar view — Monthly calendar with maintenance dots and side panel
Schedule detail modal — View full details with exceeded duration tracking

Email Notifications 

IT alerts — Emails sent when a system goes down/offline
Maintenance notifications — Emails sent on schedule create / update / cancel
Change detection — Update emails show exactly what changed
Email tag input — Per-schedule recipient management with autocomplete
File logging fallback — Logs to backend/logs/ when PHPMailer unavailable

Error Landing Page 

Custom error page — Branded G-Portal error page for all HTTP error types
Dynamic content — Pulls system name, contact number, and maintenance
details from database automatically
Error types — 404, 403, 500, 503, maintenance, down, offline
G-Portal redirect — Right panel always links to viewer.php













Demo Accounts
Super Admin: superadmin / admin123

Admin: admin / admin123





Error page URL for testing:

localhost:8080/system-directory/pages/error_page.php?type=404 - Page not found
localhost:8080/system-directory/pages/error_page.php?type=500 - Internal Server Error
localhost:8080/system-directory/pages/error_page.php?type=403 - Access Denied
localhost:8080/system-directory/pages/error_page.php?type=maintenance&domain=youtube.com - System Under Maintenance
localhost:8080/system-directory/pages/error_page.php?type=down&domain=youtube.com - System Down


/FOR DATA CLEARING IN DB/

SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM maintenance_schedules;
DELETE FROM status_logs;
DELETE FROM systems;
ALTER TABLE maintenance_schedules AUTO_INCREMENT = 1;
ALTER TABLE status_logs AUTO_INCREMENT = 1;
ALTER TABLE systems AUTO_INCREMENT = 1;
SET FOREIGN_KEY_CHECKS = 1;





/DATA CLEAR ON ANALYTICS PAGE ONLY/
-- Clear all status change logs
TRUNCATE TABLE status_logs;

-- Clear all maintenance schedules
TRUNCATE TABLE maintenance_schedules;

-- Only reset 'maintenance' systems back to 'online'
-- (since their schedules are now deleted, they'd be stuck in maintenance)
-- Down/offline/archived systems are left untouched
UPDATE systems SET status = 'online', updated_at = NOW()
WHERE status = 'maintenance';