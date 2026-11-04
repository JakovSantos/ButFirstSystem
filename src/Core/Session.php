<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session helper: login state, CSRF token and one-shot "MessageBox" flash messages.
 *
 * The flash message reproduces the C# MessageBox.Show(text, caption, buttons, icon)
 * calls, so every page can show the same Windows-style dialog after a redirect.
 */
final class Session
{
    public static function start(): void
    {
        if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name('butfirst_session');
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * @param 'information'|'warning'|'error'|'question' $icon
     */
    public static function flash(string $message, string $caption = 'Information', string $icon = 'information'): void
    {
        $_SESSION['_flash'] = [
            'message' => $message,
            'caption' => $caption,
            'icon'    => $icon,
        ];
    }

    /**
     * @return array{message:string,caption:string,icon:string}|null
     */
    public static function pullFlash(): ?array
    {
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);

        return $flash;
    }

    /**
     * Keep submitted form values for one request (used after validation errors).
     *
     * @param array<string, mixed> $values
     */
    public static function flashInput(array $values): void
    {
        $_SESSION['_old'] = $values;
    }

    /**
     * @return array<string, mixed>
     */
    public static function pullInput(): array
    {
        $old = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);

        return is_array($old) ? $old : [];
    }

    public static function csrfToken(): string
    {
        if (PHP_SAPI === 'cli') {
            return 'cli';
        }
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(16));
        }

        return $_SESSION['_csrf'];
    }
}
