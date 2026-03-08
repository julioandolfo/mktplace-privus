<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'company_id',
        'role',
        'name',
        'email',
        'password',
        'avatar_path',
        'theme_preference',
        'locale',
        'timezone',
        'settings',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'settings'          => 'json',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function designAssignments(): HasMany
    {
        return $this->hasMany(DesignAssignment::class, 'designer_id');
    }

    // ─── Role Helpers ─────────────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isDesigner(): bool
    {
        return in_array($this->role, ['designer', 'admin']);
    }

    public function isOperator(): bool
    {
        return in_array($this->role, ['operator', 'admin']);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeDesigners($query)
    {
        return $query->where('role', 'designer');
    }

    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
