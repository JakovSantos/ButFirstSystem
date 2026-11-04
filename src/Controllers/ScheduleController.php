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
 * EMPLOYEE SCHEDULE tab. Mirrors btnRandomize_Click, btnRequest_Click,
 * btnResetDayoff_Click and btnPrintSched_Click from the C# Form2.
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
