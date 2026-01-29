<?php
declare(strict_types=1);
use function App\{pdo, e, links_has_description, is_valid_hex_color, link_contrast_text, link_darken, link_muted_rgba};
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';

$u = $_GET['u'] ?? null;
$go = $_GET['go'] ?? null;

if ($go !== null) {
    $id = (int)$go;
    if ($id > 0) {
        $stmt = pdo()->prepare("SELECT id, url FROM links WHERE id = ? AND is_active = 1");
        $stmt->execute([$id]);
        if ($row = $stmt->fetch()) {
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
    $stmt = pdo()->prepare("SELECT id, display_name, username, bio, theme, avatar_path FROM users WHERE username = ?");
    $stmt->execute([$u]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(404);
        echo "User not found";
        exit;
    }
    $hasDesc = links_has_description();
    $linkCols = $hasDesc ? 'id, title, url, description, color_hex, icon_slug' : 'id, title, url, color_hex, icon_slug';
    $links = pdo()->prepare("SELECT $linkCols FROM links WHERE user_id = ? AND is_active = 1 ORDER BY position ASC, id ASC");
    $links->execute([$user['id']]);
    $links = $links->fetchAll();
    include __DIR__ . '/inc/icons.php';
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($user['display_name']) ?> · Links</title><link rel="stylesheet" href="/assets/css/paos.css"></head>
<body>
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
        <?php endforeach; ?>
      </nav>
    </div>
  </main>
</body></html><?php
    exit;
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>LinkHub</title><link rel="stylesheet" href="/assets/css/paos.css"></head>
<body>
  <main class="container">
    <div class="stack">
      <h1>LinkHub</h1>
      <p class="muted">Create your profile at <code>/admin</code> and visit <code>/@username</code> to share your link‑in‑bio page.</p>
    </div>
  </main>
</body></html>
