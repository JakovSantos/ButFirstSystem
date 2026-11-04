<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\PayrollController as WebPayroll;
use App\Core\Request;
use App\Core\Response;
use App\Models\Payroll;
use App\Support\PayrollInput;

/**
 * JSON API CRUD for payroll records: /api/payroll
 * gross_pay, net_pay and total_deductions are always computed by PayrollCalculator.
 */
final class PayrollController
{
    public function index(): void
    {
        $rows = Payroll::query()->orderBy('id')->get();
        Response::json(['data' => $rows, 'count' => $rows->count()]);
    }

    public function show(string $id): void
    {
        $row = Payroll::find((int) $id);
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

        $data += WebPayroll::calculator()->calculate($data);
        $row = Payroll::create($data);

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

        $row->fill(PayrollInput::normalize($input));
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

        $row->delete();
        Response::json(['message' => 'Payroll record deleted']);
    }
}
