<?php
/**
 * icons.php — Icon list and render.
 * Project: linkhill
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
declare(strict_types=1);
namespace App;

function icon_list(): array {
    return [
        'apple-podcasts' => '/assets/icons/apple-podcasts.svg',
        'bluesky'        => '/assets/icons/bluesky.svg',
        'calendly'       => '/assets/icons/calendly.svg',
        'email'          => '/assets/icons/email.svg',
        'facebook'       => '/assets/icons/facebook.svg',
        'github'         => '/assets/icons/github.svg',
        'home'           => '/assets/icons/home.svg',
        'instagram'      => '/assets/icons/instagram.svg',
        'link'           => '/assets/icons/link.svg',
        'linkedin'       => '/assets/icons/linkedin.svg',
        'mastodon'       => '/assets/icons/mastodon.svg',
        'medium'         => '/assets/icons/medium.svg',
        'paypal'         => '/assets/icons/paypal.svg',
        'phone'          => '/assets/icons/phone.svg',
        'spotify'        => '/assets/icons/spotify.svg',
        'substack'       => '/assets/icons/substack.svg',
        'threads'        => '/assets/icons/threads.svg',
        'tiktok'         => '/assets/icons/tiktok.svg',
        'vcard'          => '/assets/icons/vcard.svg',
        'venmo'          => '/assets/icons/venmo.svg',
        'website'        => '/assets/icons/website.svg',
        'wordpress'      => '/assets/icons/wordpress.svg',
        'x'              => '/assets/icons/x.svg',
        'youtube'        => '/assets/icons/youtube.svg',
    ];
}

function render_icon_svg(string $slug): string {
    $map = icon_list();
    if (!isset($map[$slug])) return '';
    $path = $_SERVER['DOCUMENT_ROOT'] . $map[$slug];
    if (!is_file($path)) return '';
    $svg = file_get_contents($path);
    // Minimal sanitization: strip script tags
    $svg = preg_replace('#<\s*script[^>]*>.*?<\s*/\s*script\s*>#is', '', $svg);
    return $svg ?: '';
}
