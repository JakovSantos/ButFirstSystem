<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\App;
use App\Models\EmployeeShift;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Employee accounts: add, rename and remove employees, and manage their passwords.
 *
 * An employee is one payroll record, one shift row and one login account, all linked
 * by the Employee ID (e.g. F-007). The Employee ID is also the login username.
 *
 * Every method returns ['ok' => bool, 'message' => string] so the controller only
 * has to show the message.
 */
final class EmployeeManager
{
    public const MIN_PASSWORD = 6;
    public const MAX_PASSWORD = 72; // password_hash() (bcrypt) ignores anything longer

    /** Employee IDs look like F-007: letters, a dash, then digits. */
    public static function normalizeId(string $id): string
    {
        return strtoupper(trim($id));
    }

    public static function isValidId(string $id): bool
    {
        return preg_match('/^[A-Z]{1,5}-\d{1,6}$/', $id) === 1;
    }

    public static function cleanName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name) ?? '');
    }

    /** The next free ID after the highest one in use, e.g. F-006 → F-007. */
    public static function nextId(): string
    {
        $max = 0;
        foreach (Payroll::query()->pluck('employee_id') as $id) {
            if (preg_match('/^F-(\d+)$/i', (string) $id, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return sprintf('F-%03d', $max + 1);
    }

    /**
     * Add an employee: payroll record (zero pay until the admin fills it in),
     * a shift row for the schedule, and a login with the default password.
     *
     * @return array{ok:bool,message:string,employee_id?:string}
     */
    public static function add(string $employeeId, string $name, ?float $dailyRate = null): array
    {
        $employeeId = self::normalizeId($employeeId);
        $name = self::cleanName($name);

        if (! self::isValidId($employeeId)) {
            return self::fail('Please enter an Employee ID like F-007.');
        }
        if ($error = self::nameError($name)) {
            return self::fail($error);
        }
        if (Payroll::query()->whereRaw('UPPER(employee_id) = ?', [$employeeId])->exists()
            || User::findByUsername($employeeId) !== null) {
            return self::fail("Employee ID {$employeeId} is already in use.");
        }
        if (self::nameTaken($name)) {
            return self::fail("Another employee is already named {$name}.");
        }
        $rate = $dailyRate ?? (float) App::config('payroll.daily_rate', 550);
        if ($rate <= 0 || $rate > 100000) {
            return self::fail('Daily rate must be a positive amount.');
        }

        DB::connection()->transaction(static function () use ($employeeId, $name, $rate): void {
            Payroll::create(['employee_id' => $employeeId, 'name' => $name, 'daily_rate' => $rate]);
            EmployeeShift::create(['payroll_employee_id' => $employeeId, 'names' => $name]);
            User::create([
                'username'             => $employeeId,
                'password_hash'        => password_hash(self::defaultPassword(), PASSWORD_DEFAULT),
                'role'                 => 'employee',
                'employee_id'          => $employeeId,
                'must_change_password' => true,
            ]);
        });

        return [
            'ok'          => true,
            'message'     => "{$name} was added.\nLogin: {$employeeId} / " . self::defaultPassword()
                . "\nThey will be asked to set their own password when they first sign in.",
            'employee_id' => $employeeId,
        ];
    }

    /** Rename an employee; Payroll::save() also renames the schedule row. */
    public static function rename(string $employeeId, string $name): array
    {
        $payroll = self::find($employeeId);
        $name = self::cleanName($name);

        if ($payroll === null) {
            return self::fail('That employee no longer exists.');
        }
        if ($error = self::nameError($name)) {
            return self::fail($error);
        }
        if ($name === $payroll->name) {
            return ['ok' => true, 'message' => 'The name did not change.'];
        }
        if (self::nameTaken($name, $payroll->employee_id)) {
            return self::fail("Another employee is already named {$name}.");
        }

        $old = $payroll->name;
        $payroll->name = $name;
        $payroll->save();

        return ['ok' => true, 'message' => "{$old} is now {$name} on the schedule and payroll."];
    }

    /** Remove an employee: their payroll record, shift row and login. */
    public static function remove(string $employeeId): array
    {
        $payroll = self::find($employeeId);
        if ($payroll === null) {
            return self::fail('That employee no longer exists.');
        }

        $id = $payroll->employee_id;
        $name = $payroll->name;
        DB::connection()->transaction(static function () use ($payroll, $id): void {
            User::query()->where('employee_id', $id)->delete();
            EmployeeShift::query()->where('payroll_employee_id', $id)->delete();
            $payroll->delete();
        });

        return ['ok' => true, 'message' => "{$name} ({$id}) was removed, including their login."];
    }

    /** Admin reset: back to the default password, to be changed at the next sign-in. */
    public static function resetPassword(string $employeeId): array
    {
        $payroll = self::find($employeeId);
        if ($payroll === null) {
            return self::fail('That employee no longer exists.');
        }

        $user = User::query()->where('employee_id', $payroll->employee_id)->first()
            ?? new User(['username' => $payroll->employee_id, 'role' => 'employee', 'employee_id' => $payroll->employee_id]);
        $user->password_hash = password_hash(self::defaultPassword(), PASSWORD_DEFAULT);
        $user->must_change_password = true;
        $user->save();

        return [
            'ok'      => true,
            'message' => "{$payroll->name}'s password was reset to " . self::defaultPassword()
                . ".\nThey will be asked to set a new one when they sign in.",
        ];
    }

    /** A signed-in user (employee or admin) changes their own password. */
    public static function changePassword(User $user, string $current, string $new, string $confirm): array
    {
        if (! password_verify($current, (string) $user->password_hash)) {
            return self::fail('Your current password is not correct.');
        }
        if (mb_strlen($new) < self::MIN_PASSWORD) {
            return self::fail('The new password must be at least ' . self::MIN_PASSWORD . ' characters.');
        }
        if (strlen($new) > self::MAX_PASSWORD) {
            return self::fail('The new password is too long (' . self::MAX_PASSWORD . ' characters at most).');
        }
        if ($new !== $confirm) {
            return self::fail('The new passwords do not match.');
        }
        if ($new === $current || in_array($new, self::startingPasswords(), true)) {
            return self::fail('Please choose a new password that is different from the old one.');
        }

        $user->password_hash = password_hash($new, PASSWORD_DEFAULT);
        $user->must_change_password = false;
        $user->save();

        return ['ok' => true, 'message' => 'Your password has been changed.'];
    }

    public static function defaultPassword(): string
    {
        return (string) App::config('accounts.employee_password', 'employee123');
    }

    /** The well-known starting passwords, which can never be chosen as someone's own password. @return list<string> */
    public static function startingPasswords(): array
    {
        return [self::defaultPassword(), (string) App::config('accounts.admin_password', 'admin123')];
    }

    private static function find(string $employeeId): ?Payroll
    {
        $employeeId = self::normalizeId($employeeId);

        return $employeeId === '' ? null : Payroll::query()->whereRaw('UPPER(employee_id) = ?', [$employeeId])->first();
    }

    private static function nameError(string $name): ?string
    {
        return $name === '' || mb_strlen($name) > 100 ? 'Please enter a name (up to 100 characters).' : null;
    }

    private static function nameTaken(string $name, ?string $exceptEmployeeId = null): bool
    {
        $lower = mb_strtolower($name);
        $payroll = Payroll::query()->whereRaw('LOWER(name) = ?', [$lower]);
        $shift = EmployeeShift::query()->whereRaw('LOWER(names) = ?', [$lower]);
        if ($exceptEmployeeId !== null) {
            $payroll->where('employee_id', '!=', $exceptEmployeeId);
            $shift->where(static fn ($q) => $q->whereNull('payroll_employee_id')->orWhere('payroll_employee_id', '!=', $exceptEmployeeId));
        }

        return $payroll->exists() || $shift->exists();
    }

    /** @return array{ok:false,message:string} */
    private static function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
