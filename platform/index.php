<?php
declare(strict_types=1);

// ════════════════════════════════════════════════════════════
//  SECURITY GATE — runs BEFORE everything else
//  Raw PHP, no class dependencies, cannot be bypassed
// ════════════════════════════════════════════════════════════
session_name('amz_session');
session_set_cookie_params([
    'lifetime' => 604800,   // 7 days
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Public routes — accessible without login
$_PUBLIC = ['/', '/logout', '/register', '/forgot-password', '/reset-password', '/setup'];
$_URI    = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';

$_IS_PUBLIC = false;
foreach ($_PUBLIC as $_p) {
    if ($_URI === $_p || str_starts_with($_URI, rtrim($_p, '/') . '/')) {
        $_IS_PUBLIC = true;
        break;
    }
}

if (!$_IS_PUBLIC) {
    // Direct raw session check — no wrapper classes
    $__ok = isset($_SESSION['logged_in'])
         && $_SESSION['logged_in'] === true
         && isset($_SESSION['user'])
         && !empty($_SESSION['user'])
         && isset($_SESSION['login_at'])
         && (time() - (int)$_SESSION['login_at']) < 604800;

    if (!$__ok) {
        // Kill stale session
        $_SESSION = [];
        session_destroy();

        // No-cache headers before redirect
        header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        // AJAX/JSON requests get 401
        $__accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $__xhr    = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        if ($__xhr === 'xmlhttprequest' || str_contains($__accept, 'application/json')) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Unauthorized', 'redirect' => '/']);
            exit;
        }

        // HTML redirect to login
        http_response_code(302);
        header('Location: /');
        exit;
    }
}
// ════════════════════════════════════════════════════════════
//  END SECURITY GATE — user is authenticated from here on
// ════════════════════════════════════════════════════════════

define('ROOT', __DIR__);
define('SRC',  ROOT . '/src');
require_once SRC . '/config/config.php';
require_once SRC . '/lib/Session.php';
require_once SRC . '/lib/Router.php';
require_once SRC . '/lib/Auth.php';
require_once SRC . '/lib/Firebase.php';
require_once SRC . '/lib/Logger.php';

Firebase::init();

// Global exception handler for AJAX routes
set_exception_handler(function(\Throwable $e) {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (str_contains($uri, '/products/data') || str_contains($uri, '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(200);
        echo json_encode([
            'ok'    => false,
            'error' => 'PHP Exception: ' . $e->getMessage(),
            'file'  => basename($e->getFile()) . ':' . $e->getLine(),
        ]);
        exit;
    }
});

// First-run setup redirect
$usersFile = DATA_DIR . '/users.json';
$noUsers   = !file_exists($usersFile) || empty(json_decode(@file_get_contents($usersFile), true));
$reqUri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($noUsers && !preg_match('#^/setup#', $reqUri)) { header('Location: /setup'); exit; }

$router = new Router();

// Public
$router->add('GET',  '/setup',            'AuthController', 'setupPage');
$router->add('POST', '/setup',            'AuthController', 'setupAction');
$router->add('GET',  '/',                 'AuthController', 'loginPage');
$router->add('POST', '/',                 'AuthController', 'loginAction');
$router->add('GET',  '/logout',           'AuthController', 'logout');
$router->add('GET',  '/register',         'AuthController', 'registerPage');
$router->add('GET',  '/register/:token',  'AuthController', 'registerPage');
$router->add('POST', '/register',         'AuthController', 'registerAction');
$router->add('GET',  '/forgot-password',  'AuthController', 'forgotPage');
$router->add('POST', '/forgot-password',  'AuthController', 'forgotAction');
$router->add('GET',  '/reset-password',   'AuthController', 'resetPage');
$router->add('GET',  '/reset-password/:token', 'AuthController', 'resetPage');
$router->add('POST', '/reset-password',   'AuthController', 'resetAction');

// Protected
$router->add('GET',  '/dashboard',               'DashboardController', 'index');

// Products
$router->add('GET',  '/products',                'ProductsController',  'index');
$router->add('GET',  '/products/data',           'ProductsController',  'data');
$router->add('GET',  '/products/diagnose',       'ProductsController',  'diagnose');
$router->add('POST', '/products/update',         'ProductsController',  'update');
$router->add('GET',  '/products/add',            'ProductsController',  'addPage');
$router->add('POST', '/products/add',            'ProductsController',  'addAction');
$router->add('GET',  '/products/import',         'ProductsController',  'importPage');
$router->add('POST', '/products/import',         'ProductsController',  'importAction');
$router->add('POST', '/products/restore',        'ProductsController',  'restoreArchive');
$router->add('POST', '/products/export-archive', 'ProductsController',  'exportArchive');
$router->add('GET',  '/products/export',         'ProductsController',  'export');
$router->add('GET',  '/products/template',       'ProductsController',  'template');
$router->add('GET',  '/products/brands',         'ProductsController',  'brandsForSupplier');
$router->add('POST', '/products/rebuild-cache',  'ProductsController',  'rebuildCache');
$router->add('POST', '/products/debug-import',   'ProductsController',  'debugImport');

// Other modules
$router->add('GET',  '/sync',                    'SyncController',      'index');
$router->add('POST', '/sync/run',                'SyncController',      'run');
$router->add('GET',  '/pricing',                 'PricingController',   'index');
$router->add('POST', '/pricing/calculate',       'PricingController',   'calculate');
$router->add('GET',  '/suppliers',               'SuppliersController', 'index');
$router->add('POST', '/suppliers/save',          'SuppliersController', 'save');
$router->add('POST', '/suppliers/delete',        'SuppliersController', 'delete');
$router->add('GET',  '/settings',                'SettingsController',  'index');
$router->add('GET',  '/settings/vat',            'SettingsController',  'vat');
$router->add('GET',  '/settings/prices',         'SettingsController',  'prices');
$router->add('GET',  '/settings/formulas',       'SettingsController',  'formulas');
$router->add('GET',  '/settings/integrations',   'SettingsController',  'integrations');
$router->add('GET',  '/settings/system',         'SettingsController',  'system');
$router->add('POST', '/settings/save',           'SettingsController',  'save');
$router->add('GET',  '/invite',                  'AuthController',      'invitePage');
$router->add('POST', '/invite',                  'AuthController',      'inviteAction');

// API
$router->add('GET',  '/api/stats',               'ApiController',       'stats');
$router->add('POST', '/api/test-firebase',        'ApiController',       'testFirebase');
$router->add('POST', '/api/test-email',           'ApiController',       'testEmail');
$router->add('POST', '/api/change-password',      'ApiController',       'changePassword');

$router->dispatch();
