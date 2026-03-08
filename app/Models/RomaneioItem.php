<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RomaneioItem extends Model
{
    protected $fillable = [
        'romaneio_id',
        'order_id',
        'volumes',
        'volumes_scanned',
        'items_detail',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'items_detail' => 'json',
        ];
    }

    public function romaneio(): BelongsTo
    {
        return $this->belongsTo(Romaneio::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isComplete(): bool
    {
        return $this->volumes_scanned >= $this->volumes;
    }

    public function getRemainingVolumesAttribute(): int
    {
        return max(0, $this->volumes - $this->volumes_scanned);
    }

    public function getProgressPercentAttribute(): int
    {
        if ($this->volumes === 0) {
            return 100;
        }

        return (int) round(($this->volumes_scanned / $this->volumes) * 100);
    }

    /**
     * Incrementa volumes_scanned. Retorna true se agora está completo.
     */
    public function scanVolume(): bool
    {
        if ($this->isComplete()) {
            return true;
        }

        $this->increment('volumes_scanned');
        $this->refresh();

        return $this->isComplete();
    }
}
