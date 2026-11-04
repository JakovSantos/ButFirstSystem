<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Application kernel: base path, configuration and the explicit boot sequence.
 */
final class App
{
    private static ?string $basePath = null;

    /** @var array<string, mixed> */
    private static array $config = [];

    private static bool $booted = false;

    public static function boot(string $basePath): void
    {
        if (self::$booted) {
            return;
        }

        self::$basePath = rtrim($basePath, '/\\');
        self::$config = require self::$basePath . '/config/config.php';

        date_default_timezone_set((string) self::config('app.timezone', 'UTC'));

        self::ensureStorage();

        Database::boot(self::config('database'));
        View::boot(self::$basePath . '/views');
        Session::start();

        self::$booted = true;
    }

    public static function basePath(string $path = ''): string
    {
        $base = self::$basePath ?? dirname(__DIR__, 2);

        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }

    /**
     * Dot-notation config lookup, e.g. App::config('app.name').
     */
    public static function config(string $key, mixed $default = null): mixed
    {
        $value = self::$config;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    private static function ensureStorage(): void
    {
        foreach (['storage/logs', 'storage/cache', 'public/uploads/products'] as $dir) {
            $full = self::basePath($dir);
            if (! is_dir($full)) {
                mkdir($full, 0775, true);
            }
        }
    }
}
