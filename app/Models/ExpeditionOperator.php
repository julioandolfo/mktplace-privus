<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpeditionOperator extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'role',
        'is_default',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function forCompany(?int $companyId)
    {
        return static::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public static function defaultForCompany(?int $companyId): ?self
    {
        return static::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();
    }
}
