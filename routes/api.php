<?php

declare(strict_types=1);

use App\Core\Response;
use App\Core\Router;
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;

/*
|--------------------------------------------------------------------------
| JSON API  (session cookie or "X-API-Key" header)
|--------------------------------------------------------------------------
*/
Router::middleware('GET|POST|PUT|DELETE', '/api/(products|payroll|shifts|holidays)(/.*)?', AuthMiddleware::class);
Router::middleware('POST|PUT|DELETE', '/api/(products|payroll)(/.*)?', AdminMiddleware::class);
Router::middleware('POST', '/api/shifts/randomize', AdminMiddleware::class);

Router::get('/api/health', static function (): void {
    Response::json([
        'status' => 'ok',
        'time'   => date(DATE_ATOM),
    ]);
});

// Inventory
Router::get('/api/products', 'Api\ProductController@index');
Router::get('/api/products/(\d+)', 'Api\ProductController@show');
Router::post('/api/products', 'Api\ProductController@store');
Router::put('/api/products/(\d+)', 'Api\ProductController@update');
Router::delete('/api/products/(\d+)', 'Api\ProductController@destroy');

// Payroll
Router::get('/api/payroll', 'Api\PayrollController@index');
Router::get('/api/payroll/(\d+)', 'Api\PayrollController@show');
Router::post('/api/payroll', 'Api\PayrollController@store');
Router::put('/api/payroll/(\d+)', 'Api\PayrollController@update');
Router::delete('/api/payroll/(\d+)', 'Api\PayrollController@destroy');

// Employee shifts
Router::get('/api/shifts', 'Api\ShiftController@index');
Router::get('/api/shifts/(\d+)', 'Api\ShiftController@show');
Router::put('/api/shifts/(\d+)/dayoff', 'Api\ShiftController@dayOff');
Router::post('/api/shifts/randomize', 'Api\ShiftController@randomize');

// External API proxy (Nager.Date public holidays)
Router::get('/api/holidays', 'Api\HolidayController@index');
