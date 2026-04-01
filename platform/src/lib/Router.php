<?php
class Router {
    private array $routes = [];
    private array $publicExactRoutes = ['/', '/register', '/forgot-password', '/reset-password', '/setup'];
    private array $publicPrefixes = ['/register/', '/reset-password/'];
    private array $adminPrefixes = ['/invite', '/settings', '/sync', '/suppliers'];
    private array $adminExactRoutes = ['/api/test-firebase', '/api/test-email'];

    public function add(string $method, string $path, string $controller, string $action): void {
        $this->routes[] = compact('method', 'path', 'controller', 'action');
    }

    public function dispatch(): void {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = rtrim($uri, '/') ?: '/';

        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        Security::sendNoCacheHeaders();

        foreach ($this->routes as $route) {
            if (strtoupper($route['method']) !== $method) continue;
            $params = [];
            if (!$this->matchPath($route['path'], $uri, $params)) continue;
            foreach ($params as $k => $v) $_GET[$k] = $v;

            $isJsonRoute = $this->isJsonRoute($uri);
            if (!$this->isPublicRoute($uri)) Auth::requireLogin($isJsonRoute);
            if ($this->isAdminRoute($uri)) Auth::requireAdmin($isJsonRoute);
            if (!in_array($method, ['GET', 'HEAD'], true)) Security::requireCsrf($isJsonRoute, $this->shouldAllowPublicCsrfFallback($uri));

            $ctrl = new $route['controller']();
            $ctrl->{$route['action']}();
            return;
        }

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
            if ($seg !== '' && $seg[0] === ':') {
                $params[ltrim($seg, ':')] = $uriParts[$i];
            } elseif ($seg !== $uriParts[$i]) {
                return false;
            }
        }
        return true;
    }

    private function isPublicRoute(string $uri): bool {
        if (in_array($uri, $this->publicExactRoutes, true)) return true;
        foreach ($this->publicPrefixes as $prefix) {
            if (str_starts_with($uri, $prefix)) return true;
        }
        return false;
    }

    private function isAdminRoute(string $uri): bool {
        if (in_array($uri, $this->adminExactRoutes, true)) return true;
        foreach ($this->adminPrefixes as $prefix) {
            if ($uri === $prefix || str_starts_with($uri, $prefix . '/')) return true;
        }
        return false;
    }


    private function shouldAllowPublicCsrfFallback(string $uri): bool {
        return in_array($uri, ['/', '/setup', '/register', '/forgot-password', '/reset-password'], true);
    }

    private function isJsonRoute(string $uri): bool {
        return str_starts_with($uri, '/api/')
            || str_ends_with($uri, '/data')
            || str_ends_with($uri, '/diagnose')
            || str_ends_with($uri, '/brands')
            || str_ends_with($uri, '/rebuild-cache')
            || str_ends_with($uri, '/debug-import')
            || str_ends_with($uri, '/update')
            || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }
}
