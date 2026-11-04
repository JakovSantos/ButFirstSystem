<?php

declare(strict_types=1);

/*
 * Front controller: boot the app, register routes, run the router, handle 404s.
 */

// Let the PHP built-in server serve static files (css, js, images) directly.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    if ($file !== __DIR__ . '/' && is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../vendor/autoload.php';

use App\Core\App;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;

App::boot(dirname(__DIR__));

// Uncaught errors: JSON 500 for the API, HTML 500 page otherwise.
set_exception_handler(static function (Throwable $e): void {
    Logger::error($e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $debug = (bool) App::config('app.debug', false);

    if (Request::capture()->isApi()) {
        Response::json(['error' => 'Server Error'] + ($debug ? ['message' => $e->getMessage()] : []), 500);
        return;
    }

    http_response_code(500);
    View::display('errors/500', [
        'title'   => 'Server Error',
        'message' => $debug ? $e->getMessage() : 'An unexpected error occurred.',
    ]);
});

require App::basePath('routes/web.php');
require App::basePath('routes/api.php');

Router::set404(static function (): void {
    $request = Request::capture();

    if ($request->isApi()) {
        Response::json([
            'error' => 'Not Found',
            'path'  => $request->path,
        ], 404);
        return;
    }

    http_response_code(404);
    View::display('errors/404', ['title' => 'Page not found']);
});

Router::run();
