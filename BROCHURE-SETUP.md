# Brochure & contact forms — setup & deployment

Gates the brochure PDF behind a lead form, and delivers the Contact Us form
on `index.html`. Both email `support@zibomo.in` with PHP's `mail()`, or
through a Gmail account when `mail()` cannot send. No database, nothing about
a visitor written to disk, and the PDF has no URL of its own.

> **These features need PHP hosting.** The site currently deploys to GitHub
> Pages, which serves `.php` files as plain text and never executes them.
> On Pages the Brochure button will show source code instead of a form, and
> the contact form tells visitors it could not send and offers the phone
> number and email instead.
> Everything below assumes Hostinger (or any Apache/LiteSpeed PHP host).

---

## 1. Files created

| File | Purpose |
| --- | --- |
| `brochure.php` | The request form, its server-side handler, and the success state. |
| `download-brochure.php` | The only route to the PDF. Checks the session permission (valid for 30 minutes), streams the file. |
| `contact.php` | Endpoint for the `index.html` contact form. Same-origin check, honeypot, rate limit, validation, email. Answers in JSON. |
| `includes/bootstrap.php` | Session start, config loading, CSRF, same-origin check, sanitisation, phone validation, rate limiting, download permission, logging. |
| `includes/mail.php` | Notification emails to support for both forms, and the brochure PDF emailed to the visitor. PHP `mail()`, falling back to Gmail SMTP (built in, no library) when `mail()` fails. HTML + plain-text bodies. |
| `includes/config.sample.php` | Configuration template. **Committed.** |
| `includes/config.php` | Real configuration (no password — that lives in `.env`). **Gitignored — upload manually.** |
| `.env.example` | Template for the mail settings. **Committed.** |
| `.env` | The mail settings, including the Gmail app password. **Gitignored — upload manually.** |
| `.htaccess` | Refuses every request for `.env` / `.env.example` (403). |
| `private/.htaccess` | Denies all HTTP access to the folder holding the PDF. |
| `private/zibomo-brochure.pdf` | The brochure, moved out of `assets/`. **Gitignored.** |
| `assets/.htaccess` | Defence in depth: blocks any `.pdf` under `assets/`. Images, video and CSS unaffected. |
| `.gitignore` | Keeps `includes/config.php` and `private/` out of the repository. |

## 2. Files modified

| File | Change |
| --- | --- |
| `index.html` | Brochure button `href="#contact"` → `href="brochure.php"`. Contact form gained `method="post" action="contact.php"` and a honeypot field. |
| `js/main.js` | Module 15 posts the contact form to `contact.php` and shows the real outcome. |
| `css/style.css` | Appended a `Brochure request page` block. The honeypot rule also covers the contact form's `.form-hp`, and the contact button has a sending state. |

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

### How a notification is sent

1. **PHP `mail()`** — no password, no token, the same as any plain PHP site.
   On a normal Linux host it hands the message to the server's own mail
   system.
2. **Gmail, only if `mail()` fails.** XAMPP on Windows has no mail server, so
   there `mail()` always fails and the Gmail account in `.env` sends instead,
   over SSL on port 465. It is built into `includes/mail.php`; nothing to
   install.

If either one accepts the message the visitor gets the brochure. If both
fail, the visitor sees an error and the download stays locked.

`mail()` returning `true` only means the server's mail system accepted the
message, not that it arrived. If the live site's emails never reach the
inbox (or land in spam), set `ZIBOMO_MAIL_TRANSPORT=smtp` in `.env` to skip
`mail()` and always send through Gmail.

### Configure the site: `.env`

The mail settings, including the password, live in `.env` at the site root.
Copy the template and fill it in **on the server**:

```bash
cp .env.example .env
```

```ini
ZIBOMO_MAIL_TRANSPORT=mail          # 'smtp' = always use Gmail
ZIBOMO_LEAD_RECIPIENT=support@zibomo.in
ZIBOMO_SMTP_HOST=smtp.gmail.com
ZIBOMO_SMTP_PORT=465
ZIBOMO_SMTP_ENCRYPTION=ssl          # 'ssl' for 465, 'tls' for 587
ZIBOMO_SMTP_USERNAME=zibomodigilocker@gmail.com
ZIBOMO_SMTP_PASSWORD=               # the Google App Password
```

The password is a **Google App Password** (Google Account → Security → 2-Step
Verification → App passwords), not the account's normal password. Google
shows it in groups of four; spaces are ignored.

Where a setting is given more than once, the first found wins: a real
environment variable (hPanel → Advanced → PHP Configuration → Environment
Variables), then `.env`, then `includes/config.php`, then the built-in
default. Other supported names: `ZIBOMO_MAIL_FROM`, `ZIBOMO_BROCHURE_PATH`,
`ZIBOMO_ERROR_LOG`.

**Keeping `.env` private**

- It is gitignored, so it is never committed and never published by the
  GitHub Pages workflow — which is also why **it will not exist after a
  fresh deploy**. Upload it once over FTP or the hPanel File Manager.
- The root `.htaccess` answers any request for it with 403. That is an
  Apache/LiteSpeed feature, so on an nginx host put `.env` **one level above
  the web root** instead (`/home/<user>/.env` next to `public_html/`); the
  site reads it from there too, and that copy wins.
- Verify after deploying — this must return 403 or 404:
  `https://your-domain/.env`

A failed Gmail sign-in logs Google's reply, e.g. `535 5.7.8 Username and
Password not accepted` — a wrong or revoked app password.

### The brochure emailed to the visitor

After the lead reaches support, the visitor is also sent an email with
`zibomo-brochure.pdf` attached, at the address they typed into the form.

- **From** "Zibomo Smart Lockers"; **Reply-To** `support@zibomo.in`, so
  their replies reach the team.
- **Best effort.** If it fails (a mistyped address, say), the failure is
  logged and nothing else changes — the visitor still has the download and
  support still has the lead.
- **Sent after the page.** With the 5 MB attachment the email is large (it
  took 53 seconds to upload to Gmail from the development machine), so the
  site finishes the response first and sends it afterwards. The visitor is
  on the success page within a couple of seconds either way.
- **No free text from the form reaches a stranger's inbox.** The email goes
  to whatever address was typed, so the greeting uses the name only if it
  looks like a name — otherwise it says "Hello,". The requirement comes from
  the fixed list.

Turn it off with `'email_brochure_to_visitor' => false` in
`includes/config.php`; the subject is `brochure_email_subject`.

### The From / Reply-To split

`Reply-To:` is always the visitor, so support hits Reply and writes straight
to the customer. `From:` is the site's own address — `no-reply@zibomo.in`
for `mail()`, and the Gmail account itself when Gmail sends (Gmail rewrites
any other From). Putting the visitor in `From:` is what triggers spam
filters.

---

## 5. How the download protection works

```
brochure.php  ──POST──▶  CSRF ✓  honeypot ✓  timing ✓  rate limit ✓
                              │
                         validation ✓
                              │
                     zb_send_lead_email()  ──▶  mail(), else Gmail
                         ╱          ╲
                    false            true
                      │                │
              show error         $_SESSION['brochure_access'] = time()
              NO permission             │
                                303 redirect → brochure.php?sent=1
                                          │    (then, after the response:
                                          │     brochure PDF emailed to the
                                          │     visitor — best effort)
                                          │
                          success panel starts the download itself
                            ([Download the brochure] as a fallback)
                                          │
                              download-brochure.php
                                          │
               permission under 30 min old? ── no ──▶ 303 → brochure.php
                                          │
                                         yes
                                          │
                                 stream the PDF in 8 KB chunks
```

Three properties worth stating plainly:

1. **The permission is granted in exactly one place** — immediately after
   `mail()` or Gmail accepts the message. If sending fails, the permission
   is never set and the visitor cannot download.
2. **It lasts 30 minutes, not one download.** The automatic download and
   the fallback button both have to work, and a cancelled transfer should
   be retryable. A single-use flag protected nothing: the permission lives
   in the visitor's session, so the link is useless to anyone else, and the
   visitor can share the PDF itself anyway. Change it with
   `download_window_minutes`.
3. **The PDF path never reaches the browser.** No frontend HTML references
   it, and the error page for a missing file shows no filesystem path.

### Automatic download

After a successful send the visitor lands on a success panel and the
download starts by itself about a second later. The response is an
attachment, so the browser saves it and stays on the panel. The **Download
the brochure** button remains for browsers that block automatic downloads.
It starts once per request; refreshing the panel does not download again.

Redirecting the form POST straight to the file would download it too, but
would leave the visitor on the form, spinner still turning, with no
confirmation that anything happened.

---

## 6. Spam protection (no database)

| Control | Behaviour |
| --- | --- |
| CSRF token | 32 random bytes in the session, compared with `hash_equals`, rotated after a successful send. |
| Honeypot | A text field named `zb_hp` that is in the HTML but never rendered (`hidden`), so bots that fill every field they find trip it and people never see it. Any value discards the submission. It used to be an off-screen field called `website`, which browser autofill filled for real visitors and got their enquiries discarded. |
| Timing check | Submissions under 3 seconds from page render are treated as automated. |
| Rate limit | 3 accepted submissions per session per hour, tracked as timestamps in the session. Each form has its own budget, so one never blocks the other. |
<<<<<<< HEAD
| Select allow-list | `requirement` must match the server's own list — an edited `<option>` is rejected. |
=======
| Select allow-list | A chosen `requirement` must match the server's own list — an edited `<option>` is rejected. |

**The contact form (`contact.php`)** uses the honeypot, rate limit and
allow-list above, but no CSRF token or timing check: `index.html` is static,
so there is nowhere to render a token or stamp a render time. Instead the
endpoint refuses any POST whose `Origin` (or, failing that, `Referer`) is not
this site — browsers always send one, simple bots usually do not. A forged
cross-site request could only send support an enquiry, so there is nothing
for a token to protect.
>>>>>>> 8b742b9 (completed)

**The contact form (`contact.php`)** uses the honeypot, rate limit and
allow-list above, but no CSRF token or timing check: `index.html` is static,
so there is nowhere to render a token or stamp a render time. Instead the
endpoint refuses any POST whose `Origin` (or, failing that, `Referer`) is not
this site — browsers always send one, simple bots usually do not. A forged
cross-site request could only send support an enquiry, so there is nothing
for a token to protect.

No CAPTCHA, since the site does not already use one.

---

## 7. Required and optional fields

On both forms only **Name** and **Phone Number** are required. Business,
Email, Requirement, Address and Message are optional — but what is given
must still be valid: an email must look like one, and a requirement must
come from the list.

Without an email address:

- the lead email to support has no Reply-To, and its footer says to call the
  customer on the phone number instead;
- no brochure copy is emailed, and the success page does not promise one.

---

## 8. Sanitisation and injection defence

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

## 9. Error handling

Every failure shows the visitor a plain message and writes the technical
detail to `private/zibomo-brochure.log` (configurable, outside the web root).
Nothing leaks a path, a credential or a PHP error.

A missing PDF returns HTTP 503 with a styled page offering the phone number
and support email — the lead is already captured at that point, so the
visitor should not be left stuck.

The contact form reads a JSON status. Field errors (422) are highlighted in
place. A mail failure (503), a host without PHP, or a timeout shows the phone
number and support email and keeps what the visitor typed — never a false
"sent".

---

## 10. Testing checklist

Verified locally against PHP 8.2 with a dev server. Re-run the starred items
on the live host after deploying.

**Form**
- [x] Brochure button opens the form page
- [x] Only name and phone required, on both forms, in the browser and on the server
- [x] Name + phone alone is accepted; the lead says to call, no copy is emailed
- [x] An optional email that is given must be valid (rejected otherwise)
- [x] Invalid phone rejected
- [x] CSRF: missing token rejected
- [x] CSRF: forged token rejected
- [x] Honeypot submission discarded
- [x] Sub-3-second submission treated as automated
- [x] Tampered `requirement` value rejected
- [x] Mobile layout at 390px

**Contact form**
- [x] Posts to `contact.php`; success message shown and form cleared
- [x] All fields re-validated server-side; errors highlighted in place
- [x] Honeypot submission discarded
- [x] POST from another origin, or with no Origin/Referer, rejected
- [x] Send failure or missing PHP → phone/email fallback, typed details kept
- [x] 4th accepted enquiry in an hour → 429; failed sends not counted

**Email**
- [x] `mail()` path (a host with a mail system): no password used, From
      `no-reply@zibomo.in`, To `support@zibomo.in`, Reply-To the visitor
- [x] XAMPP path: `mail()` fails, Gmail sends instead
- [x] Real Gmail sign-in over SSL works with the app password in `.env`
- [x] Real brochure request through XAMPP: Gmail accepted the lead for
      `support@zibomo.in`
- [x] Real visitor copy with the 5 MB PDF: Gmail accepted it
- [ ] ★ **Confirm both arrive — the lead in `support@zibomo.in`, the brochure
      in the visitor's inbox — and not in spam.** Do this first; everything
      else is worthless if the lead is not seen.
- [ ] ★ Customer details are all present and correctly formatted
- [ ] ★ Pressing Reply on a lead addresses the customer, not the website
- [x] Email failure prevents the download (verified: send failed → HTTP 303)
- [x] Visitor copy failing (rejected address) changes nothing else: 303,
      lead delivered, download works
- [x] A link typed as the "name" never reaches the visitor copy
- [x] Visitor is redirected before the large email finishes (Apache mod_php
      and PHP's dev server: ~2 s against an 8 s send)
- [ ] ★ `https://your-domain/.env` returns 403/404

**Download**
- [x] PDF does not download before submission
- [x] Direct access without permission → 303 to the form
- [x] Download starts automatically on the success panel, bytes identical to source
- [x] Filename is `zibomo-brochure.pdf`
- [x] Fallback button still works after the automatic download
- [x] Refreshing the success panel does not download again
- [x] Permission expires after 30 minutes
- [x] PDF path absent from frontend HTML
- [ ] ★ `https://your-domain/private/zibomo-brochure.pdf` returns 403/404

**Security**
- [x] No database
- [x] No visitor data persisted
- [x] No credentials in source: the password is only in `.env` (gitignored,
      403 over HTTP on XAMPP)
- [x] XSS: `<script>` echoed back escaped
- [x] CR/LF header injection stripped
- [x] Missing-file path leaks nothing

### Reproducing the local run

```bash
php -S localhost:8000
# then visit http://localhost:8000/brochure.php and http://localhost:8000/#contact
```

Under XAMPP the same pages are at `http://localhost/zibomo%20website/`.

On XAMPP `mail()` always fails, so every send goes through the Gmail
fallback. Blank the app password to see a failed send, which is a useful test
in itself: the error message appears and the download stays blocked.
