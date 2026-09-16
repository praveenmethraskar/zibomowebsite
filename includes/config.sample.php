<?php
/**
 * Zibomo — brochure lead-capture configuration (TEMPLATE).
 *
 * Copy this file to includes/config.php and fill in the real values.
 *
 * includes/config.php is gitignored on purpose: upload it once over FTP or
 * the hPanel File Manager and never commit it. Any value below can also be
 * supplied as a real environment variable (listed per key), which takes
 * precedence — use that if your host exposes env vars.
 */

return array(

    /* ---------------------------------------------------------------
       Where the lead notification goes
       --------------------------------------------------------------- */
    'lead_recipient' => 'support@zibomo.in',
    'lead_subject'   => 'New Zibomo Brochure Download Request',

    /* ---------------------------------------------------------------
       Envelope sender.
       This MUST be a real mailbox on a domain whose DNS (SPF/DKIM)
       authorises this server to send. Using a visitor's address here
       is what gets mail rejected or junked — the visitor goes in
       Reply-To instead, which this code does automatically.
       --------------------------------------------------------------- */
    'mail_from'      => 'no-reply@zibomo.in',
    'mail_from_name' => 'Zibomo Website',

    /* ---------------------------------------------------------------
       Transport: 'smtp' (recommended) or 'mail' (last resort).
       See includes/mail.php for why smtp matters.
       Env: ZIBOMO_MAIL_TRANSPORT
       --------------------------------------------------------------- */
    'mail_transport' => 'smtp',

    'smtp' => array(
        'host'       => 'smtp.hostinger.com',   // Env: ZIBOMO_SMTP_HOST
        'port'       => 465,                    // Env: ZIBOMO_SMTP_PORT
        'encryption' => 'ssl',                  // 'ssl' for 465, 'tls' for 587
        'username'   => 'support@zibomo.in',    // Env: ZIBOMO_SMTP_USERNAME
        'password'   => '',                     // Env: ZIBOMO_SMTP_PASSWORD  <- leave blank here, set the env var
        'timeout'    => 20,
    ),

    /* ---------------------------------------------------------------
       Brochure file.

       Candidates are tried in order and the first readable one wins.

       [0] ABOVE the web root. Nothing the web server can serve, on any
           host, regardless of .htaccess support. On Hostinger, with the
           site in public_html/, this is /home/<user>/private/ — put the
           PDF there and this entry is used.

       [1] In-project fallback for local work, protected only by
           private/.htaccess. That file is an Apache/LiteSpeed feature and
           is IGNORED by nginx, so treat this as the development path and
           use [0] in production.

       Set a single string here instead if you want to pin one location.
       Env: ZIBOMO_BROCHURE_PATH
       --------------------------------------------------------------- */
    'brochure_path'     => array(
        dirname(__DIR__, 2) . '/private/zibomo-brochure.pdf',
        dirname(__DIR__) . '/private/zibomo-brochure.pdf',
    ),
    'brochure_filename' => 'zibomo-brochure.pdf',

    /* ---------------------------------------------------------------
       Anti-spam (no database, all session based)
       --------------------------------------------------------------- */
    'max_submissions_per_hour' => 3,
    'min_seconds_on_form'      => 3,

    /* ---------------------------------------------------------------
       Server-side error log. Never shown to visitors.
       Keep it outside the web root.
       Env: ZIBOMO_ERROR_LOG
       --------------------------------------------------------------- */
    'error_log' => dirname(__DIR__) . '/private/zibomo-brochure.log',
);
