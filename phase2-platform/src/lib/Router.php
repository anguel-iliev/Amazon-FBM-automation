<?php
class Router {
    private $routes = [];

    // Public routes that never require login
    private $publicRoutes = [
        '/', '/logout',
        '/register', '/forgot-password', '/reset-password',
    ];

    public function add($method, $path, $controller, $action) {
        $this->routes[] = compact('method', 'path', 'controller', 'action');
    }

    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri    = rtrim($uri, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            $params = [];
            if (!$this->matchPath($route['path'], $uri, $params)) continue;

            foreach ($params as $k => $v) {
                $_GET[$k] = $v;
            }

            $isPublic = $this->isPublicRoute($uri);
            $isApi    = (strpos($uri, '/api/') === 0);

            if ($isApi) {
                Auth::requireLogin(true);
            } elseif (!$isPublic) {
                Auth::requireLogin();
            }

            $ctrl = new $route['controller']();
            $ctrl->{$route['action']}();
            return;
        }

        // 404
        http_response_code(404);
        if (strpos($uri, '/api/') === 0) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not found']);
        } else {
            View::render('errors/404');
        }
    }

    private function matchPath($pattern, $uri, &$params) {
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

    private function isPublicRoute($uri) {
        foreach ($this->publicRoutes as $pub) {
            if ($uri === $pub) return true;
            $prefix = rtrim($pub, '/') . '/';
            if (strpos($uri, $prefix) === 0) return true;
        }
        return false;
    }
}
