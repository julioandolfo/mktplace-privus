<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Product extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'sku', 'name', 'slug', 'description', 'short_description',
        'category_id', 'brand_id', 'type', 'status',
        'price', 'cost_price', 'compare_at_price',
        'weight', 'width', 'height', 'length',
        'ncm', 'cest', 'ean_gtin', 'origin', 'tax_data',
        'seo_title', 'seo_description',
        'ai_generated_description', 'ai_score',
        'attributes', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'weight' => 'decimal:3',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'length' => 'decimal:2',
            'tax_data' => 'json',
            'attributes' => 'json',
            'meta' => 'json',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
                $original = $product->slug;
                $count = 1;
                while (static::withTrashed()->where('slug', $product->slug)->exists()) {
                    $product->slug = $original . '-' . $count++;
                }
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'sku', 'price', 'status', 'type'])
            ->logOnlyDirty();
    }

    // Relationships

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function kitItems(): HasMany
    {
        return $this->hasMany(ProductKitItem::class, 'kit_product_id');
    }

    public function usedInKits(): HasMany
    {
        return $this->hasMany(ProductKitItem::class, 'component_product_id');
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Active);
    }

    public function scopeOfType(Builder $query, ProductType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'ilike', "%{$term}%")
              ->orWhere('sku', 'ilike', "%{$term}%")
              ->orWhere('ean_gtin', 'ilike', "%{$term}%");
        });
    }

    public function scopeLowStock(Builder $query, int $threshold = null): Builder
    {
        return $query->whereHas('stockItems', function ($q) use ($threshold) {
            if ($threshold !== null) {
                $q->whereRaw('quantity - reserved_quantity <= ?', [$threshold]);
            } else {
                $q->whereRaw('quantity - reserved_quantity <= min_quantity');
            }
        });
    }

    // Accessors

    public function getTotalStockAttribute(): int
    {
        return $this->stockItems->sum('quantity');
    }

    public function getAvailableStockAttribute(): int
    {
        return $this->stockItems->sum(fn ($item) => $item->quantity - $item->reserved_quantity);
    }

    public function getIsKitAttribute(): bool
    {
        return $this->type === ProductType::Kit;
    }

    public function getProfitMarginAttribute(): ?float
    {
        if (! $this->cost_price || $this->cost_price == 0) {
            return null;
        }

        return round((($this->price - $this->cost_price) / $this->cost_price) * 100, 2);
    }
}
