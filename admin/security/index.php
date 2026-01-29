<?php
declare(strict_types=1);
use function App\{pdo, e, require_user, config, bump_user_session_version, webauthn_available};
require __DIR__ . '/../../inc/db.php';
require __DIR__ . '/../../inc/auth.php';
require __DIR__ . '/../../inc/csrf.php';
require __DIR__ . '/../../inc/helpers.php';
require __DIR__ . '/../../inc/webauthn.php';
require_once __DIR__ . '/../../inc/totp.php';
require_once __DIR__ . '/../../inc/password_reset.php';

$me = \App\require_user();
$msg = '';
$tab = isset($_GET['tab']) && in_array($_GET['tab'], ['password', 'totp', 'passkeys'], true) ? $_GET['tab'] : 'password';

$stmt = pdo()->prepare("SELECT email, mfa_enabled, mfa_secret, password_hash FROM users WHERE id=?");
$stmt->execute([$me['id']]);
$u = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \App\csrf_verify();
    if (isset($_POST['change_password'])) {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');
        if (!password_verify($current, $u['password_hash'])) {
            $msg = 'Current password is incorrect.';
            $tab = 'password';
        } elseif (strlen($new) < 8) {
            $msg = 'New password must be at least 8 characters.';
            $tab = 'password';
        } elseif ($new !== $confirm) {
            $msg = 'New passwords do not match.';
            $tab = 'password';
        } else {
            $cfg = config();
            $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
            $opts = $algo === PASSWORD_BCRYPT ? ['cost' => (int)($cfg['password_cost'] ?? 12)] : [];
            $hash = password_hash($new, $algo, $opts);
            pdo()->prepare("UPDATE users SET password_hash = ?, password_updated_at = NOW() WHERE id = ?")->execute([$hash, $me['id']]);
            bump_user_session_version($me['id']);
            $msg = 'Password updated. Other sessions have been signed out.';
            $tab = 'password';
        }
    } elseif (isset($_POST['enable_totp'])) {
        $_SESSION['mfa_tmp_secret'] = \App\Totp\random_base32_secret();
        $tab = 'totp';
    } elseif (isset($_POST['verify_totp'])) {
        $secret = $_SESSION['mfa_tmp_secret'] ?? '';
        $code = (string)($_POST['totp_code'] ?? '');
        if ($secret && \App\Totp\verify($secret, $code, 1)) {
            pdo()->prepare("UPDATE users SET mfa_secret=?, mfa_enabled=1, updated_at=NOW() WHERE id=?")->execute([$secret, $me['id']]);
            unset($_SESSION['mfa_tmp_secret']);
            $u['mfa_enabled'] = 1;
            $u['mfa_secret'] = $secret;
            $msg = 'Two-step verification enabled.';
        } else {
            $msg = 'Invalid code.';
        }
        $tab = 'totp';
    } elseif (isset($_POST['disable_totp'])) {
        $code = (string)($_POST['totp_code_disable'] ?? '');
        if (empty($u['mfa_secret']) || !\App\Totp\verify($u['mfa_secret'], $code, 1)) {
            $msg = 'Enter your current authenticator code to disable.';
            $tab = 'totp';
        } else {
            pdo()->prepare("UPDATE users SET mfa_secret=NULL, mfa_enabled=0, updated_at=NOW() WHERE id=?")->execute([$me['id']]);
            unset($_SESSION['mfa_tmp_secret']);
            $u['mfa_enabled'] = 0;
            $u['mfa_secret'] = null;
            $msg = 'Two-step verification disabled.';
            $tab = 'totp';
        }
    }
}

$issuer = config()['app_name'];
$uri = '';
if (!empty($_SESSION['mfa_tmp_secret'])) {
    $uri = \App\Totp\provisioning_uri($_SESSION['mfa_tmp_secret'], $u['email'], $issuer);
}
$csrf = \App\csrf_token();
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Security</title><link rel="stylesheet" href="/assets/css/styles.css"></head>
<body class="theme-light"><main class="container narrow">
  <header class="admin-header">
    <h1>Security</h1>
    <nav>
      <a href="/admin/">Dashboard</a>
      <a href="/admin/profile.php">Profile</a>
      <a href="/admin/links.php">Links</a>
      <a href="/admin/security/">Security</a>
      <a href="/admin/logout.php" class="danger">Logout</a>
    </nav>
  </header>
  <nav class="tabs" role="tablist">
    <a href="/admin/security/?tab=password" class="tab<?= $tab === 'password' ? ' active' : '' ?>" role="tab">Password</a>
    <a href="/admin/security/?tab=totp" class="tab<?= $tab === 'totp' ? ' active' : '' ?>" role="tab">Two‑Step Verification</a>
    <a href="/admin/security/?tab=passkeys" class="tab<?= $tab === 'passkeys' ? ' active' : '' ?>" role="tab">Passkeys</a>
  </nav>
  <?php if ($msg): ?><div class="alert"><?= e($msg) ?></div><?php endif; ?>

  <?php if ($tab === 'password'): ?>
  <section class="card">
    <h2>Change password</h2>
    <p>Requires your current password. After changing, all other sessions will be signed out.</p>
    <form method="post">
      <input type="hidden" name="_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="change_password" value="1">
      <label>Current password<br><input type="password" name="current_password" required autocomplete="current-password"></label>
      <label>New password (min 8 characters)<br><input type="password" name="new_password" required minlength="8" autocomplete="new-password"></label>
      <label>Confirm new password<br><input type="password" name="password_confirm" required minlength="8" autocomplete="new-password"></label>
      <button type="submit">Update password</button>
    </form>
    <p><a href="/password/forgot.php">Forgot password?</a></p>
  </section>
  <?php endif; ?>

  <?php if ($tab === 'totp'): ?>
  <section class="card">
    <h2>Two‑Step Verification (TOTP)</h2>
    <p>Use an authenticator app (e.g. Google Authenticator, Authy) for a second factor when signing in.</p>
    <?php if ((int)$u['mfa_enabled'] === 1): ?>
      <p>Two-step verification is <strong>enabled</strong>.</p>
      <form method="post">
        <input type="hidden" name="_token" value="<?= e($csrf) ?>">
        <label>Enter your current authenticator code to disable<br><input type="text" name="totp_code_disable" inputmode="numeric" pattern="[0-9]{6}" required></label>
        <button class="danger" name="disable_totp" value="1" type="submit">Disable Two‑Step Verification</button>
      </form>
    <?php else: ?>
      <?php if (empty($_SESSION['mfa_tmp_secret'])): ?>
        <form method="post">
          <input type="hidden" name="_token" value="<?= e($csrf) ?>">
          <button name="enable_totp" value="1" type="submit">Enable Two‑Step Verification</button>
        </form>
      <?php else: ?>
        <p>Scan this QR code in your authenticator app, then enter the code below.</p>
        <div id="qr" style="width:180px;height:180px;"></div>
        <p>Or enter this secret manually: <code><?= e($_SESSION['mfa_tmp_secret']) ?></code></p>
        <form method="post">
          <input type="hidden" name="_token" value="<?= e($csrf) ?>">
          <label>Enter code from app<br><input name="totp_code" inputmode="numeric" pattern="[0-9]{6}" required></label>
          <button name="verify_totp" value="1" type="submit">Verify &amp; Enable</button>
        </form>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" crossorigin="anonymous"></script>
        <script>
          document.addEventListener('DOMContentLoaded', function() {
            var el = document.getElementById('qr');
            var uri = <?= json_encode($uri, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            if (el && uri && typeof QRCode !== 'undefined') {
              new QRCode(el, { text: uri, width: 180, height: 180 });
            }
          });
        </script>
      <?php endif; ?>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($tab === 'passkeys'): ?>
  <section class="card">
    <h2>Passkeys</h2>
    <p>Passkeys are phishing-resistant and fast. Sign in with your device (fingerprint, Face ID, or security key) instead of typing a password.</p>
    <p>Compatible with current Chrome, Safari, Edge, and mobile devices. <a href="https://support.apple.com/HT213305" target="_blank" rel="noopener">Learn more</a>.</p>
    <?php if (!webauthn_available()): ?>
      <div class="alert alert-error">
        <p><strong>Passkeys are not available on this server.</strong> They require Composer dependencies. Run <code>composer install</code> locally, then upload the entire <code>vendor/</code> folder to the server (e.g. via FTP/SFTP). See README for IONOS deployment.</p>
      </div>
    <?php else: ?>
      <div id="passkeys-list"></div>
      <p><button type="button" id="add-passkey-btn" class="passkey-add">Add passkey</button></p>
      <p id="passkey-hint" class="muted" style="display:none;">Passkeys require a supported browser (e.g. Chrome, Safari, Edge).</p>
    <?php endif; ?>
  </section>
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <meta name="app-base-path" content="<?= e(rtrim(parse_url(\App\base_url(), PHP_URL_PATH) ?: '', '/')) ?>">
  <script src="/assets/js/webauthn.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      if (typeof window.WebAuthnHelper !== 'undefined' && document.getElementById('passkeys-list') && document.getElementById('add-passkey-btn')) {
        window.WebAuthnHelper.initSecurityPage(document.getElementById('passkeys-list'), document.getElementById('add-passkey-btn'), document.getElementById('passkey-hint'));
      }
    });
  </script>
  <?php endif; ?>
</main></body></html>
