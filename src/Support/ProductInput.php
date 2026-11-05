<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Product;

/**
 * Validation + normalization for products, shared by the HTML and JSON controllers.
 * Validation rules for product name, category, price and quantity.
 */
final class ProductInput
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, string> field => message (empty = valid)
     */
    public static function validate(array $input, bool $partial = false): array
    {
        $errors = [];

        if (! $partial || array_key_exists('product_name', $input)) {
            if (! is_string($input['product_name'] ?? null) || trim($input['product_name']) === '') {
                $errors['product_name'] = 'Product Name cannot be empty.';
            }
        }

        if (! $partial || array_key_exists('category', $input)) {
            if (! in_array($input['category'] ?? null, Product::CATEGORIES, true)) {
                $errors['category'] = 'Please select a category.';
            }
        }

        if (! $partial || array_key_exists('price', $input)) {
            $price = $input['price'] ?? null;
            if ($price === null || trim((string) $price) === '') {
                $errors['price'] = 'Price cannot be empty.';
            } elseif (filter_var($price, FILTER_VALIDATE_FLOAT) === false || (float) $price < 0) {
                $errors['price'] = 'Please enter a valid decimal value for Price.';
            }
        }

        if (array_key_exists('quantity', $input)) {
            $qty = filter_var($input['quantity'], FILTER_VALIDATE_INT);
            if ($qty === false || $qty < 0 || $qty > 1000) {
                $errors['quantity'] = 'Quantity must be a whole number between 0 and 1000.';
            }
        }

        if (array_key_exists('date_added', $input) && $input['date_added'] !== null && $input['date_added'] !== '') {
            $d = \DateTime::createFromFormat('Y-m-d', (string) $input['date_added']);
            if ($d === false || $d->format('Y-m-d') !== $input['date_added']) {
                $errors['date_added'] = 'Date Added must be a valid date (YYYY-MM-DD).';
            }
        }

        if (array_key_exists('supplier', $input) && $input['supplier'] !== null && ! is_string($input['supplier'])) {
            $errors['supplier'] = 'The supplier must be a string.';
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

        foreach (['product_name', 'category'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = trim((string) $input[$field]);
            }
        }
        if (array_key_exists('price', $input)) {
            $data['price'] = round((float) $input['price'], 2);
        }
        if (array_key_exists('quantity', $input)) {
            $data['quantity'] = (int) $input['quantity'];
        }
        if (array_key_exists('supplier', $input)) {
            $data['supplier'] = trim((string) $input['supplier']);
        }
        if (array_key_exists('date_added', $input)) {
            $data['date_added'] = ($input['date_added'] ?? '') !== '' ? (string) $input['date_added'] : date('Y-m-d');
        }
        if (array_key_exists('image_path', $input)) {
            $data['image_path'] = ($input['image_path'] ?? '') !== '' ? (string) $input['image_path'] : null;
        }

        return $data;
    }
}
