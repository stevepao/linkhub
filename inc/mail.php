<?php
declare(strict_types=1);
namespace App;

/**
 * Send email via SMTP (PHPMailer). If SMTP not configured and dev_mode is true, log the link/body instead.
 * Returns true if sent or logged in dev; false on failure.
 */
function send_mail(string $to, string $subject, string $bodyText, ?string $bodyHtml = null): bool {
    $cfg = config();
    $smtp = $cfg['smtp'] ?? [];
    $devMode = (bool)($cfg['dev_mode'] ?? false);
    $from = $smtp['from'] ?? 'noreply@localhost';
    $fromName = $smtp['from_name'] ?? ($cfg['app_name'] ?? 'LinkHub');

    if (empty($smtp['host']) || empty($smtp['user'])) {
        if ($devMode) {
            error_log("[LinkHub mail] To: {$to}, Subject: {$subject}\nBody: {$bodyText}");
            return true;
        }
        return false;
    }

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        if ($devMode) {
            error_log("[LinkHub mail] PHPMailer not installed (vendor/autoload.php missing). Would send: To: {$to}, Subject: {$subject}\nBody: {$bodyText}");
            return true;
        }
        return false;
    }
    require_once $autoload;

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $mail->Encoding = 'base64';
        $mail->isSMTP();
        $mail->Host = $smtp['host'];
        $mail->Port = (int)($smtp['port'] ?? 587);
        $mail->SMTPAuth = true;
        $mail->Username = $smtp['user'];
        $mail->Password = $smtp['pass'];
        $secure = strtolower($smtp['secure'] ?? 'tls');
        $mail->SMTPSecure = $secure === 'ssl' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->setFrom($from, $fromName);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $bodyHtml ?? $bodyText;
        $mail->AltBody = $bodyText;
        if ($bodyHtml !== null) {
            $mail->isHTML(true);
        }
        $mail->send();
        return true;
    } catch (\Throwable $e) {
        if ($devMode) {
            error_log("[LinkHub mail] Send failed: " . $e->getMessage() . "\nWould send: To: {$to}, Subject: {$subject}\nBody: {$bodyText}");
        }
        return false;
    }
}
