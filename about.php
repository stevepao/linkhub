<?php
/**
 * about.php — About page.
 * Project: linkhill
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 */
declare(strict_types=1);
use function App\{config, e, base_url};
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
$appName = e(config()['app_name'] ?? 'Hillwork');
$canonical = rtrim(base_url(), '/') . '/about';
$metaDesc = $appName . ' is a free Linktree alternative. Create a clean, customizable link-in-bio page. No paywalls. Own your data. Best free link in bio tool.';
$metaKeywords = 'Linktree alternative, link in bio, about ' . $appName . ', free link page';
$year = (int) date('Y');
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>About · <?= $appName ?> — Free Linktree Alternative</title>
<meta name="description" content="<?= e($metaDesc) ?>">
<meta name="keywords" content="<?= e($metaKeywords) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:title" content="About · <?= $appName ?> — Free Linktree Alternative">
<meta property="og:description" content="<?= e($metaDesc) ?>">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="About · <?= $appName ?>">
<meta name="twitter:description" content="<?= e($metaDesc) ?>">
<link rel="stylesheet" href="/assets/css/linkhill.css"></head>
<body class="theme-light">
  <main class="container">
    <div class="stack">
      <h1>About</h1>
      <p><?= $appName ?> is a free, open alternative to Linktree. Create a clean, customizable link‑in‑bio page. No paywalls. Own your data.</p>
      <p><a href="/">Home</a></p>
    </div>
  </main>
  <footer class="footer"><div class="container"><nav aria-label="Footer"><a href="/about">About</a><a href="/privacy">Privacy</a><a href="/terms">Terms</a><a href="/contact">Contact</a></nav><p class="footer-copy">© <?= $year ?> <a href="https://hillwork.us">Hillwork, LLC</a></p></div></footer>
</body></html>
