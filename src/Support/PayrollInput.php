<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Payroll;

/**
 * Validation + normalization for payroll, shared by the HTML and JSON controllers.
 * Messages are shown to the user as-is.
 */
final class PayrollInput
{
    private const MONEY = ['deduction_per_hour', 'sss', 'philhealth', 'pagibig', 'others'];
    private const COUNTS = ['days_worked', 'ot_hours', 'late_hours', 'holiday_present'];

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function validate(array $input, bool $partial = false): array
    {
        $errors = [];

        foreach (['name', 'employee_id'] as $field) {
            if (! $partial || array_key_exists($field, $input)) {
                if (! is_scalar($input[$field] ?? null) || trim((string) $input[$field]) === '') {
                    $errors[$field] = 'Employee Name and ID are required.';
                }
            }
        }

        foreach (self::MONEY as $field) {
            if (! $partial || array_key_exists($field, $input)) {
                $v = $input[$field] ?? null;
                if ($v === null || trim((string) $v) === '' || filter_var($v, FILTER_VALIDATE_FLOAT) === false || (float) $v < 0) {
                    $errors[$field] = 'Please enter valid numeric values for all deduction fields.';
                }
            }
        }

        if (array_key_exists('daily_rate', $input) && ($input['daily_rate'] ?? '') !== '') {
            $r = $input['daily_rate'];
            if (filter_var($r, FILTER_VALIDATE_FLOAT) === false || (float) $r <= 0 || (float) $r > 100000) {
                $errors['daily_rate'] = 'Daily rate must be a number greater than 0.';
            }
        }

        foreach (self::COUNTS as $field) {
            if (array_key_exists($field, $input)) {
                $v = filter_var($input[$field], FILTER_VALIDATE_INT);
                if ($v === false || $v < 0 || $v > 100) {
                    $errors[$field] = 'Day and hour counts must be whole numbers between 0 and 100.';
                }
            }
        }

        if (array_key_exists('holiday_type', $input)
            && ($input['holiday_type'] ?? '') !== ''
            && ! in_array($input['holiday_type'], Payroll::HOLIDAY_TYPES, true)) {
            $errors['holiday_type'] = 'Holiday Type must be Regular or Special.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $data = [];

        foreach (['name', 'employee_id'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = trim((string) $input[$field]);
            }
        }
        foreach (self::COUNTS as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = (int) $input[$field];
            }
        }
        foreach (self::MONEY as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = round((float) $input[$field], 2);
            }
        }
        if (array_key_exists('holiday_type', $input)) {
            $data['holiday_type'] = (string) ($input['holiday_type'] ?? '');
        }
        if (array_key_exists('daily_rate', $input) && ($input['daily_rate'] ?? '') !== '') {
            $data['daily_rate'] = round((float) $input['daily_rate'], 2);
        }

        return $data;
    }

    /** First error message, used for the dialog text. */
    public static function firstError(array $errors): string
    {
        return (string) reset($errors);
    }
}
