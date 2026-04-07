<?php
declare(strict_types=1);

session_name('amz_session');
session_set_cookie_params([
    'lifetime' => 604800,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

define('ROOT', __DIR__);
define('SRC',  ROOT . '/src');
require_once SRC . '/config/config.php';
require_once SRC . '/lib/Session.php';
require_once SRC . '/lib/Router.php';
require_once SRC . '/lib/Auth.php';
require_once SRC . '/lib/UserStore.php';
require_once SRC . '/lib/Firebase.php';
require_once SRC . '/lib/XlsxParser.php';
require_once SRC . '/lib/Logger.php';
require_once SRC . '/lib/Security.php';

Firebase::init();

$_URI = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';
$_PUBLIC_EXACT = ['/', '/register', '/forgot-password', '/reset-password', '/setup'];
$_PUBLIC_PREFIXES = ['/register/', '/reset-password/'];
$_IS_PUBLIC = in_array($_URI, $_PUBLIC_EXACT, true);
if (!$_IS_PUBLIC) {
    foreach ($_PUBLIC_PREFIXES as $_prefix) {
        if (str_starts_with($_URI, $_prefix)) {
            $_IS_PUBLIC = true;
            break;
        }
    }
}

if (!$_IS_PUBLIC && !Auth::isLoggedIn()) {
    $_SESSION = [];
    session_destroy();
    Security::sendNoCacheHeaders();
    $__accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $__xhr    = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    if ($__xhr === 'xmlhttprequest' || str_contains($__accept, 'application/json')) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Unauthorized', 'redirect' => '/']);
        exit;
    }
    header('Location: /', true, 302);
    exit;
}

set_exception_handler(function(\Throwable $e) {
    Logger::error($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
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

$noUsers = !UserStore::hasUsers();
$reqUri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($noUsers && !preg_match('#^/setup#', $reqUri)) { header('Location: /setup'); exit; }

$router = new Router();
$router->add('GET',  '/setup',            'AuthController', 'setupPage');
$router->add('POST', '/setup',            'AuthController', 'setupAction');
$router->add('GET',  '/',                 'AuthController', 'loginPage');
$router->add('POST', '/',                 'AuthController', 'loginAction');
$router->add('POST', '/logout',           'AuthController', 'logout');
$router->add('GET',  '/register',         'AuthController', 'registerPage');
$router->add('GET',  '/register/:token',  'AuthController', 'registerPage');
$router->add('POST', '/register',         'AuthController', 'registerAction');
$router->add('GET',  '/forgot-password',  'AuthController', 'forgotPage');
$router->add('POST', '/forgot-password',  'AuthController', 'forgotAction');
$router->add('GET',  '/reset-password',   'AuthController', 'resetPage');
$router->add('GET',  '/reset-password/:token', 'AuthController', 'resetPage');
$router->add('POST', '/reset-password',   'AuthController', 'resetAction');

$router->add('GET',  '/dashboard',               'DashboardController', 'index');
$router->add('GET',  '/products',                'ProductsController',  'index');
$router->add('GET',  '/products/data',           'ProductsController',  'data');
$router->add('GET',  '/products/diagnose',       'ProductsController',  'diagnose');
$router->add('POST', '/products/update',         'ProductsController',  'update');
$router->add('POST', '/products/delete',         'ProductsController',  'deleteAction');
$router->add('GET',  '/products/add',            'ProductsController',  'addPage');
$router->add('POST', '/products/add',            'ProductsController',  'addAction');
$router->add('GET',  '/products/import',         'ProductsController',  'importPage');
$router->add('POST', '/products/import',         'ProductsController',  'importAction');
$router->add('POST', '/products/validate-import', 'ProductsController',  'validateImportAction');
$router->add('POST', '/products/restore',        'ProductsController',  'restoreArchive');
$router->add('GET',  '/products/export-archive', 'ProductsController',  'exportArchive');
$router->add('POST', '/products/export-archive', 'ProductsController',  'exportArchive');
$router->add('GET',  '/products/export',         'ProductsController',  'export');
$router->add('GET',  '/products/export-xlsx',    'ProductsController',  'exportXlsx');
$router->add('GET',  '/products/template',       'ProductsController',  'template');
$router->add('GET',  '/products/brands',         'ProductsController',  'brandsForSupplier');
$router->add('POST', '/products/rebuild-cache',  'ProductsController',  'rebuildCache');
$router->add('POST', '/products/debug-import',   'ProductsController',  'debugImport');

$router->add('GET',  '/sync',                    'SyncController',      'index');
$router->add('POST', '/sync/run',                'SyncController',      'run');
$router->add('GET',  '/pricing',                 'PricingController',   'redirectVat');
$router->add('GET',  '/vat',                     'PricingController',   'index');
$router->add('POST', '/pricing/calculate',       'PricingController',   'calculate');
$router->add('GET',  '/suppliers',               'SuppliersController', 'index');

$router->add('GET',  '/couriers',                'CouriersController',  'index');
$router->add('POST', '/couriers/save',           'CouriersController',  'save');
$router->add('GET',  '/couriers/template',       'CouriersController',  'template');
$router->add('GET',  '/couriers/export',         'CouriersController',  'export');
$router->add('GET',  '/couriers/download-import', 'CouriersController',  'downloadImport');
$router->add('POST', '/couriers/delete-import',   'CouriersController',  'deleteImport');
$router->add('POST', '/couriers/import',         'CouriersController',  'import');
$router->add('GET',  '/couriers/import',         'CouriersController',  'index');
$router->add('POST', '/couriers/activate',       'CouriersController',  'activate');
$router->add('POST', '/couriers/save-mode',      'CouriersController',  'saveMode');
$router->add('GET',  '/couriers/save-mode',      'CouriersController',  'index');
$router->add('POST', '/couriers/delete-rates',   'CouriersController',  'deleteRates');
$router->add('POST', '/suppliers/save',          'SuppliersController', 'save');
$router->add('POST', '/suppliers/delete',        'SuppliersController', 'delete');
$router->add('GET',  '/settings',                'SettingsController',  'index');
$router->add('GET',  '/settings/vat',            'SettingsController',  'vat');
$router->add('GET',  '/settings/prices',         'SettingsController',  'prices');
$router->add('GET',  '/settings/formulas',       'SettingsController',  'formulas');
$router->add('GET',  '/settings/integrations',   'SettingsController',  'integrations');
$router->add('GET',  '/settings/system',         'SettingsController',  'system');
$router->add('POST', '/settings/save',           'SettingsController',  'save');

$router->add('POST', '/settings/add-column',     'SettingsController',  'addColumn');
$router->add('POST', '/settings/save-formula',   'SettingsController',  'saveFormula');
$router->add('POST', '/settings/clear-formula',  'SettingsController',  'clearFormula');
$router->add('POST', '/settings/change-user-role','SettingsController', 'changeUserRole');
$router->add('GET',  '/settings/formulas/export-xlsx', 'SettingsController', 'exportFormulasXlsx');
$router->add('GET',  '/settings/formulas/template',    'SettingsController', 'downloadFormulaTemplate');
$router->add('POST', '/settings/formulas/preview-import', 'SettingsController', 'previewImportFormulas');
$router->add('POST', '/settings/formulas/import',      'SettingsController', 'importFormulas');
$router->add('GET',  '/invite',                  'AuthController',      'invitePage');
$router->add('POST', '/invite',                  'AuthController',      'inviteAction');
$router->add('POST', '/invite/delete',           'AuthController',      'deleteUserAction');
$router->add('POST', '/invite/resend',           'AuthController',      'resendInviteAction');

$router->add('GET',  '/api/stats',               'ApiController',       'stats');
$router->add('POST', '/api/test-firebase',       'ApiController',       'testFirebase');
$router->add('POST', '/api/test-email',          'ApiController',       'testEmail');
$router->add('POST', '/api/change-password',     'ApiController',       'changePassword');

$router->dispatch();
