<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\PayrollController as WebPayroll;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Payroll;
use App\Support\PayrollInput;

/**
 * JSON API CRUD for payroll records: /api/payroll
 * gross_pay, net_pay and total_deductions are always computed by PayrollCalculator.
 */
final class PayrollController
{
    /** Employee sessions only see their own record; admins and API-key callers see all. */
    private function scope(): \Illuminate\Database\Eloquent\Builder
    {
        $query = Payroll::query()->orderBy('id');
        $user = Session::get('user');
        if ($user !== null && ($user['role'] ?? '') !== 'admin') {
            $query->where('employee_id', (string) ($user['employee_id'] ?? ''));
        }

        return $query;
    }

    public function index(): void
    {
        $rows = $this->scope()->get();
        Response::json(['data' => $rows, 'count' => $rows->count()]);
    }

    public function show(string $id): void
    {
        $row = $this->scope()->where('id', (int) $id)->first();
        if ($row === null) {
            Response::json(['error' => 'Payroll record not found'], 404);
            return;
        }
        Response::json(['data' => $row]);
    }

    public function store(): void
    {
        $input = Request::json();
        if ($input === null) {
            Response::json(['error' => 'Request body must be valid JSON'], 400);
            return;
        }

        $errors = PayrollInput::validate($input);
        if ($errors !== []) {
            Response::json(['errors' => $errors], 422);
            return;
        }

        $data = PayrollInput::normalize($input);
        if (Payroll::query()->where('employee_id', $data['employee_id'])->exists()) {
            Response::json(['errors' => ['employee_id' => 'This Employee ID already has a payroll record.']], 422);
            return;
        }

        $data['daily_rate'] ??= 550.0;
        $data += WebPayroll::calculator()->calculate($data);
        $row = Payroll::create($data);
        \App\Support\Migrator::ensureEmployeeAccount($row->employee_id);

        Response::json(['data' => $row->fresh()], 201);
    }

    public function update(string $id): void
    {
        $row = Payroll::find((int) $id);
        if ($row === null) {
            Response::json(['error' => 'Payroll record not found'], 404);
            return;
        }

        $input = Request::json();
        if ($input === null) {
            Response::json(['error' => 'Request body must be valid JSON'], 400);
            return;
        }

        $errors = PayrollInput::validate($input, partial: true);
        if ($errors !== []) {
            Response::json(['errors' => $errors], 422);
            return;
        }

        $data = PayrollInput::normalize($input);
        if (isset($data['employee_id']) && $data['employee_id'] !== $row->employee_id) {
            // The Employee ID is also the login name; it is changed only from the Employees page.
            Response::json(['errors' => ['employee_id' => 'The Employee ID cannot be changed here.']], 422);
            return;
        }

        $row->fill($data);
        $row->fill(WebPayroll::calculator()->calculate($row->toArray()));
        $row->save();

        Response::json(['data' => $row]);
    }

    public function destroy(string $id): void
    {
        $row = Payroll::find((int) $id);
        if ($row === null) {
            Response::json(['error' => 'Payroll record not found'], 404);
            return;
        }

        // Same as Remove on the Employees page: payroll record, schedule row and login go together,
        // so no orphaned login is left behind.
        $result = \App\Support\EmployeeManager::remove((string) $row->employee_id);
        Response::json(['message' => $result['message']], $result['ok'] ? 200 : 404);
    }
}
