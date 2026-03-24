<?php
declare(strict_types=1);

define('ROOT', __DIR__);
define('SRC',  ROOT . '/src');
define('VERSION', '1.2.0');

require_once SRC . '/config/config.php';
require_once SRC . '/lib/Session.php';
require_once SRC . '/lib/Router.php';
require_once SRC . '/lib/Auth.php';

$router = new Router();

// ── Public routes ────────────────────────────────────────────
$router->add('GET',  '/',                    'AuthController', 'loginPage');
$router->add('POST', '/',                    'AuthController', 'loginAction');
$router->add('GET',  '/logout',              'AuthController', 'logout');
$router->add('GET',  '/register',            'AuthController', 'registerPage');
$router->add('POST', '/register',            'AuthController', 'registerAction');
$router->add('GET',  '/forgot-password',     'AuthController', 'forgotPage');
$router->add('POST', '/forgot-password',     'AuthController', 'forgotAction');
$router->add('GET',  '/reset-password',      'AuthController', 'resetPage');
$router->add('POST', '/reset-password',      'AuthController', 'resetAction');

// ── Protected routes ─────────────────────────────────────────
$router->add('GET',  '/dashboard',           'DashboardController', 'index');

// Products
$router->add('GET',  '/products',            'ProductsController',  'index');
$router->add('GET',  '/products/search',     'ProductsController',  'search');
$router->add('POST', '/products/update',     'ProductsController',  'update');
$router->add('GET',  '/products/export',     'ProductsController',  'export');
$router->add('GET',  '/products/debug',      'ProductsController',  'debug');

// Sync
$router->add('GET',  '/sync',                'SyncController',      'index');
$router->add('POST', '/sync/run',            'SyncController',      'run');
$router->add('GET',  '/sync/status',         'SyncController',      'status');

// Pricing
$router->add('GET',  '/pricing',             'PricingController',   'index');
$router->add('POST', '/pricing/calculate',   'PricingController',   'calculate');

// Settings
$router->add('GET',  '/settings',            'SettingsController',  'index');
$router->add('POST', '/settings/save',       'SettingsController',  'save');

// Admin
$router->add('GET',  '/invite',              'AuthController',      'invitePage');
$router->add('POST', '/invite',              'AuthController',      'inviteAction');

// ── API ──────────────────────────────────────────────────────
$router->add('GET',  '/api/stats',           'ApiController',       'stats');
$router->add('GET',  '/api/products',        'ApiController',       'products');
$router->add('POST', '/api/sync',            'ApiController',       'sync');

$router->dispatch();
