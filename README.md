# SignFlow — PHP Employee → Department Head Approval

A runnable starter project for **PHP 8.1+, MySQL 8+, Composer**. Includes secure PDF upload, employee signature canvas, department-head approval/rejection/return, role checks, approval history, email notifications via Gmail SMTP, cron reminders and a signed PDF download containing the original document plus its approval page.

## Quick start in VS Code

1. Install **PHP 8.1+**, **MySQL**, and **Composer** (XAMPP provides PHP/MySQL). Open this folder in VS Code.
2. Start MySQL and import `sql/schema.sql` using phpMyAdmin Import or `mysql -u root -p < sql/schema.sql`.
3. Copy `.env.example` to `.env`; update database settings. On XAMPP root often has an empty password.
4. In VS Code terminal run `composer install`.
5. Start the PHP development server from the project root with `powershell -ExecutionPolicy Bypass -File .\start-server.ps1`. This uses writable project-local upload and session temporary directories.
6. Visit `http://localhost:8000/?page=setup` to create the first admin.
7. Sign in as admin and create a **department head** and **employee** with the *exact same department name*.
8. Log in as employee, create a draft, draw your signature and submit. Log in as head, review and sign. Download approved PDF.

## Gmail notifications (optional)

Configure `MAIL_USER` (Gmail address), `MAIL_PASSWORD` (Google app password, if available on your account), `MAIL_FROM` (same verified Gmail address) and `APP_URL` in `.env`. PHPMailer sends SMTP over TLS. Without mail configuration, in-app notifications and workflow still function; email logs show `disabled`. SMTP acceptance does **not** guarantee inbox delivery.

For external links in Gmail, `APP_URL` must be a publicly accessible HTTPS URL; `localhost` works only on the same computer. Use secure hosting and HTTPS for real deployment.

## Reminder schedule

Run `php cron/reminders.php` every hour using Windows Task Scheduler or Linux cron. The script reminds heads about pending documents after `REMINDER_HOURS` and avoids sending more than once within that interval. It runs even when no user is logged in. Email outcomes are recorded in `email_logs`.

## Notes / security

- Demo starter, not a production-certified e-signature product. The PDF embeds handwritten electronic signatures; **it does not cryptographically sign the PDF**.
- User passwords are hashed, SQL uses prepared statements, forms use CSRF tokens, signing is authenticated and role-scoped, and signatures are served through access-controlled endpoints.
- Keep `.env`, `storage`, `app`, `sql`, and `vendor` outside the public web root; point your web server document root at `public`.
- Before real organizational deployment: enable HTTPS, secure sessions/cookies, add rate limiting/login lockout, password reset, detailed audit metadata, backup/retention policies, queue-based email delivery, and a formal signature/legal policy.
- Uploaded PDFs are limited to 10 MB and stored outside the public web root. Return-for-correction creates a new signed version when edited or replaced.
- Gmail SMTP is subject to Google account policies and sending limits. Do not commit `.env` to source control.
