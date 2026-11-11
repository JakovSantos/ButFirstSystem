<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Cross-site request forgery protection for every request that changes data.
 *
 *  - HTML forms (POST) must carry the session's secret _token.
 *  - JSON API writes (POST/PUT/DELETE) must be sent as application/json and must not
 *    come from another site. A browser cannot send a JSON body to another site without
 *    that site allowing it (CORS), so a malicious page cannot use a signed-in admin's
 *    cookie to change data. Callers using the X-API-Key header have no cookie to abuse.
 */
final class CsrfMiddleware implements Middleware
{
    public function handle(Request $request): void
    {
        if (! in_array($request->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        if ($request->isApi()) {
            // PUT/DELETE can never be sent cross-site without CORS; POST can (as a "simple" form
            // request), so a POST must declare a JSON body.
            if (! $request->isSameOrigin() || ($request->method === 'POST' && ! $request->isJson())) {
                Response::json(['error' => 'Requests that change data must be same-site and use Content-Type: application/json.'], 415);
                exit;
            }
            return;
        }

        $token = $request->body['_token'] ?? '';

        if (! is_string($token) || $token === '' || ! hash_equals(Session::csrfToken(), $token)) {
            Session::flash('Your session expired. Please try again.', 'Security Check', 'warning');
            Response::redirect($request->path === '/login' ? '/login' : '/inventory');
        }
    }
}
