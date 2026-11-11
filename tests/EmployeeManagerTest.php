<?php

declare(strict_types=1);

namespace Tests;

use App\Models\EmployeeShift;
use App\Models\Payroll;
use App\Models\User;
use App\Support\EmployeeManager;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

/**
 * Adding, renaming and removing employees, and their passwords.
 * Uses an in-memory SQLite database so the real data is never touched.
 */
final class EmployeeManagerTest extends TestCase
{
    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $schema = $capsule->schema();
        $schema->create('payroll', static function (Blueprint $t): void {
            $t->increments('id');
            $t->string('employee_id')->unique();
            $t->string('name');
            $t->decimal('daily_rate', 10, 2)->default(550);
            $t->timestamps();
        });
        $schema->create('employee_shift', static function (Blueprint $t): void {
            $t->increments('id');
            $t->string('payroll_employee_id')->nullable();
            $t->string('names');
            $t->string('schedule')->nullable();
            $t->string('day_off')->nullable();
            $t->timestamps();
        });
        $schema->create('users', static function (Blueprint $t): void {
            $t->increments('id');
            $t->string('username')->unique();
            $t->string('password_hash');
            $t->string('role')->default('employee');
            $t->string('employee_id')->nullable()->unique();
            $t->boolean('must_change_password')->default(false);
            $t->timestamps();
        });

        EmployeeManager::add('F-006', 'Kristine Mendoza');
    }

    public function testAddCreatesPayrollShiftAndLogin(): void
    {
        $result = EmployeeManager::add('f-007', '  Ana   Lopez ', 600);

        self::assertTrue($result['ok'], $result['message']);
        self::assertSame('F-007', $result['employee_id']);
        self::assertSame('Ana Lopez', Payroll::query()->where('employee_id', 'F-007')->value('name'));
        self::assertEquals(600, Payroll::query()->where('employee_id', 'F-007')->value('daily_rate'));
        self::assertSame('Ana Lopez', EmployeeShift::query()->where('payroll_employee_id', 'F-007')->value('names'));

        $user = User::findByUsername('f-007'); // sign-in is not case-sensitive
        self::assertNotNull($user);
        self::assertSame('employee', $user->role);
        self::assertTrue($user->must_change_password);
        self::assertTrue(password_verify('employee123', $user->password_hash));
    }

    public function testAddRejectsBadOrDuplicateInput(): void
    {
        self::assertFalse(EmployeeManager::add('7', 'Someone')['ok']);
        self::assertFalse(EmployeeManager::add('F-007', '')['ok']);
        self::assertFalse(EmployeeManager::add('f-006', 'Someone Else')['ok']);      // ID taken
        self::assertFalse(EmployeeManager::add('F-008', 'kristine mendoza')['ok']); // name taken
        self::assertSame(1, Payroll::count());
    }

    public function testNextIdFollowsTheHighestId(): void
    {
        self::assertSame('F-007', EmployeeManager::nextId());
        EmployeeManager::add('F-010', 'Ana Lopez');
        self::assertSame('F-011', EmployeeManager::nextId());
    }

    public function testRenameUpdatesPayrollAndSchedule(): void
    {
        $result = EmployeeManager::rename('F-006', 'Kristine M. Mendoza');

        self::assertTrue($result['ok'], $result['message']);
        self::assertSame('Kristine M. Mendoza', Payroll::query()->where('employee_id', 'F-006')->value('name'));
        self::assertSame('Kristine M. Mendoza', EmployeeShift::query()->where('payroll_employee_id', 'F-006')->value('names'));
    }

    public function testRenameRejectsANameAlreadyInUse(): void
    {
        EmployeeManager::add('F-007', 'Ana Lopez');

        self::assertFalse(EmployeeManager::rename('F-007', 'Kristine Mendoza')['ok']);
        self::assertFalse(EmployeeManager::rename('F-099', 'Nobody')['ok']);
    }

    public function testRemoveDeletesEverything(): void
    {
        $result = EmployeeManager::remove('F-006');

        self::assertTrue($result['ok'], $result['message']);
        self::assertSame(0, Payroll::count());
        self::assertSame(0, EmployeeShift::count());
        self::assertSame(0, User::count());
    }

    public function testEmployeeSetsOwnPasswordAndAdminCanResetIt(): void
    {
        $user = User::findByUsername('F-006');

        self::assertFalse(EmployeeManager::changePassword($user, 'wrong', 'myNewPass1', 'myNewPass1')['ok']);
        self::assertFalse(EmployeeManager::changePassword($user, 'employee123', 'short', 'short')['ok']);
        self::assertFalse(EmployeeManager::changePassword($user, 'employee123', 'myNewPass1', 'different')['ok']);
        self::assertFalse(EmployeeManager::changePassword($user, 'employee123', 'employee123', 'employee123')['ok']);

        $result = EmployeeManager::changePassword($user, 'employee123', 'myNewPass1', 'myNewPass1');
        self::assertTrue($result['ok'], $result['message']);
        $user->refresh();
        self::assertTrue(password_verify('myNewPass1', $user->password_hash));
        self::assertFalse($user->must_change_password);

        // Forgot it: the admin resets it to the starting password.
        self::assertTrue(EmployeeManager::resetPassword('F-006')['ok']);
        $user->refresh();
        self::assertTrue(password_verify('employee123', $user->password_hash));
        self::assertTrue($user->must_change_password);
    }
}
