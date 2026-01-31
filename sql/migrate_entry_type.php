<?php
/**
 * migrate_entry_type.php — Add entry_type (link|heading) to links; allow url NULL for headings.
 * Project: linkhill
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 *
 * Run once from CLI: php sql/migrate_entry_type.php
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/inc/db.php';

$pdo = \App\pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetch();
}

$done = [];
if (!column_exists($pdo, 'links', 'entry_type')) {
    $pdo->exec("ALTER TABLE links ADD COLUMN entry_type ENUM('link','heading') NOT NULL DEFAULT 'link' AFTER user_id");
    $done[] = 'entry_type';
}
$stmt = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'links' AND COLUMN_NAME = 'url' AND IS_NULLABLE = 'NO'");
if ($stmt->fetch()) {
    $pdo->exec("ALTER TABLE links MODIFY COLUMN url VARCHAR(2000) NULL");
    $done[] = 'url nullable';
}
echo empty($done) ? "Already up to date.\n" : "Done: " . implode(', ', $done) . ".\n";
