<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PricingVersion extends Model
{
    protected $fillable = [
        'branch_id', 'name', 'effective_from', 'effective_to', 'is_active',
        'imported_from', 'imported_at', 'imported_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
            'imported_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(CustomerPricingRule::class);
    }
}
