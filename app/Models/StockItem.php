<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockItem extends Model
{
    protected $fillable = [
        'product_id', 'variant_id', 'stock_location_id',
        'quantity', 'reserved_quantity', 'min_quantity',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->orderByDesc('created_at');
    }

    public function getAvailableQuantityAttribute(): int
    {
        return $this->quantity - $this->reserved_quantity;
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->available_quantity <= $this->min_quantity;
    }

    public function adjust(int $quantity, string $type, string $reason = null, $reference = null, ?int $userId = null): StockMovement
    {
        $previous = $this->quantity;
        $this->quantity += $quantity;
        $this->save();

        return StockMovement::create([
            'stock_item_id' => $this->id,
            'type' => $type,
            'quantity' => $quantity,
            'previous_quantity' => $previous,
            'new_quantity' => $this->quantity,
            'reason' => $reason,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id,
            'created_by' => $userId ?? auth()->id(),
        ]);
    }
}
