<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebmaniaAccount extends Model
{
    protected $fillable = [
        'company_id',
        'consumer_key',
        'consumer_secret',
        'access_token',
        'access_token_secret',
        'environment',
        'default_series',
        'default_cfop',
        'default_tax_data',
        'certificate_expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'consumer_key' => 'encrypted',
            'consumer_secret' => 'encrypted',
            'access_token' => 'encrypted',
            'access_token_secret' => 'encrypted',
            'default_tax_data' => 'json',
            'certificate_expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
