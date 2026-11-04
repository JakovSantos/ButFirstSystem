<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Payroll;
use App\Support\Format;
use App\Support\HolidayApi;
use App\Support\MainWindow;
use App\Support\PayrollCalculator;
use App\Support\Migrator;
use App\Support\PayrollInput;

/**
 * PAYROLL MANAGEMENT tab. Mirrors btnAddCalculate_Click_1, btnUpdatePayroll_Click,
 * btnPrintPayroll_Click and btnPrintEmpPaySum_Click from the C# Form2.
 */
final class PayrollController
{
    public function index(): void
    {
        View::display('main', MainWindow::data('payroll', $_GET));
    }

    /** POST /payroll  (Add | Calculate: insert, or update when the Employee ID exists) */
    public function store(): void
    {
        $input = $this->input();
        $errors = PayrollInput::validate($input);
        if ($errors !== []) {
            $this->fail($errors);
        }

        $data = PayrollInput::normalize($input);
        $data['daily_rate'] ??= (float) App::config('payroll.daily_rate', 550);
        $data += self::calculator()->calculate($data);

        // Payroll::save() also renames the linked shift row, so a renamed
        // employee is renamed on the schedule too.
        $payroll = Payroll::query()->firstOrNew(['employee_id' => $data['employee_id']]);
        $isNew = ! $payroll->exists;
        $payroll->fill($data)->save();

        $message = 'Payroll data saved successfully!';
        if ($isNew && Migrator::ensureEmployeeAccount($payroll->employee_id)) {
            $message .= "\nLogin created: {$payroll->employee_id} / " . App::config('accounts.employee_password', 'employee123');
        }

        Session::flash($message, 'Success', 'information');
        Response::redirect('/payroll?' . http_build_query(['selected' => $data['employee_id']] + $this->period()));
    }

    /** POST /payroll/{id}  (Update, after Edit) */
    public function update(string $id): void
    {
        $payroll = Payroll::find((int) $id);
        if ($payroll === null) {
            Session::flash('No record found to update. Employee may have been deleted.', 'Update Failed', 'warning');
            Response::redirect('/payroll');
        }

        $input = $this->input();
        $errors = PayrollInput::validate($input);
        if ($errors !== []) {
            $this->fail($errors);
        }

        $data = PayrollInput::normalize($input);

        $duplicate = Payroll::query()
            ->where('employee_id', $data['employee_id'])
            ->where('id', '!=', $payroll->id)
            ->exists();
        if ($duplicate) {
            $this->fail(['employee_id' => "Employee ID '{$data['employee_id']}' already belongs to another employee."]);
        }

        $data += self::calculator()->calculate($data);
        $payroll->fill($data)->save();

        Session::flash('Payroll updated successfully!', 'Success', 'information');
        Response::redirect('/payroll?' . http_build_query(['selected' => $payroll->employee_id] + $this->period()));
    }

    /** GET /payroll/print  (PAYROLL REPORT, landscape) */
    public function print(): void
    {
        $rows = Payroll::query()->orderBy('id')->get();

        if ($rows->isEmpty()) {
            Session::flash('No payroll data to print.', 'No Data', 'information');
            Response::redirect('/payroll');
        }

        $start = (string) ($_GET['start'] ?? date('Y-m-d'));
        $end = (string) ($_GET['end'] ?? date('Y-m-d'));

        View::display('print/payroll', [
            'title'     => 'Payroll Report',
            'company'   => App::config('app.company'),
            'address'   => App::config('app.address'),
            'preparedBy' => App::config('app.prepared_by'),
            'start'     => Format::longDate($start),
            'end'       => Format::longDate($end),
            'generated' => date('F d, Y h:i A'),
            'count'     => $rows->count(),
            'holidays'  => $holidays = $this->holidays($start, $end),
            'hasHolidays' => $holidays !== [],
            'rows'      => $rows->map(static fn (Payroll $p): array => [
                'name'             => $p->name,
                'employee_id'      => $p->employee_id,
                'days_worked'      => $p->days_worked,
                'ot_hours'         => $p->ot_hours,
                'late_hours'       => $p->late_hours,
                'gross_pay'        => Format::peso($p->gross_pay),
                'net_pay'          => Format::peso($p->net_pay),
                'total_deductions' => Format::peso($p->total_deductions),
            ])->all(),
        ], 'print/layout');
    }

    /** GET /payroll/summary?employee_id=&start=&end=  (EMPLOYEE PAY SUMMARY) */
    public function printSummary(): void
    {
        $employeeId = trim((string) ($_GET['employee_id'] ?? ''));
        $user = Session::get('user') ?? [];
        if (($user['role'] ?? '') !== 'admin') {
            $employeeId = (string) ($user['employee_id'] ?? ''); // employees can only print their own
        }
        $payroll = $employeeId === '' ? null : Payroll::query()->where('employee_id', $employeeId)->first();

        if ($payroll === null) {
            Session::flash('Please select an employee first.', 'No Selection', 'warning');
            Response::redirect('/payroll');
        }

        $start = (string) ($_GET['start'] ?? date('Y-m-d'));
        $end = (string) ($_GET['end'] ?? date('Y-m-d'));

        View::display('print/pay-summary', [
            'title'      => 'Employee Pay Summary',
            'company'    => App::config('app.company'),
            'address'    => App::config('app.address'),
            'preparedBy' => App::config('app.prepared_by'),
            'start'      => Format::longDate($start),
            'end'        => Format::longDate($end),
            'printed'    => date('F d, Y h:i A'),
            'name'       => $payroll->name,
            'employee_id' => $payroll->employee_id,
            'gross_pay'  => Format::n2($payroll->gross_pay),
            'net_pay'    => Format::n2($payroll->net_pay),
            'holidays'   => $holidays = $this->holidays($start, $end),
            'hasHolidays' => $holidays !== [],
            'portrait'   => true,
        ], 'print/layout');
    }

    public static function calculator(): PayrollCalculator
    {
        return new PayrollCalculator(
            (float) App::config('payroll.daily_rate', 550),
            (float) App::config('payroll.hours_per_day', 8),
            (float) App::config('payroll.ot_multiplier', 1.25),
        );
    }

    /**
     * Holidays in the pay period from the external API (shown on the printouts).
     *
     * @return list<array<string,string>>
     */
    private function holidays(string $start, string $end): array
    {
        return array_map(static fn (array $h): array => $h + [
            'display' => date('M d, Y', strtotime($h['date']) ?: time()),
        ], (new HolidayApi())->between($start, $end));
    }

    /**
     * @return array<string, mixed>
     */
    private function input(): array
    {
        $fields = [
            'name', 'employee_id', 'daily_rate', 'days_worked', 'ot_hours', 'late_hours', 'deduction_per_hour',
            'holiday_present', 'holiday_type', 'sss', 'philhealth', 'pagibig', 'others',
        ];
        $input = [];
        foreach ($fields as $f) {
            $input[$f] = $_POST[$f] ?? '';
        }
        foreach (['days_worked', 'ot_hours', 'late_hours', 'holiday_present'] as $f) {
            $input[$f] = $input[$f] === '' ? 0 : $input[$f];
        }

        return $input;
    }

    /**
     * @return array<string, string>
     */
    private function period(): array
    {
        return array_filter([
            'pay_start' => (string) ($_POST['pay_start'] ?? ''),
            'pay_end'   => (string) ($_POST['pay_end'] ?? ''),
        ]);
    }

    /**
     * @param array<string, string> $errors
     */
    private function fail(array $errors): never
    {
        Session::flash(PayrollInput::firstError($errors), 'Input Error', 'warning');
        Session::flashInput(['payroll' => $_POST, 'errors' => $errors]);
        Response::redirect('/payroll?' . http_build_query($this->period()));
    }
}
