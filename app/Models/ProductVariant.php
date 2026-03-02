<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'sku', 'name', 'attributes',
        'price', 'cost_price', 'weight', 'ean_gtin',
        'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'json',
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'weight' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'variant_id');
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class, 'variant_id');
    }

    public function getEffectivePriceAttribute(): float
    {
        return $this->price ?? $this->product->price;
    }

    public function getTotalStockAttribute(): int
    {
        return $this->stockItems->sum('quantity');
    }
}
