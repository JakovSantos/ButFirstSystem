<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Small immutable wrapper around the PHP superglobals.
 */
final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly array $files,
        public readonly array $server,
    ) {
    }

    public static function capture(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER,
        );
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function isApi(): bool
    {
        return $this->path === '/api' || str_starts_with($this->path, '/api/');
    }

    public function wantsJson(): bool
    {
        return $this->isApi()
            || str_contains((string) ($this->server['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    /**
     * Decoded JSON body, or null when the body is not a JSON object.
     *
     * @return array<string, mixed>|null
     */
    public static function json(): ?array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true);

        if (! is_array($data)) {
            return null;
        }

        // A JSON array at the top level is rejected; the endpoints expect an object.
        return $data !== [] && array_is_list($data) ? null : $data;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '-');
    }
}
