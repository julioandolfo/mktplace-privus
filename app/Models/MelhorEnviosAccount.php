<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MelhorEnviosAccount extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'client_id',
        'client_secret',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'environment',
        'from_name',
        'from_document',
        'from_cep',
        'from_address',
        'default_package',
        'settings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'client_secret'    => 'encrypted',
            'access_token'     => 'encrypted',
            'refresh_token'    => 'encrypted',
            'token_expires_at' => 'datetime',
            'from_address'     => 'json',
            'default_package'  => 'json',
            'settings'         => 'json',
            'is_active'        => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function marketplaceAccounts(): HasMany
    {
        return $this->hasMany(MarketplaceAccount::class);
    }

    public function shipmentLabels(): HasMany
    {
        return $this->hasMany(ShipmentLabel::class);
    }

    public function isTokenExpired(): bool
    {
        if (! $this->token_expires_at) {
            return true;
        }

        return $this->token_expires_at->isPast();
    }

    public function isSandbox(): bool
    {
        return $this->environment === 'sandbox';
    }

    public function baseUrl(): string
    {
        return $this->isSandbox()
            ? 'https://sandbox.melhorenvios.com.br/api/v2'
            : 'https://melhorenvios.com.br/api/v2';
    }
}
