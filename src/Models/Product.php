<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Inventory item (table: inventory).
 *
 * @property int    $product_id
 * @property string $product_name
 * @property string $category
 * @property float  $price
 * @property int    $quantity
 * @property string|null $supplier
 * @property string $date_added
 * @property string|null $image_path
 */
final class Product extends Model
{
    protected $table = 'inventory';

    protected $primaryKey = 'product_id';

    /** @var list<string> */
    protected $fillable = [
        'product_name', 'category', 'price', 'quantity', 'supplier', 'date_added', 'image_path',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'product_id' => 'integer',
        'price'      => 'float',
        'quantity'   => 'integer',
    ];

    /** Product categories, in display order. */
    public const CATEGORIES = [
        'SUPPLIES', 'DAIRY', 'SYRUPS', 'COFFEE', 'TEA', 'SWEETENERS', 'TOPPINGS',
        'INGREDIENTS', 'FOODS', 'CROISSANT', 'MERCHANDISE', 'CAKES', 'COOKIES BROWNIES', 'BEVERAGES',
    ];

    /**
     * LIKE search over ID, name, category and supplier.
     */
    public static function search(?string $term): \Illuminate\Database\Eloquent\Collection
    {
        $query = self::query()->orderBy('product_id');
        $term = trim((string) $term);

        if ($term !== '') {
            $like = '%' . $term . '%';
            $query->where(static function ($q) use ($like): void {
                $q->where('product_id', 'like', $like)
                    ->orWhere('product_name', 'like', $like)
                    ->orWhere('category', 'like', $like)
                    ->orWhere('supplier', 'like', $like);
            });
        }

        return $query->get();
    }
}
