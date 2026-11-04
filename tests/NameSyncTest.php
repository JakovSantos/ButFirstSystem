<?php

declare(strict_types=1);

namespace Tests;

use App\Models\EmployeeShift;
use App\Models\Payroll;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

/**
 * Renaming an employee in one module must show the new name in the other.
 * Uses an in-memory SQLite database so the real data is never touched.
 */
final class NameSyncTest extends TestCase
{
    public static function setUpBeforeClass(): void
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
            $t->string('day_off')->nullable();
            $t->timestamps();
        });
    }

    protected function setUp(): void
    {
        Payroll::query()->delete();
        EmployeeShift::query()->delete();
        Payroll::create(['employee_id' => 'F-001', 'name' => 'Juan Dela Cruz']);
        EmployeeShift::create(['payroll_employee_id' => 'F-001', 'names' => 'Juan Dela Cruz']);
        EmployeeShift::create(['payroll_employee_id' => null, 'names' => 'Walk-in Helper']);
    }

    public function testRenamingPayrollRenamesShift(): void
    {
        $p = Payroll::query()->where('employee_id', 'F-001')->firstOrFail();
        $p->name = 'Juan P. Dela Cruz';
        $p->save();

        self::assertSame('Juan P. Dela Cruz', EmployeeShift::query()->where('payroll_employee_id', 'F-001')->value('names'));
    }

    public function testRenamingShiftRenamesPayroll(): void
    {
        $s = EmployeeShift::query()->where('payroll_employee_id', 'F-001')->firstOrFail();
        $s->names = 'John Dela Cruz';
        $s->save();

        self::assertSame('John Dela Cruz', Payroll::query()->where('employee_id', 'F-001')->value('name'));
    }

    public function testUnlinkedShiftRenameLeavesPayrollAlone(): void
    {
        $s = EmployeeShift::query()->where('names', 'Walk-in Helper')->firstOrFail();
        $s->names = 'Weekend Helper';
        $s->save();

        self::assertSame('Juan Dela Cruz', Payroll::query()->where('employee_id', 'F-001')->value('name'));
    }
}
