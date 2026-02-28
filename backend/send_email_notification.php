<?php
/**
 * EMAIL NOTIFICATION FUNCTION
 * Hybrid version - works with or without PHPMailer
 * 
 * - If PHPMailer is available: Sends real emails via SMTP
 * - If PHPMailer is NOT available: Logs to file (emails.log)
 * 
 * Philippine timezone
 * 
 * DEPLOYMENT CHECKLIST:
 * 1. Pull from GitHub
 * 2. Run: composer require phpmailer/phpmailer
 * 3. Configure SMTP in config/email_config.php
 * 4. Done! Automatically switches to real emails!
 */

require_once __DIR__ . '/../config/email_config.php';
require_once __DIR__ . '/../config/database.php';

// Load PHPMailer if available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

/**
 * Send email notification for critical status changes
 */
function sendStatusChangeEmail($systemId, $systemName, $oldStatus, $newStatus, $domain, $changedBy, $note = '') {
    // Only trigger for these statuses
    $triggerStatuses = defined('EMAIL_TRIGGER_STATUSES') ? EMAIL_TRIGGER_STATUSES : ['down', 'offline'];
    if (!in_array($newStatus, $triggerStatuses)) {
        return false;
    }

    // Get admin email recipients from DB
    $recipients = getAdminEmails();
    if (empty($recipients)) {
        error_log("G-Portal: No admin emails configured — cannot send notification");
        return false;
    }

    // Build subject and body
    $prefix  = defined('EMAIL_SUBJECT_PREFIX') ? EMAIL_SUBJECT_PREFIX : '[G-Portal Alert]';
    $subject = "$prefix System $newStatus: $systemName";
    $htmlBody = buildEmailTemplate($systemName, $oldStatus, $newStatus, $domain, $changedBy, $note);
    $textBody = buildTextEmail($systemName, $oldStatus, $newStatus, $domain, $changedBy, $note);

    // Send to each recipient
    $success = true;
    foreach ($recipients as $recipient) {
        $sent = sendEmail($recipient['email'], $recipient['name'], $subject, $htmlBody, $textBody);
        if (!$sent) {
            $success = false;
            error_log("G-Portal: Failed to send email to {$recipient['email']}");
        }
    }

    return $success;
}

/**
 * Get admin email addresses from database
 */
function getAdminEmails() {
    $conn = getDBConnection();

    $recipientType = defined('EMAIL_RECIPIENTS') ? EMAIL_RECIPIENTS : 'all_admins';

    if ($recipientType === 'super_admin_only') {
        $sql = "SELECT email, username FROM users WHERE role = 'Super Admin' AND email IS NOT NULL AND email != ''";
    } else {
        $sql = "SELECT email, username FROM users WHERE role IN ('Super Admin', 'Admin') AND email IS NOT NULL AND email != ''";
    }

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

/**
 * Send email — auto-selects PHPMailer or file logging
 */
function sendEmail($to, $toName, $subject, $htmlBody, $textBody) {
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return sendEmailViaPHPMailer($to, $toName, $subject, $htmlBody, $textBody);
    } else {
        return logEmailToFile($to, $toName, $subject, $htmlBody, $textBody);
    }
}

/**
 * Send email via PHPMailer (production)
 */
function sendEmailViaPHPMailer($to, $toName, $subject, $htmlBody, $textBody) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        // Debug output (log to PHP error log)
        $debugLevel = defined('SMTP_DEBUG') ? SMTP_DEBUG : 0;
        if ($debugLevel > 0) {
            $mail->SMTPDebug  = $debugLevel;
            $mail->Debugoutput = function($str, $level) {
                error_log("PHPMailer [$level]: $str");
            };
        }

        // SMTP configuration
        $mail->isSMTP();
        $mail->Host = defined('SMTP_HOST') ? SMTP_HOST : 'localhost';
        $mail->Port = defined('SMTP_PORT') ? SMTP_PORT : 587;

        // Authentication
        $smtpUser = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
        $smtpPass = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
        if (!empty($smtpUser) && !empty($smtpPass)) {
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
        }

        // Encryption (tls / ssl / empty)
        $encryption = defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : '';
        if (!empty($encryption)) {
            $mail->SMTPSecure = $encryption;
        }

        // From
        $fromEmail = defined('EMAIL_FROM_ADDRESS') ? EMAIL_FROM_ADDRESS : 'noreply@gportal.local';
        $fromName  = defined('EMAIL_FROM_NAME')    ? EMAIL_FROM_NAME    : 'G-Portal System Monitor';
        $mail->setFrom($fromEmail, $fromName);

        // Recipient
        $mail->addAddress($to, $toName);

        // Charset
        $mail->CharSet = defined('EMAIL_CHARSET') ? EMAIL_CHARSET : 'UTF-8';

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody;

        $mail->send();
        error_log("G-Portal: Email sent to $to via PHPMailer");

        // Also log to file as backup record
        logEmailToFile($to, $toName, $subject, $htmlBody, $textBody);

        return true;

    } catch (PHPMailer\PHPMailer\Exception $e) {
        error_log("G-Portal: PHPMailer failed — {$mail->ErrorInfo}");
        // Fallback to file log
        return logEmailToFile($to, $toName, $subject, $htmlBody, $textBody);
    }
}

/**
 * Log email to file (development / fallback)
 */
function logEmailToFile($to, $toName, $subject, $htmlBody, $textBody) {
    $logFile = __DIR__ . '/logs/emails.log';
    $logDir  = dirname($logFile);

    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }

    date_default_timezone_set('Asia/Manila');

    $logEntry  = "\n" . str_repeat('=', 80) . "\n";
    $logEntry .= "Email logged at: " . date('Y-m-d H:i:s') . "\n";
    $logEntry .= "To: $toName <$to>\n";
    $logEntry .= "Subject: $subject\n";
    $logEntry .= str_repeat('-', 80) . "\n";
    $logEntry .= $textBody . "\n";
    $logEntry .= str_repeat('=', 80) . "\n";

    $result = file_put_contents($logFile, $logEntry, FILE_APPEND);

    if ($result === false) {
        error_log("G-Portal: Could not write to email log: $logFile");
        return false;
    }

    return true;
}

/**
 * Build HTML email template
 */
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

    // Strip emoji from note
    $cleanNote = preg_replace('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $note);
    $cleanNote = trim($cleanNote);

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

/**
 * Build plain text email
 */
function buildTextEmail($systemName, $oldStatus, $newStatus, $domain, $changedBy, $note) {
    date_default_timezone_set('Asia/Manila');

    // Strip emoji from note
    $cleanNote = preg_replace('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $note);
    $cleanNote = trim($cleanNote);

    $text  = "G-PORTAL SYSTEM ALERT\n";
    $text .= str_repeat('=', 60) . "\n\n";
    $text .= "SYSTEM: $systemName\n";
    $text .= "STATUS: " . strtoupper($newStatus) . "\n\n";
    $text .= str_repeat('-', 60) . "\n\n";
    $text .= "Status Change: $oldStatus → $newStatus\n";
    $text .= "Domain: $domain\n";
    $text .= "Changed By: $changedBy\n";
    $text .= "Time: " . date('F j, Y \a\t g:i A') . "\n";

    if (!empty($cleanNote)) {
        $text .= "Note: $cleanNote\n";
    }

    $text .= "\n" . str_repeat('-', 60) . "\n";
    $text .= "View system: https://$domain\n\n";
    $text .= "This is an automated notification from G-Portal.\n";
    $text .= "Please do not reply to this email.\n";

    return $text;
}
?>