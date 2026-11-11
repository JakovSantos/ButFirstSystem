<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;

/**
 * Browser security headers on every response:
 *  - Content-Security-Policy: scripts only from this site (blocks injected scripts),
 *    no framing by other sites, forms may only post back to this site
 *  - X-Content-Type-Options: no MIME sniffing (an uploaded file is never run as a script)
 *  - X-Frame-Options / frame-ancestors: no clickjacking
 *  - Referrer-Policy: URLs are not leaked to other sites
 *  - Strict-Transport-Security when the site is served over HTTPS
 */
final class SecurityHeaders implements Middleware
{
    public const CSP = "default-src 'self'; "
        . "script-src 'self'; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com; "
        . "img-src 'self' data:; "
        . "connect-src 'self'; "
        . "object-src 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self'; "
        . "frame-ancestors 'none'";

    public function handle(Request $request): void
    {
        if (headers_sent()) {
            return;
        }

        header('Content-Security-Policy: ' . self::CSP);
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header_remove('X-Powered-By');

        if ($request->isHttps()) {
            header('Strict-Transport-Security: max-age=31536000');
        }

        // Pages with personal data (payroll, accounts) must not be stored by shared caches.
        if (! str_starts_with($request->path, '/assets/')) {
            header('Cache-Control: no-store, private');
        }
    }
}
