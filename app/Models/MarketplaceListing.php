<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceListing extends Model
{
    protected $fillable = [
        'marketplace_account_id',
        'external_id',
        'product_id',
        'product_quantity',
        'title',
        'price',
        'available_quantity',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'price'              => 'decimal:2',
            'product_quantity'   => 'integer',
            'available_quantity' => 'integer',
            'meta'               => 'json',
        ];
    }

    // Relationships

    public function marketplaceAccount(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAccount::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Scopes

    public function scopeUnlinked(Builder $query): Builder
    {
        return $query->whereNull('product_id');
    }

    public function scopeLinked(Builder $query): Builder
    {
        return $query->whereNotNull('product_id');
    }

    public function scopeOfAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('marketplace_account_id', $accountId);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where('title', 'ilike', "%{$term}%")
                     ->orWhere('external_id', 'ilike', "%{$term}%");
    }

    // Accessors

    public function getIsLinkedAttribute(): bool
    {
        return $this->product_id !== null;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active'  => 'success',
            'paused'  => 'warning',
            'closed'  => 'neutral',
            'deleted' => 'danger',
            default   => 'neutral',
        };
    }
}
