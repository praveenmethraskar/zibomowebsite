<?php
/**
 * Zibomo — brochure request form.
 *
 * Captures a lead, emails it to support, and only then grants the session
 * permission that download-brochure.php requires. Nothing is written to a
 * database and no visitor data is persisted on disk.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/mail.php';

zb_session_start();

// A cached copy of this page would hand one visitor's CSRF token to the next.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

$config = zb_config();

$REQUIREMENTS = zb_requirements();

$values = array(
    'name' => '', 'business' => '', 'phone' => '', 'email' => '',
    'address' => '', 'requirement' => '', 'message' => '',
);
$errors      = array();
$formError   = '';
$showSuccess = false;
$autoStart   = false;

/* Success state is reached by redirect after a successful send, so a
   refresh cannot resubmit the form. */
if (isset($_GET['sent']) && zb_brochure_access_ok()) {
    $showSuccess = true;

    // Start the download on the first view only, not on every refresh.
    $autoStart = !empty($_SESSION['brochure_autostart']);
    unset($_SESSION['brochure_autostart']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$showSuccess) {

    $submittedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null;

    if (!zb_csrf_validate($submittedToken)) {
        $formError = 'Your session expired before the form was submitted. Please try again.';
        zb_log('CSRF validation failed.');

    } elseif (!empty($_POST['website'])) {
        // Honeypot. A real visitor never sees this field.
        zb_log('Honeypot triggered — submission discarded.');
        $formError = 'We could not process your request right now. Please try again.';

    } elseif (!zb_rate_limit_ok()) {
        $formError = 'You have submitted this form several times already. '
                   . 'Please wait a little while before trying again, or email support@zibomo.in directly.';

    } else {

        $renderedAt = isset($_SESSION['zb_form_time']) ? (int) $_SESSION['zb_form_time'] : 0;
        $minSeconds = (int) $config['min_seconds_on_form'];

        if ($renderedAt > 0 && (time() - $renderedAt) < $minSeconds) {
            // Submitted faster than a person can type: almost certainly a bot.
            zb_log('Form submitted in under ' . $minSeconds . 's — treated as automated.');
            $formError = 'We could not process your request right now. Please try again.';
        } else {

            $values['name']        = zb_clean(isset($_POST['name']) ? $_POST['name'] : '', 120);
            $values['business']    = zb_clean(isset($_POST['business']) ? $_POST['business'] : '', 160);
            $values['phone']       = zb_clean(isset($_POST['phone']) ? $_POST['phone'] : '', 40);
            $values['email']       = zb_clean(isset($_POST['email']) ? $_POST['email'] : '', 190);
            $values['address']     = zb_clean(isset($_POST['address']) ? $_POST['address'] : '', 400);
            $values['requirement'] = zb_clean(isset($_POST['requirement']) ? $_POST['requirement'] : '', 80);
            $values['message']     = zb_clean(isset($_POST['message']) ? $_POST['message'] : '', 2000);

            if ($values['name'] === '') {
                $errors['name'] = 'Please tell us your name.';
            } elseif (mb_strlen($values['name']) < 2) {
                $errors['name'] = 'Please enter your full name.';
            }

            if ($values['business'] === '') {
                $errors['business'] = 'Please enter your business or organization name.';
            }

            if ($values['phone'] === '') {
                $errors['phone'] = 'Please enter a phone number.';
            } elseif (!zb_valid_phone($values['phone'])) {
                $errors['phone'] = 'Please enter a valid phone number, for example +91 91212 08058.';
            }

            if ($values['email'] === '') {
                $errors['email'] = 'Please enter an email address.';
            } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'That email address does not look right.';
            }

            if ($values['requirement'] === '') {
                $errors['requirement'] = 'Please choose a requirement.';
            } elseif (!in_array($values['requirement'], $REQUIREMENTS, true)) {
                // Someone edited the select. Do not pass an arbitrary value on.
                $errors['requirement'] = 'Please choose a requirement from the list.';
            }

            if (!$errors) {
                $lead = $values;
                $lead['submitted_at'] = zb_submitted_at();

                if (zb_send_lead_email($lead)) {
                    // Counted only on a send that was actually accepted. A
                    // failed send must not burn the visitor's retry budget —
                    // that is precisely the first-deploy misconfiguration case.
                    zb_rate_limit_hit();

                    // The permission is granted here and nowhere else.
                    zb_brochure_grant();
                    zb_csrf_rotate();
                    unset($_SESSION['zb_form_time']);

                    header('Location: brochure.php?sent=1', true, 303);

                    // The lead is safe; now email the visitor their copy. It
                    // is best effort — a failure never costs them the
                    // download. The PDF makes it a big email (a minute on a
                    // slow uplink), so the response is finished first: the
                    // visitor is on the success page while it goes out.
                    if (!empty($config['email_brochure_to_visitor'])) {
                        session_write_close();
                        ignore_user_abort(true);
                        @set_time_limit(300);
                        zb_finish_response();
                        if (!zb_send_brochure_to_visitor($lead)) {
                            zb_log('The brochure could not be emailed to the visitor; their download is unaffected.');
                        }
                    }
                    exit;
                }

                zb_log('Lead email could not be sent; download permission NOT granted.');
                $formError = 'We could not process your request right now. '
                           . 'Please try again in a moment, or email support@zibomo.in directly.';
            } else {
                $formError = 'Please correct the highlighted fields and try again.';
            }
        }
    }
}

// Stamp the render time for the next submission's timing check.
$_SESSION['zb_form_time'] = time();
$csrfToken = zb_csrf_token();

$err = function ($field) use ($errors) {
    return isset($errors[$field]) ? $errors[$field] : '';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Download Zibomo Brochure | Smart Locker Solutions</title>
  <meta name="description" content="Request the Zibomo smart locker brochure. Share a few details and we will send the latest product and configuration guide.">
  <meta name="robots" content="noindex, follow">
  <meta name="theme-color" content="#0A1638">
  <link rel="shortcut icon" type="image/x-icon" href="assets/images/brand-favicon.ico">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="brochure-page">

<header class="brochure-bar">
  <div class="container brochure-bar__inner">
    <a href="index.html" class="brochure-bar__brand" aria-label="Zibomo home">
      <img src="assets/images/brand-logo.png" alt="Zibomo Smart Lockers" width="140" height="42">
    </a>
    <a href="index.html" class="brochure-bar__back"><i class="bi bi-arrow-left-short"></i> Back to site</a>
  </div>
</header>

<main class="brochure-main">
  <div class="container">
    <div class="row g-5 tw-justify-center">

      <!-- ---------------- Left: what they are getting ---------------- -->
      <div class="col-lg-5">
        <div class="brochure-intro">
          <span class="sec-kicker">Brochure</span>
          <h1 class="brochure-intro__title">Download Zibomo Brochure</h1>
          <p class="brochure-intro__lead">Please provide your details below to receive our latest Zibomo Smart Locker brochure.</p>

          <ul class="brochure-points">
            <li><i class="bi bi-hdd-stack"></i><span><strong>Hardware configurations</strong>Compartment sizes, terminal architecture and enclosure options.</span></li>
            <li><i class="bi bi-window-stack"></i><span><strong>Platform capabilities</strong>Authentication, monitoring, transactions and reporting.</span></li>
            <li><i class="bi bi-diagram-3"></i><span><strong>Deployment scenarios</strong>Gated communities, corporate, retail, temples and transport.</span></li>
          </ul>

          <div class="brochure-contact">
            <p>Prefer to talk first?</p>
            <a href="tel:+919154324445"><i class="bi bi-telephone"></i> +91 9154324445</a>
            <a href="mailto:support@zibomo.in"><i class="bi bi-envelope"></i> support@zibomo.in</a>
          </div>
        </div>
      </div>

      <!-- ---------------- Right: form or success ---------------- -->
      <div class="col-lg-6">
        <div class="brochure-card">

        <?php if ($showSuccess): ?>

          <div class="brochure-success" role="status">
            <span class="brochure-success__icon"><i class="bi bi-check-lg"></i></span>
            <h2>Thank you — your details are with us.</h2>
            <?php if ($autoStart): ?>
              <p>Your brochure download will start automatically. If it does not begin within a few seconds, press the button below.</p>
            <?php else: ?>
              <p>Your brochure is ready. Press the button below to download it.</p>
            <?php endif; ?>
            <?php if (!empty($config['email_brochure_to_visitor'])): ?>
              <p>We are also emailing a copy to the address you entered.</p>
            <?php endif; ?>
            <a href="download-brochure.php" class="btn-zb btn-zb--solid btn-zb--lg brochure-success__btn"
               id="brochureDownload"<?php echo $autoStart ? ' data-autostart' : ''; ?>>
              <i class="bi bi-download"></i> Download the brochure
            </a>
            <p class="brochure-success__note">
              The download link stays active for <?php echo (int) $config['download_window_minutes']; ?> minutes.
              Trouble downloading? Email <a href="mailto:support@zibomo.in">support@zibomo.in</a> and we will send it over.
            </p>
          </div>

        <?php else: ?>

          <h2 class="brochure-card__title">Your details</h2>
          <p class="brochure-card__hint"><span class="req">*</span> Required fields</p>

          <?php if ($formError !== ''): ?>
            <div class="brochure-alert brochure-alert--error" role="alert">
              <i class="bi bi-exclamation-triangle"></i>
              <span><?php echo zb_e($formError); ?></span>
            </div>
          <?php endif; ?>

          <form method="post" action="brochure.php" class="brochure-form" id="brochureForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo zb_e($csrfToken); ?>">

            <!-- Honeypot: hidden from people, filled in by bots. -->
            <div class="brochure-hp" aria-hidden="true">
              <label for="website">Website</label>
              <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="row g-3">

              <div class="col-md-6">
                <label for="name" class="form-label">Name <span class="req">*</span></label>
                <input type="text" class="form-control<?php echo $err('name') ? ' is-invalid' : ''; ?>"
                       id="name" name="name" value="<?php echo zb_e($values['name']); ?>"
                       autocomplete="name" required
                       <?php echo $err('name') ? 'aria-invalid="true" aria-describedby="err-name"' : ''; ?>>
                <?php if ($err('name')): ?><div class="invalid-feedback d-block" id="err-name"><?php echo zb_e($err('name')); ?></div><?php endif; ?>
              </div>

              <div class="col-md-6">
                <label for="business" class="form-label">Business / Company Name <span class="req">*</span></label>
                <input type="text" class="form-control<?php echo $err('business') ? ' is-invalid' : ''; ?>"
                       id="business" name="business" value="<?php echo zb_e($values['business']); ?>"
                       autocomplete="organization" required
                       <?php echo $err('business') ? 'aria-invalid="true" aria-describedby="err-business"' : ''; ?>>
                <?php if ($err('business')): ?><div class="invalid-feedback d-block" id="err-business"><?php echo zb_e($err('business')); ?></div><?php endif; ?>
              </div>

              <div class="col-md-6">
                <label for="phone" class="form-label">Phone Number <span class="req">*</span></label>
                <input type="tel" class="form-control<?php echo $err('phone') ? ' is-invalid' : ''; ?>"
                       id="phone" name="phone" value="<?php echo zb_e($values['phone']); ?>"
                       placeholder="+91 91212 08058" autocomplete="tel" required
                       <?php echo $err('phone') ? 'aria-invalid="true" aria-describedby="err-phone"' : ''; ?>>
                <?php if ($err('phone')): ?><div class="invalid-feedback d-block" id="err-phone"><?php echo zb_e($err('phone')); ?></div><?php endif; ?>
              </div>

              <div class="col-md-6">
                <label for="email" class="form-label">Email <span class="req">*</span></label>
                <input type="email" class="form-control<?php echo $err('email') ? ' is-invalid' : ''; ?>"
                       id="email" name="email" value="<?php echo zb_e($values['email']); ?>"
                       autocomplete="email" required
                       <?php echo $err('email') ? 'aria-invalid="true" aria-describedby="err-email"' : ''; ?>>
                <?php if ($err('email')): ?><div class="invalid-feedback d-block" id="err-email"><?php echo zb_e($err('email')); ?></div><?php endif; ?>
              </div>

              <div class="col-12">
                <label for="requirement" class="form-label">Requirement <span class="req">*</span></label>
                <select class="form-select<?php echo $err('requirement') ? ' is-invalid' : ''; ?>"
                        id="requirement" name="requirement" required
                        <?php echo $err('requirement') ? 'aria-invalid="true" aria-describedby="err-requirement"' : ''; ?>>
                  <option value="">Choose a requirement…</option>
                  <?php foreach ($REQUIREMENTS as $option): ?>
                    <option value="<?php echo zb_e($option); ?>"<?php echo $values['requirement'] === $option ? ' selected' : ''; ?>><?php echo zb_e($option); ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if ($err('requirement')): ?><div class="invalid-feedback d-block" id="err-requirement"><?php echo zb_e($err('requirement')); ?></div><?php endif; ?>
              </div>

              <div class="col-12">
                <label for="address" class="form-label">Address <span class="opt">(optional)</span></label>
                <input type="text" class="form-control" id="address" name="address"
                       value="<?php echo zb_e($values['address']); ?>"
                       placeholder="City, state" autocomplete="street-address">
              </div>

              <div class="col-12">
                <label for="message" class="form-label">Message <span class="opt">(optional)</span></label>
                <textarea class="form-control" id="message" name="message" rows="4"
                          placeholder="Tell us about your site, expected users or storage requirement."><?php echo zb_e($values['message']); ?></textarea>
              </div>

              <div class="col-12">
                <button type="submit" class="btn-zb btn-zb--solid btn-zb--lg brochure-submit" id="brochureSubmit">
                  <span class="brochure-submit__label">Submit &amp; Download Brochure</span>
                  <span class="brochure-submit__spinner" aria-hidden="true"></span>
                </button>
                <p class="brochure-privacy">
                  Your details are emailed to our team so we can follow up. We do not store them in a database.
                </p>
              </div>

            </div>
          </form>

        <?php endif; ?>

        </div>
      </div>

    </div>
  </div>
</main>

<footer class="brochure-foot">
  <div class="container">
    <p>&copy; <?php echo date('Y'); ?> Zibomo Pvt. Ltd. &middot; 5-A/2, IDA, Nacharam, Hyderabad, Telangana 500076</p>
  </div>
</footer>

<script>
/* Success page: start the download without a second click. The response is
   an attachment, so the browser saves the file and stays on this page. */
(function () {
  var link = document.getElementById('brochureDownload');
  if (!link || !link.hasAttribute('data-autostart')) { return; }
  setTimeout(function () { window.location.href = link.href; }, 900);
}());

/* Loading state. Native validation runs first so the button is never
   disabled on a form the browser is about to reject. */
(function () {
  var form = document.getElementById('brochureForm');
  if (!form) { return; }
  var btn = document.getElementById('brochureSubmit');

  form.addEventListener('submit', function (e) {
    if (!form.checkValidity()) {
      e.preventDefault();
      form.classList.add('was-validated');
      var firstBad = form.querySelector(':invalid');
      if (firstBad) { firstBad.focus(); }
      return;
    }
    btn.disabled = true;
    btn.classList.add('is-loading');
    form.querySelector('.brochure-submit__label').textContent = 'Sending your request…';
  });

  /* If the visitor comes back with the back button, re-enable the button. */
  window.addEventListener('pageshow', function () {
    btn.disabled = false;
    btn.classList.remove('is-loading');
    var label = form.querySelector('.brochure-submit__label');
    if (label) { label.textContent = 'Submit & Download Brochure'; }
  });
}());
</script>
</body>
</html>
