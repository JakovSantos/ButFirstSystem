<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\App;
use App\Core\Database;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;

/**
 * Additive, idempotent schema changes. Safe to run on a database that already
 * has data (composer db:migrate); also called by database/seed.php.
 *
 *  - payroll.daily_rate  : per-employee base pay (default 550, editable by admin)
 *  - users               : login accounts (admin + one account per employee ID)
 *  - users.must_change_password : employees on a default or reset password are asked
 *                          to set their own when they sign in
 */
final class Migrator
{
    /** @return list<string> what was changed */
    public static function up(): array
    {
        $schema = Database::schema();
        $done = [];

        if (! $schema->hasColumn('payroll', 'daily_rate')) {
            $schema->table('payroll', static function (Blueprint $t): void {
                $t->decimal('daily_rate', 10, 2)->default(550);
            });
            $done[] = 'Added payroll.daily_rate (default 550)';
        }

        if (! $schema->hasTable('users')) {
            $schema->create('users', static function (Blueprint $t): void {
                $t->increments('id');
                $t->string('username', 50)->unique();
                $t->string('password_hash', 255);
                $t->string('role', 20)->default('employee');
                $t->string('employee_id', 50)->nullable()->unique();
                $t->boolean('must_change_password')->default(false);
                $t->timestamps();

                $t->foreign('employee_id')->references('employee_id')->on('payroll')
                    ->cascadeOnDelete()->cascadeOnUpdate();
            });
            $done[] = 'Created users table';
        }

        if (! $schema->hasColumn('users', 'must_change_password')) {
            $schema->table('users', static function (Blueprint $t): void {
                $t->boolean('must_change_password')->default(false);
            });
            $done[] = 'Added users.must_change_password';
        }

        $created = self::syncAccounts();
        if ($created > 0) {
            $done[] = "Created {$created} login account(s)";
        }

        $flagged = self::flagStartingPasswords();
        if ($flagged > 0) {
            $done[] = "{$flagged} account(s) still on a starting password will be asked to set their own";
        }

        return $done;
    }

    /**
     * Any account (admin included) still using a well-known starting password
     * (admin123 / employee123) must choose its own at the next sign-in.
     */
    public static function flagStartingPasswords(): int
    {
        $defaults = [
            'admin'    => (string) App::config('accounts.admin_password', 'admin123'),
            'employee' => (string) App::config('accounts.employee_password', 'employee123'),
        ];
        $flagged = 0;

        foreach (User::query()->where('must_change_password', false)->get() as $user) {
            $default = $defaults[$user->role] ?? null;
            if ($default !== null && password_verify($default, (string) $user->password_hash)) {
                $user->must_change_password = true;
                $user->save();
                $flagged++;
            }
        }

        return $flagged;
    }

    /**
     * Make sure the admin account exists and every payroll employee has a login
     * (username = Employee ID, default password from config).
     */
    public static function syncAccounts(): int
    {
        $created = 0;
        $defaults = (array) App::config('accounts', []);

        if (! User::query()->where('role', 'admin')->exists()) {
            User::create([
                'username' => (string) ($defaults['admin_username'] ?? 'admin'),
                'password_hash' => password_hash((string) ($defaults['admin_password'] ?? 'admin123'), PASSWORD_DEFAULT),
                'role' => 'admin',
                'must_change_password' => true,
            ]);
            $created++;
        }

        foreach (Payroll::query()->pluck('employee_id') as $employeeId) {
            if (self::ensureEmployeeAccount((string) $employeeId)) {
                $created++;
            }
        }

        return $created;
    }

    /** Create the login for one employee if it is missing. Returns true when created. */
    public static function ensureEmployeeAccount(string $employeeId): bool
    {
        if ($employeeId === '' || User::query()->where('employee_id', $employeeId)->exists()) {
            return false;
        }

        User::create([
            'username' => $employeeId,
            'password_hash' => password_hash((string) App::config('accounts.employee_password', 'employee123'), PASSWORD_DEFAULT),
            'role' => 'employee',
            'employee_id' => $employeeId,
            'must_change_password' => true,
        ]);

        return true;
    }
}
