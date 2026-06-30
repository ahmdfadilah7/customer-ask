<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    protected $fillable = [
        'code', 'name', 'group_code', 'description', 'parent_id',
        'sort_order', 'requires_scope', 'is_pricing_slot', 'requires_airline', 'status',
    ];

    protected function casts(): array
    {
        return [
            'requires_scope' => 'boolean',
            'is_pricing_slot' => 'boolean',
            'requires_airline' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopePricingSlots(Builder $query): Builder
    {
        return $query->where('is_pricing_slot', true)->where('status', 'active');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(CustomerPricingRule::class);
    }
}
