#!/usr/bin/env php
<?php
/**
 * AMZ Retail — Setup script
 * Стартирай: php setup.php
 * Създава първия admin потребител директно.
 */
define('ROOT', __DIR__);
define('SRC',  ROOT . '/src');
define('TOKEN_EXPIRY', 86400);

// Minimal env load
$envFile = ROOT . '/.env';
if (!file_exists($envFile) && file_exists(ROOT . '/.env.example')) {
    copy(ROOT . '/.env.example', $envFile);
    echo "✓ Created .env from .env.example\n";
}

foreach (['data', 'data/logs', 'data/cache', 'data/cache/tmp_downloads'] as $dir) {
    $path = ROOT . '/' . $dir;
    if (!is_dir($path)) { mkdir($path, 0755, true); echo "✓ Created {$dir}/\n"; }
}

file_put_contents(ROOT . '/data/.htaccess', "Deny from all\n");

define('DATA_DIR', ROOT . '/data');

require_once SRC . '/lib/UserStore.php';

echo "\n===========================================\n";
echo " AMZ Retail Platform — Setup v1.1\n";
echo "===========================================\n\n";

echo "Enter admin email: ";
$email = trim(fgets(STDIN));

echo "Enter admin password (min 8 chars): ";
$password = trim(fgets(STDIN));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo "Invalid email.\n"; exit(1); }
if (strlen($password) < 8) { echo "Password too short.\n"; exit(1); }

// Create admin user directly
$users = UserStore::all();
foreach ($users as $u) {
    if (strtolower($u['email']) === strtolower($email)) {
        echo "User already exists.\n"; exit(1);
    }
}

$admin = [
    'id'             => bin2hex(random_bytes(8)),
    'email'          => strtolower($email),
    'password_hash'  => password_hash($password, PASSWORD_BCRYPT),
    'verified'       => true,
    'invited'        => false,
    'verify_token'   => '',
    'verify_expires' => 0,
    'reset_token'    => '',
    'reset_expires'  => 0,
    'invited_by'     => 'setup',
    'created_at'     => date('c'),
    'last_login'     => null,
    'role'           => 'admin',
];

$users[] = $admin;
file_put_contents(
    DATA_DIR . '/users.json',
    json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    LOCK_EX
);

echo "\n✅ Admin created: {$email}\n";
echo "✅ Open: https://amz-retail.tnsoft.eu\n\n";
echo "Next: Edit .env → set SMTP_PASS (Gmail App Password)\n";
echo "Then: Login → Settings → Add Google credentials\n";
echo "Then: Admin menu → /invite → invite other users\n\n";
