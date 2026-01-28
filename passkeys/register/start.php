<?php
declare(strict_types=1);
ob_start();
use function App\{pdo, require_user, webauthn_service, rate_limit_check, rate_limit_identifier, json_response};
require __DIR__ . '/../../inc/db.php';
require __DIR__ . '/../../inc/auth.php';
require __DIR__ . '/../../inc/csrf.php';
require __DIR__ . '/../../inc/helpers.php';
require __DIR__ . '/../../inc/webauthn.php';
require __DIR__ . '/../../inc/rate_limit.php';
\App\session_boot();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    exit;
}
\App\csrf_verify();
$user = \App\require_user();

if (!rate_limit_check('webauthn_register_start', rate_limit_identifier(), 10, 300)) {
    json_response(['error' => 'Too many requests'], 429);
}

try {
    if (!\App\webauthn_available()) {
        json_response(['error' => 'Passkeys are not available on this server. Run composer install and upload the vendor/ folder.'], 503);
    }
    $stmt = pdo()->prepare("SELECT webauthn_user_handle, email, display_name FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $u = $stmt->fetch();
    if (!$u || $u['webauthn_user_handle'] === null) {
        json_response(['error' => 'User handle not set'], 400);
    }
    $userHandle = $u['webauthn_user_handle'];
    $credIds = [];
    $rows = pdo()->prepare("SELECT credential_id FROM webauthn_credentials WHERE user_id = ?");
    $rows->execute([$user['id']]);
    while ($row = $rows->fetch()) {
        $credIds[] = $row['credential_id'];
    }
    $svc = webauthn_service();
    $options = $svc->getCreationOptions(
        $userHandle,
        $u['email'],
        $u['display_name'] ?: $u['email'],
        $credIds,
        60,
        'preferred',
        'preferred'
    );
    $_SESSION['webauthn_register_challenge'] = $svc->getStoredChallenge();
    $json = json_encode($options, JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        ob_end_clean();
        json_response(['error' => 'Failed to encode options'], 500);
    }
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo $json;
} catch (\RuntimeException $e) {
    ob_end_clean();
    json_response(['error' => $e->getMessage()], 503);
} catch (\Throwable $e) {
    error_log('WebAuthn register start: ' . $e->getMessage());
    ob_end_clean();
    $msg = 'Server error';
    if (\App\config()['dev_mode'] ?? false) {
        $msg = $e->getMessage();
    }
    json_response(['error' => $msg], 500);
}
