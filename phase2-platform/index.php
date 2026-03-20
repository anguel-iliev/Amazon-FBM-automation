<?php
declare(strict_types=1);

define('ROOT', __DIR__);
define('SRC',  ROOT . '/src');
define('VERSION', '1.0.0');

require_once SRC . '/config/config.php';
require_once SRC . '/lib/Session.php';
require_once SRC . '/lib/Router.php';
require_once SRC . '/lib/Auth.php';

$router = new Router();

// Public routes (no auth needed)
$router->add('GET',  '/',        'AuthController', 'loginPage');
$router->add('POST', '/',        'AuthController', 'loginAction');
$router->add('GET',  '/logout',  'AuthController', 'logout');

// Protected routes
$router->add('GET',  '/dashboard',          'DashboardController', 'index');
$router->add('GET',  '/products',           'ProductsController',  'index');
$router->add('GET',  '/products/search',    'ProductsController',  'search');
$router->add('POST', '/products/update',    'ProductsController',  'update');
$router->add('GET',  '/sync',               'SyncController',      'index');
$router->add('POST', '/sync/run',           'SyncController',      'run');
$router->add('GET',  '/sync/status',        'SyncController',      'status');
$router->add('GET',  '/pricing',            'PricingController',   'index');
$router->add('POST', '/pricing/calculate',  'PricingController',   'calculate');
$router->add('GET',  '/settings',           'SettingsController',  'index');
$router->add('POST', '/settings/save',      'SettingsController',  'save');

// API endpoints (JSON)
$router->add('GET',  '/api/stats',          'ApiController',       'stats');
$router->add('GET',  '/api/products',       'ApiController',       'products');
$router->add('POST', '/api/sync',           'ApiController',       'sync');

$router->dispatch();
