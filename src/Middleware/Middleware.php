<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;

/**
 * A middleware inspects the request before the controller runs.
 * To stop the request it sends a response and exits.
 */
interface Middleware
{
    public function handle(Request $request): void;
}
