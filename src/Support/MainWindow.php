<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Session;
use App\Models\EmployeeShift;
use App\Models\Payroll;
use App\Models\Product;

/**
 * Builds the view-model for views/main.mustache (the C# Form2 window).
 *
 * Like the WinForms TabControl, all three tabs are rendered on every request
 * (INVENTORY, EMPLOYEE SCHEDULE, PAYROLL MANAGEMENT); $tab only decides which one is visible.
 */
final class MainWindow
{
    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public static function data(string $tab, array $query = []): array
    {
        $search = trim((string) ($query['search'] ?? ''));

        return [
            'title'          => 'But First, System',
            'tab'            => $tab,
            'tabInventory'   => $tab === 'inventory',
            'tabSchedule'    => $tab === 'schedule',
            'tabPayroll'     => $tab === 'payroll',
            'inventory'      => self::inventory($search, $query),
            'schedule'       => self::schedule($query),
            'payroll'        => self::payroll($query),
            'today'          => date('Y-m-d'),
            // Values re-filled after a failed validation (read by public/assets/js/app.js).
            'oldJson'        => json_encode((object) Session::pullInput(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function inventory(string $search, array $query): array
    {
        $products = Product::search($search);

        $rows = [];
        foreach ($products as $p) {
            $imageUrl = self::imageUrl($p->image_path);
            $row = [
                'product_id'   => $p->product_id,
                'product_name' => $p->product_name,
                'category'     => $p->category,
                'price'        => Format::plain2($p->price),
                'quantity'     => $p->quantity,
                'supplier'     => (string) $p->supplier,
                'date_added'   => substr((string) $p->date_added, 0, 10),
                'image_path'   => (string) $p->image_path,
                'image_url'    => $imageUrl,
            ];
            $rows[] = $row + [
                'price_display' => Format::peso($p->price),
                'date_display'  => $p->date_added ? date('M j, Y', strtotime((string) $p->date_added) ?: time()) : '',
                'initials'      => mb_strtoupper(mb_substr((string) $p->product_name, 0, 2)),
                'low'           => $p->quantity <= 10,
                'json'          => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
        }

        // Summary (C# UpdateSummaryLabels): total value, most and lowest stocked.
        if ($products->isEmpty()) {
            $summary = [
                'total' => Format::peso(0),
                'most'  => 'N/A', 'mostQty' => '',
                'least' => 'N/A', 'leastQty' => '',
                'units' => 0,
            ];
        } else {
            $total = $products->sum(static fn (Product $p): float => $p->price * $p->quantity);
            $most  = $products->sortByDesc('quantity')->first();
            $least = $products->sortBy('quantity')->first();
            $summary = [
                'total'    => Format::peso($total),
                'most'     => $most->product_name,
                'mostQty'  => $most->quantity . ' units',
                'least'    => $least->product_name,
                'leastQty' => $least->quantity . ' units',
                'units'    => number_format((int) $products->sum('quantity')),
            ];
        }

        // Chart (C# UpdateChart): top 10 by quantity.
        $top10 = $products->sortByDesc('quantity')->take(10)->values();
        $points = $top10->map(static fn (Product $p): array => [
            'label' => $p->product_name,
            'value' => $p->quantity,
        ])->all();

        $categories = array_map(static fn (string $c): array => ['value' => $c], Product::CATEGORIES);

        return [
            'rows'       => $rows,
            'count'      => count($rows),
            'search'     => $search,
            'summary'    => $summary,
            'chart'      => BarChart::svg($points),
            'categories' => $categories,
            'selected'   => (string) ($query['selected'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function schedule(array $query): array
    {
        $rows = [];
        foreach (EmployeeShift::query()->orderBy('id')->get() as $s) {
            $row = [
                'id'       => $s->id,
                'names'    => $s->names,
                'schedule' => (string) $s->schedule,
                'breaks'   => (string) $s->breaks,
                'duties'   => (string) $s->duties,
                'day_off'  => (string) $s->day_off,
                'payroll_employee_id' => (string) $s->payroll_employee_id,
            ];
            $rows[] = $row + [
                'initials' => self::initials((string) $s->names),
                'json'     => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
        }

        // Day-off board: who is off on each day of the week.
        $board = [];
        foreach (EmployeeShift::DAYS as $day) {
            $names = array_values(array_map(
                static fn (array $r): string => $r['names'],
                array_filter($rows, static fn (array $r): bool => $r['day_off'] === $day)
            ));
            $board[] = ['day' => substr($day, 0, 3), 'names' => implode(', ', $names), 'taken' => $names !== []];
        }

        return [
            'rows' => $rows,
            'count' => count($rows),
            'board' => $board,
            'days' => array_map(static fn (string $d): array => ['value' => $d], EmployeeShift::DAYS),
            'start' => self::dateParam($query['sched_start'] ?? null),
            'end'   => self::dateParam($query['sched_end'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function payroll(array $query): array
    {
        $rows = [];
        foreach (Payroll::query()->orderBy('id')->get() as $p) {
            $row = [
                'id'                 => $p->id,
                'name'               => $p->name,
                'employee_id'        => $p->employee_id,
                'days_worked'        => $p->days_worked,
                'ot_hours'           => $p->ot_hours,
                'late_hours'         => $p->late_hours,
                'deduction_per_hour' => Format::plain2($p->deduction_per_hour),
                'holiday_present'    => $p->holiday_present,
                'holiday_type'       => (string) $p->holiday_type,
                'net_pay'            => Format::plain2($p->net_pay),
                'gross_pay'          => Format::plain2($p->gross_pay),
                'total_deductions'   => Format::plain2($p->total_deductions),
                'sss'                => Format::plain2($p->sss),
                'philhealth'         => Format::plain2($p->philhealth),
                'pagibig'            => Format::plain2($p->pagibig),
                'others'             => Format::plain2($p->others),
            ];
            $shift = $p->shift;
            $row['shift'] = $shift ? [
                'names' => $shift->names, 'schedule' => (string) $shift->schedule,
                'breaks' => (string) $shift->breaks, 'duties' => (string) $shift->duties,
                'day_off' => (string) $shift->day_off,
            ] : null;
            $rows[] = $row + [
                'initials'      => self::initials((string) $p->name),
                'gross_display' => Format::peso($p->gross_pay),
                'net_display'   => Format::peso($p->net_pay),
                'ded_display'   => Format::peso($p->total_deductions),
                'json'          => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
        }

        $all = Payroll::query()->get();

        return [
            'rows'         => $rows,
            'count'        => count($rows),
            'totalNet'     => Format::peso($all->sum('net_pay')),
            'totalGross'   => Format::peso($all->sum('gross_pay')),
            'totalDed'     => Format::peso($all->sum('total_deductions')),
            'holidayTypes' => array_map(static fn (string $t): array => ['value' => $t], Payroll::HOLIDAY_TYPES),
            'selected'     => (string) ($query['selected'] ?? ''),
            'start'        => self::dateParam($query['pay_start'] ?? null),
            'end'          => self::dateParam($query['pay_end'] ?? null),
        ];
    }

    public static function imageUrl(?string $path): string
    {
        if ($path === null || $path === '') {
            return '';
        }
        if (preg_match('#^https?://#', $path) || str_starts_with($path, '/')) {
            return $path;
        }

        return '/' . ltrim($path, '/');
    }

    private static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';

        return mb_strtoupper($first . $last);
    }

    private static function dateParam(mixed $value): string
    {
        $v = (string) $value;

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : date('Y-m-d');
    }
}
