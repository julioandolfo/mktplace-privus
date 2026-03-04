<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'email',
        'phone',
        'document',
        'address',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'address' => 'json',
            'meta'    => 'json',
        ];
    }

    // Relationships

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // Scopes

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'ilike', "%{$term}%")
              ->orWhere('email', 'ilike', "%{$term}%")
              ->orWhere('document', 'ilike', "%{$term}%")
              ->orWhere('phone', 'ilike', "%{$term}%");
        });
    }

    // Accessors

    public function getTotalSpentAttribute(): float
    {
        return (float) $this->orders()->sum('total');
    }

    public function getOrdersCountAttribute(): int
    {
        return $this->orders()->count();
    }

    public function getLastOrderAtAttribute(): ?\Carbon\Carbon
    {
        $last = $this->orders()->latest()->value('created_at');
        return $last ? \Carbon\Carbon::parse($last) : null;
    }
}
