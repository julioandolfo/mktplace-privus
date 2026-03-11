<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpeditionPointsLog extends Model
{
    public $timestamps = false;

    protected $table = 'expedition_points_log';

    protected $fillable = [
        'company_id',
        'operator_id',
        'order_id',
        'order_item_id',
        'event_type',
        'points',
        'reference_date',
    ];

    protected function casts(): array
    {
        return [
            'points'         => 'integer',
            'reference_date' => 'date',
            'created_at'     => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(ExpeditionOperator::class, 'operator_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
