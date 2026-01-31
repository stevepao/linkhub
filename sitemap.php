<?php
/**
 * sitemap.php — Serve sites.xml (sitemap) with correct content type.
 * Project: linkhill
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
require __DIR__ . '/inc/sites_xml.php';

$path = \App\sites_xml_path();
if (!is_file($path)) {
    \App\sites_xml_rebuild();
}
if (!is_file($path)) {
    http_response_code(404);
    exit('Sitemap not available.');
}
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
readfile($path);
