<?php
declare(strict_types=1);
use function App\{config, e};
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
$appName = e(config()['app_name'] ?? 'Hillwork');
$year = (int) date('Y');
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Contact · <?= $appName ?></title><link rel="stylesheet" href="/assets/css/paos.css"></head>
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
