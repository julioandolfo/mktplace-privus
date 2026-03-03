<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
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
        'order_number', 'company_id', 'marketplace_account_id', 'external_id',
        'status', 'payment_status', 'payment_method',
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
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'shipping_address' => 'json',
            'billing_address' => 'json',
            'subtotal' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'meta' => 'json',
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
}
