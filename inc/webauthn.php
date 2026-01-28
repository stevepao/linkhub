<?php
declare(strict_types=1);
namespace App;

// Load our WebAuthn wrapper classes (do not rely on Composer autoload for app code)
require_once __DIR__ . '/webauthn_service.php';
require_once __DIR__ . '/webauthn_lbuchs.php';

use App\WebAuthn\WebAuthnServiceInterface;
use App\WebAuthn\WebAuthnLbuchs;

/** True if Composer vendor/autoload exists so passkeys can be used (e.g. after composer install). */
function webauthn_available(): bool {
    static $available = null;
    if ($available !== null) {
        return $available;
    }
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    $available = is_file($autoload);
    return $available;
}

/**
 * @return WebAuthnServiceInterface
 * @throws \RuntimeException if vendor/autoload.php is missing (run composer install).
 */
function webauthn_service(): WebAuthnServiceInterface {
    static $instance = null;
    if ($instance instanceof WebAuthnServiceInterface) {
        return $instance;
    }
    if (!webauthn_available()) {
        throw new \RuntimeException('Passkeys require Composer dependencies. Run "composer install" and upload the vendor/ folder.');
    }
    $cfg = config();
    $wa = $cfg['webauthn'] ?? [];
    $rpId = $wa['rp_id'] ?? '';
    $rpName = $wa['rp_name'] ?? $cfg['app_name'] ?? 'LinkHub';
    $origin = $wa['origin'] ?? base_url();
    if ($rpId === '') {
        $host = parse_url($origin, PHP_URL_HOST);
        $rpId = $host ?: 'localhost';
    }
    $instance = new WebAuthnLbuchs($rpName, $rpId, $origin, ['none']);
    return $instance;
}
