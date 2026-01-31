<?php
/**
 * users.php — User management (admin).
 * Project: linkhill
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
declare(strict_types=1);
use function App\{pdo, e, require_admin, config, base_url, send_mail, users_have_email_verified};
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/csrf.php';
require __DIR__ . '/../inc/helpers.php';
require __DIR__ . '/../inc/mail.php';
require __DIR__ . '/../inc/sites_xml.php';
require_once __DIR__ . '/../inc/email_verification.php';

$me = \App\require_admin();
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \App\csrf_verify();
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        $email = trim((string)($_POST['email'] ?? ''));
        $username = \App\slugify_username((string)($_POST['username'] ?? ''));
        $display = trim((string)($_POST['display_name'] ?? ''));
        $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $pwd = (string)($_POST['password'] ?? '');
        if (!$email || !$username || !$display || strlen($pwd) < 8) {
            $msg = 'Missing or invalid fields.';
        } else {
            $hash = password_hash($pwd, PASSWORD_DEFAULT, ['cost'=>\App\config()['password_cost']]);
            $ins = pdo()->prepare("INSERT INTO users (email, username, display_name, password_hash, role) VALUES (?,?,?,?,?)");
            try {
                $ins->execute([$email, $username, $display, $hash, $role]);
                $userId = (int) pdo()->lastInsertId();
                if (users_have_email_verified()) {
                    $tokenForLink = \App\email_verification_create($userId, 60);
                    $origin = rtrim(base_url(), '/');
                    $verifyUrl = $origin . '/verify-email.php?token=' . $tokenForLink;
                    $appName = config()['app_name'] ?? 'Hillwork';
                    $bodyText = "Verify your email to activate your {$appName} account. Click the link below (valid 1 hour):\n\n" . $verifyUrl . "\n\nIf you did not create an account, ignore this email.";
                    $bodyHtml = '<p>Verify your email to activate your ' . e($appName) . ' account. Click the link below (valid 1 hour):</p><p><a href="' . e($verifyUrl) . '">Verify email</a></p><p>If you did not create an account, ignore this email.</p>';
                    $sent = send_mail($email, 'Verify your email - ' . $appName, $bodyText, $bodyHtml);
                    $msg = $sent ? 'User created. Verification email sent to ' . e($email) . '.' : 'User created. Verification email could not be sent—check SMTP config.';
                } else {
                    $msg = 'User created.';
                }
            } catch (\PDOException $e) {
                $msg = 'Error creating user: ' . e($e->getMessage());
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'reset') {
        $id = (int)($_POST['id'] ?? 0);
        $pwd = (string)($_POST['password'] ?? '');
        if ($id > 0 && strlen($pwd) >= 8) {
            $hash = password_hash($pwd, PASSWORD_DEFAULT, ['cost'=>\App\config()['password_cost']]);
            $up = pdo()->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $up->execute([$hash, $id]);
            $msg = 'Password reset.';
        } else {
            $msg = 'Invalid user or password.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && $id !== (int)$me['id']) {
            $del = pdo()->prepare("DELETE FROM users WHERE id = ?");
            $del->execute([$id]);
            \App\sites_xml_rebuild();
            $msg = 'User deleted.';
        } else {
            $msg = 'Cannot delete self or invalid ID.';
        }
    }
}
$users = pdo()->query("SELECT id,email,username,display_name,role,mfa_enabled FROM users ORDER BY id DESC")->fetchAll();
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Users</title><link rel="stylesheet" href="/assets/css/styles.css"></head>
<body class="theme-light"><main class="container">
  <header class="admin-header">
    <h1>Users</h1>
    <nav>
      <?php if (($me['role'] ?? '') === 'admin'): ?><a href="/admin/">Dashboard</a><?php endif; ?>
      <a href="/admin/profile.php">Profile</a>
      <a href="/admin/links.php">Links</a>
      <a href="/admin/security/">Security</a>
      <a href="/admin/users.php">Users</a>
      <a href="/admin/logout.php" class="danger">Logout</a>
    </nav>
  </header>
  <?php if ($msg): ?><div class="alert"><?= e($msg) ?></div><?php endif; ?>
  <section class="card">
    <h2>Create user</h2>
    <form method="post">
      <?= \App\csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <label>Email<br><input type="email" name="email" required></label>
      <label>Username<br><input type="text" name="username" required></label>
      <label>Display name<br><input type="text" name="display_name" required></label>
      <label>Role<br>
        <select name="role">
          <option value="user">User</option>
          <option value="admin">Admin</option>
        </select>
      </label>
      <label>Temp password<br><input type="text" name="password" required minlength="8"></label>
      <button type="submit">Create</button>
    </form>
  </section>
  <section class="card">
    <h2>All users</h2>
    <table class="table">
      <thead><tr><th>ID</th><th>Email</th><th>Username</th><th>Name</th><th>Role</th><th>MFA</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= (int)$u['id'] ?></td>
            <td><?= e($u['email']) ?></td>
            <td><a target="_blank" href="/@<?= e($u['username']) ?>">@<?= e($u['username']) ?></a></td>
            <td><?= e($u['display_name']) ?></td>
            <td><?= e($u['role']) ?></td>
            <td><?= ((int)$u['mfa_enabled'] === 1) ? 'On' : 'Off' ?></td>
            <td class="actions">
              <form method="post" class="inline">
                <?= \App\csrf_field() ?>
                <input type="hidden" name="action" value="reset">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <input type="text" name="password" placeholder="New password" minlength="8" required>
                <button type="submit">Reset</button>
              </form>
              <?php if ((int)$u['id'] !== (int)$me['id']): ?>
              <form method="post" class="inline" onsubmit="return confirm('Delete this user? This removes their links too.');">
                <?= \App\csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button class="danger" type="submit">Delete</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section></main></body></html>
