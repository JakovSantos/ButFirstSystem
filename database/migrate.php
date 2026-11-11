<?php

declare(strict_types=1);

/*
 * composer db:migrate
 *
 * Upgrades an existing database in place (no data is deleted):
 * adds payroll.daily_rate, the users table (with must_change_password)
 * and the login accounts.
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Core\App;
use App\Support\Migrator;

App::boot(dirname(__DIR__));

$changes = Migrator::up();

echo $changes === []
    ? "Database is already up to date (" . App::config('database.driver') . ").\n"
    : implode("\n", $changes) . "\nDone (" . App::config('database.driver') . ").\n";
