<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Rejects HTML form POSTs that do not carry the session CSRF token.
 * (JSON API routes are skipped: they use the session cookie or the X-API-Key header.)
 */
final class CsrfMiddleware implements Middleware
{
    public function handle(Request $request): void
    {
        if ($request->method !== 'POST' || $request->isApi()) {
            return;
        }

        $token = (string) ($request->body['_token'] ?? '');

        if ($token === '' || ! hash_equals(Session::csrfToken(), $token)) {
            Session::flash('Your session expired. Please try again.', 'Security Check', 'warning');
            Response::redirect($request->path === '/login' ? '/login' : '/inventory');
        }
    }
}
