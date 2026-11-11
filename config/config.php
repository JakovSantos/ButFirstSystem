<?php

declare(strict_types=1);

/*
 * Application configuration.
 *
 * Plain PHP (no .env file) as in the DIY framework module. Every value can be
 * overridden with an environment variable, e.g.  DB_DRIVER=mysql composer serve
 */

$env = static fn (string $key, mixed $default = null): mixed =>
    ($value = getenv($key)) === false || $value === '' ? $default : $value;

return [
    'app' => [
        'name'     => 'But First, System',
        'company'  => 'BUT FIRST COFFEE FLORIDABLANCA',
        'address'  => 'JAMM BLDG, Brgy Solib, Floridablanca, Pampanga',
        'prepared_by' => 'Dr. Ben B. Pagaduan, General Manager',
        'base_url' => $env('APP_URL', 'http://localhost:8000'),
        'timezone' => $env('APP_TIMEZONE', 'Asia/Manila'),
        // Off by default so error details are never shown to visitors; set APP_DEBUG=true while developing.
        'debug'    => filter_var($env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
        'log_file' => __DIR__ . '/../storage/logs/app.log',
    ],

    // Payroll defaults used by PayrollCalculator.
    // daily_rate is the default for new employees; admins can change it per employee.
    'payroll' => [
        'daily_rate'    => 550,
        'hours_per_day' => 8,
        'ot_multiplier' => 1.25,
    ],

    // Login accounts live in the `users` table. These are the defaults used when the
    // accounts are first created (composer db:seed / db:migrate, or a new payroll employee).
    // Employees sign in with their Employee ID, e.g. F-001.
    'accounts' => [
        'admin_username'    => $env('ADMIN_USERNAME', 'admin'),
        'admin_password'    => $env('ADMIN_PASSWORD', 'admin123'),
        'employee_password' => $env('EMPLOYEE_DEFAULT_PASSWORD', 'employee123'),
    ],

    // Database. SQLite is the default (zero setup); switch DB_DRIVER to mysql for XAMPP.
    'database' => [
        'driver'    => $env('DB_DRIVER', 'sqlite'),
        'database'  => $env('DB_DRIVER', 'sqlite') === 'sqlite'
            ? $env('DB_DATABASE', __DIR__ . '/../database/database.sqlite')
            : $env('DB_DATABASE', 'butfirstsystemdb'),
        'host'      => $env('DB_HOST', '127.0.0.1'),
        'port'      => $env('DB_PORT', '3306'),
        'username'  => $env('DB_USERNAME', 'root'),
        'password'  => $env('DB_PASSWORD', ''),
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix'    => '',
    ],

    // External API: Nager.Date public holidays (no key required).
    'holidays_api' => [
        'base_url'  => $env('HOLIDAYS_API_URL', 'https://date.nager.at/api/v3'),
        'country'   => $env('HOLIDAYS_COUNTRY', 'PH'),
        'timeout'   => 8,
        'cache_dir' => __DIR__ . '/../storage/cache',
        'cache_ttl' => 60 * 60 * 24, // one day
    ],

    // Our own JSON API: callers authenticate with a logged-in session
    // or with the header  X-API-Key: <key>  (handy for curl / Postman).
    // The built-in key is for local development only: set API_KEY to a long random value
    // (e.g. php -r "echo bin2hex(random_bytes(24));") anywhere other people can reach.
    'api' => [
        'key' => $env('API_KEY', 'butfirst-dev-key'),
    ],

    // Brute-force protection: after this many failed sign-ins for one username from one
    // address, that username is locked for lockout_seconds.
    'security' => [
        'login_max_attempts' => 5,
        'login_lockout_seconds' => 15 * 60,
        'api_key_max_attempts' => 20,   // wrong X-API-Key values per address per minute
    ],

    'uploads' => [
        'dir'        => __DIR__ . '/../public/uploads/products',
        'url'        => '/uploads/products',
        'max_bytes'  => 5 * 1024 * 1024,
        'extensions' => ['jpg', 'jpeg', 'png', 'bmp'],
    ],
];
