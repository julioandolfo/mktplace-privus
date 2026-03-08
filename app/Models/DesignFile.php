<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DesignFile extends Model
{
    protected $fillable = [
        'design_assignment_id',
        'order_item_id',
        'uploaded_by',
        'file_type',
        'file_name',
        'file_path',
        'file_url',
        'mime_type',
        'file_size',
        'disk',
        'is_production_file',
        'is_ai_generated',
        'ai_prompt',
    ];

    protected function casts(): array
    {
        return [
            'is_production_file' => 'boolean',
            'is_ai_generated'    => 'boolean',
            'file_size'          => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(DesignAssignment::class, 'design_assignment_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function publicUrl(): string
    {
        if ($this->file_url) {
            return $this->file_url;
        }

        if ($this->file_path) {
            return Storage::disk($this->disk ?? 'public')->url($this->file_path);
        }

        return '';
    }

    public function fileSizeFormatted(): string
    {
        if (! $this->file_size) {
            return '—';
        }

        $bytes = $this->file_size;
        if ($bytes < 1024)       return "{$bytes} B";
        if ($bytes < 1048576)    return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    public function typeLabel(): string
    {
        return match ($this->file_type) {
            'mockup'          => 'Mockup',
            'artwork'         => 'Arte',
            'production_file' => 'Arquivo de Produção',
            'reference'       => 'Referência',
            default           => ucfirst($this->file_type),
        };
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }
}
