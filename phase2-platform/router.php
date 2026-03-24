<?php
// PHP built-in server router for AMZ Retail
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static assets directly (CSS, JS, images, fonts)
if ($uri !== '/' && file_exists(__DIR__ . $uri) && is_file(__DIR__ . $uri)) {
    $ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
    // For static files (not PHP), return false to let built-in server handle
    if ($ext !== 'php') {
        return false;
    }
    // For standalone PHP files (firebase-admin.php, migrate_to_firebase.php etc.)
    // include them directly
    require __DIR__ . $uri;
    exit;
}

// Route everything through index.php (the main app router)
require_once __DIR__ . '/index.php';
