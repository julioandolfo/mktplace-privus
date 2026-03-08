<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class OrderTimeline extends Model
{
    protected $fillable = [
        'order_id',
        'performed_by',
        'event_type',
        'title',
        'description',
        'data',
        'happened_at',
    ];

    protected function casts(): array
    {
        return [
            'data'        => 'json',
            'happened_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    // ─── Static Factory ───────────────────────────────────────────────────────

    /**
     * Registra um evento na timeline do pedido.
     *
     * @param Order|int  $order
     * @param string     $eventType  Ex: 'order_created', 'design_assigned'
     * @param string     $title      Texto exibido na timeline
     * @param array      $data       Metadados extras (tracking, designer name, etc.)
     * @param int|null   $userId     Quem realizou (null = sistema)
     */
    public static function log(
        Order|int $order,
        string $eventType,
        string $title,
        string $description = '',
        array $data = [],
        ?int $userId = null
    ): self {
        $orderId = $order instanceof Order ? $order->id : $order;

        $performedBy = $userId
            ?? (Auth::check() ? Auth::id() : null);

        return static::create([
            'order_id'     => $orderId,
            'performed_by' => $performedBy,
            'event_type'   => $eventType,
            'title'        => $title,
            'description'  => $description ?: null,
            'data'         => $data ?: null,
            'happened_at'  => now(),
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function icon(): string
    {
        return match ($this->event_type) {
            'order_created'        => 'heroicon-o-shopping-bag',
            'payment_confirmed'    => 'heroicon-o-credit-card',
            'design_assigned'      => 'heroicon-o-user-circle',
            'design_started'       => 'heroicon-o-pencil-square',
            'design_completed'     => 'heroicon-o-check-badge',
            'ai_mockup_generated'  => 'heroicon-o-sparkles',
            'production_started'   => 'heroicon-o-cog-8-tooth',
            'production_completed' => 'heroicon-o-check-circle',
            'ready_to_ship'        => 'heroicon-o-truck',
            'packing_started'      => 'heroicon-o-archive-box',
            'packing_completed'    => 'heroicon-o-archive-box-arrow-down',
            'invoice_emitted'      => 'heroicon-o-document-text',
            'shipped'              => 'heroicon-o-rocket-launch',
            'delivered'            => 'heroicon-s-check-circle',
            'note_added'           => 'heroicon-o-chat-bubble-left',
            'status_changed'       => 'heroicon-o-arrow-path',
            default                => 'heroicon-o-information-circle',
        };
    }

    public function color(): string
    {
        if (in_array($this->event_type, ['design_assigned', 'design_started'])) return 'purple';
        if (in_array($this->event_type, ['design_completed', 'ai_mockup_generated'])) return 'violet';
        if (in_array($this->event_type, ['production_started', 'production_completed'])) return 'orange';

        return match ($this->event_type) {
            'order_created'   => 'blue',
            'payment_confirmed' => 'green',
            'ready_to_ship'   => 'sky',
            'invoice_emitted' => 'indigo',
            'shipped'         => 'blue',
            'delivered'       => 'green',
            default           => 'gray',
        };
    }
}
