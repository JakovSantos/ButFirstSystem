<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Product;
use App\Support\Format;
use App\Support\ImageUpload;
use App\Support\MainWindow;
use App\Support\ProductInput;

/**
 * INVENTORY module (HTML CRUD): add, update, remove, search and print the summary.
 */
final class InventoryController
{
    public function index(): void
    {
        View::display('main', MainWindow::data('inventory', $_GET));
    }

    /** POST /inventory  (Add) */
    public function store(): void
    {
        $input = $this->input();
        $errors = ProductInput::validate($input);

        if ($errors !== []) {
            $this->fail($errors);
        }

        $upload = ImageUpload::store($_FILES['image'] ?? null);
        if ($upload['error'] !== null) {
            $this->fail(['image' => $upload['error']]);
        }
        if ($upload['path'] !== null) {
            $input['image_path'] = $upload['path'];
        }

        $product = Product::create(ProductInput::normalize($input));

        Session::flash('Product added successfully!', 'Success', 'information');
        Response::redirect('/inventory?selected=' . $product->product_id);
    }

    /** POST /inventory/{id}  (Add button while editing = Update) */
    public function update(string $id): void
    {
        $product = Product::find((int) $id);
        if ($product === null) {
            $this->notFound();
        }

        $input = $this->input();
        $errors = ProductInput::validate($input);
        if ($errors !== []) {
            $this->fail($errors, (int) $id);
        }

        $upload = ImageUpload::store($_FILES['image'] ?? null);
        if ($upload['error'] !== null) {
            $this->fail(['image' => $upload['error']], (int) $id);
        }
        if ($upload['path'] !== null) {
            $input['image_path'] = $upload['path'];
        }

        $product->fill(ProductInput::normalize($input));
        $product->save();

        Session::flash('Product updated successfully!', 'Success', 'information');
        Response::redirect('/inventory?selected=' . $product->product_id);
    }

    /** POST /inventory/{id}/delete  (Remove) */
    public function destroy(string $id): void
    {
        $product = Product::find((int) $id);
        if ($product === null) {
            $this->notFound();
        }

        $product->delete();

        Session::flash('Product removed successfully!', 'Success', 'information');
        Response::redirect('/inventory');
    }

    /** GET /inventory/print  (Print Summary -> INVENTORY REPORT) */
    public function print(): void
    {
        $products = Product::search((string) ($_GET['search'] ?? ''));

        if ($products->isEmpty()) {
            Session::flash('No inventory data to print.', 'No Data', 'information');
            Response::redirect('/inventory');
        }

        $rows = $products->map(static fn (Product $p): array => [
            'product_id'   => $p->product_id,
            'product_name' => $p->product_name,
            'category'     => $p->category,
            'price'        => Format::peso($p->price),
            'quantity'     => $p->quantity,
            'supplier'     => (string) $p->supplier,
        ])->all();

        View::display('print/inventory', [
            'title'   => 'Inventory Report',
            'rows'    => $rows,
            'company' => App::config('app.company'),
            'address' => App::config('app.address'),
        ], 'print/layout');
    }

    /**
     * @return array<string, mixed>
     */
    private function input(): array
    {
        return [
            'product_name' => $_POST['product_name'] ?? '',
            'category'     => $_POST['category'] ?? '',
            'price'        => $_POST['price'] ?? '',
            'quantity'     => $_POST['quantity'] ?? 0,
            'supplier'     => $_POST['supplier'] ?? '',
            'date_added'   => $_POST['date_added'] ?? '',
            'image_path'   => $_POST['image_path'] ?? '',
        ];
    }

    /**
     * Validation failed: show the "Please correct the highlighted errors." dialog.
     *
     * @param array<string, string> $errors
     */
    private function fail(array $errors, ?int $editing = null): never
    {
        http_response_code(422);
        Session::flash("Please correct the highlighted errors.\n\n• " . implode("\n• ", array_unique($errors)), 'Validation Error', 'error');
        Session::flashInput(['inventory' => $_POST, 'errors' => $errors, 'editing' => $editing]);
        Response::redirect('/inventory' . ($editing ? '?selected=' . $editing : ''));
    }

    private function notFound(): never
    {
        Session::flash('The selected product no longer exists.', 'Not Found', 'warning');
        Response::redirect('/inventory');
    }
}
