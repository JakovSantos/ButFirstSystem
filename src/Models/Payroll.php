<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Payroll record (C# table: Payroll). employee_id is the business key, e.g. "F-006".
 */
final class Payroll extends Model
{
    protected $table = 'payroll';

    /** @var list<string> */
    protected $fillable = [
        'name', 'employee_id', 'daily_rate', 'days_worked', 'ot_hours', 'late_hours', 'deduction_per_hour',
        'holiday_present', 'holiday_type', 'net_pay', 'gross_pay', 'total_deductions',
        'sss', 'philhealth', 'pagibig', 'others',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'daily_rate'         => 'float',
        'days_worked'        => 'integer',
        'ot_hours'           => 'integer',
        'late_hours'         => 'integer',
        'holiday_present'    => 'integer',
        'deduction_per_hour' => 'float',
        'net_pay'            => 'float',
        'gross_pay'          => 'float',
        'total_deductions'   => 'float',
        'sss'                => 'float',
        'philhealth'         => 'float',
        'pagibig'            => 'float',
        'others'             => 'float',
    ];

    public const HOLIDAY_TYPES = ['Regular', 'Special'];

    /**
     * Keep the employee's name the same everywhere: renaming a payroll record also
     * renames the linked shift row (roster, day-off board, printouts).
     */
    public function save(array $options = []): bool
    {
        $renamed = $this->exists && $this->isDirty('name');
        $saved = parent::save($options);

        if ($saved && $renamed) {
            EmployeeShift::query()
                ->where('payroll_employee_id', $this->employee_id)
                ->update(['names' => $this->name]);
        }

        return $saved;
    }

    public function shift(): HasOne
    {
        return $this->hasOne(EmployeeShift::class, 'payroll_employee_id', 'employee_id');
    }
}
