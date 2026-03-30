<?php
/**
 * G-Portal — Email Notification
 * Sends alerts to all Super Admins when a system goes Down or Offline.
 * Uses PHPMailer with Office 365 SMTP.
 */

require_once __DIR__ . '/../config/email_config.php';
require_once __DIR__ . '/../config/database.php';

// FIX: Load PHPMailer manually (no Composer needed)
// Files placed in vendor/phpmailer/src/
$phpmailerBase = __DIR__ . '/../vendor/phpmailer/src/';
if (file_exists($phpmailerBase . 'Exception.php') &&
    file_exists($phpmailerBase . 'PHPMailer.php') &&
    file_exists($phpmailerBase . 'SMTP.php')) {
    require_once $phpmailerBase . 'Exception.php';
    require_once $phpmailerBase . 'PHPMailer.php';
    require_once $phpmailerBase . 'SMTP.php';
} else {
    error_log("G-Portal Email: PHPMailer files not found in vendor/phpmailer/src/");
}

// ── Log rotation settings ──────────────────────────────────
define('EMAIL_LOG_FILE',       __DIR__ . '/logs/emails.log');
define('EMAIL_LOG_MAX_LINES',  500);
define('EMAIL_LOG_KEEP_LINES', 400);

// ============================================================
// MAIN ENTRY POINT
// Called by trigger_health_check.php when status changes to
// 'down' or 'offline'.
// ============================================================
function sendStatusChangeEmail($systemId, $systemName, $oldStatus, $newStatus, $domain, $changedBy, $note = '') {

    
    if (!defined('EMAIL_NOTIFICATIONS_ENABLED') || !EMAIL_NOTIFICATIONS_ENABLED) {
        // Still log to file using real recipients so you can verify who would receive it
        $recipients = getSuperAdminEmails();
        $text       = buildTextEmail($systemName, $oldStatus, $newStatus, $domain, $changedBy, $note);
        if (!empty($recipients)) {
            foreach ($recipients as $recipient) {
                logEmailToFile($recipient['email'], $recipient['name'],
                    '[G-Portal — DISABLED] System ' . $newStatus . ': ' . $systemName,
                    '', $text);
            }
        } else {
            logEmailToFile('no-superadmin-email', 'No Super Admin email set',
                '[G-Portal — DISABLED] System ' . $newStatus . ': ' . $systemName,
                '', $text);
        }
        return false;
    }

    // ── Only send for Down / Offline ─────────────────────────
    $triggerStatuses = defined('EMAIL_TRIGGER_STATUSES') ? EMAIL_TRIGGER_STATUSES : ['down', 'offline'];
    if (!in_array($newStatus, $triggerStatuses)) return false;

    // ── Fetch Super Admin recipients from DB ─────────────────
    $recipients = getSuperAdminEmails();
    if (empty($recipients)) {
        error_log("G-Portal Email: No Super Admin emails found in users table");
        return false;
    }

    $prefix  = defined('EMAIL_SUBJECT_PREFIX') ? EMAIL_SUBJECT_PREFIX : '[G-Portal Alert]';
    $subject = "$prefix System $newStatus: $systemName";
    $html    = buildEmailTemplate($systemName, $oldStatus, $newStatus, $domain, $changedBy, $note);
    $text    = buildTextEmail($systemName, $oldStatus, $newStatus, $domain, $changedBy, $note);

    $success = true;
    foreach ($recipients as $recipient) {
        $sent = sendEmail($recipient['email'], $recipient['name'], $subject, $html, $text);
        if (!$sent) {
            $success = false;
            error_log("G-Portal Email: Failed to send to {$recipient['email']}");
        }
    }
    return $success;
}

// ============================================================
// FETCH RECIPIENTS
// Always fetches Super Admins only
// 
// ============================================================
function getSuperAdminEmails() {
    $conn = getDBConnection();

    $sql = "SELECT email, username
            FROM users
            WHERE role = 'Super Admin'
              AND email IS NOT NULL
              AND email != ''
            ORDER BY id ASC";

    $result     = $conn->query($sql);
    $recipients = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recipients[] = [
                'email' => $row['email'],
                'name'  => $row['username']
            ];
        }
    }
    
    return $recipients;
}


function getAdminEmails() {
    return getSuperAdminEmails();
}

// ============================================================
// SEND ROUTER
// Uses PHPMailer if available, otherwise falls back to log
// ============================================================
function sendEmail($to, $toName, $subject, $htmlBody, $textBody) {
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return sendEmailViaPHPMailer($to, $toName, $subject, $htmlBody, $textBody);
    }
    // PHPMailer not found — log only
    error_log("G-Portal Email: PHPMailer not found — logging only");
    return logEmailToFile($to, $toName, $subject, $htmlBody, $textBody);
}

// ============================================================
// PHPMAILER — OFFICE 365 SMTP
// ============================================================
function sendEmailViaPHPMailer($to, $toName, $subject, $htmlBody, $textBody) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        // ── Debug output ──────────────────────────────────────
        $debugLevel = defined('SMTP_DEBUG') ? SMTP_DEBUG : 0;
        if ($debugLevel > 0) {
            $mail->SMTPDebug   = $debugLevel;
            $mail->Debugoutput = function($str, $level) {
                error_log("PHPMailer [$level]: $str");
            };
        }

        // ── SMTP server settings ──────────────────────────────
        $mail->isSMTP();
        $mail->Host       = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.office365.com';
        $mail->Port       = defined('SMTP_PORT') ? SMTP_PORT : 587;
        $mail->SMTPAuth   = true;
        $mail->Username   = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
        $mail->Password   = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
        $mail->SMTPSecure = defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls';

        // ── SSL options ───────────────────────────────────────
        $verifySSL = defined('SMTP_VERIFY_SSL') ? SMTP_VERIFY_SSL : true;
        if (!$verifySSL) {
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ]
            ];
        }

        // ── Timeout ───────────────────────────────────────────
        $mail->Timeout = defined('SMTP_TIMEOUT') ? SMTP_TIMEOUT : 30;

        // ── Keep alive for multiple recipients ────────────────
        if (defined('SMTP_KEEPALIVE') && SMTP_KEEPALIVE) {
            $mail->SMTPKeepAlive = true;
        }

        // ── Sender details ────────────────────────────────────
        $fromEmail = defined('EMAIL_FROM_ADDRESS') ? EMAIL_FROM_ADDRESS : (defined('SMTP_USERNAME') ? SMTP_USERNAME : '');
        $fromName  = defined('EMAIL_FROM_NAME')    ? EMAIL_FROM_NAME    : 'G-Portal System Alerts';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to, $toName);
        $mail->CharSet = defined('EMAIL_CHARSET') ? EMAIL_CHARSET : 'UTF-8';

        // ── Email content ─────────────────────────────────────
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody;

        $mail->send();

        error_log("G-Portal Email: Sent to $to via PHPMailer (Office 365)");
        logEmailToFile($to, $toName, $subject, $htmlBody, $textBody);
        return true;

    } catch (PHPMailer\PHPMailer\Exception $e) {
        error_log("G-Portal Email: PHPMailer FAILED — " . $e->getMessage());
        logEmailToFile($to, $toName, $subject, $htmlBody, $textBody);
        return false;
    }
}

// ============================================================
// FILE LOGGER 
// ============================================================
function logEmailToFile($to, $toName, $subject, $htmlBody, $textBody) {
    $logFile = EMAIL_LOG_FILE;
    $logDir  = dirname($logFile);

    if (!file_exists($logDir)) mkdir($logDir, 0777, true);

    date_default_timezone_set('Asia/Manila');

    $logEntry  = "\n" . str_repeat('=', 80) . "\n";
    $logEntry .= "Email logged at: " . date('Y-m-d H:i:s') . "\n";
    $logEntry .= "To: $toName <$to>\n";
    $logEntry .= "Subject: $subject\n";
    $logEntry .= str_repeat('-', 80) . "\n";
    $logEntry .= $textBody . "\n";
    $logEntry .= str_repeat('=', 80) . "\n";

    // ── Log rotation: trim to 400 lines when exceeding 500 ───
    if (file_exists($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (count($lines) > EMAIL_LOG_MAX_LINES) {
            $trimmed = array_slice($lines, -EMAIL_LOG_KEEP_LINES);
            file_put_contents($logFile, implode(PHP_EOL, $trimmed) . PHP_EOL);
        }
    }

    $result = file_put_contents($logFile, $logEntry, FILE_APPEND);
    if ($result === false) {
        error_log("G-Portal Email: Could not write to log: $logFile");
        return false;
    }
    return true;
}

// ============================================================
// EMAIL TEMPLATES
// ============================================================

function buildEmailTemplate($systemName, $oldStatus, $newStatus, $domain, $changedBy, $note) {
    $statusColors = [
        'down'        => '#ef4444',
        'offline'     => '#6b7280',
        'maintenance' => '#f59e0b',
        'online'      => '#10b981'
    ];
    $newStatusColor = $statusColors[$newStatus] ?? '#6b7280';
    $oldStatusLabel = ucfirst($oldStatus);
    $newStatusLabel = ucfirst($newStatus);

    date_default_timezone_set('Asia/Manila');
    $timestamp = date('F j, Y \a\t g:i A');

    $cleanNote = trim(preg_replace('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $note));

    $noteRow = '';
    if (!empty($cleanNote)) {
        $noteRow = "
        <tr style=\"background-color: #f9fafb;\">
            <td style=\"font-weight: bold; color: #6b7280; width: 40%; padding: 10px;\">Note:</td>
            <td style=\"color: #1a1f36; padding: 10px;\">$cleanNote</td>
        </tr>";
    }

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #162660; padding: 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: bold;">G-Portal System Alert</h1>
                        </td>
                    </tr>
                    <!-- Status Banner -->
                    <tr>
                        <td style="padding: 30px; text-align: center; background-color: #fee2e2;">
                            <div style="display: inline-block; background-color: $newStatusColor; color: #ffffff; padding: 12px 24px; border-radius: 8px; font-size: 18px; font-weight: bold;">
                                System is $newStatusLabel
                            </div>
                        </td>
                    </tr>
                    <!-- Details -->
                    <tr>
                        <td style="padding: 30px;">
                            <h2 style="margin: 0 0 20px 0; color: #1a1f36; font-size: 20px;">$systemName</h2>
                            <table width="100%" cellpadding="10" cellspacing="0" style="border: 1px solid #e5e7eb; border-radius: 8px;">
                                <tr style="background-color: #f9fafb;">
                                    <td style="font-weight: bold; color: #6b7280; width: 40%;">Status Change:</td>
                                    <td style="color: #1a1f36;">
                                        <span style="background-color: #e5e7eb; padding: 4px 8px; border-radius: 4px; font-size: 12px;">$oldStatusLabel</span>
                                        &rarr;
                                        <span style="background-color: $newStatusColor; color: #ffffff; padding: 4px 8px; border-radius: 4px; font-size: 12px;">$newStatusLabel</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold; color: #6b7280;">Domain:</td>
                                    <td><a href="https://$domain" style="color: #162660; text-decoration: none;">$domain</a></td>
                                </tr>
                                <tr style="background-color: #f9fafb;">
                                    <td style="font-weight: bold; color: #6b7280;">Changed By:</td>
                                    <td style="color: #1a1f36;">$changedBy</td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold; color: #6b7280;">Time:</td>
                                    <td style="color: #1a1f36;">$timestamp</td>
                                </tr>
                                $noteRow
                            </table>
                            <div style="margin-top: 30px; text-align: center;">
                                <a href="https://$domain" style="display: inline-block; background-color: #162660; color: #ffffff; padding: 12px 30px; border-radius: 8px; text-decoration: none; font-weight: bold;">View System</a>
                            </div>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 20px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; color: #6b7280; font-size: 12px;">
                                This is an automated notification from G-Portal System Monitoring.<br>
                                Please do not reply to this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}

function buildTextEmail($systemName, $oldStatus, $newStatus, $domain, $changedBy, $note) {
    date_default_timezone_set('Asia/Manila');
    $cleanNote = trim(preg_replace('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $note));
    $text  = "G-PORTAL SYSTEM ALERT\n";
    $text .= str_repeat('=', 60) . "\n\n";
    $text .= "SYSTEM: $systemName\n";
    $text .= "STATUS: " . strtoupper($newStatus) . "\n\n";
    $text .= str_repeat('-', 60) . "\n\n";
    $text .= "Status Change: $oldStatus → $newStatus\n";
    $text .= "Domain: $domain\n";
    $text .= "Changed By: $changedBy\n";
    $text .= "Time: " . date('F j, Y \a\t g:i A') . "\n";
    if (!empty($cleanNote)) $text .= "Note: $cleanNote\n";
    $text .= "\n" . str_repeat('-', 60) . "\n";
    $text .= "View system: https://$domain\n\n";
    $text .= "This is an automated notification from G-Portal.\n";
    $text .= "Please do not reply to this email.\n";
    return $text;
}
?>