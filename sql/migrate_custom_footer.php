<?php
/**
 * migrate_custom_footer.php — Add custom_footer column to users.
 * Project: linkhill
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 *
 * Run once from CLI: php sql/migrate_custom_footer.php
 * Or from browser (ensure docroot doesn’t serve sql/ or protect the URL).
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

if (column_exists($pdo, 'users', 'custom_footer')) {
    echo "Column users.custom_footer already exists. Nothing to do.\n";
    exit(0);
}

$pdo->exec("ALTER TABLE users ADD COLUMN custom_footer TEXT NULL AFTER bio");
echo "Added users.custom_footer. Done.\n";
