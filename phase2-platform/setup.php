#!/usr/bin/env php
<?php
/**
 * AMZ Retail — Setup script
 * Стартирай: php setup.php
 */

define('ROOT', __DIR__);

echo "\n===========================================\n";
echo " AMZ Retail Platform — Setup\n";
echo "===========================================\n\n";

// Check PHP version
if (PHP_VERSION_ID < 80000) {
    echo "ERROR: PHP 8.0+ required. Current: " . PHP_VERSION . "\n";
    exit(1);
}

// Check .env
$envFile = ROOT . '/.env';
if (!file_exists($envFile)) {
    copy(ROOT . '/.env.example', $envFile);
    echo "✓ Created .env from .env.example\n";
} else {
    echo "✓ .env already exists\n";
}

// Create data directories
foreach (['data', 'data/logs', 'data/cache', 'data/cache/tmp_downloads'] as $dir) {
    $path = ROOT . '/' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
        echo "✓ Created $dir/\n";
    }
}

// Create .htaccess for data dir protection
$dataHtaccess = ROOT . '/data/.htaccess';
if (!file_exists($dataHtaccess)) {
    file_put_contents($dataHtaccess, "Deny from all\n");
    echo "✓ Protected data/ directory\n";
}

// Generate password hash
echo "\n--- Set admin password ---\n";
echo "Enter password for admin user: ";
$handle   = fopen("php://stdin", "r");
$password = trim(fgets($handle));
fclose($handle);

if (strlen($password) < 6) {
    echo "ERROR: Password must be at least 6 characters.\n";
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT);

// Update .env
$env = file_get_contents($envFile);
$env = preg_replace('/^AUTH_PASSWORD=.*/m', 'AUTH_PASSWORD=' . $hash, $env);
if (!str_contains($env, 'AUTH_PASSWORD=')) {
    $env .= "\nAUTH_PASSWORD=" . $hash;
}
file_put_contents($envFile, $env);

echo "✓ Password set and saved to .env\n";

// Check Python
echo "\n--- Python check ---\n";
$pythonVersion = shell_exec('python3 --version 2>&1');
echo "Python: " . ($pythonVersion ?: "NOT FOUND") . "\n";

$pip = shell_exec('pip3 show gspread 2>&1');
if (str_contains($pip ?? '', 'Version:')) {
    echo "✓ gspread installed\n";
} else {
    echo "⚠ gspread not installed. Run:\n";
    echo "  pip3 install gspread google-auth openpyxl pandas --user\n";
}

echo "\n✅ Setup complete!\n";
echo "Open: https://amz-retail.tnsoft.eu\n\n";
