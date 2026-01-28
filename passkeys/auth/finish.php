<?php
declare(strict_types=1);
use function App\{pdo, finish_login, webauthn_service, rate_limit_check, rate_limit_identifier, json_response};
require __DIR__ . '/../../inc/db.php';
require __DIR__ . '/../../inc/auth.php';
require __DIR__ . '/../../inc/csrf.php';
require __DIR__ . '/../../inc/helpers.php';
require __DIR__ . '/../../inc/webauthn.php';
require __DIR__ . '/../../inc/rate_limit.php';
\App\session_boot();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
\App\csrf_verify();

if (!rate_limit_check('webauthn_auth_finish', rate_limit_identifier(), 15, 300)) {
    json_response(['error' => 'Too many requests'], 429);
}

$challenge = $_SESSION['webauthn_auth_challenge'] ?? null;
unset($_SESSION['webauthn_auth_challenge']);
if (!$challenge) {
    json_response(['error' => 'No pending authentication'], 400);
}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['clientDataJSON']) || empty($input['authenticatorData']) || empty($input['signature']) || empty($input['credentialId'])) {
    json_response(['error' => 'Invalid payload'], 400);
}

$credentialIdRaw = base64_decode(str_replace(['-', '_'], ['+', '/'], $input['credentialId']) . str_repeat('=', (4 - strlen($input['credentialId']) % 4) % 4), true);
if ($credentialIdRaw === false) {
    json_response(['error' => 'Invalid credential id'], 400);
}

$stmt = pdo()->prepare("
    SELECT w.id, w.user_id, w.public_key, w.sign_count, u.email, u.username, u.display_name, u.role, u.user_session_version
    FROM webauthn_credentials w
    INNER JOIN users u ON u.id = w.user_id
    WHERE w.credential_id = ?
");
$stmt->execute([$credentialIdRaw]);
$row = $stmt->fetch();
if (!$row) {
    json_response(['error' => 'Unknown credential'], 400);
}

$clientDataJSON = base64_decode(str_replace(['-', '_'], ['+', '/'], $input['clientDataJSON']) . str_repeat('=', (4 - strlen($input['clientDataJSON']) % 4) % 4), true);
$authenticatorData = base64_decode(str_replace(['-', '_'], ['+', '/'], $input['authenticatorData']) . str_repeat('=', (4 - strlen($input['authenticatorData']) % 4) % 4), true);
$signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $input['signature']) . str_repeat('=', (4 - strlen($input['signature']) % 4) % 4), true);
if ($clientDataJSON === false || $authenticatorData === false || $signature === false) {
    json_response(['error' => 'Invalid base64'], 400);
}

try {
    if (!\App\webauthn_available()) {
        json_response(['error' => 'Passkeys are not available on this server. Run composer install and upload the vendor/ folder.'], 503);
    }
    $svc = webauthn_service();
    $ok = $svc->processGet(
        $clientDataJSON,
        $authenticatorData,
        $signature,
        $row['public_key'],
        $challenge,
        (int)$row['sign_count']
    );
    if (!$ok) {
        json_response(['error' => 'Verification failed'], 400);
    }
    $newSignCount = $svc->getSignatureCounter();
    pdo()->prepare("UPDATE webauthn_credentials SET sign_count = ?, last_used_at = NOW() WHERE id = ?")->execute([$newSignCount ?? (int)$row['sign_count'], $row['id']]);
    \App\finish_login(
        (int)$row['user_id'],
        $row['email'],
        $row['username'],
        $row['role'],
        $row['display_name'] ?: $row['email'],
        (int)$row['user_session_version']
    );
    json_response(['ok' => true, 'redirect' => '/admin/']);
} catch (\RuntimeException $e) {
    json_response(['error' => $e->getMessage()], 503);
} catch (\Throwable $e) {
    error_log('WebAuthn auth finish: ' . $e->getMessage());
    json_response(['error' => 'Verification failed'], 400);
}
