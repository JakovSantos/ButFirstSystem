<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal file logger (storage/logs/app.log).
 */
final class Logger
{
    public static function info(string $message): void
    {
        self::write('INFO', $message);
    }

    public static function error(string $message): void
    {
        self::write('ERROR', $message);
    }

    private static function write(string $level, string $message): void
    {
        $file = (string) App::config('app.log_file', App::basePath('storage/logs/app.log'));
        // Control characters (new lines etc.) are replaced, so a username such as
        // "x\n[INFO] Login 'admin'" cannot forge extra lines in the log.
        $message = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $message) ?? '';
        $line = sprintf("[%s] %s %s\n", date('Y-m-d H:i:s'), $level, mb_substr($message, 0, 1000));
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
