<?php
/**
 * Zibomo — brochure lead notification email.
 *
 * WHY SMTP IS THE PRIMARY PATH
 * ----------------------------
 * PHP's mail() returning true means the local MTA accepted the message,
 * not that anyone received it. Sending as @zibomo.in from a shared host
 * that zibomo.in's SPF/DKIM records do not authorise gets the message
 * junked. The visitor still gets their PDF and the lead is never seen.
 *
 * So: authenticate against the real mailbox over SMTP. mail() stays as a
 * documented fallback for hosts where SMTP is blocked, and it logs a
 * warning every time it is used.
 *
 * PHPMailer is optional. Drop its three source files into
 * includes/PHPMailer/ (PHPMailer.php, SMTP.php, Exception.php) or install
 * via composer; this file detects either and degrades gracefully.
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * Send the lead notification.
 *
 * @param array $lead Sanitised fields: name, business, phone, email,
 *                    address, requirement, message, submitted_at
 * @return bool true only when the transport accepted the message
 */
function zb_send_lead_email(array $lead)
{
    $config = zb_config();

    $to      = zb_header_safe($config['lead_recipient']);
    $subject = zb_header_safe($config['lead_subject']);
    $html    = zb_lead_email_html($lead);
    $text    = zb_lead_email_text($lead);

    // The visitor's address is validated before it reaches here, but strip
    // line breaks anyway — this value ends up in a header.
    $replyTo     = zb_header_safe($lead['email']);
    $replyToName = zb_header_safe($lead['name']);

    $transport = strtolower((string) $config['mail_transport']);

    if ($transport === 'smtp' && zb_phpmailer_available()) {
        return zb_send_via_phpmailer($config, $to, $subject, $html, $text, $replyTo, $replyToName);
    }

    if ($transport === 'smtp') {
        zb_log('WARNING: mail_transport is "smtp" but PHPMailer was not found. '
             . 'Falling back to mail(); deliverability will be poor. '
             . 'Install PHPMailer into includes/PHPMailer/.');
    }

    return zb_send_via_mail($config, $to, $subject, $html, $replyTo, $replyToName);
}

/**
 * @return bool
 */
function zb_phpmailer_available()
{
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        return true;
    }

    $composer = ZB_ROOT . '/vendor/autoload.php';
    if (is_readable($composer)) {
        require_once $composer;
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return true;
        }
    }

    $manual = __DIR__ . '/PHPMailer';
    if (is_readable($manual . '/PHPMailer.php')) {
        require_once $manual . '/Exception.php';
        require_once $manual . '/PHPMailer.php';
        require_once $manual . '/SMTP.php';
        return class_exists('PHPMailer\\PHPMailer\\PHPMailer');
    }

    return false;
}

/**
 * @return bool
 */
function zb_send_via_phpmailer($config, $to, $subject, $html, $text, $replyTo, $replyToName)
{
    $smtp = isset($config['smtp']) && is_array($config['smtp']) ? $config['smtp'] : array();

    $class = 'PHPMailer\\PHPMailer\\PHPMailer';
    $mail = new $class(true);

    try {
        $mail->isSMTP();
        $mail->Host       = isset($smtp['host']) ? $smtp['host'] : '';
        $mail->Port       = isset($smtp['port']) ? (int) $smtp['port'] : 465;
        $mail->SMTPAuth   = true;
        $mail->Username   = isset($smtp['username']) ? $smtp['username'] : '';
        $mail->Password   = isset($smtp['password']) ? $smtp['password'] : '';
        $mail->Timeout    = isset($smtp['timeout']) ? (int) $smtp['timeout'] : 20;
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';

        $encryption = isset($smtp['encryption']) ? strtolower($smtp['encryption']) : 'ssl';
        if ($encryption === 'tls') {
            $mail->SMTPSecure = 'tls';
        } elseif ($encryption === 'ssl') {
            $mail->SMTPSecure = 'ssl';
        } else {
            $mail->SMTPAutoTLS = false;
            $mail->SMTPSecure  = '';
        }

        if ($mail->Username === '' || $mail->Password === '') {
            zb_log('SMTP credentials are missing. Set ZIBOMO_SMTP_USERNAME / ZIBOMO_SMTP_PASSWORD '
                 . 'or fill includes/config.php.');
            return false;
        }

        $mail->setFrom($config['mail_from'], $config['mail_from_name']);
        $mail->addAddress($to);

        if ($replyTo !== '') {
            $mail->addReplyTo($replyTo, $replyToName !== '' ? $replyToName : $replyTo);
        }

        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $html;
        $mail->AltBody = $text;

        $mail->send();
        return true;
    } catch (Exception $e) {
        zb_log('PHPMailer failure: ' . $e->getMessage()
             . ' | ErrorInfo: ' . (isset($mail->ErrorInfo) ? $mail->ErrorInfo : 'n/a'));
        return false;
    }
}

/**
 * Fallback transport. Headers are assembled from values that have already
 * had CR/LF stripped, which is what closes the header-injection vector.
 *
 * @return bool
 */
function zb_send_via_mail($config, $to, $subject, $html, $replyTo, $replyToName)
{
    if (!function_exists('mail')) {
        zb_log('mail() is disabled on this host and no SMTP transport is configured.');
        return false;
    }

    $from     = zb_header_safe($config['mail_from']);
    $fromName = zb_header_safe($config['mail_from_name']);

    $headers = array(
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: ' . zb_encode_address($fromName, $from),
        'X-Mailer: Zibomo Website',
    );
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . zb_encode_address($replyToName, $replyTo);
    }

    // -f sets the envelope sender so the host does not substitute a
    // nobody@server address that fails SPF outright.
    $params = '';
    if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $params = '-f' . $from;
    }

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $ok = @mail($to, $encodedSubject, $html, implode("\r\n", $headers), $params);
    if (!$ok) {
        zb_log('mail() returned false for recipient ' . $to);
    }
    return (bool) $ok;
}

/**
 * RFC 2047 encode a display name and pair it with an address.
 *
 * @return string
 */
function zb_encode_address($name, $address)
{
    $address = zb_header_safe($address);
    $name    = zb_header_safe($name);
    if ($name === '') {
        return $address;
    }
    return '=?UTF-8?B?' . base64_encode($name) . '?= <' . $address . '>';
}

/* ------------------------------------------------------------------
   Templates
   ------------------------------------------------------------------ */

/**
 * @return string
 */
function zb_lead_email_html(array $lead)
{
    $rows = array(
        'Name'        => $lead['name'],
        'Business'    => $lead['business'],
        'Phone'       => $lead['phone'],
        'Email'       => $lead['email'],
        'Address'     => $lead['address'] !== '' ? $lead['address'] : '—',
        'Requirement' => $lead['requirement'],
        'Message'     => $lead['message'] !== '' ? $lead['message'] : '—',
    );

    $tr = '';
    foreach ($rows as $label => $value) {
        // Every value is escaped: a lead must never be able to inject
        // markup into the mail the support team opens.
        $safe = nl2br(zb_e($value));
        $tr .= '<tr>'
             . '<td style="padding:11px 16px;border-bottom:1px solid #E4E9F2;'
             . 'font-family:Arial,Helvetica,sans-serif;font-size:12px;letter-spacing:.06em;'
             . 'text-transform:uppercase;color:#7C8699;white-space:nowrap;vertical-align:top;">'
             . zb_e($label) . '</td>'
             . '<td style="padding:11px 16px;border-bottom:1px solid #E4E9F2;'
             . 'font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#10192F;line-height:1.6;">'
             . $safe . '</td>'
             . '</tr>';
    }

    $submitted = zb_e($lead['submitted_at']);

    return '<!doctype html><html><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:24px 12px;background:#F5F7FC;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" '
        . 'style="max-width:620px;margin:0 auto;background:#FFFFFF;border:1px solid #E4E9F2;border-radius:10px;overflow:hidden;">'
        . '<tr><td style="background:#0A1638;padding:22px 26px;">'
        . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;letter-spacing:.18em;'
        . 'text-transform:uppercase;color:#FF8E9D;margin-bottom:6px;">Zibomo Website</div>'
        . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:21px;font-weight:bold;color:#FFFFFF;">'
        . 'New Brochure Download Request</div></td></tr>'
        . '<tr><td style="padding:22px 26px 6px;">'
        . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:bold;'
        . 'letter-spacing:.08em;text-transform:uppercase;color:#E8112D;">Customer Details</div></td></tr>'
        . '<tr><td style="padding:10px 10px 4px;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">'
        . $tr . '</table></td></tr>'
        . '<tr><td style="padding:16px 26px 24px;">'
        . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#55617A;">'
        . 'Submitted at: <strong style="color:#10192F;">' . $submitted . '</strong></div>'
        . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#7C8699;margin-top:10px;">'
        . 'Reply directly to this email to reach the customer.</div>'
        . '</td></tr></table></body></html>';
}

/**
 * @return string
 */
function zb_lead_email_text(array $lead)
{
    $lines = array(
        'New Brochure Download Request',
        '',
        'Customer Details',
        '-------------------------',
        'Name: '        . $lead['name'],
        'Business: '    . $lead['business'],
        'Phone: '       . $lead['phone'],
        'Email: '       . $lead['email'],
        'Address: '     . ($lead['address'] !== '' ? $lead['address'] : '-'),
        'Requirement: ' . $lead['requirement'],
        'Message: '     . ($lead['message'] !== '' ? $lead['message'] : '-'),
        '',
        'Submitted At: ' . $lead['submitted_at'],
    );
    return implode("\r\n", $lines);
}
