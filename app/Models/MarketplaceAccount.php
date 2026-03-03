<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\MarketplaceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MarketplaceAccount extends Model
{
    use LogsActivity;

    protected $fillable = [
        'company_id',
        'marketplace_type',
        'account_name',
        'shop_id',
        'credentials',
        'token_expires_at',
        'webhook_secret',
        'status',
        'last_synced_at',
        'last_error',
        'settings',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'marketplace_type' => MarketplaceType::class,
            'credentials' => 'encrypted:json',
            'token_expires_at' => 'datetime',
            'status' => AccountStatus::class,
            'last_synced_at' => 'datetime',
            'settings' => 'json',
            'meta' => 'json',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['account_name', 'status', 'marketplace_type'])
            ->logOnlyDirty();
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
            $q->where('account_name', 'ilike', "%{$term}%")
              ->orWhere('shop_id', 'ilike', "%{$term}%");
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', AccountStatus::Active);
    }

    // Methods

    public function isTokenExpired(): bool
    {
        if (! $this->token_expires_at) {
            return true;
        }

        return $this->token_expires_at->isPast();
    }

    public function getIsHealthyAttribute(): bool
    {
        return $this->status === AccountStatus::Active && ! $this->isTokenExpired();
    }
}
