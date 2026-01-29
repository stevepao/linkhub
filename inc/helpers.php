<?php
declare(strict_types=1);
namespace App;

function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sanitize_url(string $u): ?string {
    $u = trim($u);
    if ($u === '') return null;
    $parts = parse_url($u);
    if (!$parts || empty($parts['scheme'])) return null;
    $scheme = strtolower($parts['scheme']);
    if (!in_array($scheme, ['http','https','mailto','tel'], true)) return null;
    return $u;
}

function slugify_username(string $s): string {
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9_]+/', '_', $s);
    $s = trim($s, '_');
    $s = preg_replace('/_+/', '_', $s);
    $len = strlen($s);
    if ($len < 3) $s = str_pad($s, 3, '_');
    if ($len > 32) $s = substr($s, 0, 32);
    return $s;
}

function is_valid_hex_color(string $c): bool {
    return (bool)preg_match('/^#[0-9A-Fa-f]{6}$/', $c);
}

function json_response(array $payload, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

/** True if links table has a description column (run migration to add it). */
function links_has_description(): bool {
    static $has = null;
    if ($has === null) {
        $stmt = \App\pdo()->query(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'links' AND COLUMN_NAME = 'description'"
        );
        $has = (bool) $stmt->fetch();
    }
    return $has;
}
