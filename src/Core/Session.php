<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session helper: login state, CSRF token and one-shot flash messages.
 *
 * A flash message (text, caption, icon) is shown once as a dialog or toast
 * on the next page after a redirect.
 */
final class Session
{
    public static function start(): void
    {
        if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Only accept session ids this server created, only from the cookie (never the URL).
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        $https = ! empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        session_name('butfirst_session');
        session_set_cookie_params([
            'path'     => '/',
            'httponly' => true,  // JavaScript cannot read the cookie
            'secure'   => $https, // sent over HTTPS only when the site uses HTTPS
            'samesite' => 'Lax', // not sent with form posts from other sites
        ]);
        session_start();

        // Sign out after a period without activity.
        $now = time();
        $last = (int) ($_SESSION['_last_activity'] ?? $now);
        if (isset($_SESSION['user']) && $now - $last > self::IDLE_TIMEOUT) {
            $_SESSION = [];
            session_regenerate_id(true);
            self::flash('You were signed out after ' . intdiv(self::IDLE_TIMEOUT, 60) . ' minutes of inactivity.', 'Session Expired', 'information');
        }
        $_SESSION['_last_activity'] = $now;
    }

    /** Seconds without a request before a signed-in user is signed out. */
    public const IDLE_TIMEOUT = 60 * 60;

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

    /**
     * New session id and new CSRF token. Called on sign-in and password change so a
     * session id or token captured before cannot be reused (session fixation).
     */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        unset($_SESSION['_csrf']);
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
