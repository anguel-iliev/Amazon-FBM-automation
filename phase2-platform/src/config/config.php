<?php
// ============================================================
//  AMZ Retail Platform — Configuration
// ============================================================

$envFile = ROOT . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $val) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
}

function env($key, $default = null) {
    return isset($_ENV[$key]) ? $_ENV[$key] : $default;
}

// ── App ─────────────────────────────────────────────────────
define('APP_NAME',    'AMZ Retail');
define('APP_URL',     env('APP_URL', 'https://amz-retail.tnsoft.eu'));
define('APP_DEBUG',   env('APP_DEBUG', 'false') === 'true');
define('TIMEZONE',    'Europe/Sofia');

date_default_timezone_set(TIMEZONE);

// ── Auth ────────────────────────────────────────────────────
define('SESSION_NAME',   'amz_session');
define('SESSION_LIFE',   86400 * 7);  // 7 дни
define('TOKEN_EXPIRY',   3600 * 24);  // 24ч за verify/reset токени

// ── SMTP ────────────────────────────────────────────────────
define('SMTP_HOST',      env('SMTP_HOST',      'smtp.gmail.com'));
define('SMTP_PORT',      (int)env('SMTP_PORT', '587'));
define('SMTP_USER',      env('SMTP_USER',      ''));
define('SMTP_PASS',      env('SMTP_PASS',      ''));
define('SMTP_FROM',      env('SMTP_FROM',      ''));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'AMZ Retail'));

// ── Google API ──────────────────────────────────────────────
define('GOOGLE_CREDENTIALS_FILE', ROOT . '/src/config/google-credentials.json');
define('GOOGLE_DRIVE_FOLDER_ID',  env('GOOGLE_DRIVE_FOLDER_ID', ''));
define('GOOGLE_SHEET_ID',         env('GOOGLE_SHEET_ID', ''));

// ── Paths ───────────────────────────────────────────────────
define('DATA_DIR',  ROOT . '/data');
define('LOGS_DIR',  DATA_DIR . '/logs');
define('CACHE_DIR', DATA_DIR . '/cache');

foreach (array(DATA_DIR, LOGS_DIR, CACHE_DIR) as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

// ── Autoloader ──────────────────────────────────────────────
spl_autoload_register(function ($class) {
    $map = array(
        'Router'              => SRC . '/lib/Router.php',
        'Session'             => SRC . '/lib/Session.php',
        'Auth'                => SRC . '/lib/Auth.php',
        'UserStore'           => SRC . '/lib/UserStore.php',
        'Mailer'              => SRC . '/lib/Mailer.php',
        'View'                => SRC . '/lib/View.php',
        'Logger'              => SRC . '/lib/Logger.php',
        'DataStore'           => SRC . '/lib/DataStore.php',
        'AuthController'      => SRC . '/auth/AuthController.php',
        'DashboardController' => SRC . '/dashboard/DashboardController.php',
        'ProductsController'  => SRC . '/modules/products/ProductsController.php',
        'SyncController'      => SRC . '/modules/sync/SyncController.php',
        'PricingController'   => SRC . '/modules/pricing/PricingController.php',
        'SettingsController'  => SRC . '/modules/settings/SettingsController.php',
        'ApiController'       => SRC . '/api/ApiController.php',
    );
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
    if (class_exists('Logger')) Logger::error("PHP Error [{$errno}]: {$errstr} in {$errfile}:{$errline}");
});
