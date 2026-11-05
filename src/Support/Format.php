<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Formatting helpers for money and dates.
 */
final class Format
{
    /** "C2" with fil-PH culture, e.g. ₱1,234.50 */
    public static function peso(float|int|string|null $value): string
    {
        $v = (float) $value;
        $s = '₱' . number_format(abs($v), 2);

        return $v < 0 ? '-' . $s : $s;
    }

    /** "N2", e.g. 12,345.00 */
    public static function n2(float|int|string|null $value): string
    {
        return number_format((float) $value, 2);
    }

    /** Long US date-time, e.g. 11/4/2026 12:00:00 AM */
    public static function dotnetDateTime(?string $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        $ts = strtotime($date);

        return $ts === false ? $date : date('n/j/Y g:i:s A', $ts);
    }

    /** "MMMM dd, yyyy", e.g. November 04, 2026 */
    public static function longDate(?string $date): string
    {
        $ts = $date ? strtotime($date) : time();

        return date('F d, Y', $ts ?: time());
    }

    /** Price without currency, "0.00" (used when a row fills the textbox). */
    public static function plain2(float|int|string|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
