# Changelog

## [0.1.0] — 2026-01-27

Initial release for new users. Fresh installs use `sql/schema.sql` only; no migration scripts required.

### Features

- **Multi-user** link-in-bio with admin and user roles
- **Auth:** password + optional TOTP and passkeys (WebAuthn)
- **Security** page: password change, TOTP, passkeys (register / rename / remove)
- **Password reset** via email (SMTP or dev log)
- **Signup** at `/signup`; admins can also create users from Users page
- **Public profiles** at `/@username` with display name, bio, avatar, theme, custom footer
- **Links:** title, URL, optional description (card blurb), color, icon, drag-to-reorder
- **Section headings** in link list (e.g. “Social”, “Shop”)
- **Minimal click analytics** via `?go=<id>` redirect (hashed IP/UA)
- **Sitemap** at `/sitemap.xml` (static + profile pages with links); auto-updated on link/user changes
- **SEO:** meta and Open Graph on homepage, About/Contact/Privacy/Terms, and profile pages
- **Local SVG icons** (GitHub, LinkedIn, Instagram, Bluesky, Substack, etc.)
- **Shared-hosting friendly:** PDO MySQL, file-based sessions and rate limiting, `.htaccess`
- **First admin:** one-time `admin_seed.php` script; delete after use
