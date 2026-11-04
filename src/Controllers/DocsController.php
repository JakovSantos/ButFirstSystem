<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

/**
 * GET /docs - API reference page.
 */
final class DocsController
{
    public function index(): void
    {
        $e = static fn (string $m, string $p, string $d, string $ok): array => compact('m', 'p', 'd', 'ok');

        View::display('docs', [
            'title' => 'API Reference',
            'groups' => [
                ['name' => 'Health', 'routes' => [
                    $e('GET', '/api/health', 'Liveness check (no auth).', '200'),
                ]],
                ['name' => 'Inventory', 'routes' => [
                    $e('GET', '/api/products?search=', 'List products (optional LIKE search).', '200'),
                    $e('GET', '/api/products/{id}', 'Show one product.', '200 / 404'),
                    $e('POST', '/api/products', 'Create (admin). Body: product_name, category, price, quantity, supplier, date_added.', '201 / 400 / 422'),
                    $e('PUT', '/api/products/{id}', 'Partial update (admin).', '200 / 400 / 404 / 422'),
                    $e('DELETE', '/api/products/{id}', 'Delete (admin).', '200 / 404'),
                ]],
                ['name' => 'Payroll', 'routes' => [
                    $e('GET', '/api/payroll', 'List payroll records.', '200'),
                    $e('GET', '/api/payroll/{id}', 'Show one record.', '200 / 404'),
                    $e('POST', '/api/payroll', 'Create (admin). Pay totals are computed server-side.', '201 / 400 / 422'),
                    $e('PUT', '/api/payroll/{id}', 'Partial update (admin), totals recomputed.', '200 / 400 / 404 / 422'),
                    $e('DELETE', '/api/payroll/{id}', 'Delete (admin).', '200 / 404'),
                ]],
                ['name' => 'Employee shifts', 'routes' => [
                    $e('GET', '/api/shifts', 'List the shift schedule.', '200'),
                    $e('GET', '/api/shifts/{id}', 'Show one shift row.', '200 / 404'),
                    $e('PUT', '/api/shifts/{id}/dayoff', 'Request a day off. Body: {"day_off":"Monday"}.', '200 / 404 / 409 / 422'),
                    $e('POST', '/api/shifts/randomize', 'Shuffle employees over the schedule slots.', '200 / 422'),
                ]],
                ['name' => 'External API (Nager.Date)', 'routes' => [
                    $e('GET', '/api/holidays?year=2026', 'Philippine public holidays for a year.', '200 / 502'),
                    $e('GET', '/api/holidays?start=&end=', 'Holidays inside a payroll period.', '200 / 422 / 502'),
                ]],
            ],
        ], 'docs-layout');
    }
}
