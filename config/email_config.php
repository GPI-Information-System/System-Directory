<?php
/*G-Portal — Email Configuration - Office 365 / Outlook*/

// ============================================================
define('EMAIL_NOTIFICATIONS_ENABLED', false); // ← Change to true when ready

// ============================================================
// OFFICE 365 SMTP CONFIGURATION
// The sender account must have SMTP AUTH enabled by IT Admin.
// ============================================================
define('SMTP_HOST',       'smtp.office365.com');
define('SMTP_PORT',       587);
define('SMTP_ENCRYPTION', 'tls');

// ── Fill these in before deployment ──────────────────────────
define('SMTP_USERNAME', 'YOUR_EMAIL@yourcompany.com');    // e.g. gportal-alerts@glory.com
define('SMTP_PASSWORD', 'YOUR_PASSWORD_HERE');             // Account password or app password

// ── Sender details (shown in "From" field of email) ──────────
define('EMAIL_FROM_ADDRESS', 'YOUR_EMAIL@yourcompany.com'); // Same as SMTP_USERNAME
define('EMAIL_FROM_NAME',    'G-Portal System Alerts');

// ============================================================
// NOTIFICATION SETTINGS
// ============================================================

// Only trigger emails when system goes Down or Offline
define('EMAIL_TRIGGER_STATUSES', ['down', 'offline']);

// Send only to Super Admins
// Options: 'super_admin_only' | 'all_admins'
define('EMAIL_RECIPIENTS', 'super_admin_only');

// Email subject prefix
define('EMAIL_SUBJECT_PREFIX', '[G-Portal Alert]');



// SMTP connection timeout in seconds
define('SMTP_TIMEOUT', 30);

// Debug level: 0 = off, 2 = verbose (use 2 when troubleshooting)
define('SMTP_DEBUG', 0);

// Keep SMTP connection alive when sending to multiple recipients
define('SMTP_KEEPALIVE', true);

// Email character encoding
define('EMAIL_CHARSET', 'UTF-8');

// SSL certificate verification
// Set to false only if your server has SSL issues
define('SMTP_VERIFY_SSL', true);


define('USE_FILE_LOGGING', true); // ← Set to false for production