<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequest extends Model
{
    protected $fillable = [
        'company_id',
        'order_id',
        'supplier_id',
        'status',
        'title',
        'notes',
        'total_cost_cents',
        'purchased_at',
        'purchased_by',
    ];

    protected function casts(): array
    {
        return [
            'total_cost_cents' => 'integer',
            'purchased_at'     => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchasedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchased_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function getTotalCostFormattedAttribute(): string
    {
        return 'R$ ' . number_format($this->total_cost_cents / 100, 2, ',', '.');
    }

    public function recalculateTotal(): void
    {
        $total = $this->items()->sum(\Illuminate\Support\Facades\DB::raw('unit_cost_cents * quantity'));
        $this->update(['total_cost_cents' => (int) $total]);
    }
}
