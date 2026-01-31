<?php
/**
 * sites_xml.php — Build and maintain sites.xml (sitemap) for SEO.
 * Project: linkhill
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 *
 * Contains: static public pages (home, about, contact, privacy, terms) and
 * profile pages for users who have at least one active link.
 * Rebuild when: links created/updated/deleted, user deleted.
 */
declare(strict_types=1);
namespace App;

/** Path to the generated sitemap file (storage dir, writable). */
function sites_xml_path(): string {
    return __DIR__ . '/../storage/sites.xml';
}

/** Static public URLs (no login). Include only pages we want indexed. */
function sites_xml_static_urls(): array {
    $base = rtrim(base_url(), '/');
    return [
        ['loc' => $base . '/', 'changefreq' => 'weekly', 'priority' => '1.0'],
        ['loc' => $base . '/about', 'changefreq' => 'monthly', 'priority' => '0.8'],
        ['loc' => $base . '/contact', 'changefreq' => 'monthly', 'priority' => '0.8'],
        ['loc' => $base . '/privacy', 'changefreq' => 'monthly', 'priority' => '0.6'],
        ['loc' => $base . '/terms', 'changefreq' => 'monthly', 'priority' => '0.6'],
    ];
}

/** User IDs and usernames that have at least one active link (entry_type=link, non-empty url). */
function sites_xml_users_with_links(): array {
    $sql = "SELECT u.id, u.username,
            GREATEST(COALESCE(u.updated_at, u.created_at), COALESCE(MAX(l.updated_at), u.created_at)) AS lastmod
            FROM users u
            INNER JOIN links l ON l.user_id = u.id AND l.is_active = 1 AND l.entry_type = 'link'
                AND l.url IS NOT NULL AND TRIM(l.url) != ''
            GROUP BY u.id, u.username, u.updated_at, u.created_at";
    $stmt = pdo()->query($sql);
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

/** Build full sitemap XML and write to storage/sites.xml. Call after link or user changes. */
function sites_xml_rebuild(): bool {
    $base = rtrim(base_url(), '/');
    $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach (sites_xml_static_urls() as $row) {
        $out .= '  <url><loc>' . htmlspecialchars($row['loc'], ENT_XML1, 'UTF-8') . '</loc>';
        if (!empty($row['changefreq'])) $out .= '<changefreq>' . htmlspecialchars($row['changefreq'], ENT_XML1, 'UTF-8') . '</changefreq>';
        if (!empty($row['priority'])) $out .= '<priority>' . htmlspecialchars($row['priority'], ENT_XML1, 'UTF-8') . '</priority>';
        $out .= '</url>' . "\n";
    }

    foreach (sites_xml_users_with_links() as $u) {
        $loc = $base . '/@' . $u['username'];
        $lastmod = !empty($u['lastmod']) ? date('Y-m-d', strtotime($u['lastmod'])) : date('Y-m-d');
        $out .= '  <url><loc>' . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . '</loc><lastmod>' . $lastmod . '</lastmod><changefreq>weekly</changefreq><priority>0.7</priority></url>' . "\n";
    }

    $out .= '</urlset>';
    $path = sites_xml_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return @file_put_contents($path, $out) !== false;
}
