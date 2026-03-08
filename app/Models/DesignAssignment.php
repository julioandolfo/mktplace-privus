<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DesignAssignment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'order_id',
        'designer_id',
        'status',
        'canvas_state',
        'mockup_url',
        'notes',
        'revision_notes',
        'assigned_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'canvas_state' => 'json',
            'assigned_at'  => 'datetime',
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function designer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'designer_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(DesignFile::class);
    }

    public function productionFiles(): HasMany
    {
        return $this->hasMany(DesignFile::class)->where('is_production_file', true);
    }

    public function mockupFile(): ?DesignFile
    {
        return $this->files()->where('file_type', 'mockup')->latest()->first();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isPending(): bool   { return $this->status === 'pending'; }
    public function isInProgress(): bool { return $this->status === 'in_progress'; }
    public function isRevision(): bool   { return $this->status === 'revision'; }
    public function isCompleted(): bool  { return $this->status === 'completed'; }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending'     => 'Aguardando',
            'in_progress' => 'Em Andamento',
            'revision'    => 'Em Revisão',
            'completed'   => 'Concluído',
            default       => ucfirst($this->status),
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'pending'     => 'warning',
            'in_progress' => 'info',
            'revision'    => 'danger',
            'completed'   => 'success',
            default       => 'default',
        };
    }
}
