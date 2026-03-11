<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestItem extends Model
{
    protected $fillable = [
        'purchase_request_id',
        'product_id',
        'order_item_id',
        'description',
        'quantity',
        'unit_cost_cents',
        'link',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity'        => 'integer',
            'unit_cost_cents' => 'integer',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function getTotalCostCentsAttribute(): int
    {
        return $this->unit_cost_cents * $this->quantity;
    }

    public function getUnitCostFormattedAttribute(): string
    {
        return 'R$ ' . number_format($this->unit_cost_cents / 100, 2, ',', '.');
    }
}
