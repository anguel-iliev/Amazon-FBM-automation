<?php
class View {
    private static $data = [];

    public static function render($template, $data = []) {
        static::$data = array_merge(static::$data, $data);
        extract(static::$data);

        $file = SRC . '/views/' . $template . '.php';
        if (!file_exists($file)) {
            throw new RuntimeException("View not found: $template");
        }
        require $file;
    }

    public static function renderWithLayout($template, $data = [], $layout = 'layouts/main') {
        ob_start();
        static::render($template, $data);
        $content = ob_get_clean();
        static::render($layout, array_merge($data, ['content' => $content]));
    }

    public static function json($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirect($url) {
        header('Location: ' . $url);
        exit;
    }

    public static function escape($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
