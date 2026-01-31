<?php
/**
 * index.php — Public homepage and profile pages.
 * Project: linkhill
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
declare(strict_types=1);
use function App\{pdo, e, config, base_url, links_has_description, is_valid_hex_color, link_contrast_text, link_darken, link_muted_rgba};
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';

$u = $_GET['u'] ?? null;
$go = $_GET['go'] ?? null;

if ($go !== null) {
    $id = (int)$go;
    if ($id > 0) {
        $stmt = pdo()->prepare("SELECT id, url, entry_type FROM links WHERE id = ? AND is_active = 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row && ($row['entry_type'] ?? 'link') === 'link' && !empty($row['url'])) {
            // Minimal analytics
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $ipHash = $ip ? hash('sha256', $ip . date('Y-m-d')) : null;
            $uaHash = $ua ? hash('sha256', $ua) : null;
            $ins = pdo()->prepare("INSERT INTO link_clicks (link_id, ip_hash, ua_hash) VALUES (?, ?, ?)");
            $ins->execute([$row['id'], $ipHash, $uaHash]);
            header("Location: " . $row['url'], true, 302);
            exit;
        }
    }
    http_response_code(404);
    echo "Link not found";
    exit;
}

if ($u !== null) {
    $stmt = pdo()->prepare("SELECT id, display_name, username, bio, custom_footer, theme, avatar_path FROM users WHERE username = ?");
    $stmt->execute([$u]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(404);
        echo "User not found";
        exit;
    }
    $hasDesc = links_has_description();
    $linkCols = $hasDesc ? 'id, entry_type, title, url, description, color_hex, icon_slug' : 'id, entry_type, title, url, color_hex, icon_slug';
    $links = pdo()->prepare("SELECT $linkCols FROM links WHERE user_id = ? AND is_active = 1 ORDER BY position ASC, id ASC");
    $links->execute([$user['id']]);
    $links = $links->fetchAll();
    include __DIR__ . '/inc/icons.php';
    $profileCanonical = rtrim(base_url(), '/') . '/@' . $user['username'];
    $profileMetaDesc = e($user['display_name']) . ' — link in bio, links and profile. View all links for ' . e($user['display_name']) . '.';
    ?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($user['display_name']) ?> · Links | Link in Bio</title>
<meta name="description" content="<?= $profileMetaDesc ?>">
<link rel="canonical" href="<?= e($profileCanonical) ?>">
<meta property="og:type" content="profile">
<meta property="og:url" content="<?= e($profileCanonical) ?>">
<meta property="og:title" content="<?= e($user['display_name']) ?> · Links">
<meta property="og:description" content="<?= $profileMetaDesc ?>">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="<?= e($user['display_name']) ?> · Links">
<meta name="twitter:description" content="<?= $profileMetaDesc ?>">
<link rel="stylesheet" href="/assets/css/linkhill.css"></head>
<body class="theme-<?= e($user['theme']) ?>">
  <header class="container">
    <div class="profile">
      <?php if (!empty($user['avatar_path'])): ?>
        <img class="avatar" src="<?= e($user['avatar_path']) ?>" alt="">
      <?php endif; ?>
      <h1 class="site-title"><?= e($user['display_name']) ?></h1>
      <?php if (!empty($user['bio'])): ?>
        <p class="site-subtitle"><?= nl2br(e($user['bio'])) ?></p>
      <?php endif; ?>
    </div>
  </header>
  <main class="container">
    <div class="stack">
      <nav class="link-list">
        <?php foreach ($links as $l): ?>
          <?php if (($l['entry_type'] ?? 'link') === 'heading'): ?>
            <h2 class="entry-heading"><?= e($l['title']) ?></h2>
          <?php else: ?>
          <?php
            $href = '/index.php?go=' . (int)$l['id'];
            $showCard = $hasDesc && !empty(trim((string)($l['description'] ?? '')));
            $hex = (is_valid_hex_color($l['color_hex'] ?? '') ? $l['color_hex'] : '#111827');
            $btnStyle = '--button-bg:' . $hex . ';--button-text:' . link_contrast_text($hex) . ';--button-hover:' . link_darken($hex) . ';';
            $cardStyle = '--card-bg:' . $hex . ';--border:' . link_darken($hex, 0.2) . ';color:' . link_contrast_text($hex) . ';--muted:' . link_muted_rgba($hex) . ';';
          ?>
          <?php if ($showCard): ?>
            <a class="card" href="<?= e($href) ?>" rel="noopener" style="<?= e($cardStyle) ?>">
              <h3><?= e($l['title']) ?></h3>
              <p><?= nl2br(e(trim($l['description']))) ?></p>
            </a>
          <?php else: ?>
            <a class="button" href="<?= e($href) ?>" rel="noopener" style="<?= e($btnStyle) ?>">
              <span class="icon">
                <?php $svg = \App\render_icon_svg($l['icon_slug'] ?? 'link'); echo $svg ?: ''; ?>
              </span>
              <span class="title"><?= e($l['title']) ?></span>
            </a>
          <?php endif; ?>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
      <?php if (!empty(trim((string)($user['custom_footer'] ?? '')))): ?>
      <footer class="profile-footer"><?= nl2br(e(trim($user['custom_footer']))) ?></footer>
      <?php endif; ?>
    </div>
  </main>
</body></html><?php
    exit;
}
// Homepage: informational only; no auth UI
$appName = config()['app_name'] ?? 'Hillwork';
$canonical = rtrim(base_url(), '/');
$metaDesc = 'Free Linktree alternative: create a clean link-in-bio page in minutes. No paywalls, no lock-in. Best free link in bio tool for Instagram, TikTok, and more.';
$metaKeywords = 'Linktree alternative, link in bio, link in bio free, bio link, Instagram link, TikTok link, link hub, link page';
$year = (int) date('Y');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($appName) ?> — Free Linktree Alternative | Link in Bio</title>
  <meta name="description" content="<?= e($metaDesc) ?>">
  <meta name="keywords" content="<?= e($metaKeywords) ?>">
  <link rel="canonical" href="<?= e($canonical) ?>/">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= e($canonical) ?>/">
  <meta property="og:title" content="<?= e($appName) ?> — Free Linktree Alternative | Link in Bio">
  <meta property="og:description" content="<?= e($metaDesc) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($appName) ?> — Free Linktree Alternative | Link in Bio">
  <meta name="twitter:description" content="<?= e($metaDesc) ?>">
  <link rel="stylesheet" href="/assets/css/linkhill.css">
</head>
<body class="theme-light home-page">
  <header class="container hero">
    <div class="profile">
      <h1 class="site-title"><?= e($appName) ?></h1>
      <p class="site-subtitle">All your links, one simple page — free &amp; open.</p>
      <p class="site-subtitle">Create a clean, customizable link hub in minutes. No paywalls. Own your data.</p>
    </div>
  </header>
  <main class="container">
    <div class="stack">
      <nav class="cta-group" aria-label="Sign up and log in">
        <a href="/signup" class="button" id="cta-signup">Create free account</a>
        <a href="/login" class="button button--secondary" id="cta-login">Log in</a>
      </nav>
      <section class="benefits" aria-labelledby="benefits-heading">
        <h2 id="benefits-heading" class="sr-only">Benefits</h2>
        <div class="benefit-cards">
          <div class="card">
            <h3>Free &amp; open</h3>
            <p>No subscriptions. Open ethos. No vendor lock‑in.</p>
          </div>
          <div class="card">
            <h3>Fast &amp; private</h3>
            <p>Minimal tracking. Privacy‑respectful by default.</p>
          </div>
          <div class="card">
            <h3>Customizable</h3>
            <p>Your links, your branding, your control.</p>
          </div>
        </div>
      </section>
      <p class="muted callout">An open alternative to Linktree. Your page, your data.</p>
    </div>
  </main>
  <footer class="footer">
    <div class="container">
      <nav aria-label="Footer">
        <a href="/about">About</a>
        <a href="/privacy">Privacy</a>
        <a href="/terms">Terms</a>
        <a href="/contact">Contact</a>
      </nav>
      <p class="footer-copy">© <?= $year ?> <a href="https://hillwork.us">Hillwork, LLC</a></p>
    </div>
  </footer>
</body>
</html>
