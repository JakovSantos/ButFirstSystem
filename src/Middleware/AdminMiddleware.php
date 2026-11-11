<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Admin-only actions (adding or updating payroll, editing or removing products,
 * renaming employees) are not available to employee accounts. The buttons are disabled in the UI and the
 * matching routes are blocked here so they cannot be called directly.
 */
final class AdminMiddleware implements Middleware
{
    public function handle(Request $request): void
    {
        $user = Session::get('user');

        // API-key callers act as admin.
        if ($user === null && $request->isApi()) {
            return;
        }

        if (($user['role'] ?? null) === 'admin') {
            return;
        }

        if ($request->isApi()) {
            Response::json(['error' => 'This action is not allowed for employee accounts.'], 403);
            exit;
        }

        Session::flash('This action is not allowed for employee accounts.', 'Access Denied', 'warning');
        // back() only follows same-site paths, so a forged Referer cannot redirect to another site.
        Response::back($request->server, '/inventory');
    }
}
