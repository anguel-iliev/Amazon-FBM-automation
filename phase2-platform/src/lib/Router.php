<?php
class Router {
    private array $routes = [];

    public function add(string $method, string $path, string $controller, string $action): void {
        $this->routes[] = compact('method', 'path', 'controller', 'action');
    }

    public function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri    = rtrim($uri, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;
            if ($route['path'] !== $uri) continue;

            // Check auth for protected routes
            $publicRoutes = ['/', '/logout'];
            if (!in_array($uri, $publicRoutes) && !str_starts_with($uri, '/api/')) {
                Auth::requireLogin();
            }
            if (str_starts_with($uri, '/api/')) {
                Auth::requireLogin(true); // JSON response on fail
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
}
