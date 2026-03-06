<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderMessage extends Model
{
    protected $fillable = [
        'order_id',
        'external_id',
        'direction',
        'sender_user_id',
        'text',
        'status',
        'moderation_status',
        'is_read',
        'message_date',
        'attachments',
    ];

    protected function casts(): array
    {
        return [
            'is_read'      => 'boolean',
            'message_date' => 'json',
            'attachments'  => 'json',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getIsSentAttribute(): bool
    {
        return $this->direction === 'sent';
    }

    public function getIsModeratedAttribute(): bool
    {
        return $this->moderation_status === 'rejected';
    }

    public function getCreatedAtFormattedAttribute(): string
    {
        $date = $this->message_date['created'] ?? null;
        if ($date) {
            return \Carbon\Carbon::parse($date)->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i');
        }
        return $this->created_at?->format('d/m/Y H:i') ?? '';
    }
}
