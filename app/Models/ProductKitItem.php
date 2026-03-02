<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductKitItem extends Model
{
    protected $fillable = [
        'kit_product_id', 'component_product_id',
        'component_variant_id', 'quantity', 'sort_order',
    ];

    public function kitProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'kit_product_id');
    }

    public function componentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }

    public function componentVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'component_variant_id');
    }

    public function getComponentNameAttribute(): string
    {
        if ($this->componentVariant) {
            return $this->componentProduct->name . ' - ' . $this->componentVariant->name;
        }
        return $this->componentProduct->name;
    }
}
