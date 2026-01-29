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

/** Returns #ffffff or #111111 for readable text on the given hex background. */
function link_contrast_text(string $hex): string {
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) return '#111111';
    $r = hexdec(substr($hex, 0, 2)) / 255;
    $g = hexdec(substr($hex, 2, 2)) / 255;
    $b = hexdec(substr($hex, 4, 2)) / 255;
    $r = $r <= 0.03928 ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
    $g = $g <= 0.03928 ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
    $b = $b <= 0.03928 ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);
    $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    return $luminance > 0.5 ? '#111111' : '#ffffff';
}

/** Darken hex by mixing with black (0–1, e.g. 0.15 = 15% darker). */
function link_darken(string $hex, float $amount = 0.15): string {
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) return '#' . $hex;
    $r = max(0, (int)round(hexdec(substr($hex, 0, 2)) * (1 - $amount)));
    $g = max(0, (int)round(hexdec(substr($hex, 2, 2)) * (1 - $amount)));
    $b = max(0, (int)round(hexdec(substr($hex, 4, 2)) * (1 - $amount)));
    return '#' . sprintf('%02x%02x%02x', min(255, $r), min(255, $g), min(255, $b));
}

/** Returns rgba() string for muted text on a link card (contrast color at ~85% opacity). */
function link_muted_rgba(string $hex): string {
    $text = link_contrast_text($hex);
    if ($text === '#ffffff') return 'rgba(255,255,255,0.85)';
    return 'rgba(17,17,17,0.85)';
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
