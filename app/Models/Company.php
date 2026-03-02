<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Company extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'trade_name',
        'document_type',
        'document',
        'state_registration',
        'municipal_registration',
        'address',
        'phone',
        'email',
        'logo_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'address' => 'json',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }

    public function webmaniaAccount(): HasOne
    {
        return $this->hasOne(WebmaniaAccount::class);
    }

    public function marketplaceAccounts(): HasMany
    {
        return $this->hasMany(MarketplaceAccount::class);
    }

    public function getFormattedDocumentAttribute(): string
    {
        $doc = preg_replace('/\D/', '', $this->document);

        if ($this->document_type === 'cnpj' && strlen($doc) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $doc);
        }

        if (strlen($doc) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $doc);
        }

        return $this->document;
    }
}
