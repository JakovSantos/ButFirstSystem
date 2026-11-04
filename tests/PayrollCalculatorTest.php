<?php

declare(strict_types=1);

namespace Tests;

use App\Support\PayrollCalculator;
use PHPUnit\Framework\TestCase;

/**
 * The PHP calculator must give the same numbers as the C# CalculatePayroll().
 */
final class PayrollCalculatorTest extends TestCase
{
    public function testMatchesCSharpFormula(): void
    {
        $r = (new PayrollCalculator())->calculate([
            'days_worked' => 13, 'ot_hours' => 4, 'late_hours' => 1, 'deduction_per_hour' => 50,
            'holiday_present' => 1, 'sss' => 450, 'philhealth' => 250, 'pagibig' => 200, 'others' => 0,
        ]);

        // basic 13*550 = 7150, holiday 550, OT 4*(550/8*1.25) = 343.75, deductions 900 + 1*50 = 950
        self::assertSame(7150.0, $r['gross_pay']);
        self::assertSame(950.0, $r['total_deductions']);
        self::assertSame(7093.75, $r['net_pay']);
    }

    public function testZeroInputGivesZero(): void
    {
        $r = (new PayrollCalculator())->calculate([]);

        self::assertSame(['gross_pay' => 0.0, 'net_pay' => 0.0, 'total_deductions' => 0.0], $r);
    }

    public function testNetPayCanBeNegativeWhenDeductionsExceedPay(): void
    {
        $r = (new PayrollCalculator())->calculate(['days_worked' => 1, 'sss' => 1000]);

        self::assertSame(-450.0, $r['net_pay']);
    }

    public function testRatesAreConfigurable(): void
    {
        $r = (new PayrollCalculator(600, 8, 1.3))->calculate(['days_worked' => 2, 'ot_hours' => 8]);

        self::assertSame(1200.0, $r['gross_pay']);
        self::assertSame(1200.0 + 8 * 75 * 1.3, $r['net_pay']);
    }
}
