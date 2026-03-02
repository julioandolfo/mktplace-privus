<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\MarketplaceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isTokenExpired(): bool
    {
        if (! $this->token_expires_at) {
            return true;
        }

        return $this->token_expires_at->isPast();
    }
}
