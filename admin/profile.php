<?php
/**
 * profile.php — User profile.
 * Project: linkhill
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
declare(strict_types=1);
use function App\{pdo, e, require_user};
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/csrf.php';
require __DIR__ . '/../inc/helpers.php';

$me = \App\require_user();
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \App\csrf_verify();
    $display = trim((string)($_POST['display_name'] ?? ''));
    $bio     = trim((string)($_POST['bio'] ?? ''));
    $customFooter = trim((string)($_POST['custom_footer'] ?? ''));
    $theme   = in_array($_POST['theme'] ?? 'light', ['light','dark','custom'], true) ? $_POST['theme'] : 'light';
    // Handle avatar upload optionally
    $avatarPath = null;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['avatar'];
        if ($f['size'] > 200*1024) {
            $msg = 'Avatar too large (max 200KB).';
        } else {
            $fi = new finfo(FILEINFO_MIME_TYPE);
            $mime = $fi->file($f['tmp_name']);
            $ext = $mime === 'image/jpeg' ? 'jpg' : ($mime === 'image/png' ? 'png' : '');
            if (!$ext) {
                $msg = 'Invalid avatar format. Use JPG or PNG.';
            } else {
                $name = bin2hex(random_bytes(8)) . '.' . $ext;
                $dest = __DIR__ . '/../assets/img/avatars/' . $name;
                if (move_uploaded_file($f['tmp_name'], $dest)) {
                    $avatarPath = '/assets/img/avatars/' . $name;
                } else {
                    $msg = 'Failed to save avatar.';
                }
            }
        }
    }
    if ($display && !$msg) {
        $sql = "UPDATE users SET display_name=?, bio=?, custom_footer=?, theme=?, updated_at=NOW()";
        $args = [$display, $bio, $customFooter, $theme];
        if ($avatarPath) { $sql .= ", avatar_path=?"; $args[] = $avatarPath; }
        $sql .= " WHERE id=?";
        $args[] = $me['id'];
        $up = pdo()->prepare($sql);
        $up->execute($args);
        $msg = 'Profile updated.';
    } elseif (!$msg) {
        $msg = 'Display name required.';
    }
}
$stmt = pdo()->prepare("SELECT email,username,display_name,bio,custom_footer,theme,avatar_path FROM users WHERE id=?");
$stmt->execute([$me['id']]);
$row = $stmt->fetch();
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Profile</title><link rel="stylesheet" href="/assets/css/styles.css"></head>
<body class="theme-light"><main class="container">
  <header class="admin-header">
    <h1>Profile</h1>
    <nav>
      <?php if (($me['role'] ?? '') === 'admin'): ?><a href="/admin/">Dashboard</a><?php endif; ?>
      <a href="/admin/profile.php">Profile</a>
      <a href="/admin/links.php">Links</a>
      <a href="/admin/security/">Security</a>
      <?php if (($me['role'] ?? '') === 'admin'): ?><a href="/admin/users.php">Users</a><?php endif; ?>
      <a href="/admin/logout.php" class="danger">Logout</a>
    </nav>
  </header>
  <?php if ($msg): ?><div class="alert"><?= e($msg) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <?= \App\csrf_field() ?>
    <div class="grid">
      <label>Display name<br><input type="text" name="display_name" value="<?= e($row['display_name']) ?>" required></label>
      <label>Username (public URL)<br><input type="text" value="@<?= e($row['username']) ?>" disabled></label>
    </div>
    <label>Bio<br><textarea name="bio" rows="4"><?= e($row['bio']) ?></textarea></label>
    <label>Custom footer (optional)<br><textarea name="custom_footer" rows="3" placeholder="Shown centered below your links on your public page"><?= e($row['custom_footer'] ?? '') ?></textarea></label>
    <label>Theme<br>
      <select name="theme">
        <option value="light" <?= $row['theme']==='light'?'selected':'' ?>>Light</option>
        <option value="dark"  <?= $row['theme']==='dark'?'selected':'' ?>>Dark</option>
        <option value="custom"<?= $row['theme']==='custom'?'selected':'' ?>>Custom</option>
      </select>
    </label>
    <div class="avatar-row">
      <?php if (!empty($row['avatar_path'])): ?>
        <img src="<?= e($row['avatar_path']) ?>" alt="Avatar" class="avatar sm">
      <?php endif; ?>
      <label>Avatar (JPG/PNG, ≤200KB)
        <input type="file" name="avatar" accept="image/jpeg,image/png">
      </label>
    </div>
    <button type="submit">Save profile</button>
  </form>
  <p>Public page: <a href="/@<?= e($row['username']) ?>" target="_blank">/@<?= e($row['username']) ?></a></p></main></body></html>
