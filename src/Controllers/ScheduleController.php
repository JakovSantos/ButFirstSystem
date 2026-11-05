<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\EmployeeShift;
use App\Support\Format;
use App\Support\MainWindow;
use App\Support\ShiftScheduler;

/**
 * EMPLOYEE SCHEDULE module: randomize shifts, request and reset days off,
 * rename employees and print the schedule.
 */
final class ScheduleController
{
    public function index(): void
    {
        View::display('main', MainWindow::data('schedule', $_GET));
    }

    /** POST /schedule/randomize */
    public function randomize(): void
    {
        $result = (new ShiftScheduler())->randomize();

        if ($result !== null) {
            Session::flash($result['message'], $result['caption'], $result['icon']);
        } else {
            Session::flash('Schedule randomized successfully!', 'Success', 'information');
        }

        Response::redirect('/schedule' . $this->periodQuery());
    }

    /** POST /schedule/dayoff  (Request) */
    public function requestDayOff(): void
    {
        $name = trim((string) ($_POST['names'] ?? ''));
        $day = trim((string) ($_POST['day_off'] ?? ''));

        $result = (new ShiftScheduler())->requestDayOff($name, $day);
        Session::flash($result['message'], $result['caption'], $result['icon']);

        Response::redirect('/schedule' . $this->periodQuery());
    }

    /** POST /schedule/{id}/name  (admin) — rename an employee everywhere */
    public function rename(string $id): void
    {
        $shift = EmployeeShift::find((int) $id);
        $name = trim(preg_replace('/\s+/', ' ', (string) ($_POST['names'] ?? '')) ?? '');

        if ($shift === null) {
            Session::flash('The selected employee no longer exists.', 'Not Found', 'warning');
        } elseif ($name === '' || mb_strlen($name) > 100) {
            Session::flash('Please enter a name (up to 100 characters).', 'Validation Error', 'warning');
        } elseif (EmployeeShift::query()->where('id', '!=', $shift->id)->whereRaw('LOWER(names) = ?', [mb_strtolower($name)])->exists()) {
            Session::flash("Another employee is already named {$name}.", 'Duplicate Name', 'warning');
        } else {
            $old = $shift->names;
            $shift->names = $name;
            $shift->save(); // EmployeeShift::save() also renames the linked payroll record
            Session::flash("{$old} is now {$name} on the schedule and payroll.", 'Name Updated', 'information');
        }

        Response::redirect('/schedule' . $this->periodQuery());
    }

    /** POST /schedule/reset-dayoff */
    public function resetDayOff(): void
    {
        $count = EmployeeShift::query()->update(['day_off' => null]);

        Session::flash(
            "Day off assignments have been reset successfully.\n{$count} record(s) updated.",
            'Reset Complete',
            'information'
        );

        Response::redirect('/schedule' . $this->periodQuery());
    }

    /** GET /schedule/print?start=&end=  (EMPLOYEE SHIFT SCHEDULE) */
    public function print(): void
    {
        $rows = EmployeeShift::query()->orderBy('id')->get();

        if ($rows->isEmpty()) {
            Session::flash('No schedule data to print.', 'No Data', 'information');
            Response::redirect('/schedule');
        }

        View::display('print/schedule', [
            'title'     => 'Employee Shift Schedule',
            'rows'      => $rows->map(static fn (EmployeeShift $s): array => $s->only(['names', 'schedule', 'breaks', 'duties', 'day_off']))->all(),
            'start'     => Format::longDate($_GET['start'] ?? null),
            'end'       => Format::longDate($_GET['end'] ?? null),
            'generated' => date('F d, Y H:i'),
            'company'   => App::config('app.company'),
        ], 'print/layout');
    }

    private function periodQuery(): string
    {
        $q = array_filter([
            'sched_start' => $_POST['sched_start'] ?? null,
            'sched_end'   => $_POST['sched_end'] ?? null,
        ]);

        return $q ? '?' . http_build_query($q) : '';
    }
}
