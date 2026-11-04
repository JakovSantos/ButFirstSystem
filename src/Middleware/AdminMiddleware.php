<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Mirrors the C# employee restrictions from Form1.btnLogin_Click:
 *   btnAddCalculate (add payroll), btnUpdate (edit product) and btnRemove (remove product)
 * are disabled for the "employee" account. The buttons are disabled in the UI and the
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
        $back = parse_url((string) ($request->server['HTTP_REFERER'] ?? '/inventory'), PHP_URL_PATH) ?: '/inventory';
        Response::redirect($back);
    }
}
