# LinkHill

**v0.1** — A free, open link-in-bio app for shared LAMP hosting (e.g. IONOS). Create a single page that houses all your links—social profiles, store, newsletter—with one URL. Multi-user, MySQL-backed; public profile URLs use `/@username`. No paywalls; you own your data.

- **Project:** linkhill  
- **License:** MIT  
- **Copyright (c) 2026 Hillwork, LLC**

---

## What LinkHill does

LinkHill is a **Linktree alternative** that you host yourself. It gives you:

- **One URL** (e.g. `yoursite.com/@yourname`) that shows your name, bio, avatar, and a list of links.
- **Links** that can be buttons (title + URL), or cards with a short description. You can add **section headings** to group links (e.g. “Social”, “Shop”).
- **Customization**: button color, icon (GitHub, Instagram, etc.), light/dark theme, optional custom footer on your page.
- **Multiple users**: an admin creates accounts; each user has their own profile and links. Admins can manage users (create, reset password, delete).

Visitors click a link on your page and are redirected through your site (optional minimal click analytics). No vendor lock-in; data stays in your database.

---

## Features

- **Multi-user** with roles: **admin**, **user**
- **Auth:** password + optional TOTP (authenticator app) and **passkeys** (WebAuthn)
- **Security** page: change password, enable/disable TOTP, register/rename/remove passkeys
- **Password reset** via email (SMTP) or, in dev mode, via link in the error log
- **Public pages** at **`/@username`** (e.g. `/@jane`)
- **Links:** title, URL, optional description (card blurb), color, icon, drag-to-reorder
- **Section headings** on your link page (e.g. “Social links”, “My shop”)
- **Profile:** display name, username, bio, avatar (JPG/PNG), theme (light/dark), custom footer
- **Minimal click analytics** via redirect `?go=<id>` (hashed IP/UA for aggregates)
- **Sitemap** at `/sitemap.xml` (static pages + profile pages that have links); updated when links or users change
- **SEO:** meta and Open Graph on homepage, About/Contact/Privacy/Terms, and profile pages
- **Local SVG icons** (GitHub, LinkedIn, Instagram, Bluesky, Substack, etc.) in `assets/icons/`
- **Shared-hosting friendly:** PDO MySQL, file-based sessions and rate limiting, `.htaccess` rewrites

---

## Requirements

- **PHP 8.1+** (8.2+ preferred; Composer dependencies require 8.1)
- **MySQL** (create database and user in your control panel)
- **Writable directories:** `storage/sessions`, `storage/rate_limit`, `storage/` (for generated `sites.xml`)
- **HTTPS** recommended (required for passkeys in production)

---

## Installation (new install, v0.1)

This is for a **fresh install** with no existing users. You only need the schema; no migration scripts.

1. **Create a MySQL database** and user (host, dbname, user, pass) in your hosting control panel.

2. **Copy config**
   - Copy `config/config.example` to `config/config.php`.
   - Edit `config/config.php`: set **db** (host, dbname, user, pass).
   - Keep `cookie_secure => true` on HTTPS. Optionally set **smtp** and **webauthn** (see [Configuration](#configuration)).

3. **Import the schema**
   - In phpMyAdmin or your DB manager, import **`sql/schema.sql`** into your database.
   - This creates all tables (users, links, link_clicks, password_resets, webauthn_credentials, email_verifications). No other SQL scripts are needed for a new install.

4. **Composer (for passkeys and email)**
   - On your machine (with PHP and Composer), run: `composer install`
   - Upload the **entire project** to the server (e.g. via SFTP), **including the `vendor/` folder**.
   - If you don’t upload `vendor/`, the app still runs (password + TOTP, links, profiles); passkeys and SMTP-based password reset will report that Composer dependencies are required.

5. **Upload the app**
   - Upload all files to your web root (or the folder that will be the document root for your domain).
   - Ensure **`storage/sessions`**, **`storage/rate_limit`**, and **`storage/`** are writable (e.g. `chmod 755`).

6. **Create the first admin**
   - In a browser, open **`/admin_seed.php`** once.
   - It creates an initial admin user and shows the temporary credentials on the page.
   - Sign in at **`/login`** (or `/admin/login.php`), then go to **Security** and change the password.
   - **Delete `admin_seed.php`** from the server after use.

7. **Use the app**
   - **Login:** `/login` or `/admin/login.php`
   - **Sign up:** `/signup` (optional; admins can also create users from **Users**)
   - **Profile:** set display name, bio, avatar, theme, custom footer
   - **Links:** add links and headings, reorder by drag, set color and icon
   - **Public page:** `/@username` (e.g. `yoursite.com/@jane`)
   - **Sitemap:** `yoursite.com/sitemap.xml`

---

## Configuration

In `config/config.php` (copy from `config/config.example`):

| Key | Purpose |
|-----|--------|
| **app_name** | Name shown on the homepage and in emails |
| **base_url** | Leave empty to auto-detect, or set e.g. `https://links.example.com` |
| **db** | host, dbname, user, pass, charset |
| **session_name**, **cookie_secure**, **cookie_samesite**, **password_cost**, **timezone** | Session and app behavior |
| **dev_mode** | If `true`, password reset links are written to the PHP error log when SMTP isn’t configured |
| **smtp** | host, port, secure (tls/ssl), user, pass, from, from_name. Required for sending password reset emails |
| **webauthn** | **rp_id** (e.g. `example.com`), **rp_name**, **origin** (e.g. `https://example.com`). Required for passkeys |

- **Passkeys:** Need HTTPS. Set **webauthn** `origin` and `rp_id` to your domain.
- **Base URL:** Set **base_url** if the app lives in a subdirectory or you need a fixed URL for emails and sitemaps.

---

## Deploying on 1&1 / IONOS

- Set **PHP 8.1+** for the domain in the control panel.
- Create a MySQL database and user; put credentials in `config/config.php`.
- Point the domain’s **document root** to the folder that contains `index.php` and `.htaccess`.
- Make **storage/sessions**, **storage/rate_limit**, and **storage** writable (File Manager or SFTP).
- To use passkeys and SMTP without SSH: run `composer install` locally, then upload the project **including `vendor/`**.
- **HTTPS** is required for passkeys; IONOS typically offers free SSL.

---

## Troubleshooting

### 500 when visiting `/admin/login.php` or `/login`

- Ensure **storage/sessions** exists and is writable.
- Enable `display_errors` or check the PHP error log; set **session.save_path** in `.user.ini` if the default path isn’t writable.

### `/@username` returns 404 or wrong page

- Try **`index.php?u=username`** (works without rewrites).
- Set the domain’s document root to the folder that contains `.htaccess`.
- If the app is in a subdirectory, set **RewriteBase** in `.htaccess` (e.g. `RewriteBase /link`).

### “Call to undefined function” / “Class not found”

- Confirm **PHP 8.1+** in the control panel.
- If using passkeys or email, ensure **vendor/** was uploaded after `composer install`.

### Sitemap not updating

- Ensure **storage/** is writable so `storage/sites.xml` can be created/updated when links or users change.

---

## Notes

- **Security:** CSRF is required on all POST and AJAX requests; sessions use SameSite, Secure, HttpOnly.
- **Rate limiting:** Login, password reset, signup, and WebAuthn endpoints are rate-limited (file-based in `storage/rate_limit/`).
- **Icons:** SVGs in `assets/icons/` can be replaced with your own.
- **Backups:** Back up the database regularly.
- **v0.1:** For a new install you only need `sql/schema.sql`. Other files in `sql/` are for upgrading from older development builds and are not required for new installs.
