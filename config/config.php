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
        'debug'    => filter_var($env('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOL),
        'log_file' => __DIR__ . '/../storage/logs/app.log',
    ],

    // Payroll constants taken from the C# CalculatePayroll() method.
    'payroll' => [
        'daily_rate'    => 550,
        'hours_per_day' => 8,
        'ot_multiplier' => 1.25,
    ],

    // Hard-coded accounts from the C# Login form (Form1.cs).
    // Passwords are stored as hashes; the plain values are admin123 / employee123.
    'users' => [
        'admin' => [
            'role' => 'admin',
            'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
        ],
        'employee' => [
            'role' => 'employee',
            'password_hash' => password_hash('employee123', PASSWORD_DEFAULT),
        ],
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
    'api' => [
        'key' => $env('API_KEY', 'butfirst-dev-key'),
    ],

    'uploads' => [
        'dir'        => __DIR__ . '/../public/uploads/products',
        'url'        => '/uploads/products',
        'max_bytes'  => 5 * 1024 * 1024,
        'extensions' => ['jpg', 'jpeg', 'png', 'bmp'],
    ],
];
