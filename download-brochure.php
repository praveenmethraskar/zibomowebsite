<?php
/**
 * Zibomo — gated brochure download.
 *
 * Serves the PDF only when brochure.php has granted permission for this
 * session. The file itself lives outside the web root (see config), so
 * this script is the only route to it.
 *
 * The permission is consumed after a complete stream. If the transfer is
 * aborted the script dies before that point, which leaves the permission
 * in place so the visitor can retry — that is deliberate.
 */

require_once __DIR__ . '/includes/bootstrap.php';

zb_session_start();

/* ------------------------------------------------------------------
   1. Permission
   ------------------------------------------------------------------ */
if (empty($_SESSION['brochure_access'])) {
    header('Location: brochure.php', true, 303);
    exit;
}

/* ------------------------------------------------------------------
   2. The file
   ------------------------------------------------------------------ */
$config = zb_config();
$real   = zb_brochure_path();

if ($real === false) {
    // The visitor must not learn anything about the filesystem.
    zb_log('Brochure file not readable at any configured candidate path.');

    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="en"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Brochure unavailable | Zibomo</title>'
       . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
       . 'background:#F5F7FC;font-family:Poppins,system-ui,-apple-system,"Segoe UI",sans-serif;color:#10192F;padding:24px;}'
       . '.b{max-width:460px;background:#fff;border:1px solid #E4E9F2;border-radius:16px;padding:32px;text-align:center;}'
       . 'h1{font-size:1.25rem;margin:0 0 10px;}p{color:#55617A;line-height:1.7;margin:0 0 18px;font-size:.95rem;}'
       . 'a{color:#E8112D;}</style></head><body><div class="b">'
       . '<h1>The brochure is temporarily unavailable</h1>'
       . '<p>Your request reached us. We could not attach the file just now &mdash; '
       . 'email <a href="mailto:support@zibomo.in">support@zibomo.in</a> '
       . 'or call <a href="tel:+919121208058">+91 9121208058</a> and we will send it straight over.</p>'
       . '<p><a href="index.html">Back to the Zibomo site</a></p>'
       . '</div></body></html>';
    exit;
}

$filename = (string) $config['brochure_filename'];
// Never let a configured value put a path or CR/LF into the header.
$filename = preg_replace('/[^A-Za-z0-9._-]/', '', basename($filename));
if ($filename === '') {
    $filename = 'zibomo-brochure.pdf';
}

$size = filesize($real);

/* ------------------------------------------------------------------
   3. Stream it
   ------------------------------------------------------------------ */

// Output buffering would otherwise hold the whole 5 MB in memory.
while (ob_get_level() > 0) {
    ob_end_clean();
}

if (function_exists('set_time_limit')) {
    @set_time_limit(0);
}

// Some shared hosts enable zlib.output_compression in php.ini, which
// ob_end_clean() does not defeat. It would gzip the body while
// Content-Length still declares the uncompressed size, and some clients
// truncate the file at that point.
@ini_set('zlib.output_compression', 'Off');

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
if ($size !== false) {
    header('Content-Length: ' . $size);
}
header('Content-Transfer-Encoding: binary');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

$handle = @fopen($real, 'rb');
if ($handle === false) {
    zb_log('Failed to open brochure for reading: ' . $real);
    http_response_code(503);
    exit;
}

while (!feof($handle)) {
    $chunk = fread($handle, 8192);
    if ($chunk === false) {
        break;
    }
    echo $chunk;
    flush();
}
fclose($handle);

/* ------------------------------------------------------------------
   4. Consume the permission
   ------------------------------------------------------------------ */
unset($_SESSION['brochure_access']);
exit;
