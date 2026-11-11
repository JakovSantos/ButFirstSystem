<?php

declare(strict_types=1);

namespace Tests;

use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\SecurityHeaders;
use App\Support\PayrollInput;
use App\Support\ProductInput;
use PHPUnit\Framework\TestCase;

/**
 * Defensive checks in the framework core and the input validators.
 */
final class SecurityTest extends TestCase
{
    private static string $dir;

    public static function setUpBeforeClass(): void
    {
        self::$dir = sys_get_temp_dir() . '/butfirst-ratelimit-' . bin2hex(random_bytes(4));
        RateLimiter::useDirectory(self::$dir);
    }

    public static function tearDownAfterClass(): void
    {
        array_map('unlink', glob(self::$dir . '/*') ?: []);
        @rmdir(self::$dir);
    }

    public function testRedirectsStayOnThisSite(): void
    {
        self::assertSame('/payroll?selected=F-001', Response::safeLocation('/payroll?selected=F-001'));
        self::assertSame('/', Response::safeLocation('https://evil.example/login'));
        self::assertSame('/', Response::safeLocation('//evil.example/login'));
        self::assertSame('/', Response::safeLocation('/\\evil.example'));
        self::assertSame('/', Response::safeLocation('javascript:alert(1)'));
        // Header injection: new lines are removed.
        self::assertSame('/inventorySet-Cookie: x=1', Response::safeLocation("/inventory\r\nSet-Cookie: x=1"));
    }

    public function testSameOriginCheck(): void
    {
        $req = static fn (array $server): Request => new Request('POST', '/api/products', [], [], [], $server + ['HTTP_HOST' => 'localhost:8000']);

        self::assertTrue($req([])->isSameOrigin());                                          // curl / Postman
        self::assertTrue($req(['HTTP_ORIGIN' => 'http://localhost:8000'])->isSameOrigin());
        self::assertFalse($req(['HTTP_ORIGIN' => 'https://evil.example'])->isSameOrigin());
        self::assertFalse($req(['HTTP_ORIGIN' => 'http://localhost:9999'])->isSameOrigin());
        self::assertFalse($req(['HTTP_ORIGIN' => 'null'])->isSameOrigin());                   // sandboxed iframe
        self::assertFalse($req(['HTTP_REFERER' => 'http://localhost.evil.example/x'])->isSameOrigin());
        self::assertTrue($req(['CONTENT_TYPE' => 'application/json; charset=utf-8'])->isJson());
        self::assertFalse($req(['CONTENT_TYPE' => 'text/plain'])->isJson());
    }

    public function testRateLimiterLocksAfterTooManyAttempts(): void
    {
        $key = 'login|admin|127.0.0.1';
        RateLimiter::clear($key);

        for ($i = 1; $i <= 4; $i++) {
            RateLimiter::hit($key, 900);
            self::assertFalse(RateLimiter::tooManyAttempts($key, 5), "attempt {$i}");
        }
        RateLimiter::hit($key, 900);
        self::assertTrue(RateLimiter::tooManyAttempts($key, 5));
        self::assertGreaterThan(800, RateLimiter::availableIn($key));

        RateLimiter::clear($key); // a successful sign-in resets the counter
        self::assertFalse(RateLimiter::tooManyAttempts($key, 5));
    }

    public function testRateLimiterKeysNeverBecomeFilePaths(): void
    {
        $key = '../../config/config.php';
        RateLimiter::hit($key, 60);

        self::assertFileDoesNotExist(self::$dir . '/../../config/config.php.json');
        self::assertSame([], array_filter(glob(self::$dir . '/*') ?: [], static fn (string $f): bool => str_contains($f, 'config')));
        RateLimiter::clear($key);
    }

    public function testContentSecurityPolicyBlocksInlineAndForeignScripts(): void
    {
        self::assertStringContainsString("script-src 'self';", SecurityHeaders::CSP);
        self::assertStringContainsString("frame-ancestors 'none'", SecurityHeaders::CSP);
        self::assertStringContainsString("object-src 'none'", SecurityHeaders::CSP);
        self::assertStringNotContainsString("'unsafe-eval'", SecurityHeaders::CSP);
    }

    public function testProductImagePathMustBeAnUploadedImage(): void
    {
        $valid = ['product_name' => 'Latte', 'category' => 'BEVERAGES', 'price' => '120'];

        self::assertSame([], ProductInput::validate($valid + ['image_path' => 'uploads/products/bfc-hot-coffee.jpg']));
        self::assertSame([], ProductInput::validate($valid + ['image_path' => '/uploads/products/latte-1a2b3c4d.png']));

        foreach ([
            'https://evil.example/x.jpg',
            'javascript:alert(1)',
            'uploads/products/../../config/config.php',
            'uploads/products/shell.php',
            '../database/database.sqlite',
        ] as $bad) {
            self::assertArrayHasKey('image_path', ProductInput::validate($valid + ['image_path' => $bad]), $bad);
        }
    }

    public function testInputLengthsTypesAndRangesAreLimited(): void
    {
        $valid = ['product_name' => 'Latte', 'category' => 'BEVERAGES', 'price' => '120'];

        self::assertArrayHasKey('product_name', ProductInput::validate(['product_name' => str_repeat('a', 101)] + $valid));
        self::assertArrayHasKey('supplier', ProductInput::validate($valid + ['supplier' => str_repeat('a', 101)]));
        self::assertArrayHasKey('price', ProductInput::validate(['price' => '99999999999'] + $valid));
        self::assertArrayHasKey('price', ProductInput::validate(['price' => ['1']] + $valid));      // array instead of text
        self::assertArrayHasKey('quantity', ProductInput::validate($valid + ['quantity' => ['1']]));

        $pay = [
            'name' => 'Ana Lopez', 'employee_id' => 'F-007', 'deduction_per_hour' => 0,
            'sss' => 0, 'philhealth' => 0, 'pagibig' => 0, 'others' => 0,
        ];
        self::assertSame([], PayrollInput::validate($pay));
        self::assertArrayHasKey('employee_id', PayrollInput::validate(['employee_id' => "F-007'; DROP TABLE payroll;--"] + $pay));
        self::assertArrayHasKey('name', PayrollInput::validate(['name' => str_repeat('a', 101)] + $pay));
        self::assertArrayHasKey('sss', PayrollInput::validate(['sss' => ['450']] + $pay));
        self::assertArrayHasKey('sss', PayrollInput::validate(['sss' => '5000000'] + $pay));
        self::assertSame('F-007', PayrollInput::normalize(['employee_id' => ' f-007 '])['employee_id']);
    }
}
