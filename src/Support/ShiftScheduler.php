<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\EmployeeShift;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Shift logic shared by the HTML and JSON controllers.
 */
final class ShiftScheduler
{
    /**
     * Randomize: shuffle employees over the existing schedule slots
     * (Schedule / Breaks / Duties) and clear every Day Off.
     *
     * @return array{message:string,caption:string,icon:string}|null  null on success
     */
    public function randomize(?callable $shuffle = null): ?array
    {
        $rows = EmployeeShift::query()->orderBy('id')->get();
        $employees = $rows->filter(static fn (EmployeeShift $s): bool => trim((string) $s->names) !== '')
            ->unique(static fn (EmployeeShift $s): string => mb_strtolower(trim((string) $s->names)))
            ->values();

        if ($employees->count() < 2) {
            return [
                'message' => 'Need at least 2 employees to generate schedule.',
                'caption' => 'Insufficient Data',
                'icon'    => 'warning',
            ];
        }

        if ($rows->isEmpty()) {
            return [
                'message' => 'No existing schedule found to shuffle.',
                'caption' => 'No Data',
                'icon'    => 'warning',
            ];
        }

        // Existing slots, in table order.
        $slots = $rows->map(static fn (EmployeeShift $s): array => [
            'schedule' => $s->schedule,
            'breaks'   => $s->breaks,
            'duties'   => $s->duties,
        ])->all();

        // Randomly shuffle all employees (name + payroll link travel together).
        $people = $employees->map(static fn (EmployeeShift $s): array => [
            'names' => $s->names,
            'payroll_employee_id' => $s->payroll_employee_id,
        ])->all();
        $shuffle ??= static function (array $a): array {
            shuffle($a);
            return $a;
        };
        $people = $shuffle($people);

        DB::connection()->transaction(static function () use ($slots, $people): void {
            EmployeeShift::query()->delete();
            $n = min(count($people), count($slots));
            for ($i = 0; $i < $n; $i++) {
                EmployeeShift::create($people[$i] + $slots[$i] + ['day_off' => null]);
            }
        });

        return null;
    }

    /**
     * Request a day off: refused if another employee already has that day.
     *
     * @return array{message:string,caption:string,icon:string,ok?:bool}
     */
    public function requestDayOff(string $name, string $day): array
    {
        if ($name === '') {
            return ['message' => 'Please select an employee from the grid.', 'caption' => 'Validation Error', 'icon' => 'warning'];
        }

        if (! in_array($day, EmployeeShift::DAYS, true)) {
            return ['message' => 'Please select a day off from the dropdown.', 'caption' => 'Validation Error', 'icon' => 'warning'];
        }

        $takenBy = EmployeeShift::query()
            ->where('day_off', $day)
            ->where('names', '!=', $name)
            ->value('names');

        if ($takenBy !== null) {
            return [
                'message' => "The day off '{$day}' is already taken by {$takenBy}.\nPlease choose a different day.",
                'caption' => 'Day Off Already Taken',
                'icon'    => 'error',
            ];
        }

        $updated = EmployeeShift::query()->where('names', $name)->update(['day_off' => $day]);

        if ($updated === 0) {
            return ['message' => "Employee '{$name}' not found in the schedule.", 'caption' => 'Update Error', 'icon' => 'warning'];
        }

        return [
            'message' => "Day off request for {$name} has been saved successfully.\nDay Off: {$day}",
            'caption' => 'Request Saved',
            'icon'    => 'information',
            'ok'      => true,
        ];
    }
}
