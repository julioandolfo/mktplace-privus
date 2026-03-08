<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentLabel extends Model
{
    protected $fillable = [
        'order_id',
        'melhor_envios_account_id',
        'me_label_id',
        'carrier',
        'service',
        'tracking_code',
        'cost',
        'customer_paid',
        'label_url',
        'status',
        'quoted_at',
        'purchased_at',
        'cancelled_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'cost'          => 'decimal:2',
            'customer_paid' => 'decimal:2',
            'quoted_at'     => 'datetime',
            'purchased_at'  => 'datetime',
            'cancelled_at'  => 'datetime',
            'meta'          => 'json',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function melhorEnviosAccount(): BelongsTo
    {
        return $this->belongsTo(MelhorEnviosAccount::class);
    }

    public function isPurchased(): bool
    {
        return $this->status === 'purchased';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getFreightMarginAttribute(): ?float
    {
        if (! $this->cost || ! $this->customer_paid) {
            return null;
        }

        return round($this->customer_paid - $this->cost, 2);
    }
}
