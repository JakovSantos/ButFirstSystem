<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Server-side SVG chart "Top 10 Most Stocked Products":
 * horizontal bars of quantity per product, value label at the end of each bar.
 * Drawn in the brand style and responsive (viewBox, width 100%).
 */
final class BarChart
{
    private const W = 760;
    private const ROW = 30;

    /**
     * @param list<array{label:string,value:int}> $points  highest first (drawn top to bottom)
     */
    public static function svg(array $points, string $title = 'Top 10 Most Stocked Products'): string
    {
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');

        if ($points === []) {
            return sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d 80" role="img" aria-label="%s"><text x="%d" y="45" text-anchor="middle" font-family="Figtree, sans-serif" font-size="14" fill="#7c746b">No products to chart</text></svg>',
                self::W,
                $e($title),
                self::W / 2
            );
        }

        $labelW = 210;
        $left = $labelW + 16;
        $right = self::W - 56;
        $top = 8;
        $plotW = $right - $left;
        $n = count($points);
        $axisY = $top + $n * self::ROW + 4;
        $H = $axisY + 26;

        $max = max(1, max(array_column($points, 'value')));
        [$axisMax, $step] = self::niceScale($max);

        $out = [];
        $out[] = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" role="img" aria-label="%s" font-family="Figtree, system-ui, sans-serif">',
            self::W,
            $H,
            $e($title)
        );
        $out[] = '<title>' . $e($title) . '</title>';

        // Vertical gridlines + quantity scale.
        for ($v = 0; $v <= $axisMax + 0.0001; $v += $step) {
            $x = $left + ($v / $axisMax) * $plotW;
            $out[] = sprintf('<line x1="%.1f" y1="%d" x2="%.1f" y2="%d" stroke="#efe9e0" stroke-width="1"/>', $x, $top, $x, $axisY);
            $out[] = sprintf('<text x="%.1f" y="%d" text-anchor="middle" font-size="11" fill="#9a9187">%d</text>', $x, $axisY + 16, (int) $v);
        }

        foreach ($points as $i => $p) {
            $y = $top + $i * self::ROW;
            $w = max(4.0, ($p['value'] / $axisMax) * $plotW);
            $fill = $i === 0 ? '#141414' : '#b07a4f';
            $opacity = $i === 0 ? 1 : max(0.45, 1 - $i * 0.06);

            $out[] = sprintf(
                '<text x="%d" y="%.1f" text-anchor="end" font-size="12.5" fill="#2b2b2b">%s</text>',
                $labelW,
                $y + self::ROW / 2 + 4,
                $e(self::truncate($p['label'], 30))
            );
            $out[] = sprintf(
                '<rect x="%d" y="%.1f" width="%.1f" height="%d" rx="6" fill="%s" fill-opacity="%.2f"><title>%s: %d</title></rect>',
                $left,
                $y + 6,
                $w,
                self::ROW - 12,
                $fill,
                $opacity,
                $e($p['label']),
                $p['value']
            );
            $out[] = sprintf(
                '<text x="%.1f" y="%.1f" font-size="12.5" font-weight="700" fill="#141414">%d</text>',
                $left + $w + 8,
                $y + self::ROW / 2 + 4,
                $p['value']
            );
        }

        $out[] = '</svg>';

        return implode('', $out);
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
        $rough = $max / 5;
        $pow = 10 ** floor(log10(max(1, $rough)));
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
