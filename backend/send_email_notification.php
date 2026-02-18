<?php
/**
 * EMAIL NOTIFICATION FUNCTION
 * Hybrid version - works with or without PHPMailer
 * 
 * - If PHPMailer is available: Sends real emails via SMTP
 * - If PHPMailer is NOT available: Logs to file
 * 
 * Philippine timezone + No emoji in notes
 * 
 * 
 * 
 *1. Pull from GitHub
 *2. Run: composer require phpmailer/phpmailer
 *3. Configure SMTP in email_config.php
 *4. Done! Automatically switches to real emails!
 */

require_once __DIR__ . '/../config/email_config.php';
require_once __DIR__ . '/../config/database.php';

// Try to load PHPMailer if available (suppresses errors if not found)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

/**
 * Send email notification for critical status changes
 */
function sendStatusChangeEmail($systemId, $systemName, $oldStatus, $newStatus, $domain, $changedBy, $note = '') {
    // Check if this status change should trigger an email
    $triggerStatuses = defined('EMAIL_TRIGGER_STATUSES') ? EMAIL_TRIGGER_STATUSES : ['down', 'offline'];
    if (!in_array($newStatus, $triggerStatuses)) {
        return false;
    }
    
    // Get admin email addresses
    $recipients = getAdminEmails();
    if (empty($recipients)) {
        error_log("No admin emails configured - cannot send notification");
        return false;
    }
    
    // Prepare email content
    $subject = (defined('EMAIL_SUBJECT_PREFIX') ? EMAIL_SUBJECT_PREFIX : '[G-Portal Alert]') . " System $newStatus: $systemName";
    $htmlBody = buildEmailTemplate($systemName, $oldStatus, $newStatus, $domain, $changedBy, $note);
    $textBody = buildTextEmail($systemName, $oldStatus, $newStatus, $domain, $changedBy, $note);
    
    // Send email to each recipient
    $success = true;
    foreach ($recipients as $recipient) {
        if (!sendEmail($recipient['email'], $recipient['name'], $subject, $htmlBody, $textBody)) {
            $success = false;
            error_log("Failed to send email to {$recipient['email']}");
        }
    }
    
    return $success;
}

/**
 * Get admin email addresses from database
 */
function getAdminEmails() {
    $conn = getDBConnection();
    
    $recipients_type = defined('EMAIL_RECIPIENTS') ? EMAIL_RECIPIENTS : 'all_admins';
    
    if ($recipients_type === 'super_admin_only') {
        $sql = "SELECT email, username FROM users WHERE role = 'Super Admin' AND email IS NOT NULL AND email != ''";
    } else {
        $sql = "SELECT email, username FROM users WHERE role IN ('Super Admin', 'Admin') AND email IS NOT NULL AND email != ''";
    }
    
    $result = $conn->query($sql);
    $recipients = [];
    
    while ($row = $result->fetch_assoc()) {
        $recipients[] = [
            'email' => $row['email'],
            'name' => $row['username']
        ];
    }
    
    return $recipients;
}

/**
 * Send email - automatically chooses between PHPMailer or file logging
 */
function sendEmail($to, $toName, $subject, $htmlBody, $textBody) {
    // Check if PHPMailer is available
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        // PHPMailer is available - send real email
        return sendEmailViaPHPMailer($to, $toName, $subject, $htmlBody, $textBody);
    } else {
        // PHPMailer not available - log to file
        return logEmailToFile($to, $toName, $subject, $htmlBody, $textBody);
    }
}

/**
 * Send email via PHPMailer (when available)
 */
function sendEmailViaPHPMailer($to, $toName, $subject, $htmlBody, $textBody) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        if (defined('constant_name: "SMTP_DEBUG"') && SMTP_DEBUG > 0) {
            $mail->SMTPDebug = SMTP_DEBUG;
            $mail->Debugoutput = function($str, $level) {
                error_log("PHPMailer [$level]: $str");
            };
        }
        
        $mail->isSMTP();
        $mail->Host = defined('SMTP_HOST') ? SMTP_HOST : 'localhost';
        $mail->Port = defined('SMTP_PORT') ? SMTP_PORT : 587;
        
        // Authentication
        if (defined('SMTP_USERNAME') && !empty(SMTP_USERNAME) && defined('SMTP_PASSWORD') && !empty(SMTP_PASSWORD)) {
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
        }
        
        // Encryption
        if (defined('SMTP_ENCRYPTION') && !empty(SMTP_ENCRYPTION)) {
            $mail->SMTPSecure = SMTP_ENCRYPTION;
        }
        
        // From
        $fromEmail = defined('EMAIL_FROM_ADDRESS') ? EMAIL_FROM_ADDRESS : 'noreply@gportal.local';
        $fromName = defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : 'G-Portal System Monitor';
        $mail->setFrom($fromEmail, $fromName);
        
        // Recipient
        $mail->addAddress($to, $toName);
        
        // Content
        $mail->CharSet = defined('constant_name: "EMAIL_CHARSET"') ? EMAIL_CHARSET : 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;
        
        // Send
        $mail->send();
        error_log("Email sent successfully to $to via PHPMailer");
        
        // Also log to file for backup
        logEmailToFile($to, $toName, $subject, $htmlBody, $textBody);
        
        return true;
        
    } catch (PHPMailer\PHPMailer\Exception $e) {
        error_log("Email sending failed via PHPMailer: {$mail->ErrorInfo}");
        
        // Fallback to file logging
        return logEmailToFile($to, $toName, $subject, $htmlBody, $textBody);
    }
}

/**
 * Log email to file (fallback method)
 */
function logEmailToFile($to, $toName, $subject, $htmlBody, $textBody) {
    $logFile = __DIR__ . '/logs/emails.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    // Set timezone to Philippine Time
    date_default_timezone_set('Asia/Manila');
    
    // Build log entry
    $logEntry = "\n" . str_repeat('=', 80) . "\n";
    $logEntry .= "Email logged at: " . date('Y-m-d H:i:s') . "\n";
    $logEntry .= "To: $toName <$to>\n";
    $logEntry .= "Subject: $subject\n";
    $logEntry .= str_repeat('-', 80) . "\n";
    $logEntry .= $textBody . "\n";
    $logEntry .= str_repeat('=', 80) . "\n";
    
    // Write to log file
    $result = file_put_contents($logFile, $logEntry, FILE_APPEND);
    
    if ($result === false) {
        error_log("ERROR: Could not write to email log file: $logFile");
        return false;
    }
    
    error_log("Email logged to file for $to (PHPMailer not available)");
    return true;
}

/**
 * Build HTML email template
 */
function buildEmailTemplate($systemName, $oldStatus, $newStatus, $domain, $changedBy, $note) {
    $statusColors = [
        'down' => '#ef4444',
        'offline' => '#6b7280',
        'maintenance' => '#f59e0b',
        'online' => '#10b981'
    ];
    
    $newStatusColor = $statusColors[$newStatus] ?? '#6b7280';
    $oldStatusLabel = ucfirst($oldStatus);
    $newStatusLabel = ucfirst($newStatus);
    
    // Set timezone to Philippine Time
    date_default_timezone_set('Asia/Manila');
    $timestamp = date('F j, Y \a\t g:i A');
    
    // Remove emoji from note
    $cleanNote = preg_replace('/[\x{1F300}-\x{1F9FF}]/u', '', $note);
    $cleanNote = trim($cleanNote);
    
    $html = <<<HTML
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
                    <tr>
                        <td style="background-color: #1e3a8a; padding: 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: bold;">G-Portal System Alert</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 30px; text-align: center; background-color: #fee2e2;">
                            <div style="display: inline-block; background-color: $newStatusColor; color: #ffffff; padding: 12px 24px; border-radius: 8px; font-size: 18px; font-weight: bold;">
                                System is $newStatusLabel
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 30px;">
                            <h2 style="margin: 0 0 20px 0; color: #1a1f36; font-size: 20px;">$systemName</h2>
                            
                            <table width="100%" cellpadding="10" cellspacing="0" style="border: 1px solid #e5e7eb; border-radius: 8px;">
                                <tr style="background-color: #f9fafb;">
                                    <td style="font-weight: bold; color: #6b7280; width: 40%;">Status Change:</td>
                                    <td style="color: #1a1f36;">
                                        <span style="background-color: #e5e7eb; padding: 4px 8px; border-radius: 4px; font-size: 12px;">$oldStatusLabel</span>
                                        →
                                        <span style="background-color: $newStatusColor; color: #ffffff; padding: 4px 8px; border-radius: 4px; font-size: 12px;">$newStatusLabel</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold; color: #6b7280;">Domain:</td>
                                    <td style="color: #1e3a8a;"><a href="https://$domain" style="color: #1e3a8a; text-decoration: none;">$domain</a></td>
                                </tr>
                                <tr style="background-color: #f9fafb;">
                                    <td style="font-weight: bold; color: #6b7280;">Changed By:</td>
                                    <td style="color: #1a1f36;">$changedBy</td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold; color: #6b7280;">Time:</td>
                                    <td style="color: #1a1f36;">$timestamp</td>
                                </tr>
HTML;

    if (!empty($cleanNote)) {
        $html .= <<<HTML
                                <tr style="background-color: #f9fafb;">
                                    <td style="font-weight: bold; color: #6b7280;">Note:</td>
                                    <td style="color: #1a1f36;">$cleanNote</td>
                                </tr>
HTML;
    }

    $html .= <<<HTML
                            </table>
                            
                            <div style="margin-top: 30px; text-align: center;">
                                <a href="https://$domain" style="display: inline-block; background-color: #1e3a8a; color: #ffffff; padding: 12px 30px; border-radius: 8px; text-decoration: none; font-weight: bold;">View System</a>
                            </div>
                        </td>
                    </tr>
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

    return $html;
}

/**
 * Build plain text email
 */
function buildTextEmail($systemName, $oldStatus, $newStatus, $domain, $changedBy, $note) {
    // Set timezone to Philippine Time
    date_default_timezone_set('Asia/Manila');
    
    // Remove emoji from note
    $cleanNote = preg_replace('/[\x{1F300}-\x{1F9FF}]/u', '', $note);
    $cleanNote = trim($cleanNote);
    
    $text = "G-PORTAL SYSTEM ALERT\n";
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