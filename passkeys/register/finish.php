<?php
declare(strict_types=1);
use function App\{pdo, require_user, webauthn_service, rate_limit_check, rate_limit_identifier, json_response};
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
$user = \App\require_user();

if (!rate_limit_check('webauthn_register_finish', rate_limit_identifier(), 10, 300)) {
    json_response(['error' => 'Too many requests'], 429);
}

$challenge = $_SESSION['webauthn_register_challenge'] ?? null;
unset($_SESSION['webauthn_register_challenge']);
if (!$challenge) {
    json_response(['error' => 'No pending registration'], 400);
}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['clientDataJSON']) || empty($input['attestationObject'])) {
    json_response(['error' => 'Invalid payload'], 400);
}
$clientDataJSON = base64_decode(str_replace(['-', '_'], ['+', '/'], $input['clientDataJSON']) . str_repeat('=', (4 - strlen($input['clientDataJSON']) % 4) % 4), true);
$attestationObject = base64_decode(str_replace(['-', '_'], ['+', '/'], $input['attestationObject']) . str_repeat('=', (4 - strlen($input['attestationObject']) % 4) % 4), true);
if ($clientDataJSON === false || $attestationObject === false) {
    json_response(['error' => 'Invalid base64'], 400);
}

try {
    if (!\App\webauthn_available()) {
        json_response(['error' => 'Passkeys are not available on this server. Run composer install and upload the vendor/ folder.'], 503);
    }
    $svc = webauthn_service();
    $data = $svc->processCreate($clientDataJSON, $attestationObject, $challenge);
    $nickname = isset($input['nickname']) && is_string($input['nickname']) ? substr(trim($input['nickname']), 0, 100) : null;
    $aaguid = $data->aaguid ?? null;
    $aaguidBin = is_string($aaguid) ? $aaguid : (is_object($aaguid) && method_exists($aaguid, 'getBinaryString') ? $aaguid->getBinaryString() : null);
    if ($aaguidBin !== null && strlen($aaguidBin) !== 16) {
        $aaguidBin = null;
    }
    pdo()->prepare("
        INSERT INTO webauthn_credentials (user_id, credential_id, public_key, sign_count, aaguid, attestation_format, nickname, backup_eligible, backup_state, uv_initialized)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $user['id'],
        $data->credentialId,
        $data->credentialPublicKey,
        (int)$data->signCount,
        $aaguidBin,
        $data->attestationFormat ?? null,
        $nickname,
        !empty($data->backupEligible) ? 1 : 0,
        !empty($data->backedUp) ? 1 : 0,
        !empty($data->userVerified) ? 1 : 0,
    ]);
    json_response(['ok' => true]);
} catch (\RuntimeException $e) {
    json_response(['error' => $e->getMessage()], 503);
} catch (\Throwable $e) {
    error_log('WebAuthn register finish: ' . $e->getMessage());
    json_response(['error' => 'Verification failed'], 400);
}
