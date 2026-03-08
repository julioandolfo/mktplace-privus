<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Romaneio extends Model
{
    use LogsActivity;

    protected $fillable = [
        'company_id',
        'created_by',
        'name',
        'status',
        'closed_at',
        'closed_by',
        'notes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
            'meta'      => 'json',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'status', 'closed_at'])
            ->logOnlyDirty();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RomaneioItem::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function getTotalOrdersAttribute(): int
    {
        return $this->items()->count();
    }

    public function getTotalVolumesAttribute(): int
    {
        return $this->items()->sum('volumes');
    }

    public function getTotalVolumesScannedAttribute(): int
    {
        return $this->items()->sum('volumes_scanned');
    }

    public function getCompletionPercentAttribute(): int
    {
        $total = $this->total_volumes;

        if ($total === 0) {
            return 0;
        }

        return (int) round(($this->total_volumes_scanned / $total) * 100);
    }

    public function getPendingItemsCountAttribute(): int
    {
        return $this->items()
            ->whereColumn('volumes_scanned', '<', 'volumes')
            ->count();
    }
}
