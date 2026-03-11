<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpeditionGoalOperator extends Model
{
    protected $fillable = [
        'goal_id',
        'operator_id',
        'total_points',
        'total_orders',
        'total_items',
        'value_cents',
    ];

    protected function casts(): array
    {
        return [
            'total_points' => 'integer',
            'total_orders' => 'integer',
            'total_items'  => 'integer',
            'value_cents'  => 'integer',
        ];
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(ExpeditionGoal::class, 'goal_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(ExpeditionOperator::class, 'operator_id');
    }

    public function getValueFormattedAttribute(): string
    {
        return 'R$ ' . number_format($this->value_cents / 100, 2, ',', '.');
    }
}
