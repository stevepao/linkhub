<?php
/**
 * login.php — Admin login.
 * Project: linkhill
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
declare(strict_types=1);
use function App\{config, e, pdo, base_url, send_mail, webauthn_available, users_have_email_verified, rate_limit_check, rate_limit_identifier_with_email};
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/csrf.php';
require __DIR__ . '/../inc/helpers.php';
require __DIR__ . '/../inc/mail.php';
require __DIR__ . '/../inc/rate_limit.php';
require __DIR__ . '/../inc/webauthn.php';
require_once __DIR__ . '/../inc/email_verification.php';
\App\session_boot();
$passkeys_available = webauthn_available();

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \App\csrf_verify();
    if (isset($_POST['email'], $_POST['password'])) {
        $res = \App\login($_POST['email'], $_POST['password']);
        if ($res === 'ok') {
            $u = \App\current_user();
            header('Location: ' . (($u['role'] ?? '') === 'admin' ? '/admin/' : '/admin/profile.php'));
            exit;
        } elseif ($res === 'mfa') {
            // fall through to TOTP form
        } elseif ($res === 'unverified') {
            $email = trim((string) $_POST['email']);
            if (users_have_email_verified() && $email !== '') {
                $stmt = pdo()->prepare("SELECT id FROM users WHERE email = ? AND email_verified_at IS NULL");
                $stmt->execute([$email]);
                $row = $stmt->fetch();
                if ($row && rate_limit_check('verification_resend', rate_limit_identifier_with_email($email), 3, 3600)) {
                    $tokenForLink = \App\email_verification_create((int) $row['id'], 60);
                    $origin = rtrim(base_url(), '/');
                    $verifyUrl = $origin . '/verify-email.php?token=' . $tokenForLink;
                    $appName = config()['app_name'] ?? 'Hillwork';
                    $bodyText = "Verify your email to activate your {$appName} account. Click the link below (valid 1 hour):\n\n" . $verifyUrl . "\n\nIf you did not request this, ignore this email.";
                    $bodyHtml = '<p>Verify your email to activate your ' . e($appName) . ' account. Click the link below (valid 1 hour):</p><p><a href="' . e($verifyUrl) . '">Verify email</a></p><p>If you did not request this, ignore this email.</p>';
                    send_mail($email, 'Verify your email - ' . $appName, $bodyText, $bodyHtml);
                    $err = 'Please verify your email before signing in. We\'ve sent a new verification link to your email address.';
                } else {
                    $err = 'Please verify your email before signing in. Check your inbox for the verification link. You can request another link in about an hour.';
                }
            } else {
                $err = 'Please verify your email before signing in. Check your inbox for the verification link.';
            }
        } else {
            $err = 'Invalid credentials.';
            sleep(1);
        }
    } elseif (isset($_POST['totp_code'])) {
        $uid = $_SESSION['pending_mfa_user_id'] ?? 0;
        if ($uid && \App\verify_totp_and_finish((int)$uid, $_POST['totp_code'])) {
            $u = \App\current_user();
            header('Location: ' . (($u['role'] ?? '') === 'admin' ? '/admin/' : '/admin/profile.php'));
            exit;
        } else {
            $err = 'Invalid code.';
            sleep(1);
        }
    }
}
$pending = isset($_SESSION['pending_mfa_user_id']);
$verified = isset($_GET['verified']) && $_GET['verified'] === '1';
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Login · <?= e(config()['app_name']) ?></title><link rel="stylesheet" href="/assets/css/styles.css"></head>
<body class="theme-light"><main class="container">
  <h1>Sign in</h1>
  <?php if ($verified): ?><div class="alert">Your email is verified. You can sign in below.</div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>
  <?php if (!$pending): ?>
  <form method="post" id="login-form">
    <?= \App\csrf_field() ?>
    <label>Email<br><input type="email" name="email" id="login-email" required></label>
    <label>Password<br><input type="password" name="password" required></label>
    <button type="submit">Continue</button>
  </form>
  <p style="margin-top:16px;"><a href="/password/forgot.php">Forgot password?</a></p>
  <?php if ($passkeys_available): ?>
  <p style="margin-top:12px;">
    <button type="button" id="passkey-btn" style="display:none;">Sign in with a passkey</button>
  </p>
  <?php endif; ?>
  <meta name="csrf-token" content="<?= e(\App\csrf_token()) ?>">
  <meta name="app-base-path" content="<?= e(rtrim(parse_url(\App\base_url(), PHP_URL_PATH) ?: '', '/')) ?>">
  <script src="/assets/js/webauthn.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      if (typeof window.WebAuthnHelper !== 'undefined' && window.WebAuthnHelper.supported && <?= $passkeys_available ? 'true' : 'false' ?>) {
        var btn = document.getElementById('passkey-btn');
        if (btn) { btn.style.display = 'inline-block'; window.WebAuthnHelper.initLoginPage(btn, document.getElementById('login-email')); if (window.location.search.indexOf('method=passkey') !== -1) btn.focus(); }
      }
    });
  </script>
  <?php else: ?>
  <form method="post">
    <?= \App\csrf_field() ?>
    <p>Enter your 6‑digit authentication code.</p>
    <label>Authenticator code<br><input type="text" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" required></label>
    <button type="submit">Verify</button>
  </form>
  <?php endif; ?>
  <p style="margin-top:16px;"><a href="/">Home</a></p>
</main></body></html>
