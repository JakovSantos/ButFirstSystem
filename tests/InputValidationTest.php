<?php

declare(strict_types=1);

namespace Tests;

use App\Support\PayrollInput;
use App\Support\ProductInput;
use PHPUnit\Framework\TestCase;

final class InputValidationTest extends TestCase
{
    public function testValidProductPasses(): void
    {
        $errors = ProductInput::validate([
            'product_name' => 'Iced Latte', 'category' => 'BEVERAGES', 'price' => '120.50',
            'quantity' => '10', 'date_added' => '2026-11-04',
        ]);

        self::assertSame([], $errors);
    }

    public function testProductRequiredFieldsUseCSharpMessages(): void
    {
        $errors = ProductInput::validate(['product_name' => ' ', 'category' => 'NOPE', 'price' => '']);

        self::assertSame('Product Name cannot be empty.', $errors['product_name']);
        self::assertSame('Please select a category.', $errors['category']);
        self::assertSame('Price cannot be empty.', $errors['price']);
    }

    public function testProductPriceMustBeDecimal(): void
    {
        $errors = ProductInput::validate(['product_name' => 'A', 'category' => 'TEA', 'price' => '12a']);

        self::assertSame('Please enter a valid decimal value for Price.', $errors['price']);
    }

    public function testQuantityMaximumIs1000LikeTheNumericUpDown(): void
    {
        $errors = ProductInput::validate(['product_name' => 'A', 'category' => 'TEA', 'price' => '1', 'quantity' => 1001]);

        self::assertArrayHasKey('quantity', $errors);
    }

    public function testPartialProductUpdateOnlyChecksGivenFields(): void
    {
        self::assertSame([], ProductInput::validate(['quantity' => 5], partial: true));
    }

    public function testProductNormalizeTrimsAndCasts(): void
    {
        $data = ProductInput::normalize(['product_name' => '  Mocha ', 'price' => '99.999', 'quantity' => '7', 'image_path' => '']);

        self::assertSame('Mocha', $data['product_name']);
        self::assertSame(100.0, $data['price']);
        self::assertSame(7, $data['quantity']);
        self::assertNull($data['image_path']);
    }

    public function testPayrollNameAndIdRequired(): void
    {
        $errors = PayrollInput::validate([
            'name' => '', 'employee_id' => '', 'deduction_per_hour' => 0, 'sss' => 0,
            'philhealth' => 0, 'pagibig' => 0, 'others' => 0,
        ]);

        self::assertSame('Employee Name and ID are required.', PayrollInput::firstError($errors));
    }

    public function testPayrollDeductionsMustBeNumeric(): void
    {
        $errors = PayrollInput::validate([
            'name' => 'A', 'employee_id' => 'F-1', 'deduction_per_hour' => 'x', 'sss' => '',
            'philhealth' => 0, 'pagibig' => 0, 'others' => 0,
        ]);

        self::assertSame('Please enter valid numeric values for all deduction fields.', $errors['deduction_per_hour']);
        self::assertArrayHasKey('sss', $errors);
    }

    public function testPayrollHolidayTypeMustBeRegularOrSpecial(): void
    {
        $errors = PayrollInput::validate(['holiday_type' => 'Weekend'], partial: true);

        self::assertArrayHasKey('holiday_type', $errors);
    }
}
