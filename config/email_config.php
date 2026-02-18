<?php
/**
 * ============================================================
 * EMAIL CONFIGURATION - PRODUCTION READY
 * PHPMailer SMTP Configuration
 * 
 * DEPLOYMENT INSTRUCTIONS:
 * 1. Install PHPMailer (see below)
 * 2. Uncomment your email provider section
 * 3. Fill in SMTP credentials
 * 4. Set EMAIL_NOTIFICATIONS_ENABLED to true
 * 5. Test before going live
 * ============================================================
 */

// ============================================================
// EMAIL NOTIFICATIONS TOGGLE
// ============================================================
define('EMAIL_NOTIFICATIONS_ENABLED', true); // Set to true for production

// ============================================================
// CHOOSE YOUR EMAIL PROVIDER
// Uncomment ONE section below and fill in your credentials
// ============================================================

// ------------------------------------------------------------
// OPTION 1: GMAIL (Recommended for Testing/Small Scale)
// ------------------------------------------------------------
// IMPORTANT: Use App Password, not regular password!
// 
// How to get Gmail App Password:
// 1. Go to https://myaccount.google.com/security
// 2. Enable 2-Step Verification
// 3. Search for "App passwords"
// 4. Select "Mail" and your device
// 5. Copy the 16-character password
// 6. Use it below (with or without spaces)

/*
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls');
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'xxxx xxxx xxxx xxxx'); // 16-char App Password
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
define('SMTP_FROM_NAME', 'G-Portal System Monitoring');
*/

// ------------------------------------------------------------
// OPTION 2: OFFICE 365 / OUTLOOK (Company Email)
// ------------------------------------------------------------

/*
define('SMTP_HOST', 'smtp-mail.outlook.com');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls');
define('SMTP_USERNAME', 'yourname@yourcompany.com');
define('SMTP_PASSWORD', 'your-password');
define('SMTP_FROM_EMAIL', 'yourname@yourcompany.com');
define('SMTP_FROM_NAME', 'G-Portal System Alerts');
*/

// ------------------------------------------------------------
// OPTION 3: CUSTOM SMTP SERVER (Company Mail Server)
// ------------------------------------------------------------

/*
define('SMTP_HOST', 'mail.yourcompany.com');
define('SMTP_PORT', 587);  // Common ports: 25, 465, 587
define('SMTP_ENCRYPTION', 'tls');  // Options: 'tls', 'ssl', or ''
define('SMTP_USERNAME', 'notifications@yourcompany.com');
define('SMTP_PASSWORD', 'your-password');
define('SMTP_FROM_EMAIL', 'notifications@yourcompany.com');
define('SMTP_FROM_NAME', 'G-Portal Monitoring');
*/

// ------------------------------------------------------------
// DEVELOPMENT/TESTING (File Logging Only - No Real Emails)
// ------------------------------------------------------------
// COMMENT THIS OUT FOR PRODUCTION!

define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 25);
define('SMTP_ENCRYPTION', '');
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', 'no-reply@gportal.local');
define('SMTP_FROM_NAME', 'G-Portal [TEST MODE]');
define('USE_FILE_LOGGING', true); // Set to false for production

// ============================================================
// EMAIL NOTIFICATION SETTINGS
// ============================================================

// Which status changes trigger email notifications?
define('EMAIL_TRIGGER_STATUSES', ['down', 'offline']);

// Who receives notification emails?
// Options: 'all_admins' or 'super_admin_only'
define('EMAIL_RECIPIENTS', 'all_admins');

// Email subject prefix
define('EMAIL_SUBJECT_PREFIX', '[G-Portal Alert]');

// ============================================================
// ADVANCED SETTINGS
// ============================================================

// SMTP connection timeout (seconds)
define('SMTP_TIMEOUT', 30);

// Debug level (0 = off, 1 = client messages, 2 = client + server, 3 = detailed)
// Use 0 for production, 2 for troubleshooting
define('SMTP_DEBUG', 0);

// Keep connection alive for multiple emails
define('SMTP_KEEPALIVE', true);

// Email character set
define('EMAIL_CHARSET', 'UTF-8');

// Verify SSL certificates (set to false if using self-signed)
define('SMTP_VERIFY_SSL', true);

// ============================================================
// PHPMAILER PATHS
// ============================================================
// Auto-detect PHPMailer installation
// Supports Composer and manual installation

define('PHPMAILER_PATH', __DIR__ . '/../vendor/phpmailer/phpmailer/src/');

// ============================================================
// DEPLOYMENT CHECKLIST
// ============================================================

/*
BEFORE DEPLOYING TO PRODUCTION:

SETUP:
[ ] PHPMailer installed (Composer or manual)
[ ] Email provider section uncommented
[ ] SMTP credentials filled in correctly
[ ] Tested with one email successfully

CONFIGURATION:
[ ] EMAIL_NOTIFICATIONS_ENABLED = true
[ ] USE_FILE_LOGGING = false (or remove line)
[ ] SMTP_DEBUG = 0
[ ] Development config section commented out

TESTING:
[ ] Test email sent successfully
[ ] Email received (check spam folder too)
[ ] Philippine timezone correct in email
[ ] No emoji in auto-detected notes
[ ] All admins received email

SECURITY:
[ ] Using App Password (not account password)
[ ] SMTP credentials NOT in version control
[ ] File permissions set correctly
[ ] SSL/TLS encryption enabled
*/

?>