<?php
define('ROOT', __DIR__);
define('SRC',  ROOT . '/src');
define('VERSION', '1.8.0');

require_once SRC . '/config/config.php';
require_once SRC . '/lib/Session.php';
require_once SRC . '/lib/Router.php';
require_once SRC . '/lib/Auth.php';

// ── First-run detection ──────────────────────────────────────
// If no users.json exists, redirect every request to /setup
// EXCEPT the /setup routes themselves.
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$usersJson  = DATA_DIR . '/users.json';
$noUsers    = !file_exists($usersJson) ||
              empty(json_decode(@file_get_contents($usersJson), true));

if ($noUsers && !preg_match('#^/setup#', $requestUri)) {
    header('Location: /setup');
    exit;
}

$router = new Router();

// ── First-run setup (no users) ───────────────────────────────
$router->add('GET',  '/setup',                     'AuthController', 'setupPage');
$router->add('POST', '/setup',                     'AuthController', 'setupAction');

// ── Public routes ────────────────────────────────────────────
$router->add('GET',  '/',                          'AuthController', 'loginPage');
$router->add('POST', '/',                          'AuthController', 'loginAction');
$router->add('GET',  '/logout',                    'AuthController', 'logout');

// Registration via invite (supports both ?token=X and /register/TOKEN)
$router->add('GET',  '/register',                  'AuthController', 'registerPage');
$router->add('GET',  '/register/:token',           'AuthController', 'registerPage');
$router->add('POST', '/register',                  'AuthController', 'registerAction');

// Forgot / Reset password (supports both ?token=X and /reset-password/TOKEN)
$router->add('GET',  '/forgot-password',           'AuthController', 'forgotPage');
$router->add('POST', '/forgot-password',           'AuthController', 'forgotAction');
$router->add('GET',  '/reset-password',            'AuthController', 'resetPage');
$router->add('GET',  '/reset-password/:token',     'AuthController', 'resetPage');
$router->add('POST', '/reset-password',            'AuthController', 'resetAction');

// ── Protected routes ─────────────────────────────────────────
$router->add('GET',  '/dashboard',                 'DashboardController', 'index');
$router->add('GET',  '/products',                  'ProductsController',  'index');
$router->add('GET',  '/products/search',           'ProductsController',  'search');
$router->add('POST', '/products/update',           'ProductsController',  'update');
$router->add('GET',  '/sync',                      'SyncController',      'index');
$router->add('POST', '/sync/run',                  'SyncController',      'run');
$router->add('GET',  '/sync/status',               'SyncController',      'status');
$router->add('GET',  '/pricing',                   'PricingController',   'index');
$router->add('POST', '/pricing/calculate',         'PricingController',   'calculate');
$router->add('GET',  '/settings',                  'SettingsController',  'index');
$router->add('GET',  '/settings/vat',              'SettingsController',  'vat');
$router->add('GET',  '/settings/prices',           'SettingsController',  'prices');
$router->add('GET',  '/settings/formulas',         'SettingsController',  'formulas');
$router->add('GET',  '/settings/integrations',     'SettingsController',  'integrations');
$router->add('GET',  '/settings/system',           'SettingsController',  'system');
$router->add('POST', '/settings/save',             'SettingsController',  'save');
$router->add('POST', '/settings/save-formulas',    'SettingsController',  'saveFormulas');

// Suppliers
$router->add('GET',  '/suppliers',                 'SuppliersController', 'index');
$router->add('POST', '/suppliers/save',            'SuppliersController', 'save');
$router->add('POST', '/suppliers/delete',          'SuppliersController', 'delete');

// Admin only
$router->add('GET',  '/invite',                    'AuthController',      'invitePage');
$router->add('POST', '/invite',                    'AuthController',      'inviteAction');
$router->add('POST', '/invite/delete',             'AuthController',      'deleteUserAction');
$router->add('POST', '/invite/resend',             'AuthController',      'resendInviteAction');

// ── API ──────────────────────────────────────────────────────
$router->add('GET',  '/api/stats',                 'ApiController',       'stats');
$router->add('GET',  '/api/products',              'ApiController',       'products');
$router->add('POST', '/api/sync',                  'ApiController',       'sync');
$router->add('POST', '/api/test-email',            'ApiController',       'testEmail');
$router->add('POST', '/api/import-excel',          'ApiController',       'importExcel');
$router->add('POST', '/api/change-password',       'ApiController',       'changePassword');
$router->add('POST', '/api/apply-formula',         'ApiController',       'applyFormula');
$router->add('GET',  '/api/suppliers',             'ApiController',       'suppliersApi');
$router->add('POST', '/api/export-csv',            'ApiController',       'exportCsv');
$router->add('POST', '/api/save-price-columns',    'ApiController',       'savePriceColumns');
$router->add('POST', '/api/save-carriers',         'ApiController',       'saveCarriers');
$router->add('POST', '/api/save-extra-formula-col','ApiController',       'saveExtraFormulaCol');
$router->add('POST', '/api/test-firebase',        'ApiController',       'testFirebase');
$router->add('POST', '/api/sync-to-firebase',     'ApiController',       'syncToFirebase');

$router->add('GET',  '/firebase-admin',             'SettingsController',  'firebaseAdmin');
$router->add('POST', '/firebase-admin',             'SettingsController',  'firebaseAdminAction');

$router->dispatch();
