<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Logger;
use App\Core\Request;
use App\Core\Session;

/**
 * Writes one line per request to storage/logs/app.log.
 */
final class RequestLogger implements Middleware
{
    public function handle(Request $request): void
    {
        if (($request->server['REQUEST_METHOD'] ?? '') === 'HEAD') {
            return; // health checks
        }

        $user = Session::get('user')['username'] ?? 'guest';
        Logger::info(sprintf('%s %s %s user=%s', $request->ip(), $request->method, $request->path, $user));
    }
}
