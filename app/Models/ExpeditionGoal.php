<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpeditionGoal extends Model
{
    protected $fillable = [
        'company_id',
        'month',
        'total_pending_orders',
        'working_days',
        'daily_order_goal',
        'total_points_earned',
        'total_value_cents',
        'is_locked',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'month'                => 'date',
            'total_pending_orders' => 'integer',
            'working_days'         => 'integer',
            'daily_order_goal'     => 'integer',
            'total_points_earned'  => 'integer',
            'total_value_cents'    => 'integer',
            'is_locked'            => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function operators(): HasMany
    {
        return $this->hasMany(ExpeditionGoalOperator::class, 'goal_id');
    }

    public function getTotalValueFormattedAttribute(): string
    {
        return 'R$ ' . number_format($this->total_value_cents / 100, 2, ',', '.');
    }
}
