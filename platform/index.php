<?php
declare(strict_types=1);
define('ROOT', __DIR__);
define('SRC',  ROOT . '/src');
require_once SRC . '/config/config.php';
require_once SRC . '/lib/Session.php';
require_once SRC . '/lib/Router.php';
require_once SRC . '/lib/Auth.php';
require_once SRC . '/lib/Firebase.php';
require_once SRC . '/lib/Logger.php';
Firebase::init();

// First-run
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

// Dashboard
$router->add('GET',  '/dashboard',               'DashboardController', 'index');

// Products
$router->add('GET',  '/products',                'ProductsController',  'index');
$router->add('GET',  '/products/data',           'ProductsController',  'data');       // AJAX
$router->add('GET',  '/products/diagnose',       'ProductsController',  'diagnose');   // Debug
$router->add('POST', '/products/update',         'ProductsController',  'update');
$router->add('GET',  '/products/add',            'ProductsController',  'addPage');
$router->add('POST', '/products/add',            'ProductsController',  'addAction');
$router->add('GET',  '/products/import',         'ProductsController',  'importPage');
$router->add('POST', '/products/import',         'ProductsController',  'importAction');
$router->add('POST', '/products/restore',        'ProductsController',  'restoreArchive');
$router->add('GET',  '/products/export',         'ProductsController',  'export');
$router->add('GET',  '/products/template',       'ProductsController',  'template');

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
