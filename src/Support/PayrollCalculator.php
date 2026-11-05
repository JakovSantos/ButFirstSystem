<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Payroll formula.
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
        // Per-employee base pay (set by the admin); falls back to the default rate.
        $dailyRate = isset($p['daily_rate']) && (float) $p['daily_rate'] > 0 ? (float) $p['daily_rate'] : $this->dailyRate;
        $hourlyRate = $dailyRate / $this->hoursPerDay;

        $basicPay        = (int) ($p['days_worked'] ?? 0) * $dailyRate;
        $holidayExtraPay = (int) ($p['holiday_present'] ?? 0) * $dailyRate;
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
