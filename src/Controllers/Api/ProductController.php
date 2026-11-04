<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Models\Product;
use App\Support\ProductInput;

/**
 * JSON API CRUD for inventory products: /api/products
 */
final class ProductController
{
    /** GET /api/products?search= */
    public function index(): void
    {
        $products = Product::search((string) ($_GET['search'] ?? ''));

        Response::json([
            'data'  => $products,
            'count' => $products->count(),
        ]);
    }

    /** GET /api/products/{id} */
    public function show(string $id): void
    {
        $product = Product::find((int) $id);
        if ($product === null) {
            Response::json(['error' => 'Product not found'], 404);
            return;
        }

        Response::json(['data' => $product]);
    }

    /** POST /api/products */
    public function store(): void
    {
        $input = Request::json();
        if ($input === null) {
            Response::json(['error' => 'Request body must be valid JSON'], 400);
            return;
        }

        $errors = ProductInput::validate($input);
        if ($errors !== []) {
            Response::json(['errors' => $errors], 422);
            return;
        }

        $input += ['quantity' => 0, 'date_added' => date('Y-m-d')];
        $product = Product::create(ProductInput::normalize($input));

        Response::json(['data' => $product->fresh()], 201);
    }

    /** PUT /api/products/{id}  (partial update) */
    public function update(string $id): void
    {
        $product = Product::find((int) $id);
        if ($product === null) {
            Response::json(['error' => 'Product not found'], 404);
            return;
        }

        $input = Request::json();
        if ($input === null) {
            Response::json(['error' => 'Request body must be valid JSON'], 400);
            return;
        }

        $errors = ProductInput::validate($input, partial: true);
        if ($errors !== []) {
            Response::json(['errors' => $errors], 422);
            return;
        }

        $product->fill(ProductInput::normalize($input));
        $product->save();

        Response::json(['data' => $product]);
    }

    /** DELETE /api/products/{id} */
    public function destroy(string $id): void
    {
        $product = Product::find((int) $id);
        if ($product === null) {
            Response::json(['error' => 'Product not found'], 404);
            return;
        }

        $product->delete();
        Response::json(['message' => 'Product deleted']);
    }
}
