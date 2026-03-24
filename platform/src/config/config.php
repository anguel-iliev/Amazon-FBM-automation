<?php
// ============================================================
//  AMZ Retail v2.0 — Config
// ============================================================

// Load .env
$envFile = ROOT . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
    }
}

function env(string $key, $default = null) { return $_ENV[$key] ?? $default; }

// ── App ──────────────────────────────────────────────────────
define('APP_NAME',    'AMZ Retail');
define('APP_URL',     env('APP_URL', 'https://amz-retail.tnsoft.eu'));
define('APP_DEBUG',   env('APP_DEBUG', 'false') === 'true');
define('TIMEZONE',    'Europe/Sofia');
define('VERSION',     '2.5.0');

date_default_timezone_set(TIMEZONE);

// ── Session ──────────────────────────────────────────────────
define('SESSION_NAME', 'amz_session');
define('SESSION_LIFE', 86400 * 7);
define('TOKEN_EXPIRY', 86400);

// ── SMTP ──────────────────────────────────────────────────────
define('SMTP_HOST',      env('SMTP_HOST',      'smtp.gmail.com'));
define('SMTP_PORT',      (int)env('SMTP_PORT', '587'));
define('SMTP_USER',      env('SMTP_USER',      ''));
define('SMTP_PASS',      env('SMTP_PASS',      ''));
define('SMTP_FROM',      env('SMTP_FROM',      ''));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'AMZ Retail'));

// ── Firebase ──────────────────────────────────────────────────
define('FIREBASE_DATABASE_URL', env('FIREBASE_DATABASE_URL', ''));
define('FIREBASE_SECRET',       env('FIREBASE_SECRET',       ''));
define('FIREBASE_PROJECT_ID',   env('FIREBASE_PROJECT_ID',   ''));

// ── Paths ─────────────────────────────────────────────────────
define('DATA_DIR',    ROOT . '/data');
define('LOGS_DIR',    DATA_DIR . '/logs');
define('ARCHIVE_DIR', DATA_DIR . '/archives');

foreach ([DATA_DIR, LOGS_DIR, ARCHIVE_DIR] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

// ── Autoloader ───────────────────────────────────────────────
spl_autoload_register(function (string $class) {
    $map = [
        // Lib
        'Firebase'            => SRC . '/lib/Firebase.php',
        'Session'             => SRC . '/lib/Session.php',
        'Auth'                => SRC . '/lib/Auth.php',
        'UserStore'           => SRC . '/lib/UserStore.php',
        'Mailer'              => SRC . '/lib/Mailer.php',
        'View'                => SRC . '/lib/View.php',
        'Router'              => SRC . '/lib/Router.php',
        'Logger'              => SRC . '/lib/Logger.php',
        'XlsxParser'          => SRC . '/lib/XlsxParser.php',
        // Controllers
        'AuthController'      => SRC . '/auth/AuthController.php',
        'DashboardController' => SRC . '/dashboard/DashboardController.php',
        'ProductsController'  => SRC . '/modules/products/ProductsController.php',
        'SyncController'      => SRC . '/modules/sync/SyncController.php',
        'PricingController'   => SRC . '/modules/pricing/PricingController.php',
        'SettingsController'  => SRC . '/modules/settings/SettingsController.php',
        'SuppliersController' => SRC . '/modules/suppliers/SuppliersController.php',
        'ApiController'       => SRC . '/api/ApiController.php',
    ];
    if (isset($map[$class])) require_once $map[$class];
});

// ── Error handling ────────────────────────────────────────────
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    Logger::error("PHP[$errno]: $errstr in $errfile:$errline");
    return true;
});
