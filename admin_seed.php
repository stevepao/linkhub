<?php
/**
 * admin_seed.php — One-time script to create the first admin user.
 * Project: linkhill
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2026 Hillwork, LLC
 *
 * Run once after importing schema.sql, then delete this file from the server.
 */
declare(strict_types=1);
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';

header('Content-Type: text/plain; charset=utf-8');
echo "LinkHill — Seed first admin\n";
echo str_repeat('-', 40) . "\n";

$email = 'admin@example.com';
$username = 'admin';
$displayName = 'Administrator';
$password = 'ChangeMeNow!123';

$cfg = \App\config();
$hash = password_hash($password, PASSWORD_DEFAULT, ['cost' => (int)($cfg['password_cost'] ?? 12)]);

try {
    $stmt = \App\pdo()->prepare(
        "INSERT INTO users (email, username, display_name, password_hash, role) VALUES (?, ?, ?, ?, 'admin')"
    );
    $stmt->execute([$email, $username, $displayName, $hash]);
    echo "Admin user created.\n";
    echo "  Email:    {$email}\n";
    echo "  Password: {$password}\n";
    echo "\n** IMPORTANT: Sign in at /login, change the password in Security, then delete admin_seed.php from the server. **\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "If the user already exists, sign in at /login or delete the user in the database and run this again.\n";
}
