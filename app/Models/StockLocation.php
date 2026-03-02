<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockLocation extends Model
{
    protected $fillable = ['name', 'address', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }
}
