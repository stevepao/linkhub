# LinkHill (Shared‑Hosting Friendly Link‑in‑Bio)

Multi‑user, MySQL‑backed Linktree‑style app for shared LAMP hosting (e.g., IONOS). Public profile URLs use `/@username`. Admins manage users; users manage their own links with drag‑to‑reorder, color picker, and local SVG icon presets.

## Features

- Multi‑user with roles: `admin`, `user`
- Authentication: password + optional TOTP (Two‑Step Verification) and **passkeys** (WebAuthn)
- **Security** page: Password change, Two‑Step Verification (TOTP), Passkeys (register/rename/remove)
- **Password reset** via email (SMTP or dev log)
- Public pages at `/@username`
- Links: title, URL, color, icon preset, drag‑to‑reorder
- Minimal click analytics via redirect `/index.php?go=<id>`
- Local SVG icon set (customizable)
- Shared‑hosting safe: PDO MySQL, sessions, `.htaccess`

## Quickstart (IONOS or similar)

1. **Create MySQL DB** (host, dbname, user, pass) in your control panel.
2. **Set PHP 8.1+** for the site (8.2+ preferred; 8.1 minimum for Composer deps).
3. Edit `config/config.php` from `config/config.example` (DB credentials, optional SMTP and WebAuthn; keep `cookie_secure=true` on HTTPS).
4. **Import schema**: upload `sql/schema.sql` via phpMyAdmin or DB Manager.
5. **Run migration**: `php sql/migrate.php` (CLI) or use the web runner — see [Deploying on 1&1 IONOS](#deploying-on-11-ionos-shared-hosting) if you don’t have CLI.
6. **Composer**: run `composer install` in the project root (required for passkeys and email). Upload the entire project including the `vendor/` folder if you deploy via FTP/SFTP only.
7. **Upload files** to your web root (or a subfolder) via SFTP. Ensure `storage/sessions` and `storage/rate_limit` are writable.
8. Open `/admin_seed.php` once — it creates an initial admin. **Delete this file immediately.**
9. Visit `/admin/login.php`, sign in. Use **Security** (formerly MFA) for password change, Two‑Step Verification, and passkeys.
10. As admin, go to `/admin/users.php` to create more users. Public page is at `/@username`.

## Configuration (config.php)

Copy `config/config.example` to `config/config.php` and set:

- **db**: host, dbname, user, pass, charset (e.g. utf8mb4).
- **session_name**, **cookie_secure** (true on HTTPS), **cookie_samesite** (Lax), **password_cost**, **timezone**.
- **dev_mode** (bool): if `true`, password reset links are written to the server error log when SMTP is not configured; useful for local/dev.
- **smtp** (optional): host, port, secure (tls/ssl), user, pass, from, from_name. If not set, password reset emails are not sent (in dev_mode the link is logged).
- **webauthn** (for passkeys): **rp_id** (effective domain, e.g. `example.com` or `localhost` for dev), **rp_name** (site name shown in passkey UI), **origin** (e.g. `https://example.com` or `https://localhost:8443` for dev). If `origin` is empty, the app uses `base_url()`; if `rp_id` is empty, it is derived from the origin host.

### HTTPS and passkeys (RP_ID / ORIGIN)

- Passkeys require a **secure context** (HTTPS in production). For local dev you can use `https://localhost` (e.g. with mkcert or a self-signed cert).
- **rp_id** must match the effective domain the browser uses (e.g. `example.com` or `localhost`).
- **origin** must match the scheme + host + port the user sees (e.g. `https://example.com`). Mismatches cause WebAuthn to fail.

### Dev email behavior

- When **dev_mode** is true and SMTP is not configured (or send fails), password reset links are logged with `error_log()` so you can copy the link from the server log instead of receiving an email.

## Migration and rollback

- **Apply**: run `php sql/migrate.php` from the project root, or use the web runner (see [Deploying on 1&1 IONOS](#deploying-on-11-ionos-shared-hosting)). It adds columns to `users` (password_updated_at, webauthn_user_handle, last_login_at, user_session_version), ensures email is unique, creates `password_resets` and `webauthn_credentials` if missing, and backfills `webauthn_user_handle` for existing users. Safe to run multiple times.
- **Rollback**: not automatic. To undo schema changes you would need to drop added columns/tables and restore from backup. Back up the DB before migrating.

## Deploying on 1&1 IONOS shared hosting

The app is designed to run on typical IONOS shared hosting. Requirements and notes:

### Requirements

- **PHP 8.1 or 8.2** (set in IONOS control panel for the domain). PHP 8.1 is the minimum for Composer dependencies.
- **MySQL** (create DB and user in IONOS; use host, dbname, user, pass in `config/config.php`).
- **Writable directories**: `storage/sessions` and `storage/rate_limit` must be writable by the web server (e.g. chmod 755 via File Manager or SFTP).
- **HTTPS** recommended (IONOS usually offers free SSL). Required for passkeys; password/TOTP work over HTTP in dev only.

### What works without Composer on the server

- **Password + TOTP login**, Security page (Password and Two‑Step Verification tabs), password reset **request** page, and the rest of the app run without the `vendor/` folder.
- **Password reset emails** and **passkeys** require the Composer dependencies. If `vendor/` is missing:
  - Password reset: with `dev_mode` true, the reset link is written to the PHP error log instead of emailed; you can copy it from the log.
  - Passkeys: the “Sign in with a passkey” button and the Passkeys tab show a clear message that Composer dependencies are required (run `composer install` locally and upload `vendor/`).

### Running Composer when you only have FTP/SFTP

IONOS shared hosting often does **not** provide SSH, so you cannot run `composer install` on the server:

1. On your **local machine** (with PHP and Composer installed), run `composer install` in the project root.
2. Upload the **entire project** to IONOS via SFTP/FTP, **including the `vendor/` directory**. The first upload may take a few minutes because `vendor/` has many files. Subsequent uploads can skip unchanged files if your client supports it.

After that, passkeys and SMTP-based password reset will work.

### Running the migration without CLI

On IONOS you usually cannot run `php sql/migrate.php` from a shell:

1. In `config/config.php`, set **migration_key** to a long random secret (e.g. `bin2hex(random_bytes(32))`).
2. Upload **run_migration.php** (it is in the project root).
3. In a browser, open: `https://yoursite.com/run_migration.php?key=YOUR_MIGRATION_KEY` (use the same value as `migration_key`).
4. Check the output; then **delete run_migration.php** from the server for security.

### SMTP on IONOS

- Use IONOS’s SMTP relay (see their docs for host, port, and authentication) so password reset emails are sent from your domain.
- If outbound SMTP is restricted, keep **dev_mode** true and use the error log to get password reset links during setup or debugging.

### Passkeys on IONOS

- Enable **HTTPS** for the site (required for WebAuthn).
- In config, set **webauthn** `origin` to your full URL (e.g. `https://yourdomain.com`) and **rp_id** to your domain (e.g. `yourdomain.com`). If `origin` is empty, the app uses the auto-detected base URL.

### Summary of IONOS-friendly choices

- **PHP 8.1** supported (composer.json) so it runs where only 8.1 is available.
- **No SSH required**: migration via `run_migration.php?key=...`; dependencies by uploading `vendor/` from local.
- **Graceful degradation**: app works without `vendor/`; passkeys and email show clear messages when dependencies are missing.
- **File-based rate limiting** and **local session storage** avoid needing Redis or memcached.
- **PDO + prepared statements** work with IONOS MySQL.

## Troubleshooting

### 500 error when visiting /admin/login.php

A 500 usually means the server couldn’t start sessions or hit a PHP error. The app uses a **project-local session directory** (`storage/sessions/`) so the default system path doesn’t have to be writable.

1. **Make `storage/sessions` writable on the server**  
   After uploading, set permissions so the web server can write there, e.g. `chmod 755 storage` and `chmod 755 storage/sessions` (or 700 if your host allows). On IONOS you can do this in the File Manager or via SFTP.

2. **See the real PHP error**  
   In your **project root**, add or edit `.user.ini` and set:
   ```ini
   display_errors = 1
   log_errors = 1
   ```
   Reload `/admin/login.php`; the page may now show the error. Or check the **PHP error log** in your IONOS control panel (e.g. “Error log” or “Log files”) for the exact message.

3. **Force a custom session path (if needed)**  
   If the app can’t use `storage/sessions`, set it yourself. In `.user.ini` in the project root:
   ```ini
   session.save_path = "/path/to/your/writable/sessions"
   ```
   Use the full server path to a folder that exists and is writable by the web server.

### /@username returns 404 or “another page” (e.g. on 1&1/IONOS)

Pretty URLs like `https://link.hillwork.net/@spao` rely on Apache rewriting `/@spao` to `index.php?u=spao`. If you get a 404 or a different page:

1. **Use the fallback URL**  
   `https://yoursite.example.com/index.php?u=spao` works without rewrites. If that loads the profile, the app is fine and the problem is only rewrite config.

2. **Point the domain at the app folder**  
   For `link.example.com`, set the domain’s **document root** in the 1&1/IONOS control panel to the folder that contains `index.php` and `.htaccess` (e.g. `…/htdocs/link/`). If the docroot is a parent folder that doesn’t contain `.htaccess`, `/@spao` will never be rewritten.

3. **If the app is in a subdirectory**  
   If the app lives at `yoursite.example.com/link/`, open `.htaccess` and set:
   ```apache
   RewriteBase /link
   ```
   (no trailing slash). Then use `yoursite.example.com/link/@spao` or `yoursite.example.com/link/index.php?u=spao`.

4. **Allow .htaccess to run**  
   Rewrites only work if the server allows it. In 1&1/IONOS, “AllowOverride” for the site must allow `.htaccess` (often “All” or at least “FileInfo”). If you can’t change this, use `index.php?u=username` as the profile URL.

### Other issues

- **“Call to undefined function” or “Class not found”** — Confirm the site runs on **PHP 8.1 or higher** in the IONOS control panel.
- **Blank page or “headers already sent”** — Ensure no PHP file has output (spaces/BOM) before `<?php`, and that no file sends output before `header()` / redirects.

## Notes

- **Security headers** are set in `.htaccess`. If your host blocks headers, add them via PHP.
- **CSRF** is required on all POST and AJAX requests (including passkey and password-reset endpoints). Use `X-CSRF-Token` header for JSON requests.
- **Sessions**: SameSite=Lax, Secure, HttpOnly. Session ID is regenerated on login. Password change and reset invalidate other sessions via `user_session_version`.
- **Rate limiting**: Sensitive endpoints (login, password reset request/confirm, WebAuthn start/finish) are rate-limited (file-based in `storage/rate_limit/`).
- **Icons**: Replace SVGs in `/assets/icons/` with your preferred set.
- **JS**: SortableJS and QRCode are used in admin; WebAuthn is in `/assets/js/webauthn.js`. Passkey UI is hidden when `PublicKeyCredential` is not available.
- **Backups**: Back up DB regularly; consider a daily dump.
