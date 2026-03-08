<?php

namespace App\Models;

use App\Enums\ProductionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'variant_id',
        'name', 'sku', 'quantity',
        'shipped_quantity', 'cancelled_quantity',
        'unit_price', 'discount', 'total',
        'production_status', 'artwork_url', 'artwork_approved',
        'production_notes', 'production_completed_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'unit_price'               => 'decimal:2',
            'discount'                 => 'decimal:2',
            'total'                    => 'decimal:2',
            'production_status'        => ProductionStatus::class,
            'artwork_approved'         => 'boolean',
            'production_completed_at'  => 'datetime',
            'meta'                     => 'json',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    // Accessors

    public function getSubtotalAttribute(): float
    {
        return ($this->unit_price * $this->quantity) - $this->discount;
    }

    public function getPendingQuantityAttribute(): int
    {
        return max(0, $this->quantity - $this->shipped_quantity - $this->cancelled_quantity);
    }

    public function getIsFullyShippedAttribute(): bool
    {
        return $this->pending_quantity === 0 && $this->shipped_quantity > 0;
    }

    public function getIsPartiallyShippedAttribute(): bool
    {
        return $this->shipped_quantity > 0 && $this->pending_quantity > 0;
    }

    public function getNeedsProductionAttribute(): bool
    {
        return in_array($this->production_status, [
            ProductionStatus::Pending,
            ProductionStatus::InProgress,
        ]);
    }

    public function getHasArtworkAttribute(): bool
    {
        return ! empty($this->artwork_url);
    }
}

