<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProviderSetting extends Model
{
    protected $fillable = [
        'provider',
        'api_key',
        'base_url',
        'default_model',
        'settings',
        'monthly_budget_limit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'settings' => 'json',
            'monthly_budget_limit' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
