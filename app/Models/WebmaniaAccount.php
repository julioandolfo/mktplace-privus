<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebmaniaAccount extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        // API 1.0 — NF-e
        'consumer_key',
        'consumer_secret',
        'access_token',
        'access_token_secret',
        // API 2.0 — NFS-e
        'bearer_token',
        'environment',
        'default_series',
        'default_cfop',
        'default_tax_data',
        'certificate_expires_at',
        'is_active',
        // Configurações padrão de emissão
        'default_tax_class',
        'default_ncm',
        'default_cest',
        'default_nature_operation',
        'default_origin',
        'default_shipping_modality',
        'intermediador_type',
        'intermediador_cnpj',
        'intermediador_id',
        'additional_info_fisco',
        'additional_info_consumer',
        'auto_emit_trigger',
        'auto_send_email',
        'emit_with_order_date',
        'error_email',
    ];

    protected function casts(): array
    {
        return [
            'consumer_key'        => 'encrypted',
            'consumer_secret'     => 'encrypted',
            'access_token'        => 'encrypted',
            'access_token_secret' => 'encrypted',
            'bearer_token'        => 'encrypted',
            'default_tax_data'    => 'json',
            'certificate_expires_at' => 'datetime',
            'is_active'           => 'boolean',
            'auto_send_email'     => 'boolean',
            'emit_with_order_date' => 'boolean',
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

    public function isSandbox(): bool
    {
        return $this->environment === 'homologacao';
    }

    public function apiBaseUrl(): string
    {
        return $this->isSandbox()
            ? 'https://sandbox.webmaniabr.com/api/1'
            : 'https://webmaniabr.com/api/1';
    }
}

