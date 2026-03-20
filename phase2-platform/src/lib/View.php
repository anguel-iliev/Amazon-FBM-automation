<?php
class View {
    private static array $data = [];

    public static function render(string $template, array $data = []): void {
        static::$data = array_merge(static::$data, $data);
        extract(static::$data);

        $file = SRC . '/views/' . $template . '.php';
        if (!file_exists($file)) {
            throw new RuntimeException("View not found: $template");
        }
        require $file;
    }

    public static function renderWithLayout(string $template, array $data = [], string $layout = 'layouts/main'): void {
        // Capture content
        ob_start();
        static::render($template, $data);
        $content = ob_get_clean();

        // Render layout with content
        static::render($layout, array_merge($data, ['content' => $content]));
    }

    public static function json(mixed $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirect(string $url): void {
        header('Location: ' . $url);
        exit;
    }

    public static function escape(mixed $value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
