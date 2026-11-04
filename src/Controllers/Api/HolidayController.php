<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Response;
use App\Support\HolidayApi;

/**
 * GET /api/holidays?start=YYYY-MM-DD&end=YYYY-MM-DD
 * GET /api/holidays?year=2026
 *
 * Proxies the external Nager.Date API (with caching) so the browser never calls it directly.
 */
final class HolidayController
{
    public function index(): void
    {
        $api = new HolidayApi();
        $start = (string) ($_GET['start'] ?? '');
        $end = (string) ($_GET['end'] ?? '');
        $year = $_GET['year'] ?? null;

        $isDate = static fn (string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);

        if ($start !== '' || $end !== '') {
            if (! $isDate($start) || ! $isDate($end)) {
                Response::json(['errors' => ['start' => 'start and end must be dates in YYYY-MM-DD format.']], 422);
                return;
            }
            $data = $api->between($start, $end);
        } else {
            $y = filter_var($year ?? date('Y'), FILTER_VALIDATE_INT);
            if ($y === false) {
                Response::json(['errors' => ['year' => 'year must be a number.']], 422);
                return;
            }
            $data = $api->forYear($y);
        }

        if ($data === [] && $api->lastError !== null) {
            Response::json(['data' => [], 'count' => 0, 'error' => $api->lastError, 'source' => $api->source], 502);
            return;
        }

        Response::json([
            'data'   => $data,
            'count'  => count($data),
            'source' => $api->source,
            'provider' => 'Nager.Date (https://date.nager.at)',
        ]);
    }
}
