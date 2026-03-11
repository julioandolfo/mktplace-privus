<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpeditionBonusConfig extends Model
{
    protected $fillable = [
        'company_id',
        'points_value_cents',
        'default_product_points',
        'deadline_buffer_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'points_value_cents'    => 'integer',
            'default_product_points' => 'integer',
            'deadline_buffer_days'  => 'integer',
            'is_active'             => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Retorna config da empresa, criando com defaults se não existir.
     */
    public static function forCompany(int $companyId): self
    {
        return static::firstOrCreate(
            ['company_id' => $companyId],
            [
                'points_value_cents'    => 10,
                'default_product_points' => 1,
                'deadline_buffer_days'  => 1,
                'is_active'             => true,
            ]
        );
    }

    /**
     * Valor formatado do ponto em reais.
     */
    public function getPointsValueFormattedAttribute(): string
    {
        return 'R$ ' . number_format($this->points_value_cents / 100, 2, ',', '.');
    }
}
