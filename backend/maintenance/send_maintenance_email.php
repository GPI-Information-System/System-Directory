<?php
/* MAINTENANCE SCHEDULE EMAIL NOTIFICATIONS - Sends emails to specified recipients when a maintenance schedule is created, updated, or cancelled/deleted. */

require_once __DIR__ . '/../../config/email_config.php';
require_once __DIR__ . '/../../config/database.php';

$phpmailerBase = __DIR__ . '/../../vendor/phpmailer/src/';
if (file_exists($phpmailerBase . 'Exception.php') &&
    file_exists($phpmailerBase . 'PHPMailer.php') &&
    file_exists($phpmailerBase . 'SMTP.php')) {
    require_once $phpmailerBase . 'Exception.php';
    require_once $phpmailerBase . 'PHPMailer.php';
    require_once $phpmailerBase . 'SMTP.php';
} else {
    error_log("G-Portal Maintenance Email: PHPMailer files not found in vendor/phpmailer/src/");
}

/**
 * Main entry point — send maintenance notification emails
 *
 * @param string $trigger        'created' | 'updated' | 'cancelled'
 * @param array  $scheduleData   Current schedule data
 * @param array  $recipients     Array of email strings ['a@b.com', ...]
 * @param array  $oldData        Previous data (for 'updated' trigger — shows what changed)
 */
function sendMaintenanceEmail($trigger, array $scheduleData, array $recipients, array $oldData = []) {
    if (!defined('EMAIL_NOTIFICATIONS_ENABLED') || !EMAIL_NOTIFICATIONS_ENABLED) {
        return false;
    }

    if (empty($recipients)) {
        return false;
    }

    $scheduleData = enrichWithSystemData($scheduleData);

    $subject = buildMaintenanceSubject($trigger, $scheduleData);

    $htmlBody = buildMaintenanceHtml($trigger, $scheduleData, $oldData);
    $textBody = buildMaintenanceText($trigger, $scheduleData, $oldData);

    $resolvedRecipients = resolveRecipients($recipients);
    trackEmailUsage($recipients);

    $allSuccess = true;
    foreach ($resolvedRecipients as $recipient) {
        $sent = sendEmail(
            $recipient['email'],
            $recipient['name'],
            $subject,
            $htmlBody,
            $textBody
        );
        if (!$sent) {
            $allSuccess = false;
            error_log("G-Portal Maintenance Email: Failed to send to {$recipient['email']}");
        }
    }

    return $allSuccess;
}


function enrichWithSystemData(array $data): array {
    if (!empty($data['system_name']) && !empty($data['contact_number'])) {
        return $data; 
    }

    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT name, contact_number FROM systems WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $data['system_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $data['system_name']    = $data['system_name']    ?? $row['name'];
        $data['contact_number'] = $data['contact_number'] ?? $row['contact_number'];
    }

    return $data;
}


function resolveRecipients(array $emails): array {
    if (empty($emails)) return [];

    $conn         = getDBConnection();
    $placeholders = implode(',', array_fill(0, count($emails), '?'));
    $types        = str_repeat('s', count($emails));

    $stmt = $conn->prepare(
        "SELECT email, name FROM email_recipients WHERE email IN ($placeholders)"
    );
    $stmt->bind_param($types, ...$emails);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $nameMap = [];
    foreach ($rows as $row) {
        $nameMap[$row['email']] = $row['name'];
    }

    $resolved = [];
    foreach ($emails as $email) {
        $resolved[] = [
            'email' => $email,
            'name'  => $nameMap[$email] ?? '',
        ];
    }

    return $resolved;
}


function trackEmailUsage(array $emails): void {
    if (empty($emails)) return;

    $conn = getDBConnection();

    foreach ($emails as $email) {
        $email = trim(strtolower($email));
        if (empty($email)) continue;

        $stmt = $conn->prepare("
            INSERT INTO email_recipients (email, last_used, use_count)
            VALUES (?, NOW(), 1)
            ON DUPLICATE KEY UPDATE
                last_used  = NOW(),
                use_count  = use_count + 1
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->close();
    }
}


function buildMaintenanceSubject(string $trigger, array $data): string {
    $prefix     = defined('EMAIL_SUBJECT_PREFIX') ? EMAIL_SUBJECT_PREFIX : '[G-Portal Alert]';
    $systemName = $data['system_name'] ?? 'System';
    $title      = $data['title']       ?? 'Maintenance';

    switch ($trigger) {
        case 'created':
            return "$prefix Scheduled Maintenance: $systemName — $title";
        case 'updated':
            return "$prefix Maintenance Updated: $systemName — $title";
        case 'cancelled':
            return "$prefix Maintenance Cancelled: $systemName — $title";
        default:
            return "$prefix Maintenance Notice: $systemName";
    }
}


function detectChanges(array $new, array $old): array {
    $changes = [];

    date_default_timezone_set('Asia/Manila');

    $fields = [
        'title'          => 'Title',
        'description'    => 'Description',
        'start_datetime' => 'Start time',
        'end_datetime'   => 'End time',
        'status'         => 'Status',
    ];

    foreach ($fields as $key => $label) {
        $oldVal = $old[$key] ?? '';
        $newVal = $new[$key] ?? '';

        if ($oldVal === $newVal) continue;

        if (in_array($key, ['start_datetime', 'end_datetime'])) {
            $oldFormatted = !empty($oldVal) ? date('F j, Y \a\t g:i A', strtotime($oldVal)) : '—';
            $newFormatted = !empty($newVal) ? date('F j, Y \a\t g:i A', strtotime($newVal)) : '—';
            $changes[] = "$label changed from <strong>$oldFormatted</strong> to <strong>$newFormatted</strong>";
        } else {
            $oldDisplay = !empty($oldVal) ? htmlspecialchars($oldVal) : '—';
            $newDisplay = !empty($newVal) ? htmlspecialchars($newVal) : '—';
            $changes[] = "$label changed from <strong>$oldDisplay</strong> to <strong>$newDisplay</strong>";
        }
    }

    return $changes;
}


function buildMaintenanceHtml(string $trigger, array $data, array $oldData = []): string {
    date_default_timezone_set('Asia/Manila');

    $systemName    = htmlspecialchars($data['system_name']    ?? 'Unknown System');
    $title         = htmlspecialchars($data['title']          ?? '');
    $description   = htmlspecialchars($data['description']    ?? '');
    $startDt       = !empty($data['start_datetime']) ? date('F j, Y \a\t g:i A', strtotime($data['start_datetime'])) : '—';
    $endDt         = !empty($data['end_datetime'])   ? date('F j, Y \a\t g:i A', strtotime($data['end_datetime']))   : '—';
    $scheduledBy   = htmlspecialchars($data['scheduled_by']   ?? 'Administrator');
    $contactNumber = htmlspecialchars($data['contact_number'] ?? '');
    $now           = date('F j, Y \a\t g:i A');

    $triggerConfig = [
        'created'   => ['color' => '#1e40af', 'bg' => '#EFF6FF', 'border' => '#BFDBFE', 'badge_bg' => '#1e40af', 'badge_text' => '#fff', 'label' => 'Scheduled',  'icon' => '📅'],
        'updated'   => ['color' => '#92400e', 'bg' => '#FFFBEB', 'border' => '#FDE68A', 'badge_bg' => '#D97706', 'badge_text' => '#fff', 'label' => 'Updated',    'icon' => '✏️'],
        'cancelled' => ['color' => '#991b1b', 'bg' => '#FFF1F2', 'border' => '#FECDD3', 'badge_bg' => '#E11D48', 'badge_text' => '#fff', 'label' => 'Cancelled',  'icon' => '🚫'],
    ];
    $cfg = $triggerConfig[$trigger] ?? $triggerConfig['created'];

    $headerMessages = [
        'created'   => "A maintenance schedule has been set for <strong>$systemName</strong>. Please plan accordingly.",
        'updated'   => "The maintenance schedule for <strong>$systemName</strong> has been updated. Please review the changes below.",
        'cancelled' => "The maintenance schedule for <strong>$systemName</strong> has been cancelled by $scheduledBy.",
    ];
    $headerMessage = $headerMessages[$trigger] ?? '';

    $changesHtml = '';
    if ($trigger === 'updated' && !empty($oldData)) {
        $changes = detectChanges($data, $oldData);
        if (!empty($changes)) {
            $changeItems = implode('', array_map(fn($c) => "
                <tr>
                    <td style=\"padding: 8px 12px; border-bottom: 1px solid #fde68a; font-size: 13px; color: #78350f;\">
                        <span style=\"margin-right: 6px;\">→</span>{$c}
                    </td>
                </tr>", $changes));

            $changesHtml = "
                <tr>
                    <td style=\"padding: 0 30px 20px;\">
                        <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"background: #fffbeb; border: 1.5px solid #fde68a; border-radius: 8px; overflow: hidden;\">
                            <tr>
                                <td style=\"padding: 10px 12px; background: #fef3c7; border-bottom: 1px solid #fde68a;\">
                                    <span style=\"font-size: 12px; font-weight: 700; color: #92400e; text-transform: uppercase; letter-spacing: 0.5px;\">What Changed</span>
                                </td>
                            </tr>
                            $changeItems
                        </table>
                    </td>
                </tr>";
        }
    }

    $descriptionHtml = '';
    if (!empty($description)) {
        $descriptionHtml = "
                                <tr>
                                    <td style=\"padding: 10px 14px; border-bottom: 1px solid #e5e7eb; background: #f9fafb;\">
                                        <div style=\"font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;\">Description</div>
                                        <div style=\"font-size: 13.5px; color: #374151;\">$description</div>
                                    </td>
                                </tr>";
    }

    $contactHtml = '';
    if (!empty($contactNumber)) {
        $contactHtml = "
                    <tr>
                        <td style=\"padding: 20px 30px; background: #f8fafc; border-top: 1px solid #e5e7eb; text-align: center;\">
                            <p style=\"margin: 0 0 4px; font-size: 12px; color: #94a3b8;\">Questions? Contact the IT Department</p>
                            <p style=\"margin: 0; font-size: 16px; font-weight: 700; color: #1e40af;\">📞 $contactNumber</p>
                        </td>
                    </tr>";
    }

    $detailStyle = $trigger === 'cancelled' ? 'opacity: 0.55; text-decoration: line-through;' : '';

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0; padding:0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background:#f1f5f9;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9; padding: 40px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff; border-radius:16px; overflow:hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08);">

    <!-- Header -->
    <tr>
        <td style="background: linear-gradient(135deg, #0f1f5c 0%, #1a3080 100%); padding: 28px 30px; text-align: center;">
            <div style="font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.55); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 6px;">System Monitoring</div>
            <div style="font-size: 26px; font-weight: 800; color: #fff; letter-spacing: -0.5px;">G-Portal</div>
        </td>
    </tr>

    <!-- Status Badge -->
    <tr>
        <td style="padding: 24px 30px 16px; text-align: center;">
            <div style="display:inline-block; background:{$cfg['badge_bg']}; color:{$cfg['badge_text']}; padding: 7px 18px; border-radius: 20px; font-size: 12px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;">
                Maintenance {$cfg['label']}
            </div>
            <h2 style="margin: 14px 0 6px; font-size: 20px; font-weight: 700; color: #0f172a;">$systemName</h2>
            <p style="margin: 0; font-size: 13.5px; color: #64748b; line-height: 1.5;">$headerMessage</p>
        </td>
    </tr>

    <!-- Changes section (updated only) -->
    $changesHtml

    <!-- Schedule Details Card -->
    <tr>
        <td style="padding: 8px 30px 24px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="border: 1.5px solid #e2e8f0; border-radius: 10px; overflow: hidden; $detailStyle">

                <tr style="background: #f8fafc;">
                    <td style="padding: 10px 14px; border-bottom: 1px solid #e5e7eb;">
                        <div style="font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Maintenance Title</div>
                        <div style="font-size: 14px; font-weight: 600; color: #0f172a;">$title</div>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 0;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td width="50%" style="padding: 10px 14px; border-bottom: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb;">
                                    <div style="font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Start</div>
                                    <div style="font-size: 13px; font-weight: 600; color: #0f172a;">$startDt</div>
                                </td>
                                <td width="50%" style="padding: 10px 14px; border-bottom: 1px solid #e5e7eb;">
                                    <div style="font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">End</div>
                                    <div style="font-size: 13px; font-weight: 600; color: #0f172a;">$endDt</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                $descriptionHtml

                <tr style="background: #f8fafc;">
                    <td style="padding: 10px 14px;">
                        <div style="font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Scheduled by</div>
                        <div style="font-size: 13px; color: #374151;">$scheduledBy</div>
                    </td>
                </tr>

            </table>
        </td>
    </tr>

    <!-- Contact -->
    $contactHtml

    <!-- Footer -->
    <tr>
        <td style="padding: 18px 30px; background: #f8fafc; border-top: 1px solid #e5e7eb; text-align: center;">
            <p style="margin: 0; font-size: 11.5px; color: #94a3b8; line-height: 1.6;">
                This is an automated notification from <strong>G-Portal System Monitoring</strong>.<br>
                Sent on $now · Please do not reply to this email.
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

/**
 * Build plain text email fallback
 */
function buildMaintenanceText(string $trigger, array $data, array $oldData = []): string {
    date_default_timezone_set('Asia/Manila');

    $systemName    = $data['system_name']    ?? 'Unknown System';
    $title         = $data['title']          ?? '';
    $description   = $data['description']    ?? '';
    $startDt       = !empty($data['start_datetime']) ? date('F j, Y \a\t g:i A', strtotime($data['start_datetime'])) : '—';
    $endDt         = !empty($data['end_datetime'])   ? date('F j, Y \a\t g:i A', strtotime($data['end_datetime']))   : '—';
    $scheduledBy   = $data['scheduled_by']   ?? 'Administrator';
    $contactNumber = $data['contact_number'] ?? '';

    $labels = [
        'created'   => 'MAINTENANCE SCHEDULED',
        'updated'   => 'MAINTENANCE UPDATED',
        'cancelled' => 'MAINTENANCE CANCELLED',
    ];
    $label = $labels[$trigger] ?? 'MAINTENANCE NOTICE';

    $text  = "G-PORTAL — $label\n";
    $text .= str_repeat('=', 60) . "\n\n";
    $text .= "System  : $systemName\n";
    $text .= "Title   : $title\n";
    $text .= "Start   : $startDt\n";
    $text .= "End     : $endDt\n";
    $text .= "By      : $scheduledBy\n";

    if (!empty($description)) {
        $text .= "Details : $description\n";
    }

    if ($trigger === 'updated' && !empty($oldData)) {
        $changes = detectChanges($data, $oldData);
        if (!empty($changes)) {
            $text .= "\n" . str_repeat('-', 60) . "\n";
            $text .= "CHANGES:\n";
            foreach ($changes as $c) {
                $text .= "  → " . strip_tags($c) . "\n";
            }
        }
    }

    if ($trigger === 'cancelled') {
        $text .= "\nThis maintenance schedule has been cancelled.\n";
    }

    if (!empty($contactNumber)) {
        $text .= "\n" . str_repeat('-', 60) . "\n";
        $text .= "Questions? Contact IT: $contactNumber\n";
    }

    $text .= "\n" . str_repeat('=', 60) . "\n";
    $text .= "Automated notification from G-Portal. Do not reply.\n";
    $text .= "Sent: " . date('F j, Y \a\t g:i A') . "\n";

    return $text;
}

/**
 * Send email — auto-selects PHPMailer or file logging
 */
function sendEmail($to, $toName, $subject, $htmlBody, $textBody) {
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return sendEmailViaPHPMailer($to, $toName, $subject, $htmlBody, $textBody);
    }
    
    error_log("G-Portal Maintenance Email: PHPMailer not found — logging only");
    return logMaintenanceEmailToFile($to, $toName, $subject, $textBody);
}

function sendEmailViaPHPMailer($to, $toName, $subject, $htmlBody, $textBody) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        $debugLevel = defined('SMTP_DEBUG') ? SMTP_DEBUG : 0;
        if ($debugLevel > 0) {
            $mail->SMTPDebug   = $debugLevel;
            $mail->Debugoutput = fn($str, $level) => error_log("PHPMailer [$level]: $str");
        }

        $mail->isSMTP();
        $mail->Host       = defined('SMTP_HOST')       ? SMTP_HOST       : 'smtp.office365.com';
        $mail->Port       = defined('SMTP_PORT')       ? SMTP_PORT       : 587;
        $mail->SMTPAuth   = true;
        $mail->Username   = defined('SMTP_USERNAME')   ? SMTP_USERNAME   : '';
        $mail->Password   = defined('SMTP_PASSWORD')   ? SMTP_PASSWORD   : '';
        $mail->SMTPSecure = defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls';

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

        $mail->Timeout = defined('SMTP_TIMEOUT') ? SMTP_TIMEOUT : 30;

        if (defined('SMTP_KEEPALIVE') && SMTP_KEEPALIVE) {
            $mail->SMTPKeepAlive = true;
        }

        $fromEmail = defined('EMAIL_FROM_ADDRESS') ? EMAIL_FROM_ADDRESS : (defined('SMTP_USERNAME') ? SMTP_USERNAME : '');
        $fromName  = defined('EMAIL_FROM_NAME')    ? EMAIL_FROM_NAME    : 'G-Portal System Alerts';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to, $toName);
        $mail->CharSet = defined('EMAIL_CHARSET') ? EMAIL_CHARSET : 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody;
        $mail->send();

        error_log("G-Portal Maintenance Email: Sent to $to via PHPMailer");
        logMaintenanceEmailToFile($to, $toName, $subject, $textBody);
        return true;

    } catch (PHPMailer\PHPMailer\Exception $e) {
        error_log("G-Portal Maintenance Email PHPMailer error: " . $e->getMessage());
        return logMaintenanceEmailToFile($to, $toName, $subject, $textBody);
    }
}

function logMaintenanceEmailToFile($to, $toName, $subject, $textBody) {
    $logFile = __DIR__ . '/../logs/maintenance_emails.log';
    $logDir  = dirname($logFile);

    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }

    date_default_timezone_set('Asia/Manila');

    $entry  = "\n" . str_repeat('=', 80) . "\n";
    $entry .= "Logged: " . date('Y-m-d H:i:s') . "\n";
    $entry .= "To: $toName <$to>\n";
    $entry .= "Subject: $subject\n";
    $entry .= str_repeat('-', 80) . "\n";
    $entry .= $textBody . "\n";
    $entry .= str_repeat('=', 80) . "\n";

    return file_put_contents($logFile, $entry, FILE_APPEND) !== false;
}
?>