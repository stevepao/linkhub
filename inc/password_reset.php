<?php
declare(strict_types=1);
namespace App;

/**
 * Password reset token lifecycle: create, find by token, mark used.
 * Store only SHA-512 hash of token; raw token sent once in email/link.
 */
function password_reset_create(int $userId, int $expireMinutes = 30): string {
    $raw = random_bytes(32);
    $tokenForLink = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($raw));
    $hash = hash('sha512', $raw, true);
    // Use UTC so expiry matches MySQL UTC_TIMESTAMP() regardless of server timezone
    $expires = gmdate('Y-m-d H:i:s', time() + $expireMinutes * 60);
    $stmt = pdo()->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $hash, $expires]);
    return $tokenForLink;
}

/** @return array{user_id: int, id: int}|null */
function password_reset_find_valid(string $rawToken): ?array {
    $rawToken = trim($rawToken);
    // Normalize Unicode hyphen-like chars to ASCII hyphen (email clients sometimes replace - with en-dash)
    $rawToken = str_replace(["\xE2\x80\x93", "\xE2\x80\x90", "\xC2\xAD"], '-', $rawToken); // en-dash, hyphen, soft hyphen
    // Strip any characters that aren't base64url (e.g. spaces/newlines from email line wrapping)
    $rawToken = preg_replace('/[^A-Za-z0-9_-]/', '', $rawToken);
    if ($rawToken === '') {
        return null;
    }
    $raw = base64_decode(str_replace(['-', '_'], ['+', '/'], $rawToken) . str_repeat('=', (4 - strlen($rawToken) % 4) % 4), true);
    if ($raw === false || strlen($raw) !== 32) {
        return null;
    }
    $hash = hash('sha512', $raw, true);
    // Compare with UTC so expiry matches what we stored (gmdate)
    $stmt = pdo()->prepare("SELECT id, user_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()");
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    return $row ? ['user_id' => (int)$row['user_id'], 'id' => (int)$row['id']] : null;
}

function password_reset_mark_used(int $resetId): void {
    $pdo = pdo();
    $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")->execute([$resetId]);
}

function bump_user_session_version(int $userId): void {
    pdo()->prepare("UPDATE users SET user_session_version = user_session_version + 1 WHERE id = ?")->execute([$userId]);
}

function get_user_session_version(int $userId): int {
    $stmt = pdo()->prepare("SELECT user_session_version FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return (int)($row['user_session_version'] ?? 0);
}
