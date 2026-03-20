<?php
class Router {
    private array $routes = [];

    // Public routes that never require login
    private array $publicRoutes = [
        '/', '/logout',
        '/register', '/forgot-password', '/reset-password',
    ];

    public function add(string $method, string $path, string $controller, string $action): void {
        $this->routes[] = compact('method', 'path', 'controller', 'action');
    }

    public function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri    = rtrim($uri, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            // Support path parameters: /register/:token  /reset-password/:token
            $params = [];
            if (!$this->matchPath($route['path'], $uri, $params)) continue;

            // Inject path params into $_GET so controllers can use $_GET['token']
            foreach ($params as $k => $v) {
                $_GET[$k] = $v;
            }

            // Auth check
            $isPublic = $this->isPublicRoute($uri);
            $isApi    = str_starts_with($uri, '/api/');

            if ($isApi) {
                Auth::requireLogin(true); // JSON 401 on fail
            } elseif (!$isPublic) {
                Auth::requireLogin();
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

    /**
     * Match a route pattern against a URI.
     * Supports :param segments, e.g. /register/:token
     * Returns true on match; fills $params array.
     */
    private function matchPath(string $pattern, string $uri, array &$params): bool {
        if ($pattern === $uri) return true;

        $patParts = explode('/', trim($pattern, '/'));
        $uriParts = explode('/', trim($uri, '/'));

        if (count($patParts) !== count($uriParts)) return false;

        foreach ($patParts as $i => $seg) {
            if (str_starts_with($seg, ':')) {
                $params[ltrim($seg, ':')] = $uriParts[$i];
            } elseif ($seg !== $uriParts[$i]) {
                return false;
            }
        }
        return true;
    }

    /**
     * Determine if a URI is publicly accessible without login.
     */
    private function isPublicRoute(string $uri): bool {
        foreach ($this->publicRoutes as $pub) {
            if ($uri === $pub) return true;
            // prefix match: /register/TOKEN starts with /register
            if (str_starts_with($uri, rtrim($pub, '/') . '/')) return true;
        }
        return false;
    }
}
