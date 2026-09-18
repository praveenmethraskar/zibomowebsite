<?php
/**
 * Zibomo — shared bootstrap for the brochure and contact forms.
 *
 * Session handling, configuration, CSRF, sanitisation and logging.
 * Included by brochure.php, download-brochure.php and contact.php.
 *
 * No database. Nothing about a visitor is written to disk; the only
 * persisted state is a session timestamp granting brochure downloads
 * for a short window.
 */

if (!defined('ZB_ROOT')) {
    define('ZB_ROOT', dirname(__DIR__));
}

/* ------------------------------------------------------------------
   Configuration
   ------------------------------------------------------------------ */

/**
 * Load includes/config.php once, letting real environment variables win.
 *
 * @return array
 */
function zb_config()
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $file = __DIR__ . '/config.php';
    $config = is_readable($file) ? require $file : array();
    if (!is_array($config)) {
        $config = array();
    }

    $config += array(
        'lead_recipient'           => 'support@zibomo.in',
        'lead_subject'             => 'New Zibomo Brochure Download Request',
        'contact_subject'          => 'New Zibomo Contact Enquiry',
        'mail_from'                => 'no-reply@zibomo.in',
        'mail_from_name'           => 'Zibomo Website',
        'mail_transport'           => 'mail',
        'smtp'                     => array(),
        'brochure_path'            => ZB_ROOT . '/assets/zibomo-brochure.pdf',
        'brochure_filename'        => 'zibomo-brochure.pdf',
        'email_brochure_to_visitor' => true,
        'brochure_email_subject'   => 'Your Zibomo Smart Locker Brochure',
        'download_window_minutes'  => 30,
        'max_submissions_per_hour' => 3,
        'min_seconds_on_form'      => 3,
        'error_log'                => '',
    );

    // Environment variables — real ones, or lines in a .env file — override
    // the file, so credentials never need to live in source control.
    $env = array(
        'ZIBOMO_MAIL_TRANSPORT' => 'mail_transport',
        'ZIBOMO_LEAD_RECIPIENT' => 'lead_recipient',
        'ZIBOMO_MAIL_FROM'      => 'mail_from',
        'ZIBOMO_BROCHURE_PATH'  => 'brochure_path',
        'ZIBOMO_ERROR_LOG'      => 'error_log',
    );
    foreach ($env as $var => $key) {
        $val = zb_env($var);
        if ($val !== false && $val !== '') {
            $config[$key] = $val;
        }
    }

    if (!is_array($config['smtp'])) {
        $config['smtp'] = array();
    }
    $smtpEnv = array(
        'ZIBOMO_SMTP_HOST'       => 'host',
        'ZIBOMO_SMTP_PORT'       => 'port',
        'ZIBOMO_SMTP_ENCRYPTION' => 'encryption',
        'ZIBOMO_SMTP_USERNAME'   => 'username',
        'ZIBOMO_SMTP_PASSWORD'   => 'password',
    );
    foreach ($smtpEnv as $var => $key) {
        $val = zb_env($var);
        if ($val !== false && $val !== '') {
            $config['smtp'][$key] = $val;
        }
    }

    return $config;
}

/**
 * One setting from the environment: a real environment variable if the
 * host sets one, otherwise the same name in a .env file.
 *
 * @param string $name
 * @return string|false
 */
function zb_env($name)
{
    $val = getenv($name);
    if ($val !== false && $val !== '') {
        return $val;
    }
    $file = zb_dotenv();
    return isset($file[$name]) ? $file[$name] : false;
}

/**
 * KEY=VALUE lines from .env, read once. Blank lines and # comments are
 * skipped and surrounding quotes removed; nothing is expanded.
 *
 * Two places are read. The project's own .env is blocked from the web by
 * the root .htaccess, but that is an Apache/LiteSpeed feature, so a .env
 * one level above the web root — which no URL can reach on any server —
 * is read as well and wins where both set a value.
 *
 * @return array
 */
function zb_dotenv()
{
    static $vars = null;
    if ($vars !== null) {
        return $vars;
    }

    $vars = array();
    foreach (array(ZB_ROOT . '/.env', dirname(ZB_ROOT) . '/.env') as $file) {
        if (!is_file($file) || !is_readable($file)) {
            continue;
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            continue;
        }
        foreach ($lines as $i => $line) {
            if ($i === 0) {
                // Notepad may save a UTF-8 byte order mark.
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
            }
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            list($key, $value) = explode('=', $line, 2);
            $key   = trim(preg_replace('/^export\s+/', '', trim($key)));
            $value = trim($value);
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
                $value = substr($value, 1, -1);
            }
            $vars[$key] = $value;
        }
    }
    return $vars;
}

/**
 * Resolve the brochure to an absolute path.
 *
 * 'brochure_path' may be a single string or an ordered list of candidates;
 * the first readable one wins, so a deployment can keep the file above the
 * web root while a local checkout uses the in-project copy.
 *
 * @return string|false absolute path, or false when nothing is readable
 */
function zb_brochure_path()
{
    $config = zb_config();
    $candidates = $config['brochure_path'];
    if (!is_array($candidates)) {
        $candidates = array($candidates);
    }

    foreach ($candidates as $candidate) {
        $candidate = (string) $candidate;
        if ($candidate === '') {
            continue;
        }
        $real = @realpath($candidate);
        if ($real !== false && is_file($real) && is_readable($real)) {
            return $real;
        }
    }
    return false;
}

/**
 * Hand the response to the visitor now and let the script carry on
 * without them waiting. PHP-FPM and LiteSpeed have a call for this;
 * elsewhere (Apache's mod_php, XAMPP) an empty body with Content-Length: 0
 * lets the browser finish while the script keeps running. Only for
 * responses with no body, such as a redirect.
 */
function zb_finish_response()
{
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        return;
    }
    if (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
        return;
    }

    // Compression would put bytes after a declared length of zero.
    @ini_set('zlib.output_compression', 'Off');
    header('Content-Length: 0');
    header('Connection: close');
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
}

/**
 * Let this session download the brochure. Called in exactly one place:
 * straight after the lead email was accepted.
 */
function zb_brochure_grant()
{
    $_SESSION['brochure_access'] = time();
    // The success page starts the download itself, once per grant.
    $_SESSION['brochure_autostart'] = true;
}

/**
 * Whether this session may download the brochure.
 *
 * The permission is a timestamp rather than a single-use flag so that the
 * success page's automatic download and its fallback button both work.
 *
 * @return bool
 */
function zb_brochure_access_ok()
{
    $grantedAt = isset($_SESSION['brochure_access']) ? $_SESSION['brochure_access'] : 0;
    if (!is_int($grantedAt) || $grantedAt <= 0) {
        return false;
    }
    $config = zb_config();
    return (time() - $grantedAt) < ((int) $config['download_window_minutes'] * 60);
}

/* ------------------------------------------------------------------
   Shared form data
   ------------------------------------------------------------------ */

/**
 * The requirement options both forms offer. Submitted values are checked
 * against this list so an edited <option> cannot pass arbitrary text on.
 *
 * @return array
 */
function zb_requirements()
{
    return array(
        'Business / Corporate lockers',
        'Gated community lockers',
        'Convention / Event lockers',
        'Temple / Public storage lockers',
        'Transport / Luggage lockers',
        'Laundry / Parcel lockers',
        'Product & software demo',
        'Other',
    );
}

/**
 * Submission timestamp in the site's timezone.
 *
 * @return string
 */
function zb_submitted_at()
{
    $tz = 'Asia/Kolkata';
    try {
        $now = new DateTime('now', new DateTimeZone($tz));
        return $now->format('j F Y, g:i A') . ' IST';
    } catch (Exception $e) {
        return gmdate('j F Y, g:i A') . ' UTC';
    }
}

/* ------------------------------------------------------------------
   Session
   ------------------------------------------------------------------ */

/**
 * Start a session with conservative cookie flags.
 */
function zb_session_start()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $params = session_get_cookie_params();
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params(array(
            'lifetime' => 0,
            'path'     => isset($params['path']) ? $params['path'] : '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    } else {
        session_set_cookie_params(0, isset($params['path']) ? $params['path'] : '/', '', $https, true);
    }

    session_name('ZIBOMOSESS');
    session_start();
}

/* ------------------------------------------------------------------
   Logging — server side only, never surfaced to a visitor
   ------------------------------------------------------------------ */

/**
 * @param string $message
 */
function zb_log($message)
{
    $config = zb_config();
    $line = '[' . gmdate('Y-m-d H:i:s') . ' UTC] ' . $message;

    $target = isset($config['error_log']) ? $config['error_log'] : '';
    if ($target !== '' && @is_dir(dirname($target))) {
        @error_log($line . PHP_EOL, 3, $target);
        return;
    }
    error_log('[zibomo-brochure] ' . $message);
}

/* ------------------------------------------------------------------
   CSRF
   ------------------------------------------------------------------ */

/**
 * @return string the token to embed in the form
 */
function zb_csrf_token()
{
    if (empty($_SESSION['zb_csrf'])) {
        $_SESSION['zb_csrf'] = bin2hex(zb_random_bytes(32));
    }
    return $_SESSION['zb_csrf'];
}

/**
 * Constant-time comparison of the submitted token against the session token.
 *
 * @param string|null $submitted
 * @return bool
 */
function zb_csrf_validate($submitted)
{
    if (!is_string($submitted) || $submitted === '' || empty($_SESSION['zb_csrf'])) {
        return false;
    }
    return hash_equals($_SESSION['zb_csrf'], $submitted);
}

/**
 * The static contact form cannot carry a CSRF token, so its endpoint
 * checks where the POST came from instead. Browsers send Origin on every
 * scripted POST; Referer covers the rare one that does not. A request with
 * neither did not come from a page on this site.
 *
 * @return bool
 */
function zb_same_origin()
{
    $host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
    if ($host === '') {
        return false;
    }

    foreach (array('HTTP_ORIGIN', 'HTTP_REFERER') as $header) {
        if (empty($_SERVER[$header])) {
            continue;
        }
        $parts = parse_url($_SERVER[$header]);
        if (!is_array($parts) || empty($parts['host'])) {
            return false;
        }
        $source = strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
        return $source === $host;
    }
    return false;
}

/**
 * Rotate the token after a successful submission so it cannot be replayed.
 */
function zb_csrf_rotate()
{
    $_SESSION['zb_csrf'] = bin2hex(zb_random_bytes(32));
}

/**
 * @param int $length
 * @return string
 */
function zb_random_bytes($length)
{
    if (function_exists('random_bytes')) {
        try {
            return random_bytes($length);
        } catch (Exception $e) {
            // fall through
        }
    }
    if (function_exists('openssl_random_pseudo_bytes')) {
        $bytes = openssl_random_pseudo_bytes($length, $strong);
        if ($bytes !== false && $strong) {
            return $bytes;
        }
    }
    // Last resort. Still unpredictable enough for a CSRF token.
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= chr(mt_rand(0, 255));
    }
    return $out;
}

/* ------------------------------------------------------------------
   Input handling
   ------------------------------------------------------------------ */

/**
 * Normalise a submitted scalar: force a string, strip control characters
 * (including the CR/LF used for mail header injection), collapse runaway
 * whitespace and cap the length.
 *
 * @param mixed $value
 * @param int   $maxLength
 * @return string
 */
function zb_clean($value, $maxLength = 500)
{
    if (is_array($value) || $value === null) {
        return '';
    }
    $value = (string) $value;

    // Drop anything that is not valid UTF-8 rather than letting it through.
    if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
        return '';
    }

    // Strip C0/C1 control characters but keep tab and newline for textareas.
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    if ($value === null) {
        return '';
    }

    $value = trim($value);
    $value = preg_replace("/(\r\n|\r|\n){3,}/", "\n\n", $value);

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }
    return substr($value, 0, $maxLength);
}

/**
 * A value that is about to be placed in a mail header must contain no
 * line breaks at all — that is the header-injection vector.
 *
 * @param string $value
 * @return string
 */
function zb_header_safe($value)
{
    $value = str_replace(array("\r", "\n", "\t", "\0"), ' ', (string) $value);
    return trim(preg_replace('/\s+/', ' ', $value));
}

/**
 * Escape for HTML output. Used for both the page and the email body.
 *
 * @param string $value
 * @return string
 */
function zb_e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Phone validation that accepts the formats Indian visitors actually type
 * (+91 98765 43210, 098765-43210, 9876543210) without being so loose that
 * junk gets through.
 *
 * @param string $phone
 * @return bool
 */
function zb_valid_phone($phone)
{
    $digits = preg_replace('/\D+/', '', $phone);
    if ($digits === null) {
        return false;
    }
    $len = strlen($digits);
    if ($len < 8 || $len > 15) {
        return false;
    }
    // Reject obvious filler such as 0000000000 or 1111111111.
    return !preg_match('/^(\d)\1+$/', $digits);
}

/* ------------------------------------------------------------------
   Rate limiting — session based, no storage
   ------------------------------------------------------------------ */

/**
 * Each form has its own bucket, so requesting the brochure never uses up
 * a visitor's contact enquiries or the other way round.
 *
 * @param string $bucket session key holding the timestamps
 * @return bool true when the visitor is under the hourly cap
 */
function zb_rate_limit_ok($bucket = 'zb_hits')
{
    $config = zb_config();
    $max = (int) $config['max_submissions_per_hour'];
    if ($max <= 0) {
        return true;
    }

    $now = time();
    $hits = isset($_SESSION[$bucket]) && is_array($_SESSION[$bucket])
        ? $_SESSION[$bucket]
        : array();

    $hits = array_values(array_filter($hits, function ($t) use ($now) {
        return is_int($t) && ($now - $t) < 3600;
    }));
    $_SESSION[$bucket] = $hits;

    return count($hits) < $max;
}

/**
 * Record a submission attempt against the hourly cap.
 *
 * @param string $bucket session key holding the timestamps
 */
function zb_rate_limit_hit($bucket = 'zb_hits')
{
    if (!isset($_SESSION[$bucket]) || !is_array($_SESSION[$bucket])) {
        $_SESSION[$bucket] = array();
    }
    $_SESSION[$bucket][] = time();
}
