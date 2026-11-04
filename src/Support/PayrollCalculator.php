<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Port of the C# Form2.CalculatePayroll() method.
 *
 *   dailyRate       = 550
 *   hourlyRate      = dailyRate / 8
 *   basicPay        = daysWorked * dailyRate
 *   holidayExtraPay = holidaysWorked * dailyRate
 *   otPay           = otHours * hourlyRate * 1.25
 *   lateDeduction   = lateHours * deductionPerHour
 *   totalDeductions = sss + philhealth + pagibig + others + lateDeduction
 *   grossPay        = basicPay
 *   netPay          = grossPay + holidayExtraPay + otPay - totalDeductions
 */
final class PayrollCalculator
{
    public function __construct(
        private readonly float $dailyRate = 550.0,
        private readonly float $hoursPerDay = 8.0,
        private readonly float $otMultiplier = 1.25,
    ) {
    }

    /**
     * @param array<string, mixed> $p normalized payroll input
     * @return array{gross_pay: float, net_pay: float, total_deductions: float}
     */
    public function calculate(array $p): array
    {
        $hourlyRate = $this->dailyRate / $this->hoursPerDay;

        $basicPay        = (int) ($p['days_worked'] ?? 0) * $this->dailyRate;
        $holidayExtraPay = (int) ($p['holiday_present'] ?? 0) * $this->dailyRate;
        $otPay           = (int) ($p['ot_hours'] ?? 0) * ($hourlyRate * $this->otMultiplier);
        $lateDeduction   = (int) ($p['late_hours'] ?? 0) * (float) ($p['deduction_per_hour'] ?? 0);

        $totalDeductions = (float) ($p['sss'] ?? 0)
            + (float) ($p['philhealth'] ?? 0)
            + (float) ($p['pagibig'] ?? 0)
            + (float) ($p['others'] ?? 0)
            + $lateDeduction;

        $grossPay = $basicPay;
        $netPay = $grossPay + $holidayExtraPay + $otPay - $totalDeductions;

        return [
            'gross_pay'        => round($grossPay, 2),
            'net_pay'          => round($netPay, 2),
            'total_deductions' => round($totalDeductions, 2),
        ];
    }
}
