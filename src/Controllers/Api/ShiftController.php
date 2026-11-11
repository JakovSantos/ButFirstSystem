<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\EmployeeShift;
use App\Support\ShiftScheduler;

/**
 * JSON API for the employee shift schedule: /api/shifts
 */
final class ShiftController
{
    public function index(): void
    {
        $rows = EmployeeShift::query()->orderBy('id')->get();
        Response::json(['data' => $rows, 'count' => $rows->count()]);
    }

    public function show(string $id): void
    {
        $row = EmployeeShift::find((int) $id);
        if ($row === null) {
            Response::json(['error' => 'Shift not found'], 404);
            return;
        }
        Response::json(['data' => $row]);
    }

    /** PUT /api/shifts/{id}/dayoff   body: {"day_off": "Monday"} */
    public function dayOff(string $id): void
    {
        $row = EmployeeShift::find((int) $id);
        if ($row === null) {
            Response::json(['error' => 'Shift not found'], 404);
            return;
        }

        if (! ShiftScheduler::mayChange(Session::get('user'), (string) $row->names)) {
            Response::json(['error' => 'You can only request a day off for yourself.'], 403);
            return;
        }

        $input = Request::json();
        if ($input === null) {
            Response::json(['error' => 'Request body must be valid JSON'], 400);
            return;
        }

        $day = $input['day_off'] ?? '';
        $result = (new ShiftScheduler())->requestDayOff((string) $row->names, is_string($day) ? $day : '');

        if (! ($result['ok'] ?? false)) {
            $status = $result['caption'] === 'Day Off Already Taken' ? 409 : 422;
            Response::json(['error' => $result['message']], $status);
            return;
        }

        Response::json(['data' => $row->fresh(), 'message' => $result['message']]);
    }

    /** POST /api/shifts/randomize */
    public function randomize(): void
    {
        $error = (new ShiftScheduler())->randomize();
        if ($error !== null) {
            Response::json(['error' => $error['message']], 422);
            return;
        }

        $rows = EmployeeShift::query()->orderBy('id')->get();
        Response::json(['data' => $rows, 'message' => 'Schedule randomized successfully!']);
    }
}
