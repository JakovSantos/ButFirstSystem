<?php

declare(strict_types=1);

namespace App\Core;

/**
 * JSON and redirect helpers.
 */
final class Response
{
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Post/Redirect/Get. Exits so nothing is sent after the redirect.
     *
     * Only paths on this site are allowed ("/payroll?x=1"). Anything else, such as
     * "https://evil.example", "//evil.example" or "/\evil.example", goes to "/" instead,
     * so the app can never be used as an open redirect.
     */
    public static function redirect(string $url, int $status = 302): never
    {
        http_response_code($status);
        header('Location: ' . self::safeLocation($url));
        exit;
    }

    /**
     * Go back to the page the user came from (same site only), or to $fallback.
     *
     * @param array<string, mixed> $server
     */
    public static function back(array $server, string $fallback = '/'): never
    {
        $path = parse_url((string) ($server['HTTP_REFERER'] ?? ''), PHP_URL_PATH);

        self::redirect(is_string($path) && $path !== '' ? $path : $fallback);
    }

    public static function safeLocation(string $url): string
    {
        $url = str_replace(["\r", "\n", "\0"], '', $url);

        $isLocalPath = str_starts_with($url, '/')
            && ! str_starts_with($url, '//')
            && ! str_starts_with($url, '/\\');

        return $isLocalPath ? $url : '/';
    }

    public static function html(string $html, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }
}
