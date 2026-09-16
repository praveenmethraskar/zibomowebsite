<?php
/**
 * Zibomo — shared bootstrap for the brochure lead-capture flow.
 *
 * Session handling, configuration, CSRF, sanitisation and logging.
 * Included by brochure.php and download-brochure.php.
 *
 * No database. Nothing about a visitor is written to disk; the only
 * persisted state is a session flag granting one brochure download.
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
        'mail_from'                => 'no-reply@zibomo.in',
        'mail_from_name'           => 'Zibomo Website',
        'mail_transport'           => 'mail',
        'smtp'                     => array(),
        'brochure_path'            => ZB_ROOT . '/assets/zibomo-brochure.pdf',
        'brochure_filename'        => 'zibomo-brochure.pdf',
        'max_submissions_per_hour' => 3,
        'min_seconds_on_form'      => 3,
        'error_log'                => '',
    );

    // Environment variables override the file, so credentials never need
    // to live in source control.
    $env = array(
        'ZIBOMO_MAIL_TRANSPORT' => 'mail_transport',
        'ZIBOMO_LEAD_RECIPIENT' => 'lead_recipient',
        'ZIBOMO_MAIL_FROM'      => 'mail_from',
        'ZIBOMO_BROCHURE_PATH'  => 'brochure_path',
        'ZIBOMO_ERROR_LOG'      => 'error_log',
    );
    foreach ($env as $var => $key) {
        $val = getenv($var);
        if ($val !== false && $val !== '') {
            $config[$key] = $val;
        }
    }

    $smtpEnv = array(
        'ZIBOMO_SMTP_HOST'     => 'host',
        'ZIBOMO_SMTP_PORT'     => 'port',
        'ZIBOMO_SMTP_USERNAME' => 'username',
        'ZIBOMO_SMTP_PASSWORD' => 'password',
        'ZIBOMO_SMTP_ENCRYPTION' => 'encryption',
    );
    foreach ($smtpEnv as $var => $key) {
        $val = getenv($var);
        if ($val !== false && $val !== '') {
            $config['smtp'][$key] = $val;
        }
    }

    return $config;
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
 * @return bool true when the visitor is under the hourly cap
 */
function zb_rate_limit_ok()
{
    $config = zb_config();
    $max = (int) $config['max_submissions_per_hour'];
    if ($max <= 0) {
        return true;
    }

    $now = time();
    $hits = isset($_SESSION['zb_hits']) && is_array($_SESSION['zb_hits'])
        ? $_SESSION['zb_hits']
        : array();

    $hits = array_values(array_filter($hits, function ($t) use ($now) {
        return is_int($t) && ($now - $t) < 3600;
    }));
    $_SESSION['zb_hits'] = $hits;

    return count($hits) < $max;
}

/**
 * Record a submission attempt against the hourly cap.
 */
function zb_rate_limit_hit()
{
    if (!isset($_SESSION['zb_hits']) || !is_array($_SESSION['zb_hits'])) {
        $_SESSION['zb_hits'] = array();
    }
    $_SESSION['zb_hits'][] = time();
}
