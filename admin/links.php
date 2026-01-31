<?php
/**
 * links.php — Manage links.
 * Project: linkhill
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
declare(strict_types=1);
use function App\{pdo, e, require_user, links_has_description};
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/csrf.php';
require __DIR__ . '/../inc/helpers.php';
require __DIR__ . '/../inc/icons.php';

$me = \App\require_user();
$csrf = \App\csrf_token();
$hasDesc = links_has_description();
$cols = $hasDesc ? 'id, title, url, description, color_hex, icon_slug, position, is_active' : 'id, title, url, color_hex, icon_slug, position, is_active';
$links = pdo()->prepare("SELECT $cols FROM links WHERE user_id=? ORDER BY position ASC, id ASC");
$links->execute([$me['id']]);
$links = $links->fetchAll();
$icons = \App\icon_list();
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Links</title><link rel="stylesheet" href="/assets/css/styles.css"></head>
<body class="theme-light"><main class="container">
  <header class="admin-header">
    <h1>Links</h1>
    <nav>
      <?php if (($me['role'] ?? '') === 'admin'): ?><a href="/admin/">Dashboard</a><?php endif; ?>
      <a href="/admin/profile.php">Profile</a>
      <a href="/admin/links.php">Links</a>
      <a href="/admin/security/">Security</a>
      <?php if (($me['role'] ?? '') === 'admin'): ?><a href="/admin/users.php">Users</a><?php endif; ?>
      <a href="/admin/logout.php" class="danger">Logout</a>
    </nav>
  </header>
  <section class="card">
    <h2>Add link</h2>
    <form id="addForm">
      <input type="hidden" name="_token" value="<?= e($csrf) ?>">
      <div class="grid">
        <label>Title<br><input type="text" name="title" required maxlength="80"></label>
        <label>URL<br><input type="url" name="url" placeholder="https://..." required></label>
      </div>
      <?php if ($hasDesc): ?><label>Description (optional; if set, link shows as a card with blurb on your page)<br><input type="text" name="description" placeholder="Optional blurb" maxlength="500"></label><?php endif; ?>
      <div class="add-form__row">
        <div class="grid">
          <label>Color<br><input type="color" name="color_hex" value="#111827"></label>
          <label>Icon<br>
            <select name="icon_slug">
              <?php foreach ($icons as $slug => $path): ?>
                <option value="<?= e($slug) ?>"><?= e($slug) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <button type="submit">Add</button>
      </div>
    </form>
  </section>
  <section class="card">
    <h2>Your links</h2>
    <ul id="linkList" class="link-list<?= $hasDesc ? ' link-list--with-desc' : '' ?>" data-csrf="<?= e($csrf) ?>">
      <?php foreach ($links as $l): ?>
        <li class="link-item" data-id="<?= (int)$l['id'] ?>">
          <div class="link-item__row">
            <span class="drag" title="Drag to reorder">⋮⋮</span>
            <div class="link-item__fields">
              <div class="grid">
                <label class="link-item__label">Title<br><input class="title" type="text" value="<?= e($l['title']) ?>" maxlength="80" placeholder="Link title"></label>
                <label class="link-item__label">URL<br><input class="url" type="url" value="<?= e($l['url']) ?>" placeholder="https://..."></label>
              </div>
              <?php if ($hasDesc): ?><label class="link-item__label">Description (optional)<br><input class="description" type="text" placeholder="Optional blurb (shows as card)" value="<?= e($l['description'] ?? '') ?>" maxlength="500"></label><?php endif; ?>
              <div class="link-item__meta">
                <label class="link-item__meta-label">Color <input class="color" type="color" value="<?= e($l['color_hex']) ?>" title="Button color"></label>
                <label class="link-item__meta-label">Icon <select class="icon"><?php foreach ($icons as $slug => $path): ?><option value="<?= e($slug) ?>" <?= $slug === ($l['icon_slug'] ?? 'link') ? 'selected' : '' ?>><?= e($slug) ?></option><?php endforeach; ?></select></label>
                <button type="button" class="save">Save</button>
                <button type="button" class="delete danger">Delete</button>
              </div>
            </div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section></main>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
const list = document.getElementById('linkList');
const csrf = list.dataset.csrf;
new Sortable(list, {
  handle: '.drag',
  animation: 150,
  onEnd: async function () {
    const ids = Array.from(list.querySelectorAll('.link-item')).map((li, idx) => ({id: li.dataset.id, position: idx}));
    await fetch('/admin/api/reorder_links.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf},
      body: JSON.stringify({items: ids})
    });
  }
});
document.getElementById('addForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action', 'create');
  const res = await fetch('/admin/api/link_crud.php', { method: 'POST', body: fd });
  const json = await res.json();
  if (json && json.id) location.reload();
  else alert(json.error || 'Failed to add link');
});
list.addEventListener('click', async (e) => {
  const li = e.target.closest('.link-item');
  if (!li) return;
  if (e.target.classList.contains('save')) {
    const fd = new FormData();
    fd.append('_token', csrf);
    fd.append('action', 'update');
    fd.append('id', li.dataset.id);
    fd.append('title', li.querySelector('.title').value);
    fd.append('url', li.querySelector('.url').value);
    var descEl = li.querySelector('.description');
    if (descEl) fd.append('description', descEl.value.trim());
    fd.append('color_hex', li.querySelector('.color').value);
    fd.append('icon_slug', li.querySelector('.icon').value);
    const res = await fetch('/admin/api/link_crud.php', {method: 'POST', body: fd});
    const json = await res.json();
    if (!json.ok) alert(json.error || 'Update failed');
  } else if (e.target.classList.contains('delete')) {
    if (!confirm('Delete this link?')) return;
    const fd = new FormData();
    fd.append('_token', csrf);
    fd.append('action', 'delete');
    fd.append('id', li.dataset.id);
    const res = await fetch('/admin/api/link_crud.php', {method: 'POST', body: fd});
    const json = await res.json();
    if (json.ok) li.remove();
    else alert(json.error || 'Delete failed');
  }
});
</script></body></html>
