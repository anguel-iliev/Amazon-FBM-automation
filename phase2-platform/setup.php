#!/usr/bin/env php
<?php
/**
 * AMZ Retail — Setup script v1.2
 * Стартирай: php setup.php
 *
 * Какво прави:
 *   1. Създава .env от .env.example (ако не съществува)
 *   2. Пита за Gmail App Password и го записва в .env
 *   3. Създава нужните директории (data/, data/logs/, data/cache/)
 *   4. Създава първия admin потребител
 */
define('ROOT', __DIR__);
define('SRC',  ROOT . '/src');
define('TOKEN_EXPIRY', 86400);

// ─────────────────────────────────────────────────────────────────
// 1. .env setup
// ─────────────────────────────────────────────────────────────────
$envFile     = ROOT . '/.env';
$envExample  = ROOT . '/.env.example';

if (!file_exists($envFile)) {
    if (file_exists($envExample)) {
        copy($envExample, $envFile);
        echo "✓ Created .env from .env.example\n";
    } else {
        // Create minimal .env inline
        file_put_contents($envFile, implode("\n", [
            'APP_URL=https://amz-retail.tnsoft.eu',
            'APP_DEBUG=false',
            'SMTP_HOST=smtp.gmail.com',
            'SMTP_PORT=587',
            'SMTP_USER=tnsoftsales@gmail.com',
            'SMTP_PASS=',
            'SMTP_FROM=tnsoftsales@gmail.com',
            'SMTP_FROM_NAME=AMZ Retail',
            'GOOGLE_DRIVE_FOLDER_ID=100T4KgyVIXhKlJczQv7DR9CJlV27DbUx',
            'GOOGLE_SHEET_ID=',
            '',
        ]));
        echo "✓ Created minimal .env\n";
    }
} else {
    echo "✓ .env already exists — skipping creation.\n";
}

// ─────────────────────────────────────────────────────────────────
// Helper: read .env into array
// ─────────────────────────────────────────────────────────────────
function readEnv(string $file{
    $map = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) { $map[] = $line; continue; }
        if (strpos($line, '=') === false)   { $map[] = $line; continue; }
        [$k, $v] = explode('=', $line, 2);
        $map[trim($k)] = trim($v);
    }
    return $map;
}

// ─────────────────────────────────────────────────────────────────
// Helper: write .env from array
// ─────────────────────────────────────────────────────────────────
function writeEnv(string $file, array $map{
    $lines = [];
    foreach ($map as $k => $v) {
        if (is_int($k)) { $lines[] = $v; } // comments / blank lines
        else            { $lines[] = "{$k}={$v}"; }
    }
    file_put_contents($file, implode("\n", $lines) . "\n", LOCK_EX);
}

// ─────────────────────────────────────────────────────────────────
// 2. SMTP_PASS prompt
// ─────────────────────────────────────────────────────────────────
$envMap  = readEnv($envFile);
$curPass = $envMap['SMTP_PASS'] ?? '';

echo "\n===========================================\n";
echo " AMZ Retail Platform — Setup v1.2\n";
echo "===========================================\n\n";

echo "──────────────────────────────────────────\n";
echo " Gmail App Password (SMTP_PASS)\n";
echo "──────────────────────────────────────────\n";
echo "  Стъпки:\n";
echo "    1. https://myaccount.google.com\n";
echo "    2. Security → 2-Step Verification (трябва да е ON)\n";
echo "    3. App passwords → Add → Mail / Other: \"AMZ Retail\"\n";
echo "    4. Копирай 16-символната парола (xxxx xxxx xxxx xxxx)\n\n";

if (!empty($curPass) && $curPass !== 'your_16char_app_password_here') {
    echo "  Текуща стойност: " . str_repeat('*', strlen($curPass)) . " (скрита)\n";
    echo "  Въведи нова (или Enter за запазване на старата): ";
} else {
    echo "  Въведи Gmail App Password: ";
}

$smtpPass = trim(fgets(STDIN));

if (!empty($smtpPass)) {
    // Strip spaces (Google shows it as "xxxx xxxx xxxx xxxx")
    $smtpPass = str_replace(' ', '', $smtpPass);
    $envMap['SMTP_PASS'] = $smtpPass;
    writeEnv($envFile, $envMap);
    echo "✓ SMTP_PASS записан в .env\n\n";
} else {
    echo "  (SMTP_PASS непроменен)\n\n";
}

// ─────────────────────────────────────────────────────────────────
// 3. Create directories
// ─────────────────────────────────────────────────────────────────
foreach (['data', 'data/logs', 'data/cache', 'data/cache/tmp_downloads'] as $dir) {
    $path = ROOT . '/' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
        echo "✓ Created {$dir}/\n";
    }
}
file_put_contents(ROOT . '/data/.htaccess', "Deny from all\n");

define('DATA_DIR', ROOT . '/data');

// ─────────────────────────────────────────────────────────────────
// 4. Create admin user
// ─────────────────────────────────────────────────────────────────
require_once SRC . '/lib/UserStore.php';

echo "──────────────────────────────────────────\n";
echo " Admin потребител\n";
echo "──────────────────────────────────────────\n";

echo "Enter admin email: ";
$email = trim(fgets(STDIN));

echo "Enter admin password (min 8 chars): ";
$password = trim(fgets(STDIN));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "✗ Невалиден имейл.\n"; exit(1);
}
if (strlen($password) < 8) {
    echo "✗ Паролата е твърде кратка (мин. 8 символа).\n"; exit(1);
}

// Check existing
$users = UserStore::all();
foreach ($users as $u) {
    if (strtolower($u['email']) === strtolower($email)) {
        echo "⚠  Потребителят вече съществува — пропускам създаването.\n";
        echo "✅ Open: https://amz-retail.tnsoft.eu\n\n";
        exit(0);
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

echo "\n✅ Admin създаден: {$email}\n";
echo "✅ Отвори: https://amz-retail.tnsoft.eu\n\n";
echo "Следващи стъпки:\n";
echo "  → Влез с имейла и паролата\n";
echo "  → Settings → добави Google Sheet ID и credentials.json\n";
echo "  → Admin menu → /invite → покани останалите потребители\n\n";
