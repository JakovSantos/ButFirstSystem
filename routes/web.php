<?php

declare(strict_types=1);

use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RequestLogger;
use App\Middleware\SecurityHeaders;

/*
|--------------------------------------------------------------------------
| Middleware (runs before the matching routes, in this order)
|--------------------------------------------------------------------------
*/
Router::middleware('GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS', '/.*', SecurityHeaders::class);
Router::middleware('GET|POST|PUT|DELETE', '/.*', RequestLogger::class);
Router::middleware('POST|PUT|PATCH|DELETE', '/.*', CsrfMiddleware::class);
Router::middleware('GET|POST', '/(inventory|schedule|payroll|employees|account|logout)(/.*)?', AuthMiddleware::class);

// Admin-only actions: employee accounts cannot edit products, payroll or names.
Router::middleware('POST', '/inventory/\d+', AdminMiddleware::class);
Router::middleware('POST', '/inventory/\d+/delete', AdminMiddleware::class);
Router::middleware('POST', '/payroll', AdminMiddleware::class);

// Payroll is private: employees see only their own record, so editing payroll,
// the full payroll report and renaming employees are admin-only.
Router::middleware('POST', '/payroll/\d+', AdminMiddleware::class);
Router::middleware('GET', '/payroll/print', AdminMiddleware::class);
Router::middleware('POST', '/schedule/\d+/name', AdminMiddleware::class);
// Shuffling the roster and clearing everyone's days off affect all employees: admin-only.
Router::middleware('POST', '/schedule/(randomize|reset-dayoff)', AdminMiddleware::class);

// Managing employees (add, rename, remove, reset password) is admin-only.
Router::middleware('GET|POST', '/employees(/.*)?', AdminMiddleware::class);

/*
|--------------------------------------------------------------------------
| Login
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
| Main window - INVENTORY
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
Router::post('/schedule/(\d+)/name', 'ScheduleController@rename');

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
| EMPLOYEES (admin) - Employee IDs look like F-007
|--------------------------------------------------------------------------
*/
Router::get('/employees', 'EmployeeController@index');
Router::post('/employees', 'EmployeeController@store');
Router::post('/employees/([A-Za-z]+-\d+)/name', 'EmployeeController@rename');
Router::post('/employees/([A-Za-z]+-\d+)/delete', 'EmployeeController@destroy');
Router::post('/employees/([A-Za-z]+-\d+)/reset-password', 'EmployeeController@resetPassword');

/*
|--------------------------------------------------------------------------
| MY ACCOUNT - change your own password
|--------------------------------------------------------------------------
*/
Router::get('/account', 'AccountController@index');
Router::post('/account/password', 'AccountController@updatePassword');

/*
|--------------------------------------------------------------------------
| API reference page
|--------------------------------------------------------------------------
*/
Router::get('/docs', 'DocsController@index');
