<?php

declare(strict_types=1);

namespace App\Core;

use Mustache_Engine;
use Mustache_Loader_FilesystemLoader;

/**
 * Mustache renderer with layout composition.
 * The page template is rendered first and injected into the layout as {{{content}}}.
 */
final class View
{
    private static ?Mustache_Engine $engine = null;

    public static function boot(string $viewsPath): void
    {
        $options = ['extension' => '.mustache'];

        self::$engine = new Mustache_Engine([
            'loader'          => new Mustache_Loader_FilesystemLoader($viewsPath, $options),
            'partials_loader' => new Mustache_Loader_FilesystemLoader($viewsPath . '/partials', $options),
            'escape'          => static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'),
            'strict_callables' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        if (self::$engine === null) {
            throw new \RuntimeException('View engine has not been booted. Call App::boot() first.');
        }

        $data += self::shared();
        $content = self::$engine->loadTemplate($template)->render($data);

        if ($layout === null) {
            return $content;
        }

        return self::$engine->loadTemplate($layout)->render($data + ['content' => $content]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function display(string $template, array $data = [], ?string $layout = 'layout'): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo self::render($template, $data, $layout);
    }

    /**
     * Values every template can use.
     *
     * @return array<string, mixed>
     */
    private static function shared(): array
    {
        $user = Session::get('user');

        return [
            'appName'   => App::config('app.name'),
            'csrfToken' => Session::csrfToken(),
            'user'      => $user,
            'isAdmin'   => ($user['role'] ?? null) === 'admin',
            'isEmployee' => ($user['role'] ?? null) === 'employee',
            'userInitial' => strtoupper(substr((string) ($user['name'] ?? $user['username'] ?? ''), 0, 1)),
            'displayName' => (string) ($user['name'] ?? $user['username'] ?? ''),
            'roleLabel' => ($user['role'] ?? null) === 'admin' ? 'Administrator' : 'Employee',
            'flash'     => Session::pullFlash(),
            'year'      => date('Y'),
        ];
    }
}
