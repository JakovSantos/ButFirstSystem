<?php

declare(strict_types=1);

use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RequestLogger;

/*
|--------------------------------------------------------------------------
| Middleware (runs before the matching routes, in this order)
|--------------------------------------------------------------------------
*/
Router::middleware('GET|POST|PUT|DELETE', '/.*', RequestLogger::class);
Router::middleware('POST', '/.*', CsrfMiddleware::class);
Router::middleware('GET|POST', '/(inventory|schedule|payroll|logout)(/.*)?', AuthMiddleware::class);

// Employee restrictions from the C# login (btnUpdate, btnRemove, btnAddCalculate disabled).
Router::middleware('POST', '/inventory/\d+', AdminMiddleware::class);
Router::middleware('POST', '/inventory/\d+/delete', AdminMiddleware::class);
Router::middleware('POST', '/payroll', AdminMiddleware::class);

/*
|--------------------------------------------------------------------------
| Login (C# Form1)
|--------------------------------------------------------------------------
*/
Router::get('/', static function (): void {
    Response::redirect(Session::get('user') ? '/inventory' : '/login');
});
Router::get('/login', 'AuthController@show');
Router::post('/login', 'AuthController@login');
Router::post('/logout', 'AuthController@logout');

/*
|--------------------------------------------------------------------------
| Main window (C# Form2) - INVENTORY tab
|--------------------------------------------------------------------------
*/
Router::get('/inventory', 'InventoryController@index');
Router::get('/inventory/print', 'InventoryController@print');
Router::post('/inventory', 'InventoryController@store');
Router::post('/inventory/(\d+)', 'InventoryController@update');
Router::post('/inventory/(\d+)/delete', 'InventoryController@destroy');

/*
|--------------------------------------------------------------------------
| EMPLOYEE SCHEDULE tab
|--------------------------------------------------------------------------
*/
Router::get('/schedule', 'ScheduleController@index');
Router::get('/schedule/print', 'ScheduleController@print');
Router::post('/schedule/randomize', 'ScheduleController@randomize');
Router::post('/schedule/dayoff', 'ScheduleController@requestDayOff');
Router::post('/schedule/reset-dayoff', 'ScheduleController@resetDayOff');

/*
|--------------------------------------------------------------------------
| PAYROLL MANAGEMENT tab
|--------------------------------------------------------------------------
*/
Router::get('/payroll', 'PayrollController@index');
Router::get('/payroll/print', 'PayrollController@print');
Router::get('/payroll/summary', 'PayrollController@printSummary');
Router::post('/payroll', 'PayrollController@store');
Router::post('/payroll/(\d+)', 'PayrollController@update');

/*
|--------------------------------------------------------------------------
| API reference page
|--------------------------------------------------------------------------
*/
Router::get('/docs', 'DocsController@index');
