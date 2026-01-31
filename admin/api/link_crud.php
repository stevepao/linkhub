<?php
/**
 * link_crud.php — Link create/update/delete API.
 * Project: linkhill
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
declare(strict_types=1);
use function App\{pdo, require_user, json_response, sanitize_url, is_valid_hex_color, links_has_description};
require __DIR__ . '/../../inc/db.php';
require __DIR__ . '/../../inc/auth.php';
require __DIR__ . '/../../inc/csrf.php';
require __DIR__ . '/../../inc/helpers.php';
require __DIR__ . '/../../inc/icons.php';

$me = \App\require_user();
\App\csrf_verify();

$action = $_POST['action'] ?? '';
if ($action === 'create') {
    $entryType = ($_POST['entry_type'] ?? 'link') === 'heading' ? 'heading' : 'link';
    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') {
        json_response(['error'=>'Title required'], 422);
    }
    $pos = (int)pdo()->query("SELECT COALESCE(MAX(position),-1)+1 AS p FROM links WHERE user_id=".(int)$me['id'])->fetch()['p'];
    if ($entryType === 'heading') {
        $ins = pdo()->prepare("INSERT INTO links (user_id, entry_type, title, url, position) VALUES (?, 'heading', ?, NULL, ?)");
        $ins->execute([$me['id'], $title, $pos]);
    } else {
        $url   = sanitize_url((string)($_POST['url'] ?? ''));
        $desc  = trim((string)($_POST['description'] ?? ''));
        $color = (string)($_POST['color_hex'] ?? '#111827');
        $icon  = (string)($_POST['icon_slug'] ?? 'link');
        if (!$url || !is_valid_hex_color($color)) {
            json_response(['error'=>'Invalid input'], 422);
        }
        $icons = \App\icon_list();
        if (!isset($icons[$icon])) $icon = 'link';
        if (links_has_description()) {
            $ins = pdo()->prepare("INSERT INTO links (user_id, entry_type, title, url, description, color_hex, icon_slug, position) VALUES (?, 'link', ?, ?, ?, ?, ?, ?)");
            $ins->execute([$me['id'], $title, $url, $desc ?: null, $color, $icon, $pos]);
        } else {
            $ins = pdo()->prepare("INSERT INTO links (user_id, entry_type, title, url, color_hex, icon_slug, position) VALUES (?, 'link', ?, ?, ?, ?, ?)");
            $ins->execute([$me['id'], $title, $url, $color, $icon, $pos]);
        }
    }
    $id = (int)pdo()->lastInsertId();
    json_response(['id'=>$id, 'position'=>$pos]);
} elseif ($action === 'update') {
    $id    = (int)($_POST['id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    if ($id <= 0 || $title === '') {
        json_response(['error'=>'Invalid input'], 422);
    }
    $own = pdo()->prepare("SELECT id, entry_type FROM links WHERE id=? AND user_id=?");
    $own->execute([$id, $me['id']]);
    $row = $own->fetch();
    if (!$row) json_response(['error'=>'Not found or no permission'], 404);
    $entryType = $row['entry_type'];
    if ($entryType === 'heading') {
        $up = pdo()->prepare("UPDATE links SET title=?, updated_at=NOW() WHERE id=? AND user_id=?");
        $up->execute([$title, $id, $me['id']]);
    } else {
        $url   = sanitize_url((string)($_POST['url'] ?? ''));
        $desc  = trim((string)($_POST['description'] ?? ''));
        $color = (string)($_POST['color_hex'] ?? '#111827');
        $icon  = (string)($_POST['icon_slug'] ?? 'link');
        if (!$url || !is_valid_hex_color($color)) {
            json_response(['error'=>'Invalid input'], 422);
        }
        $icons = \App\icon_list();
        if (!isset($icons[$icon])) $icon = 'link';
        if (links_has_description()) {
            $up = pdo()->prepare("UPDATE links SET title=?, url=?, description=?, color_hex=?, icon_slug=?, updated_at=NOW() WHERE id=? AND user_id=?");
            $up->execute([$title, $url, $desc ?: null, $color, $icon, $id, $me['id']]);
        } else {
            $up = pdo()->prepare("UPDATE links SET title=?, url=?, color_hex=?, icon_slug=?, updated_at=NOW() WHERE id=? AND user_id=?");
            $up->execute([$title, $url, $color, $icon, $id, $me['id']]);
        }
    }
    json_response(['ok'=>true]);
} elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) json_response(['error'=>'Invalid id'], 422);
    $own = pdo()->prepare("DELETE FROM links WHERE id=? AND user_id=?");
    $own->execute([$id, $me['id']]);
    json_response(['ok'=>true]);
} else {
    json_response(['error'=>'Unknown action'], 400);
}
