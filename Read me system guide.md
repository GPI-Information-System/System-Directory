

system-directory/
│
├── assets/
│   ├── css/
│   │   ├── style.css                    # Dashboard & Login UI styles
│   │   ├── viewer.css                   # Viewer page UI styles
│   │   ├── analytics.css                # Analytics page styles
│   │   ├── notifications.css            # Notification bell widget styles
│   │   ├── maintenance.css              # Maintenance scheduling UI styles
│   │   └── error_page.css               # Error landing page styles
│   │
│   └── js/
│       ├── main.js                      # Login & admin JavaScript functionality
│       ├── viewer_maitenance.js         # Viewer Maintenance Schedule page JS functionality
│       ├── analytics.js                 # Charts, reports, PDF export, edit note
│       ├── notifications.js             # Bell widget, recent updates, auto-refresh
│       └── maintenance.js               # Calendar, maintenance modal, bulk scheduling,
│                                        # conflict detection, email tag input
│
├── backend/
│   ├── logs/
│   │   ├── emails.log                   # IT alert email log (system down/offline)
│   │   ├── maintenance_emails.log       # Maintenance notification email log
│   │   └── health_check.log             # Automated health check log
│   │
│   ├── maintenance/
│   │   ├── save_maintenance.php         # Create, update maintenance schedules
│   │   │                                # + email notification trigger
│   │   ├── get_maintenance.php          # API: calendar, day, system, single queries
│   │   ├── delete_maintenance.php       # Soft delete maintenance schedules
│   │   ├── check_maintenance_schedule.php  # Check active schedule conflicts
│   │   ├── send_maintenance_email.php   # Email function for maintenance notifications
│   │   │                                # (created / updated / cancelled triggers)
│   │   └── email_recipients_api.php     # Autocomplete API for email recipients
│   │
│   ├── add_system.php                   # Add systems (Super Admin only)
│   ├── edit_system.php                  # Edit systems + log changes + email alerts
│   ├── delete_system.php                # Delete systems (Super Admin only)
│   ├── logout.php                       # User logout
│   ├── get_analytics_data.php           # API for charts, uptime, reports
│   ├── update_log_note.php              # Edit notes on existing status logs
│   ├── get_notifications.php            # API for recent status changes (bell widget)
│   ├── send_email_notification.php      # Email function for critical status alerts
│   ├── trigger_maintenance_check.php    # Automated maintenance status checker
│   ├── trigger_health_check.php         # Automated system health checker
│   └── check_systems_health.php        # Health check logic
│
├── config/
│   ├── database.php                     # Database connection (XAMPP/phpMyAdmin)
│   ├── session.php                      # Session & role management
│   └── email_config.php                 # SMTP settings for email notifications
│
├── pages/
│   ├── dashboard.php                    # Super Admin & Admin dashboard
│   │                                    # + maintenance scheduling modals
│   │                                    # + bulk scheduling with conflict detection
│   │                                    # + email notification toggle & tag input
│   ├── analytics.php                    # Analytics & reporting dashboard
│   ├── viewer.php                       # Employee/viewer page (public access)
│   └── error_page.php                   # Custom error landing page
│                                        # (404, 403, 500, 503, maintenance, down)
│
├── uploads/
│   └── logos/                           # System logo uploads storage
│
├── .htaccess                            # Apache custom error page routing
├── index.php                            # Login page (main entry point)
├── database_setup.sql                   # Database creation script
├── email_recipients_table.sql           # email_recipients table for autocomplete
├── README.md                            # Complete installation guide
├── QUICKSTART.md                        # 5-minute quick start guide
└── system_directory_db.sql              # Full database export with sample data




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