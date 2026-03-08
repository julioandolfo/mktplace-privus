<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PipelineStatus;
use App\Enums\ProductionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Order extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'order_number', 'company_id', 'marketplace_account_id', 'customer_id', 'external_id',
        'status', 'pipeline_status', 'payment_status', 'payment_method',
        'customer_name', 'customer_email', 'customer_phone', 'customer_document',
        'shipping_address', 'billing_address',
        'subtotal', 'shipping_cost', 'discount', 'total',
        'tracking_code', 'shipping_method', 'notes', 'internal_notes',
        'paid_at', 'shipped_at', 'delivered_at', 'cancelled_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'status'           => OrderStatus::class,
            'pipeline_status'  => PipelineStatus::class,
            'payment_status'   => PaymentStatus::class,
            'shipping_address' => 'json',
            'billing_address'  => 'json',
            'subtotal'         => 'decimal:2',
            'shipping_cost'    => 'decimal:2',
            'discount'         => 'decimal:2',
            'total'            => 'decimal:2',
            'paid_at'          => 'datetime',
            'shipped_at'       => 'datetime',
            'delivered_at'     => 'datetime',
            'cancelled_at'     => 'datetime',
            'meta'             => 'json',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber();
            }
        });
    }

    public static function generateOrderNumber(): string
    {
        $prefix = 'PED';
        $date = now()->format('Ymd');
        $last = static::withTrashed()
            ->where('order_number', 'like', "{$prefix}{$date}%")
            ->orderByDesc('order_number')
            ->value('order_number');

        if ($last) {
            $seq = (int) substr($last, -4) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . $date . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'payment_status', 'total', 'tracking_code'])
            ->logOnlyDirty();
    }

    // Relationships

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function marketplaceAccount(): BelongsTo
    {
        return $this->belongsTo(MarketplaceAccount::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function messages(): HasMany
    {
        // Order by the actual ML message date stored in the JSON field.
        // Using created_at is unreliable as messages are inserted in batch.
        return $this->hasMany(OrderMessage::class)
            ->orderByRaw("(message_date->>'created')::text ASC NULLS LAST");
    }

    public function romaneioItems(): HasMany
    {
        return $this->hasMany(RomaneioItem::class);
    }

    public function shipmentLabels(): HasMany
    {
        return $this->hasMany(ShipmentLabel::class);
    }

    // Scopes

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('order_number', 'ilike', "%{$term}%")
              ->orWhere('customer_name', 'ilike', "%{$term}%")
              ->orWhere('customer_email', 'ilike', "%{$term}%")
              ->orWhere('customer_document', 'ilike', "%{$term}%")
              ->orWhere('tracking_code', 'ilike', "%{$term}%");
        });
    }

    public function scopeOfStatus(Builder $query, OrderStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeOfPaymentStatus(Builder $query, PaymentStatus $status): Builder
    {
        return $query->where('payment_status', $status);
    }

    // Accessors

    public function getItemsCountAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    public function getIsEditableAttribute(): bool
    {
        return in_array($this->status, [
            OrderStatus::Pending,
            OrderStatus::Confirmed,
        ]);
    }

    public function getIsCancellableAttribute(): bool
    {
        return ! in_array($this->status, [
            OrderStatus::Shipped,
            OrderStatus::Delivered,
            OrderStatus::Cancelled,
            OrderStatus::Returned,
        ]);
    }

    // Methods

    public function recalculateTotals(): void
    {
        $subtotal = $this->items()->sum(\DB::raw('unit_price * quantity - discount'));
        $this->update([
            'subtotal' => $subtotal,
            'total' => $subtotal + $this->shipping_cost - $this->discount,
        ]);
    }

    /**
     * Recalcula o pipeline_status interno com base no estado dos order_items.
     * Chamado automaticamente pelo OrderItemObserver ao salvar/atualizar itens.
     */
    public function recalculatePipelineStatus(): void
    {
        $items = $this->items()->get();

        if ($items->isEmpty()) {
            return;
        }

        // Se já está despachado ou embalado, não regressar automaticamente
        if (in_array($this->pipeline_status, [
            PipelineStatus::Packing,
            PipelineStatus::Packed,
        ])) {
            // Só avança para shipped/partially_shipped se houver despachos
        }

        $totalQty    = $items->sum('quantity');
        $shippedQty  = $items->sum('shipped_quantity');
        $cancelledQty = $items->sum('cancelled_quantity');
        $pendingQty  = $totalQty - $shippedQty - $cancelledQty;

        // Envio concluído
        if ($pendingQty <= 0 && $shippedQty > 0) {
            $this->update(['pipeline_status' => PipelineStatus::Shipped]);
            return;
        }

        // Envio parcial (ao menos 1 item foi e ainda tem pendente)
        if ($shippedQty > 0 && $pendingQty > 0) {
            $this->update(['pipeline_status' => PipelineStatus::PartiallyShipped]);
            return;
        }

        // Verifica produção pendente
        $hasProductionPending = $items->contains(
            fn ($item) => in_array($item->production_status, [
                ProductionStatus::Pending->value,
                ProductionStatus::InProgress->value,
            ])
        );

        if ($hasProductionPending) {
            $allInProgress = $items->every(
                fn ($item) => $item->production_status !== ProductionStatus::Pending->value
            );

            $this->update([
                'pipeline_status' => $allInProgress
                    ? PipelineStatus::InProduction
                    : PipelineStatus::AwaitingProduction,
            ]);
            return;
        }

        // Não regressar de packing/packed para ready_to_ship automaticamente
        if (! in_array($this->pipeline_status, [
            PipelineStatus::Packing,
            PipelineStatus::Packed,
            PipelineStatus::PartiallyShipped,
            PipelineStatus::Shipped,
        ])) {
            $this->update(['pipeline_status' => PipelineStatus::ReadyToShip]);
        }
    }

    /**
     * Retorna o pipeline_status inicial para um novo pedido,
     * baseado nos itens (se algum produto requer produção).
     */
    public static function initialPipelineStatus(array $items = []): PipelineStatus
    {
        foreach ($items as $item) {
            if (isset($item['production_status']) &&
                $item['production_status'] === ProductionStatus::Pending->value) {
                return PipelineStatus::AwaitingProduction;
            }
        }

        return PipelineStatus::ReadyToShip;
    }

    // Accessors de expedição

    public function getIsInExpeditionAttribute(): bool
    {
        return in_array($this->pipeline_status, PipelineStatus::expeditionStatuses());
    }

    public function getIsInProductionAttribute(): bool
    {
        return in_array($this->pipeline_status, PipelineStatus::productionStatuses());
    }

    public function getIsPackedAttribute(): bool
    {
        return $this->pipeline_status === PipelineStatus::Packed;
    }

    public function getDispatchDeadlineAttribute(): ?string
    {
        return $this->meta['ml_shipping_deadline'] ?? null;
    }

    public function getExpeditionVolumesAttribute(): int
    {
        return (int) ($this->meta['expedition_volumes'] ?? 1);
    }
}
