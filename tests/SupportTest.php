<?php

declare(strict_types=1);

namespace Tests;

use App\Support\BarChart;
use App\Support\Format;
use App\Support\HolidayApi;
use PHPUnit\Framework\TestCase;

final class SupportTest extends TestCase
{
    public function testPesoFormatMatchesFilPhC2(): void
    {
        self::assertSame('₱1,234.50', Format::peso(1234.5));
        self::assertSame('₱0.00', Format::peso(null));
        self::assertSame('-₱5.00', Format::peso(-5));
    }

    public function testDotNetDateTimeFormat(): void
    {
        self::assertSame('11/4/2026 12:00:00 AM', Format::dotnetDateTime('2026-11-04'));
        self::assertSame('November 04, 2026', Format::longDate('2026-11-04'));
    }

    public function testChartContainsTitleLegendAndBars(): void
    {
        $svg = BarChart::svg([['label' => 'Iced Coffee', 'value' => 55], ['label' => 'Hot Coffee', 'value' => 40]]);

        self::assertStringContainsString('Top 10 Most Stocked Products', $svg);
        self::assertStringContainsString('Quantity', $svg);
        self::assertSame(3, substr_count($svg, 'fill="#FFDEAD"')); // legend swatch + 2 bars
        self::assertStringContainsString('>55<', $svg);
    }

    public function testChartEscapesProductNames(): void
    {
        $svg = BarChart::svg([['label' => '<script>x</script>', 'value' => 1]]);

        self::assertStringNotContainsString('<script>', $svg);
    }

    public function testHolidayApiUsesCacheAndMapsTypes(): void
    {
        $dir = sys_get_temp_dir() . '/bf-holiday-test-' . bin2hex(random_bytes(3));
        mkdir($dir);
        file_put_contents($dir . '/holidays-PH-2026.json', json_encode([
            ['date' => '2026-12-25', 'name' => 'Christmas Day', 'localName' => 'Araw ng Pasko', 'types' => ['Public']],
            ['date' => '2026-12-24', 'name' => 'Christmas Eve', 'localName' => 'Bisperas', 'types' => ['Optional']],
            ['date' => '2026-06-12', 'name' => 'Independence Day', 'localName' => 'Araw ng Kalayaan', 'types' => ['Public']],
        ]));

        // Unreachable base URL proves the cache is used and no network call is made.
        $api = new HolidayApi(['base_url' => 'http://127.0.0.1:9', 'country' => 'PH', 'cache_dir' => $dir, 'cache_ttl' => 3600, 'timeout' => 1]);
        $december = $api->between('2026-12-01', '2026-12-31');

        self::assertSame('cache', $api->source);
        self::assertCount(2, $december);
        self::assertSame('Regular', $december[0]['type']);
        self::assertSame('Special', $december[1]['type']);

        array_map('unlink', glob($dir . '/*'));
        rmdir($dir);
    }

    public function testHolidayApiFailsGracefully(): void
    {
        $dir = sys_get_temp_dir() . '/bf-holiday-test-' . bin2hex(random_bytes(3));
        mkdir($dir);
        $api = new HolidayApi(['base_url' => 'http://127.0.0.1:9', 'country' => 'PH', 'cache_dir' => $dir, 'cache_ttl' => 3600, 'timeout' => 1]);

        self::assertSame([], @$api->forYear(2030));
        self::assertNotNull($api->lastError);

        rmdir($dir);
    }
}
