<?php

namespace App\Models;

use App\Enums\NfeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Invoice extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'order_id', 'company_id', 'number', 'series', 'access_key', 'protocol',
        'status', 'type', 'customer_name', 'customer_document', 'customer_address',
        'total_products', 'total_shipping', 'total_discount', 'total_tax', 'total',
        'nature_operation', 'tax_data', 'pdf_url', 'xml_url', 'xml_content',
        'rejection_reason', 'cancellation_reason', 'cancelled_at',
        'external_id', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'status' => NfeStatus::class,
            'customer_address' => 'json',
            'total_products' => 'decimal:2',
            'total_shipping' => 'decimal:2',
            'total_discount' => 'decimal:2',
            'total_tax' => 'decimal:2',
            'total' => 'decimal:2',
            'tax_data' => 'json',
            'cancelled_at' => 'datetime',
            'meta' => 'json',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'number', 'access_key', 'total'])
            ->logOnlyDirty();
    }

    // Relationships

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // Scopes

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('number', 'ilike', "%{$term}%")
              ->orWhere('access_key', 'ilike', "%{$term}%")
              ->orWhere('customer_name', 'ilike', "%{$term}%")
              ->orWhere('customer_document', 'ilike', "%{$term}%");
        });
    }

    public function scopeOfStatus(Builder $query, NfeStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    // Accessors

    public function getFormattedAccessKeyAttribute(): ?string
    {
        if (! $this->access_key) {
            return null;
        }

        return implode(' ', str_split($this->access_key, 4));
    }

    public function getIsCancellableAttribute(): bool
    {
        return $this->status === NfeStatus::Approved;
    }
}
