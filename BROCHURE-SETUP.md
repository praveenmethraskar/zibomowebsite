# Brochure lead capture — setup & deployment

Gates the brochure PDF behind a lead form. No database, nothing about a
visitor written to disk, and the PDF has no URL of its own.

> **This feature needs PHP hosting.** The site currently deploys to GitHub
> Pages, which serves `.php` files as plain text and never executes them.
> On Pages the Brochure button will show source code instead of a form.
> Everything below assumes Hostinger (or any Apache/LiteSpeed PHP host).

---

## 1. Files created

| File | Purpose |
| --- | --- |
| `brochure.php` | The request form, its server-side handler, and the success state. |
| `download-brochure.php` | The only route to the PDF. Checks the session permission, streams the file, consumes the permission. |
| `includes/bootstrap.php` | Session start, config loading, CSRF, sanitisation, phone validation, rate limiting, logging. |
| `includes/mail.php` | Lead notification. PHPMailer/SMTP when available, `mail()` as a fallback. HTML + plain-text bodies. |
| `includes/config.sample.php` | Configuration template. **Committed.** |
| `includes/config.php` | Real configuration including credentials. **Gitignored — upload manually.** |
| `private/.htaccess` | Denies all HTTP access to the folder holding the PDF. |
| `private/zibomo-brochure.pdf` | The brochure, moved out of `assets/`. **Gitignored.** |
| `assets/.htaccess` | Defence in depth: blocks any `.pdf` under `assets/`. Images, video and CSS unaffected. |
| `.gitignore` | Keeps `includes/config.php` and `private/` out of the repository. |

## 2. Files modified

| File | Change |
| --- | --- |
| `index.html` | Brochure button `href="#contact"` → `href="brochure.php"`. One line. |
| `css/style.css` | Appended a `Brochure request page` block. Nothing existing was changed. |

The Brochure button previously jumped to the contact section. "Contact Us"
is still in the nav separately, so nothing was lost by repointing it.

---

## 3. Where the PDF goes

`includes/config.php` takes an **ordered list** of candidate paths and uses
the first readable one:

```php
'brochure_path' => array(
    dirname(__DIR__, 2) . '/private/zibomo-brochure.pdf',  // [0] above the web root
    dirname(__DIR__)    . '/private/zibomo-brochure.pdf',  // [1] in-project fallback
),
```

**On Hostinger, use [0].** With the site in `public_html/`, that resolves to
`/home/<your-user>/private/zibomo-brochure.pdf` — one level *above*
`public_html`, so no URL can reach it on any web server, whatever the
`.htaccess` support.

```
/home/u123456/
├── private/
│   └── zibomo-brochure.pdf      ← upload here
└── public_html/
    ├── index.html
    ├── brochure.php
    ├── download-brochure.php
    ├── includes/
    └── assets/
```

Entry [1] (`public_html/private/`) is the local-development fallback and is
protected only by `private/.htaccess`. **That is an Apache/LiteSpeed feature
and is silently ignored by nginx.** Hostinger runs LiteSpeed so it works
there, but [0] is strictly better and costs nothing.

Verify after deploying — this must 404 or 403:

```
https://your-domain/private/zibomo-brochure.pdf
```

---

## 4. Mail configuration

### Why SMTP, not `mail()`

`mail()` returning `true` means the local mail server accepted the message,
not that anyone received it. Sending as `@zibomo.in` from a shared host that
zibomo.in's SPF/DKIM records do not authorise gets the message junked.

The failure mode matters: **the visitor still gets their PDF, and the lead
lands in a spam folder nobody checks.** The gate protects the visitor, not
the business. Use SMTP.

### Install PHPMailer (no Composer needed)

Download the PHPMailer release, and copy three files from its `src/`:

```
includes/PHPMailer/
├── PHPMailer.php
├── SMTP.php
└── Exception.php
```

`includes/mail.php` detects this automatically. It also picks up
`vendor/autoload.php` if you prefer Composer.

### Configure credentials

Copy the template and fill it in **on the server**:

```bash
cp includes/config.sample.php includes/config.php
```

```php
'mail_transport' => 'smtp',
'smtp' => array(
    'host'       => 'smtp.hostinger.com',
    'port'       => 465,
    'encryption' => 'ssl',            // 'ssl' for 465, 'tls' for 587
    'username'   => 'support@zibomo.in',
    'password'   => '',               // leave blank — set the env var below
),
```

**Never commit a password.** `includes/config.php` is gitignored. Prefer an
environment variable, which overrides the file:

```
ZIBOMO_SMTP_PASSWORD=your-mailbox-password
```

On Hostinger: hPanel → Advanced → PHP Configuration → Environment Variables.
Other supported vars: `ZIBOMO_SMTP_HOST`, `ZIBOMO_SMTP_PORT`,
`ZIBOMO_SMTP_USERNAME`, `ZIBOMO_SMTP_ENCRYPTION`, `ZIBOMO_MAIL_TRANSPORT`,
`ZIBOMO_LEAD_RECIPIENT`, `ZIBOMO_MAIL_FROM`, `ZIBOMO_BROCHURE_PATH`,
`ZIBOMO_ERROR_LOG`.

> `includes/config.php` is gitignored, so **it will not exist after a fresh
> deploy**. Upload it once over FTP or the hPanel File Manager. If it is
> missing, defaults apply and mail will fail — check the log.

### The From / Reply-To split

`From:` is `no-reply@zibomo.in` (a domain you control, so SPF passes) and
`Reply-To:` is the visitor. Support hits Reply and writes straight to the
customer. Putting the visitor in `From:` is what triggers spam filters.

---

## 5. How the download protection works

```
brochure.php  ──POST──▶  CSRF ✓  honeypot ✓  timing ✓  rate limit ✓
                              │
                         validation ✓
                              │
                     zb_send_lead_email()
                         ╱          ╲
                    false            true
                      │                │
              show error         $_SESSION['brochure_access'] = true
              NO permission             │
                                303 redirect → brochure.php?sent=1
                                          │
                                 [Download the brochure]
                                          │
                              download-brochure.php
                                          │
                          permission? ── no ──▶ 303 → brochure.php
                                          │
                                         yes
                                          │
                                 stream the PDF in 8 KB chunks
                                          │
                              unset($_SESSION['brochure_access'])
```

Three properties worth stating plainly:

1. **The permission is granted in exactly one place** — immediately after
   the mail transport confirms it accepted the message. If sending fails,
   the flag is never set and the visitor cannot download.
2. **It is consumed only after a complete stream.** If the transfer is
   aborted the script dies before the `unset`, so the permission survives
   and the visitor can retry. That is deliberate, not an oversight.
3. **The PDF path never reaches the browser.** No frontend HTML references
   it, and the error page for a missing file shows no filesystem path.

### One deviation from the brief

The brief said redirect straight to `download-brochure.php` after a
successful send. A 302 to an attachment endpoint downloads the file but
leaves the visitor sitting on the form with no confirmation that anything
happened. Instead the redirect goes to a success panel with an explicit
**Download the brochure** button. Same gate, same single-use flag, clearer
outcome.

---

## 6. Spam protection (no database)

| Control | Behaviour |
| --- | --- |
| CSRF token | 32 random bytes in the session, compared with `hash_equals`, rotated after a successful send. |
| Honeypot | A field named `website`, positioned off-screen with CSS rather than `type="hidden"` so bots still fill it. Any value discards the submission. |
| Timing check | Submissions under 3 seconds from page render are treated as automated. |
| Rate limit | 3 submissions per session per hour, tracked as timestamps in the session. |
| Select allow-list | `requirement` must match the server's own list — an edited `<option>` is rejected. |

No CAPTCHA, since the site does not already use one.

---

## 7. Sanitisation and injection defence

- `zb_clean()` forces a string, rejects invalid UTF-8, strips C0/C1 control
  characters (including the CR/LF used for header injection), trims, and
  caps length per field.
- `zb_header_safe()` additionally collapses every whitespace run to a single
  space for anything bound for a mail header. Subject, From and Reply-To all
  pass through it.
- `zb_e()` (`htmlspecialchars` with `ENT_QUOTES | ENT_SUBSTITUTE`) escapes
  every value on redisplay **and** in the HTML email, so a lead cannot inject
  markup into the message support opens.
- Email is validated with `filter_var($email, FILTER_VALIDATE_EMAIL)`.
- Phone accepts 8–15 digits in the formats Indian visitors actually type
  (`+91 98765 43210`, `098765-43210`, `9876543210`) and rejects repeated-digit
  filler like `0000000000`.

---

## 8. Error handling

Every failure shows the visitor a plain message and writes the technical
detail to `private/zibomo-brochure.log` (configurable, outside the web root).
Nothing leaks a path, a credential or a PHP error.

A missing PDF returns HTTP 503 with a styled page offering the phone number
and support email — the lead is already captured at that point, so the
visitor should not be left stuck.

---

## 9. Testing checklist

Verified locally against PHP 8.2 with a dev server. Re-run the starred items
on the live host after deploying.

**Form**
- [x] Brochure button opens the form page
- [x] All required fields validated server-side
- [x] Invalid email rejected
- [x] Invalid phone rejected
- [x] CSRF: missing token rejected
- [x] CSRF: forged token rejected
- [x] Honeypot submission discarded
- [x] Sub-3-second submission treated as automated
- [x] Tampered `requirement` value rejected
- [x] Mobile layout at 390px

**Email**
- [ ] ★ **Send one real submission and confirm it arrives in the
      `support@zibomo.in` inbox — not the spam folder.** Do this first;
      everything else is worthless if the lead is not seen.
- [ ] ★ Customer details are all present and correctly formatted
- [ ] ★ Pressing Reply addresses the customer, not the website
- [x] Email failure prevents the download (verified: send failed → HTTP 303)

**Download**
- [x] PDF does not download before submission
- [x] Direct access without permission → 303 to the form
- [x] PDF streams after permission is granted, bytes identical to source
- [x] Filename is `zibomo-brochure.pdf`
- [x] Second attempt blocked — permission consumed
- [x] PDF path absent from frontend HTML
- [ ] ★ `https://your-domain/private/zibomo-brochure.pdf` returns 403/404

**Security**
- [x] No database
- [x] No visitor data persisted
- [x] No credentials in source (gitignored config + env vars)
- [x] XSS: `<script>` echoed back escaped
- [x] CR/LF header injection stripped
- [x] Missing-file path leaks nothing

### Reproducing the local run

```bash
php -S localhost:8000
# then visit http://localhost:8000/brochure.php
```

Without SMTP configured the send fails, which is a useful test in itself:
the error message appears and the download stays blocked.
