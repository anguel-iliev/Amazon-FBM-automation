<?php
class Logger {
    private static function write($level, $message, ?string $channel = null) {
        $date = date('Y-m-d');
        $time = date('H:i:s');
        $suffix = $channel ? '.' . preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($channel)) : '';
        $file = LOGS_DIR . "/{$date}{$suffix}.log";
        $line = "[{$time}] [{$level}] {$message}" . PHP_EOL;
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    public static function info($msg)  { static::write('INFO',  $msg); }
    public static function error($msg) { static::write('ERROR', $msg); }
    public static function warn($msg)  { static::write('WARN',  $msg); }
    public static function debug($msg) {
        if (defined('APP_DEBUG') && APP_DEBUG) static::write('DEBUG', $msg);
    }

    public static function audit(string $action, array $context = []): void {
        $pairs = [];
        foreach ($context as $k => $v) {
            if (is_bool($v)) $v = $v ? 'true' : 'false';
            elseif (!is_scalar($v) && $v !== null) $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $pairs[] = $k . '=' . (string)$v;
        }
        static::write('AUDIT', $action . (empty($pairs) ? '' : ' | ' . implode(' | ', $pairs)), 'audit');
    }
}
