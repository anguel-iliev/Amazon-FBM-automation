<?php
class Logger {
    private static function write($level, $message) {
        $date = date('Y-m-d');
        $time = date('H:i:s');
        $file = LOGS_DIR . "/{$date}.log";
        $line = "[{$time}] [{$level}] {$message}" . PHP_EOL;
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    public static function info($msg)  { static::write('INFO',  $msg); }
    public static function error($msg) { static::write('ERROR', $msg); }
    public static function warn($msg)  { static::write('WARN',  $msg); }
    public static function debug($msg) {
        if (defined('APP_DEBUG') && APP_DEBUG) static::write('DEBUG', $msg);
    }
}
