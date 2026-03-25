<?php
class Router {

    private array $routes = [];

    // ONLY these routes are public — everything else requires login
    private array $publicRoutes = [
        '/',
        '/register',
        '/forgot-password',
        '/reset-password',
        '/setup',
    ];

    public function add(string $method, string $path, string $controller, string $action): void {
        $this->routes[] = compact('method', 'path', 'controller', 'action');
    }

    public function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri    = rtrim($uri, '/') ?: '/';

        // ── Security headers on every response ───────────────
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            $params = [];
            if (!$this->matchPath($route['path'], $uri, $params)) continue;

            foreach ($params as $k => $v) {
                $_GET[$k] = $v;
            }

            // ── ACCESS CONTROL ────────────────────────────────
            // DEFAULT: ALL routes require login
            // EXCEPTION: only explicitly listed public routes
            if (!$this->isPublicRoute($uri)) {
                $isJsonRoute = str_starts_with($uri, '/api/')
                    || str_ends_with($uri, '/data')
                    || str_ends_with($uri, '/diagnose')
                    || str_ends_with($uri, '/brands')
                    || str_ends_with($uri, '/rebuild-cache')
                    || str_ends_with($uri, '/debug-import')
                    || str_ends_with($uri, '/update')
                    || str_ends_with($uri, '/import')
                    || $_SERVER['HTTP_ACCEPT'] ?? '' === 'application/json';

                Auth::requireLogin($isJsonRoute);
            }

            $ctrl = new $route['controller']();
            $ctrl->{$route['action']}();
            return;
        }

        // 404
        http_response_code(404);
        if (str_starts_with($uri, '/api/')) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not found']);
        } else {
            View::render('errors/404');
        }
    }

    private function matchPath(string $pattern, string $uri, array &$params): bool {
        if ($pattern === $uri) return true;

        $patParts = explode('/', trim($pattern, '/'));
        $uriParts = explode('/', trim($uri, '/'));

        if (count($patParts) !== count($uriParts)) return false;

        foreach ($patParts as $i => $seg) {
            if (strlen($seg) > 0 && $seg[0] === ':') {
                $params[ltrim($seg, ':')] = $uriParts[$i];
            } elseif ($seg !== $uriParts[$i]) {
                return false;
            }
        }
        return true;
    }

    private function isPublicRoute(string $uri): bool {
        foreach ($this->publicRoutes as $pub) {
            if ($uri === $pub) return true;
            // Allow sub-paths: /register/TOKEN, /reset-password/TOKEN etc.
            if (str_starts_with($uri, rtrim($pub, '/') . '/')) return true;
        }
        return false;
    }
}
