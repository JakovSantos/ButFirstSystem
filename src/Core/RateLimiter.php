<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Counts attempts per key in a time window (file-based, no extra services).
 * Used to slow down password guessing and API-key guessing.
 *
 *   if (RateLimiter::tooManyAttempts($key, 5)) { ... wait RateLimiter::availableIn($key) seconds ... }
 *   RateLimiter::hit($key, 900);   // count a failure, window of 15 minutes
 *   RateLimiter::clear($key);      // after a success
 */
final class RateLimiter
{
    private static ?string $dir = null;

    /** Where the counters are stored (tests point this at a temporary folder). */
    public static function useDirectory(string $dir): void
    {
        self::$dir = rtrim($dir, '/\\');
    }

    public static function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return self::read($key)['count'] >= $maxAttempts;
    }

    /** Record one attempt; returns the number of attempts in the current window. */
    public static function hit(string $key, int $decaySeconds): int
    {
        $data = self::read($key);
        if ($data['count'] === 0) {
            $data['expires'] = time() + $decaySeconds;
        }
        $data['count']++;
        self::write($key, $data);

        return $data['count'];
    }

    /** Seconds until the window for this key resets. */
    public static function availableIn(string $key): int
    {
        return max(0, self::read($key)['expires'] - time());
    }

    public static function clear(string $key): void
    {
        @unlink(self::file($key));
    }

    /** @return array{count:int,expires:int} */
    private static function read(string $key): array
    {
        $raw = @file_get_contents(self::file($key));
        $data = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($data) || (int) ($data['expires'] ?? 0) <= time()) {
            return ['count' => 0, 'expires' => 0];
        }

        return ['count' => (int) ($data['count'] ?? 0), 'expires' => (int) $data['expires']];
    }

    /** @param array{count:int,expires:int} $data */
    private static function write(string $key, array $data): void
    {
        $file = self::file($key);
        if (! is_dir(dirname($file))) {
            @mkdir(dirname($file), 0775, true);
        }
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }

    /** The key is hashed, so user input never becomes part of a file path. */
    private static function file(string $key): string
    {
        $dir = self::$dir ?? App::basePath('storage/cache/ratelimit');

        return $dir . '/' . hash('sha256', $key) . '.json';
    }
}
