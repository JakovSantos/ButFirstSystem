<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Employee shift row (C# table: EmployeeShift).
 */
final class EmployeeShift extends Model
{
    protected $table = 'employee_shift';

    /** @var list<string> */
    protected $fillable = ['payroll_employee_id', 'names', 'schedule', 'breaks', 'duties', 'day_off'];

    public const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /**
     * Renaming an employee on the schedule also renames the linked payroll record.
     * (A query update is used so Payroll::save() does not loop back.)
     */
    public function save(array $options = []): bool
    {
        $renamed = $this->exists && $this->isDirty('names');
        $saved = parent::save($options);

        if ($saved && $renamed && $this->payroll_employee_id) {
            Payroll::query()
                ->where('employee_id', $this->payroll_employee_id)
                ->update(['name' => $this->names]);
        }

        return $saved;
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'payroll_employee_id', 'employee_id');
    }
}
