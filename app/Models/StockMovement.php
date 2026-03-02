<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'stock_item_id', 'type', 'quantity',
        'previous_quantity', 'new_quantity',
        'reason', 'reference_type', 'reference_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StockMovement $movement) {
            $movement->created_at = $movement->created_at ?? now();
        });
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'in' => 'Entrada',
            'out' => 'Saida',
            'adjustment' => 'Ajuste',
            'transfer' => 'Transferencia',
            'reservation' => 'Reserva',
            'release' => 'Liberacao',
            default => $this->type,
        };
    }
}
