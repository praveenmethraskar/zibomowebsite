<?php
/**
 * Zibomo — contact form endpoint.
 *
 * index.html is static, so its Contact Us form posts here over XHR
 * (js/main.js, module 15) and reads a JSON reply. The enquiry is emailed
 * to support; nothing is written to a database or to disk.
 *
 * There is no CSRF token: a static page has nowhere to render one, and a
 * forged request could only send our own inbox an enquiry. The real threat
 * is spam, which the same-origin check, the honeypot and the per-session
 * rate limit deal with.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/mail.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

/**
 * Send the JSON reply and stop.
 *
 * @param int   $status
 * @param array $body
 */
function zb_contact_reply($status, array $body)
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

// The page shows its own message with the phone number and email for
// these, so the wording here only matters to anything else that calls in.
$GENERIC_ERROR = 'We could not send your message right now. Please email support@zibomo.in directly.';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    zb_contact_reply(405, array('ok' => false, 'message' => 'Method not allowed.'));
}

if (!zb_same_origin()) {
    zb_log('Contact form: POST without a matching Origin/Referer rejected.');
    zb_contact_reply(403, array('ok' => false, 'message' => $GENERIC_ERROR));
}

if (!empty($_POST['website'])) {
    // Honeypot. A real visitor never sees this field.
    zb_log('Contact form: honeypot triggered — submission discarded.');
    zb_contact_reply(400, array('ok' => false, 'message' => $GENERIC_ERROR));
}

zb_session_start();

if (!zb_rate_limit_ok('zb_contact_hits')) {
    zb_contact_reply(429, array(
        'ok'      => false,
        'message' => 'You have sent several enquiries already. Please wait a little while '
                   . 'before trying again, or email support@zibomo.in directly.',
    ));
}

$values = array(
    'name'    => zb_clean(isset($_POST['name']) ? $_POST['name'] : '', 120),
    'company' => zb_clean(isset($_POST['company']) ? $_POST['company'] : '', 160),
    'phone'   => zb_clean(isset($_POST['phone']) ? $_POST['phone'] : '', 40),
    'email'   => zb_clean(isset($_POST['email']) ? $_POST['email'] : '', 190),
    'subject' => zb_clean(isset($_POST['subject']) ? $_POST['subject'] : '', 80),
    'message' => zb_clean(isset($_POST['message']) ? $_POST['message'] : '', 2000),
);

// Same rules as the checks in js/main.js, which only exist to save a round trip.
$errors = array();

if (mb_strlen($values['name']) < 2) {
    $errors['name'] = 'Please tell us your name.';
}

if ($values['company'] === '') {
    $errors['company'] = 'Please tell us your business or organization.';
}

if (!zb_valid_phone($values['phone'])) {
    $errors['phone'] = 'Please enter a valid phone number.';
}

if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}

if (!in_array($values['subject'], zb_requirements(), true)) {
    $errors['subject'] = 'Please choose a requirement.';
}

if (mb_strlen($values['message']) < 10) {
    $errors['message'] = 'Please add a short message.';
}

if ($errors) {
    zb_contact_reply(422, array(
        'ok'      => false,
        'message' => 'Please correct the highlighted fields and try again.',
        'errors'  => $errors,
    ));
}

$values['submitted_at'] = zb_submitted_at();

if (!zb_send_contact_email($values)) {
    zb_log('Contact enquiry email could not be sent.');
    zb_contact_reply(503, array('ok' => false, 'message' => $GENERIC_ERROR));
}

// Counted only on a send that was accepted, as on the brochure form.
zb_rate_limit_hit('zb_contact_hits');

zb_contact_reply(200, array('ok' => true));
