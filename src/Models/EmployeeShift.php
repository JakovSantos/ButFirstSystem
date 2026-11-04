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

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'payroll_employee_id', 'employee_id');
    }
}
