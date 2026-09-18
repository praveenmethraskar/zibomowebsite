<?php
/**
 * Zibomo — brochure and contact form configuration (TEMPLATE).
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
       Where both forms' notifications go
       Env: ZIBOMO_LEAD_RECIPIENT
       --------------------------------------------------------------- */
    'lead_recipient'  => 'support@zibomo.in',
    'lead_subject'    => 'New Zibomo Brochure Download Request',
    'contact_subject' => 'New Zibomo Contact Enquiry',

    /* ---------------------------------------------------------------
       Sender used by mail(). The visitor goes in Reply-To, so support
       can answer them directly.
       Env: ZIBOMO_MAIL_FROM
       --------------------------------------------------------------- */
    'mail_from'      => 'no-reply@zibomo.in',
    'mail_from_name' => 'Zibomo Website',

    /* ---------------------------------------------------------------
       How mail is sent.
         'mail' — PHP's mail(), no password needed. If it fails (XAMPP
                  on Windows has no mail server), the Gmail account
                  below is used instead.
         'smtp' — always use the Gmail account below. Use this if
                  mail() says it sent on the live server but nothing
                  arrives in the inbox.
       Env: ZIBOMO_MAIL_TRANSPORT
       --------------------------------------------------------------- */
    'mail_transport' => 'mail',

    /* ---------------------------------------------------------------
       Gmail account used when mail() cannot send. The password is a
       Google App Password (Google Account → Security → App passwords),
       not the account's normal password. Mail sent this way comes from
       this address.
       --------------------------------------------------------------- */
    'smtp' => array(
        'host'       => 'smtp.gmail.com',  // Env: ZIBOMO_SMTP_HOST
        'port'       => 465,               // Env: ZIBOMO_SMTP_PORT
        'encryption' => 'ssl',             // 'ssl' for 465, 'tls' for 587      Env: ZIBOMO_SMTP_ENCRYPTION
        'username'   => 'zibomodigilocker@gmail.com',                // the Gmail address                 Env: ZIBOMO_SMTP_USERNAME
        'password'   => '',                // set ZIBOMO_SMTP_PASSWORD in .env — never put it here  Env: ZIBOMO_SMTP_PASSWORD
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

    // Also email the PDF to the address the visitor entered (Reply-To is
    // lead_recipient, so their replies reach support).
    'email_brochure_to_visitor' => true,
    'brochure_email_subject'    => 'Your Zibomo Smart Locker Brochure',

    // How long after a successful request the download link keeps working.
    'download_window_minutes' => 30,

    /* ---------------------------------------------------------------
       Anti-spam (no database, all session based).
       The hourly cap applies to each form separately.
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
