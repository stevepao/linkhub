<?php
/**
 * contact.php — Contact page.
 * Project: linkhill
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
declare(strict_types=1);
use function App\{config, e, base_url};
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
$appName = e(config()['app_name'] ?? 'Hillwork');
$canonical = rtrim(base_url(), '/') . '/contact';
$metaDesc = 'Contact ' . $appName . ' — support and feedback for our free Linktree alternative and link-in-bio tool.';
$metaKeywords = 'contact ' . $appName . ', Linktree alternative support, link in bio help';
$year = (int) date('Y');
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Contact · <?= $appName ?> — Free Link in Bio</title>
<meta name="description" content="<?= e($metaDesc) ?>">
<meta name="keywords" content="<?= e($metaKeywords) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:title" content="Contact · <?= $appName ?>">
<meta property="og:description" content="<?= e($metaDesc) ?>">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="Contact · <?= $appName ?>">
<meta name="twitter:description" content="<?= e($metaDesc) ?>">
<link rel="stylesheet" href="/assets/css/linkhill.css"></head>
<body class="theme-light">
  <main class="container">
    <div class="stack">
      <h1>Contact</h1>
      <p>For support or feedback, contact the <a href="/@administrator">administrator</a>.</p>
      <p><a href="/">Home</a></p>
    </div>
  </main>
  <footer class="footer"><div class="container"><nav aria-label="Footer"><a href="/about">About</a><a href="/privacy">Privacy</a><a href="/terms">Terms</a><a href="/contact">Contact</a></nav><p class="footer-copy">© <?= $year ?> <a href="https://hillwork.us">Hillwork, LLC</a></p></div></footer>
</body></html>
