<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;

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
            // The account may have been removed by the admin since this user signed in.
            $account = User::find((int) $user['id']);
            if ($account === null) {
                Session::destroy();
                Session::start();
                Session::flash('Your account no longer exists. Please contact the admin.', 'Signed Out', 'warning');
                Response::redirect('/login');
            }

            // Keep the name in the sidebar current if the admin renamed this employee.
            if ($account->role === 'employee') {
                $name = (string) ($account->payroll?->name ?? $account->username);
                if ($name !== ($user['name'] ?? '')) {
                    Session::put('user', ['name' => $name] + $user);
                }
            }

            // New accounts and admin resets must choose their own password first.
            $path = rtrim($request->path, '/') ?: '/';
            if ($account->must_change_password && ! $request->isApi()
                && ! in_array($path, ['/account', '/account/password', '/logout'], true)) {
                Session::flash('Please set your own password before you continue.', 'Set Your Password', 'information');
                Response::redirect('/account');
            }

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
