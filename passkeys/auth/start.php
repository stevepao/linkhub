<?php
declare(strict_types=1);
use function App\{pdo, webauthn_service, rate_limit_check, rate_limit_identifier, json_response};
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

if (!rate_limit_check('webauthn_auth_start', rate_limit_identifier(), 15, 300)) {
    json_response(['error' => 'Too many requests'], 429);
}

$input = json_decode((string)file_get_contents('php://input'), true);
$email = isset($input['email']) && is_string($input['email']) ? trim($input['email']) : null;
$allowCredentialIds = [];
if ($email !== null && $email !== '') {
    $stmt = pdo()->prepare("SELECT u.id FROM users u INNER JOIN webauthn_credentials w ON w.user_id = u.id WHERE u.email = ?");
    $stmt->execute([$email]);
    $userId = $stmt->fetchColumn();
    if ($userId) {
        $stmt2 = pdo()->prepare("SELECT credential_id FROM webauthn_credentials WHERE user_id = ?");
        $stmt2->execute([$userId]);
        while ($row = $stmt2->fetch()) {
            $allowCredentialIds[] = $row['credential_id'];
        }
    }
}

try {
    if (!\App\webauthn_available()) {
        json_response(['error' => 'Passkeys are not available on this server. Run composer install and upload the vendor/ folder.'], 503);
    }
    $svc = webauthn_service();
    $options = $svc->getRequestOptions($allowCredentialIds, 60, 'preferred');
    $_SESSION['webauthn_auth_challenge'] = $svc->getStoredChallenge();
    $json = json_encode($options, JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        json_response(['error' => 'Failed to encode options'], 500);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo $json;
} catch (\RuntimeException $e) {
    json_response(['error' => $e->getMessage()], 503);
} catch (\Throwable $e) {
    error_log('WebAuthn auth start: ' . $e->getMessage());
    json_response(['error' => 'Server error'], 500);
}
