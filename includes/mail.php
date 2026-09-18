<?php
/**
 * Zibomo — emails for the brochure and contact forms: notifications to the
 * support inbox, and the brochure itself to the visitor who asked for it.
 *
 * HOW MAIL IS SENT
 * ----------------
 * 1. PHP's mail(), the same as any plain PHP site: no password, no token.
 *    On a normal Linux host it hands the message to the server's own mail
 *    system.
 * 2. If mail() fails — on XAMPP for Windows it always does, because there
 *    is no mail server — the same message goes out through Gmail's SMTP
 *    server, signed in with the account and app password from the smtp
 *    block in includes/config.php. No library is needed for this.
 *
 * 'mail_transport' => 'smtp' skips mail() and always uses Gmail. Use it on
 * a host where mail() reports success but the messages never arrive:
 * mail() returning true only means the server's mail system accepted the
 * message, not that it was delivered.
 */

require_once __DIR__ . '/bootstrap.php';

/*
 * Both notifications list every field with the label and in the order the
 * visitor saw on the form, so support reads the email like the form itself.
 */

/**
 * Brochure lead notification.
 *
 * @param array $lead Sanitised fields: name, business, phone, email,
 *                    address, requirement, message, submitted_at
 * @return bool true only when the message was accepted for delivery
 */
function zb_send_lead_email(array $lead)
{
    $config = zb_config();

    return zb_send_notification($config['lead_subject'], 'New Brochure Download Request', array(
        'Name'                    => $lead['name'],
        'Business / Company Name' => $lead['business'],
        'Phone Number'            => $lead['phone'],
        'Email'                   => $lead['email'],
        'Requirement'             => $lead['requirement'],
        'Address'                 => $lead['address'],
        'Message'                 => $lead['message'],
    ), $lead['submitted_at'], $lead['email'], $lead['name']);
}

/**
 * Contact form enquiry notification.
 *
 * @param array $enquiry Sanitised fields: name, company, phone, email,
 *                       subject, message, submitted_at
 * @return bool true only when the message was accepted for delivery
 */
function zb_send_contact_email(array $enquiry)
{
    $config = zb_config();

    return zb_send_notification($config['contact_subject'], 'New Contact Enquiry', array(
        'Name'                    => $enquiry['name'],
        'Business / Organization' => $enquiry['company'],
        'Phone Number'            => $enquiry['phone'],
        'Email'                   => $enquiry['email'],
        'Requirement'             => $enquiry['subject'],
        'Message'                 => $enquiry['message'],
    ), $enquiry['submitted_at'], $enquiry['email'], $enquiry['name']);
}

/**
 * Email the brochure PDF to the visitor who asked for it. Replies go to
 * the support inbox.
 *
 * @param array $lead Sanitised fields, as for zb_send_lead_email()
 * @return bool true only when the message was accepted for delivery
 */
function zb_send_brochure_to_visitor(array $lead)
{
    $config = zb_config();

<<<<<<< HEAD
    $path = zb_brochure_path();
    $pdf  = $path !== false ? @file_get_contents($path) : false;
    if ($pdf === false) {
        zb_log('Brochure email not sent: the PDF could not be read.');
        return false;
    }

=======
    // Email is optional on the form: no address, nothing to send.
    if (!filter_var($lead['email'], FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $path = zb_brochure_path();
    $pdf  = $path !== false ? @file_get_contents($path) : false;
    if ($pdf === false) {
        zb_log('Brochure email not sent: the PDF could not be read.');
        return false;
    }

>>>>>>> 8b742b9 (completed)
    $filename = preg_replace('/[^A-Za-z0-9._-]/', '', basename((string) $config['brochure_filename']));
    if ($filename === '') {
        $filename = 'zibomo-brochure.pdf';
    }

    // This email goes to whatever address was typed in, so only a name that
    // looks like a name reaches the greeting. Otherwise the form could be
    // used to send a stranger someone else's words or links.
    $greeting = preg_match("/^\\p{L}[\\p{L}\\p{M} .'-]{0,59}$/u", $lead['name'])
        ? 'Hi ' . $lead['name'] . ','
        : 'Hello,';

    return zb_deliver(
        $lead['email'],
        $config['brochure_email_subject'],
        zb_brochure_email_text($greeting, $lead['requirement'], $filename),
        zb_brochure_email_html($greeting, $lead['requirement'], $filename),
        $config['lead_recipient'],
        'Zibomo Support',
        array(array('name' => $filename, 'type' => 'application/pdf', 'content' => $pdf)),
        'Zibomo Smart Lockers'
    );
}

/**
 * Send one notification to the support inbox.
 *
 * @param string $subject
 * @param string $heading     title at the top of the message
 * @param array  $rows        label => sanitised value
 * @param string $submittedAt
 * @param string $replyTo     the visitor's (validated) address
 * @param string $replyToName
 * @return bool true only when the message was accepted for delivery
 */
function zb_send_notification($subject, $heading, array $rows, $submittedAt, $replyTo, $replyToName)
{
    $config = zb_config();

<<<<<<< HEAD
    return zb_deliver(
        $config['lead_recipient'],
        $subject,
        zb_email_text($heading, $rows, $submittedAt),
        zb_email_html($heading, $rows, $submittedAt),
=======
    // Email is optional on both forms. Without one there is no Reply-To,
    // so point support at the phone number instead.
    $note = filter_var($replyTo, FILTER_VALIDATE_EMAIL)
        ? 'Reply directly to this email to reach the customer.'
        : 'No email address was given — call the customer on the phone number above.';

    return zb_deliver(
        $config['lead_recipient'],
        $subject,
        zb_email_text($heading, $rows, $submittedAt, $note),
        zb_email_html($heading, $rows, $submittedAt, $note),
>>>>>>> 8b742b9 (completed)
        $replyTo,
        $replyToName
    );
}

/**
 * Send one message: mail() first, then the SMTP account if mail() fails,
 * or the SMTP account only when 'mail_transport' is 'smtp'.
 *
 * @param string      $to
 * @param string      $subject
 * @param string      $text
 * @param string      $html
 * @param string      $replyTo
 * @param string      $replyToName
 * @param array       $attachments each array('name' =>, 'type' =>, 'content' =>)
 * @param string|null $fromName    defaults to 'mail_from_name'
 * @return bool true only when the message was accepted for delivery
 */
function zb_deliver($to, $subject, $text, $html, $replyTo, $replyToName, array $attachments = array(), $fromName = null)
{
    // mail() failing once means it fails for the rest of this request, and
    // on XAMPP each attempt costs a couple of seconds.
    static $mailFailed = false;

    $config = zb_config();

    // Addresses are validated before they reach here, but strip line breaks
    // anyway — these values end up in headers.
    $to          = zb_header_safe($to);
    $subject     = zb_header_safe($subject);
    $replyTo     = zb_header_safe($replyTo);
    $replyToName = zb_header_safe($replyToName);
    $fromName    = zb_header_safe($fromName !== null ? $fromName : $config['mail_from_name']);

    if (strtolower((string) $config['mail_transport']) !== 'smtp' && !$mailFailed) {
        if (zb_send_via_mail($config, $to, $subject, $text, $html, $replyTo, $replyToName, $attachments, $fromName)) {
            return true;
        }
        $mailFailed = true;

        $smtp = zb_smtp_settings($config);
        if ($smtp['username'] === '' || $smtp['password'] === '') {
            return false;
        }
        zb_log('Sending through SMTP (' . $smtp['host'] . ') instead.');
    }

    return zb_send_via_smtp($config, $to, $subject, $text, $html, $replyTo, $replyToName, $attachments, $fromName);
}

/**
 * PHP's mail(). Headers are assembled from values that have already had
 * CR/LF stripped, which is what closes the header-injection vector.
 *
 * @return bool
 */
function zb_send_via_mail($config, $to, $subject, $text, $html, $replyTo, $replyToName, array $attachments, $fromName)
{
    if (!function_exists('mail')) {
        zb_log('mail() is disabled on this host.');
        return false;
    }

    $from = zb_header_safe($config['mail_from']);
    list($contentType, $body) = zb_mime_body($text, $html, $attachments);

    $headers = array(
        'From: ' . zb_encode_address($fromName, $from),
        'MIME-Version: 1.0',
        $contentType,
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

    $ok = @mail($to, zb_encode_header($subject), $body, implode("\r\n", $headers), $params);
    if (!$ok) {
        zb_log('mail() returned false for recipient ' . $to);
    }
    return (bool) $ok;
}

/**
 * The smtp block from the config, with Gmail's defaults filled in.
 *
 * @return array host, port, encryption, username, password, timeout
 */
function zb_smtp_settings($config)
{
    $smtp = isset($config['smtp']) && is_array($config['smtp']) ? $config['smtp'] : array();

    $host = isset($smtp['host']) && trim((string) $smtp['host']) !== '' ? trim((string) $smtp['host']) : 'smtp.gmail.com';
    $port = isset($smtp['port']) && (int) $smtp['port'] > 0 ? (int) $smtp['port'] : 465;

    $password = isset($smtp['password']) ? trim((string) $smtp['password']) : '';
    if (preg_match('/(^|\.)(gmail|googlemail)\.com$/i', $host)) {
        // Google shows app passwords in groups of four; the spaces are not part of it.
        $password = str_replace(' ', '', $password);
    }

    return array(
        'host'       => $host,
        'port'       => $port,
        'encryption' => isset($smtp['encryption']) && $smtp['encryption'] !== ''
            ? strtolower((string) $smtp['encryption'])
            : ($port === 465 ? 'ssl' : 'tls'),
        'username'   => isset($smtp['username']) ? trim((string) $smtp['username']) : '',
        'password'   => $password,
        'timeout'    => isset($smtp['timeout']) ? max(5, (int) $smtp['timeout']) : 20,
    );
}

/**
 * Send through an SMTP server, signed in with a username and password.
 * Gmail sends as the signed-in account whatever From says, so From is that
 * account; the visitor stays in Reply-To.
 *
 * @return bool true only when the server accepted the message
 */
function zb_send_via_smtp($config, $to, $subject, $text, $html, $replyTo, $replyToName, array $attachments, $fromName)
{
    $s = zb_smtp_settings($config);
    if ($s['username'] === '' || $s['password'] === '') {
        zb_log('SMTP is not set up: put ZIBOMO_SMTP_USERNAME and ZIBOMO_SMTP_PASSWORD (the Gmail address and app password) in .env.');
        return false;
    }

    $fp = zb_smtp_open($s);
    if (!$fp) {
        return false;
    }

    $from   = zb_header_safe($s['username']);
    $domain = strpos($from, '@') !== false ? substr($from, strrpos($from, '@') + 1) : 'localhost';
    list($contentType, $body) = zb_mime_body($text, $html, $attachments);

    $headers = array(
        'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
        'Message-ID: <' . bin2hex(zb_random_bytes(16)) . '@' . $domain . '>',
        'From: ' . zb_encode_address($fromName, $from),
        'To: ' . $to,
        'Subject: ' . zb_encode_header($subject),
    );
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . zb_encode_address($replyToName, $replyTo);
    }
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = $contentType;
    $headers[] = 'X-Mailer: Zibomo Website';

    // A line that starts with "." would end the message early; double it.
    $message = preg_replace('/^\./m', '..', implode("\r\n", $headers) . "\r\n\r\n" . rtrim($body, "\r\n"));

    $ok = zb_smtp_expect($fp, 'MAIL FROM:<' . $from . '>', 250, 'MAIL FROM')
        && zb_smtp_expect($fp, 'RCPT TO:<' . $to . '>', 250, 'RCPT TO ' . $to)
        && zb_smtp_expect($fp, 'DATA', 354, 'DATA')
        && zb_smtp_expect($fp, $message . "\r\n.", 250, 'sending the message');

    @fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return $ok;
}

/**
 * Connect, secure the connection and sign in.
 *
 * @param array $s from zb_smtp_settings()
 * @return resource|false the connection, ready for MAIL FROM
 */
function zb_smtp_open(array $s)
{
    $loopback = in_array(strtolower($s['host']), array('localhost', '127.0.0.1', '::1'), true);
    if ($s['encryption'] === 'none' && !$loopback) {
        zb_log('SMTP encryption is "none" for ' . $s['host'] . '; refusing to send the password unencrypted.');
        return false;
    }

    $context = stream_context_create(array('ssl' => array(
        'verify_peer'      => true,
        'verify_peer_name' => true,
        'peer_name'        => $s['host'],
    )));
    $remote = ($s['encryption'] === 'ssl' ? 'ssl://' : 'tcp://') . $s['host'] . ':' . $s['port'];

    $errno  = 0;
    $errstr = '';
    $fp = @stream_socket_client($remote, $errno, $errstr, $s['timeout'], STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        zb_log('SMTP could not connect to ' . $s['host'] . ':' . $s['port'] . ' — ' . $errstr . ' (' . $errno . ')');
        return false;
    }
    stream_set_timeout($fp, $s['timeout']);

    $ehlo = 'EHLO ' . (preg_replace('/[^A-Za-z0-9.-]/', '', (string) gethostname()) ?: 'localhost');

    $ok = zb_smtp_expect($fp, null, 220, 'greeting')
        && zb_smtp_expect($fp, $ehlo, 250, 'EHLO');

    if ($ok && $s['encryption'] === 'tls') {
        $ok = zb_smtp_expect($fp, 'STARTTLS', 220, 'STARTTLS');
        if ($ok) {
            $method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }
            $ok = (bool) @stream_socket_enable_crypto($fp, true, $method);
            if (!$ok) {
                zb_log('SMTP could not start TLS with ' . $s['host'] . '.');
            }
        }
        $ok = $ok && zb_smtp_expect($fp, $ehlo, 250, 'EHLO after STARTTLS');
    }

    // AUTH LOGIN sends the credentials only after the server has agreed to
    // the command, never inside it.
    $ok = $ok
        && zb_smtp_expect($fp, 'AUTH LOGIN', 334, 'AUTH LOGIN')
        && zb_smtp_expect($fp, base64_encode($s['username']), 334, 'AUTH username')
        && zb_smtp_expect($fp, base64_encode($s['password']), 235, 'sign-in (check the username and app password)');

    if (!$ok) {
        @fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return false;
    }
    return $fp;
}

/**
 * Send one command (or nothing, to read the greeting) and check the reply.
 * Only the label and the server's reply are logged, never the command, so
 * the credentials cannot end up in the log.
 *
 * @param resource    $fp
 * @param string|null $command without the trailing CRLF
 * @param int         $expect  reply code that means success
 * @param string      $label
 * @return bool
 */
function zb_smtp_expect($fp, $command, $expect, $label)
{
    if ($command !== null) {
        // In slices: a socket may take only part of a large write (the
        // brochure email is several megabytes).
        $data = $command . "\r\n";
        $size = strlen($data);
        for ($done = 0; $done < $size; $done += $n) {
            $n = @fwrite($fp, substr($data, $done, 65536));
            if ($n === false || $n === 0) {
                zb_log('SMTP ' . $label . ' failed: the connection was closed.');
                return false;
            }
        }
    }

    $reply = '';
    while (($line = fgets($fp, 1024)) !== false) {
        $reply .= $line;
        // "250-" continues a multi-line reply; "250 " ends it.
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }

    if ((int) substr($reply, 0, 3) === $expect) {
        return true;
    }

    $meta = stream_get_meta_data($fp);
    zb_log('SMTP ' . $label . ' failed: '
         . ($reply !== '' ? substr(zb_header_safe($reply), 0, 300) : (!empty($meta['timed_out']) ? 'timed out' : 'no reply')));
    return false;
}

/**
 * The message body: the plain-text and HTML versions as
 * multipart/alternative, wrapped in multipart/mixed when there are
 * attachments. Everything is base64, so no line runs long or starts with
 * a dot.
 *
 * @param string $text
 * @param string $html
 * @param array  $attachments each array('name' =>, 'type' =>, 'content' =>)
 * @return array(string $contentTypeHeader, string $body)
 */
function zb_mime_body($text, $html, array $attachments = array())
{
    $alt = '=_zibomo_alt_' . bin2hex(zb_random_bytes(12));
    $alternative = '--' . $alt . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text), 76, "\r\n")
        . '--' . $alt . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html), 76, "\r\n")
        . '--' . $alt . "--\r\n";

    if (!$attachments) {
        return array('Content-Type: multipart/alternative; boundary="' . $alt . '"', $alternative);
    }

    $mixed = '=_zibomo_mixed_' . bin2hex(zb_random_bytes(12));
    $body  = '--' . $mixed . "\r\n"
           . 'Content-Type: multipart/alternative; boundary="' . $alt . '"' . "\r\n\r\n"
           . $alternative;
    foreach ($attachments as $file) {
        $name  = preg_replace('/[^A-Za-z0-9._-]/', '', (string) $file['name']);
        $body .= '--' . $mixed . "\r\n"
               . 'Content-Type: ' . $file['type'] . '; name="' . $name . '"' . "\r\n"
               . "Content-Transfer-Encoding: base64\r\n"
               . 'Content-Disposition: attachment; filename="' . $name . '"' . "\r\n\r\n"
               . chunk_split(base64_encode($file['content']), 76, "\r\n");
    }
    $body .= '--' . $mixed . "--\r\n";

    return array('Content-Type: multipart/mixed; boundary="' . $mixed . '"', $body);
}

/**
 * RFC 2047 encode a header value such as the subject.
 *
 * @return string
 */
function zb_encode_header($value)
{
    return '=?UTF-8?B?' . base64_encode(zb_header_safe($value)) . '?=';
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
    return zb_encode_header($name) . ' <' . $address . '>';
}

/* ------------------------------------------------------------------
   Templates
   ------------------------------------------------------------------ */

/**
 * @param string $heading
 * @param array  $rows label => value
 * @param string $submittedAt
<<<<<<< HEAD
 * @return string
 */
function zb_email_html($heading, array $rows, $submittedAt)
=======
 * @param string $note        how support can reach the customer
 * @return string
 */
function zb_email_html($heading, array $rows, $submittedAt, $note)
>>>>>>> 8b742b9 (completed)
{
    $tr = '';
    foreach ($rows as $label => $value) {
        // Every value is escaped: a lead must never be able to inject
        // markup into the mail the support team opens.
        $safe = $value !== '' ? nl2br(zb_e($value)) : '—';
        // Fixed column widths, wrapping labels and breakable values keep the
        // table inside a phone screen — a long email address would otherwise
        // widen it past the edge, where the rounded card clips it.
        $tr .= '<tr>'
             . '<td width="38%" style="padding:11px 12px 11px 16px;border-bottom:1px solid #E4E9F2;'
             . 'font-family:Arial,Helvetica,sans-serif;font-size:12px;letter-spacing:.06em;line-height:1.5;'
             . 'text-transform:uppercase;color:#7C8699;vertical-align:top;">'
             . zb_e($label) . '</td>'
             . '<td style="padding:11px 16px 11px 12px;border-bottom:1px solid #E4E9F2;'
             . 'font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#10192F;line-height:1.6;'
             . 'vertical-align:top;word-break:break-word;overflow-wrap:anywhere;">'
             . $safe . '</td>'
             . '</tr>';
    }

    $submitted = zb_e($submittedAt);

    return '<!doctype html><html><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:24px 12px;background:#F5F7FC;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" '
        . 'style="max-width:620px;margin:0 auto;background:#FFFFFF;border:1px solid #E4E9F2;border-radius:10px;overflow:hidden;">'
        . '<tr><td style="background:#0A1638;padding:22px 26px;">'
        . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;letter-spacing:.18em;'
        . 'text-transform:uppercase;color:#FF8E9D;margin-bottom:6px;">Zibomo Website</div>'
        . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:21px;font-weight:bold;color:#FFFFFF;">'
        . zb_e($heading) . '</div></td></tr>'
        . '<tr><td style="padding:22px 26px 6px;">'
        . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:bold;'
        . 'letter-spacing:.08em;text-transform:uppercase;color:#E8112D;">Customer Details</div></td></tr>'
        . '<tr><td style="padding:10px 10px 4px;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="table-layout:fixed;">'
        . $tr . '</table></td></tr>'
        . '<tr><td style="padding:16px 26px 24px;">'
        . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#55617A;">'
        . 'Submitted at: <strong style="color:#10192F;">' . $submitted . '</strong></div>'
        . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#7C8699;margin-top:10px;">'
        . zb_e($note) . '</div>'
        . '</td></tr></table></body></html>';
}

/**
 * @param string $heading
 * @param array  $rows label => value
 * @param string $submittedAt
<<<<<<< HEAD
 * @return string
 */
function zb_email_text($heading, array $rows, $submittedAt)
=======
 * @param string $note        how support can reach the customer
 * @return string
 */
function zb_email_text($heading, array $rows, $submittedAt, $note)
>>>>>>> 8b742b9 (completed)
{
    $lines = array(
        $heading,
        '',
        'Customer Details',
        '-------------------------',
    );
    foreach ($rows as $label => $value) {
        $lines[] = $label . ': ' . ($value !== '' ? $value : '-');
    }
    $lines[] = '';
    $lines[] = 'Submitted At: ' . $submittedAt;
<<<<<<< HEAD
=======
    $lines[] = $note;
>>>>>>> 8b742b9 (completed)

    return implode("\r\n", $lines);
}

/**
 * The email that carries the brochure to the visitor.
 *
 * @param string $greeting    already reduced to "Hi <name>," or "Hello,"
<<<<<<< HEAD
 * @param string $requirement one of zb_requirements()
=======
 * @param string $requirement one of zb_requirements(), or '' — it is optional
>>>>>>> 8b742b9 (completed)
 * @param string $filename
 * @return string
 */
function zb_brochure_email_html($greeting, $requirement, $filename)
{
    $p = 'font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.7;color:#10192F;margin:0 0 16px;';

<<<<<<< HEAD
=======
    $followUp = $requirement !== ''
        ? 'We have noted your interest in <strong>' . zb_e($requirement) . '</strong>, and our team will be in touch shortly.'
        : 'Our team will be in touch shortly.';

>>>>>>> 8b742b9 (completed)
    return '<!doctype html><html><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:24px 12px;background:#F5F7FC;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" '
        . 'style="max-width:620px;margin:0 auto;background:#FFFFFF;border:1px solid #E4E9F2;border-radius:10px;overflow:hidden;">'
        . '<tr><td style="background:#0A1638;padding:22px 26px;">'
        . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;letter-spacing:.18em;'
        . 'text-transform:uppercase;color:#FF8E9D;margin-bottom:6px;">Zibomo Smart Lockers</div>'
        . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:21px;font-weight:bold;color:#FFFFFF;">'
        . 'Your brochure is attached</div></td></tr>'
        . '<tr><td style="padding:26px 26px 6px;">'
        . '<p style="' . $p . '">' . zb_e($greeting) . '</p>'
        . '<p style="' . $p . '">Thank you for your interest in Zibomo. The brochure you asked for is attached '
        . 'to this email as <strong>' . zb_e($filename) . '</strong>. It covers hardware configurations, '
        . 'platform capabilities and deployment scenarios.</p>'
<<<<<<< HEAD
        . '<p style="' . $p . '">We have noted your interest in <strong>' . zb_e($requirement) . '</strong>, '
        . 'and our team will be in touch shortly.</p>'
=======
        . '<p style="' . $p . '">' . $followUp . '</p>'
>>>>>>> 8b742b9 (completed)
        . '<p style="' . $p . '">Want to talk sooner? Call <a href="tel:+919154324445" style="color:#E8112D;">'
        . '+91 9154324445</a> or simply reply to this email.</p>'
        . '<p style="' . $p . '">— Team Zibomo</p>'
        . '</td></tr>'
        . '<tr><td style="padding:0 26px 24px;">'
        . '<div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:#7C8699;'
        . 'border-top:1px solid #E4E9F2;padding-top:14px;">'
        . 'Zibomo Pvt. Ltd. · 5-A/2, IDA, Nacharam, Hyderabad, Telangana 500076<br>'
        . 'You received this because this address was entered on the Zibomo brochure request form.</div>'
        . '</td></tr></table></body></html>';
}

/**
 * @param string $greeting
 * @param string $requirement
 * @param string $filename
 * @return string
 */
function zb_brochure_email_text($greeting, $requirement, $filename)
{
    return implode("\r\n", array(
        $greeting,
        '',
        'Thank you for your interest in Zibomo. The brochure you asked for is attached to this email as '
            . $filename . '. It covers hardware configurations, platform capabilities and deployment scenarios.',
        '',
<<<<<<< HEAD
        'We have noted your interest in ' . $requirement . ', and our team will be in touch shortly.',
=======
        $requirement !== ''
            ? 'We have noted your interest in ' . $requirement . ', and our team will be in touch shortly.'
            : 'Our team will be in touch shortly.',
>>>>>>> 8b742b9 (completed)
        '',
        'Want to talk sooner? Call +91 9154324445 or simply reply to this email.',
        '',
        '— Team Zibomo',
        '',
        '--',
        'Zibomo Pvt. Ltd. · 5-A/2, IDA, Nacharam, Hyderabad, Telangana 500076',
        'You received this because this address was entered on the Zibomo brochure request form.',
    ));
}
