<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Only logged-in users (admin or employee) may open the main window.
 * API callers may instead send the X-API-Key header.
 */
final class AuthMiddleware implements Middleware
{
    public function handle(Request $request): void
    {
        $user = Session::get('user');
        if ($user !== null && ! isset($user['id'])) {
            Session::forget('user'); // session from before database-backed accounts: sign in again
            $user = null;
        }
        if ($user !== null) {
            return;
        }

        if ($request->isApi()) {
            $key = (string) ($request->server['HTTP_X_API_KEY'] ?? '');
            if ($key !== '' && hash_equals((string) App::config('api.key'), $key)) {
                return;
            }

            Response::json(['error' => 'Unauthenticated. Log in or send the X-API-Key header.'], 401);
            exit;
        }

        Response::redirect('/login');
    }
}
