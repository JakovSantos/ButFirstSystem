<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Server-side SVG replica of the C# MS Chart (chart1) on the Inventory tab:
 *   - Title "Top 10 Most Stocked Products"
 *   - SeriesChartType.Bar (horizontal), NavajoWhite bars, value labels (Calibri 8pt bold)
 *   - Legend "Quantity" on the right, axis titles "Products" / "Quantity"
 *   - 520 x 259, DimGray solid border
 */
final class BarChart
{
    private const W = 520;
    private const H = 259;

    /**
     * @param list<array{label:string,value:int}> $points  highest first (first point is drawn at the bottom)
     */
    public static function svg(array $points, string $title = 'Top 10 Most Stocked Products'): string
    {
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $W = self::W;
        $H = self::H;

        $out = [];
        $out[] = sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" font-family="Microsoft Sans Serif, Tahoma, Arial, sans-serif" font-size="11">', $W, $H, $W, $H);
        $out[] = sprintf('<rect x="0.5" y="0.5" width="%d" height="%d" fill="#fff" stroke="#696969"/>', $W - 1, $H - 1);
        $out[] = sprintf('<text x="%d" y="16" text-anchor="middle" font-size="11">%s</text>', $W / 2, $e($title));

        if ($points === []) {
            $out[] = '</svg>';
            return implode('', $out);
        }

        // Legend (right side, like the default MS Chart legend).
        $legendX = $W - 92;
        $out[] = sprintf('<rect x="%d" y="30" width="14" height="10" fill="#FFDEAD"/>', $legendX);
        $out[] = sprintf('<text x="%d" y="39">Quantity</text>', $legendX + 18);

        // Plot area.
        $labelW = self::labelWidth($points);
        $left   = 22 + $labelW;          // room for "Products" axis title + labels
        $right  = $legendX - 12;
        $top    = 28;
        $bottom = $H - 38;               // room for numbers + "Quantity" axis title
        $plotW  = $right - $left;
        $plotH  = $bottom - $top;

        $max = max(array_column($points, 'value'));
        [$axisMax, $step] = self::niceScale($max);

        // Vertical gridlines + Y(value) axis labels.
        for ($v = 0; $v <= $axisMax + 0.0001; $v += $step) {
            $x = $left + ($axisMax > 0 ? $v / $axisMax : 0) * $plotW;
            $out[] = sprintf('<line x1="%.1f" y1="%d" x2="%.1f" y2="%d" stroke="#000" stroke-width="1"/>', $x, $top, $x, $bottom + 4);
            $out[] = sprintf('<text x="%.1f" y="%d" text-anchor="middle" font-size="10">%s</text>', $x, $bottom + 15, (string) (int) $v);
        }

        $n = count($points);
        $slot = $plotH / $n;
        $barH = max(2.0, $slot * 0.8);

        foreach ($points as $i => $p) {
            // First point at the bottom (MS Chart Bar behaviour).
            $cy = $bottom - ($i + 0.5) * $slot;
            $w  = $axisMax > 0 ? ($p['value'] / $axisMax) * $plotW : 0;

            // Horizontal gridline/tick at each category.
            $out[] = sprintf('<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" stroke="#000"/>', $left - 4, $cy, $left, $cy);
            $out[] = sprintf('<rect x="%d" y="%.1f" width="%.1f" height="%.1f" fill="#FFDEAD"/>', $left, $cy - $barH / 2, $w, $barH);
            $out[] = sprintf(
                '<text x="%.1f" y="%.1f" font-family="Calibri, Carlito, Arial, sans-serif" font-weight="bold" font-size="10.5">%d</text>',
                $left + $w + 3,
                $cy + 3.5,
                $p['value']
            );
            $out[] = sprintf(
                '<text x="%d" y="%.1f" text-anchor="end" font-size="%s">%s</text>',
                $left - 6,
                $cy + 3.5,
                $n > 8 ? '9' : '10',
                $e(self::truncate($p['label'], 26))
            );
        }

        // Axes.
        $out[] = sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#000"/>', $left, $top, $left, $bottom);
        $out[] = sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#000"/>', $left, $bottom, $right, $bottom);

        // Axis titles.
        $out[] = sprintf('<text x="%.1f" y="%d" text-anchor="middle">Quantity</text>', $left + $plotW / 2, $H - 8);
        $out[] = sprintf('<text transform="translate(12 %.1f) rotate(-90)" text-anchor="middle">Products</text>', $top + $plotH / 2);

        $out[] = '</svg>';

        return implode('', $out);
    }

    /**
     * @param list<array{label:string,value:int}> $points
     */
    private static function labelWidth(array $points): int
    {
        $longest = 0;
        foreach ($points as $p) {
            $longest = max($longest, mb_strlen(self::truncate($p['label'], 26)));
        }

        return (int) min(170, max(40, $longest * 5.6));
    }

    private static function truncate(string $s, int $len): string
    {
        return mb_strlen($s) > $len ? mb_substr($s, 0, $len - 1) . '…' : $s;
    }

    /**
     * @return array{0:float,1:float} axis maximum, step
     */
    private static function niceScale(int $max): array
    {
        if ($max <= 0) {
            return [10, 2];
        }
        $rough = $max / 5;
        $pow = 10 ** floor(log10($rough));
        $step = $pow;
        foreach ([1, 2, 5, 10] as $m) {
            if ($rough <= $m * $pow) {
                $step = $m * $pow;
                break;
            }
        }

        return [ceil($max / $step) * $step, $step];
    }
}
