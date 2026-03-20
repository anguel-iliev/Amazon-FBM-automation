<?php
class Logger {
    private static function write(string $level, string $message): void {
        $date = date('Y-m-d');
        $time = date('H:i:s');
        $file = LOGS_DIR . "/{$date}.log";
        $line = "[{$time}] [{$level}] {$message}" . PHP_EOL;
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $msg): void  { static::write('INFO',  $msg); }
    public static function error(string $msg): void { static::write('ERROR', $msg); }
    public static function warn(string $msg): void  { static::write('WARN',  $msg); }
    public static function debug(string $msg): void {
        if (APP_DEBUG) static::write('DEBUG', $msg);
    }
}
