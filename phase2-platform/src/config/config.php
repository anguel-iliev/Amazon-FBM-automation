<?php
// ============================================================
//  AMZ Retail Platform — Configuration
//  Копирай .env.example в .env и попълни стойностите
// ============================================================

// Load .env if exists
$envFile = ROOT . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
}

function env(string $key, $default = null) {
    return $_ENV[$key] ?? $default;
}

// ── App ─────────────────────────────────────────────────────
define('APP_NAME',    'AMZ Retail');
define('APP_URL',     env('APP_URL', 'https://amz-retail.tnsoft.eu'));
define('APP_DEBUG',   env('APP_DEBUG', 'false') === 'true');
define('TIMEZONE',    'Europe/Sofia');

date_default_timezone_set(TIMEZONE);

// ── Auth ────────────────────────────────────────────────────
define('AUTH_USER',     env('AUTH_USER', 'admin'));
define('AUTH_PASSWORD', env('AUTH_PASSWORD', '')); // bcrypt hash
define('SESSION_NAME',  'amz_session');
define('SESSION_LIFE',  86400 * 7); // 7 days

// ── Google API ──────────────────────────────────────────────
define('GOOGLE_CREDENTIALS_FILE', ROOT . '/src/config/google-credentials.json');
define('GOOGLE_DRIVE_FOLDER_ID',  env('GOOGLE_DRIVE_FOLDER_ID', ''));
define('GOOGLE_SHEET_ID',         env('GOOGLE_SHEET_ID', ''));

// ── Paths ───────────────────────────────────────────────────
define('DATA_DIR',  ROOT . '/data');
define('LOGS_DIR',  DATA_DIR . '/logs');
define('CACHE_DIR', DATA_DIR . '/cache');

foreach ([DATA_DIR, LOGS_DIR, CACHE_DIR] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

// ── Autoloader ──────────────────────────────────────────────
spl_autoload_register(function (string $class) {
    $map = [
        'Router'              => SRC . '/lib/Router.php',
        'Session'             => SRC . '/lib/Session.php',
        'Auth'                => SRC . '/lib/Auth.php',
        'View'                => SRC . '/lib/View.php',
        'GoogleApi'           => SRC . '/lib/GoogleApi.php',
        'Logger'              => SRC . '/lib/Logger.php',
        'AuthController'      => SRC . '/auth/AuthController.php',
        'DashboardController' => SRC . '/dashboard/DashboardController.php',
        'ProductsController'  => SRC . '/modules/products/ProductsController.php',
        'SyncController'      => SRC . '/modules/sync/SyncController.php',
        'PricingController'   => SRC . '/modules/pricing/PricingController.php',
        'SettingsController'  => SRC . '/modules/settings/SettingsController.php',
        'ApiController'       => SRC . '/api/ApiController.php',
    ];
    if (isset($map[$class])) require_once $map[$class];
});

// ── Error handling ───────────────────────────────────────────
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (class_exists('Logger')) Logger::error("PHP Error [$errno]: $errstr in $errfile:$errline");
});
