<?php
declare(strict_types=1);
use function App\{config, e, pdo, base_url, send_mail, rate_limit_check, rate_limit_identifier_with_email, password_reset_create, csrf_token, csrf_field, csrf_verify};
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/helpers.php';
require __DIR__ . '/../inc/csrf.php';
require __DIR__ . '/../inc/mail.php';
require __DIR__ . '/../inc/rate_limit.php';
require_once __DIR__ . '/../inc/password_reset.php';
\App\session_boot();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim((string)($_POST['email'] ?? ''));
    if ($email === '') {
        $msg = 'Please enter your email address.';
    } else {
        $identifier = rate_limit_identifier_with_email($email);
        if (!rate_limit_check('password_reset_request', $identifier, 5, 3600)) {
            $msg = 'Too many requests. Please try again later.';
        } else {
            $stmt = pdo()->prepare("SELECT id, email FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $u = $stmt->fetch();
            if ($u) {
                $tokenForLink = password_reset_create((int)$u['id'], 30);
                $origin = base_url();
                $resetUrl = $origin . '/password/reset.php?token=' . $tokenForLink;
                $appName = config()['app_name'] ?? 'LinkHub';
                $bodyText = "You requested a password reset. Click the link below (valid 30 minutes):\n\n" . $resetUrl . "\n\nIf you did not request this, ignore this email.";
                $bodyHtml = '<p>You requested a password reset. Click the link below (valid 30 minutes):</p><p><a href="' . e($resetUrl) . '">Reset password</a></p><p>If you did not request this, ignore this email.</p>';
                $sent = send_mail($u['email'], 'Password reset - ' . $appName, $bodyText, $bodyHtml);
                if (!$sent && (config()['dev_mode'] ?? false)) {
                    error_log('[LinkHub password reset] ' . $resetUrl);
                }
            }
            header('Location: /password/reset_sent.php');
            exit;
        }
    }
}
$success = false;
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Forgot password · <?= e(config()['app_name']) ?></title><link rel="stylesheet" href="/assets/css/styles.css"></head>
<body class="theme-light"><main class="container narrow">
  <h1>Forgot password</h1>
  <?php if ($msg): ?><div class="alert alert-error"><?= e($msg) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
    <label>Email<br><input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></label>
    <button type="submit">Send reset link</button>
  </form>
  <p><a href="/admin/login.php">Back to sign in</a></p>
</main></body></html>
