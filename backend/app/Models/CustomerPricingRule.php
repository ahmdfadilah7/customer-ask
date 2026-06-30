<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPricingRule extends Model
{
    protected $fillable = [
        'customer_id', 'pricing_version_id', 'service_category_id',
        'region_scope_id', 'airline_id', 'source_row', 'raw_value',
        'fee_type', 'calculation_basis', 'percentage_value', 'fixed_amount',
        'minimum_amount', 'currency', 'is_visible_to_client', 'hide_garuda_fee',
        'separate_ga_non_ga', 'internal_note',
    ];

    protected function casts(): array
    {
        return [
            'percentage_value' => 'decimal:4',
            'fixed_amount' => 'decimal:2',
            'minimum_amount' => 'decimal:2',
            'is_visible_to_client' => 'boolean',
            'hide_garuda_fee' => 'boolean',
            'separate_ga_non_ga' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function pricingVersion(): BelongsTo
    {
        return $this->belongsTo(PricingVersion::class);
    }

    public function serviceCategory(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class);
    }

    public function regionScope(): BelongsTo
    {
        return $this->belongsTo(RegionScope::class);
    }

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class);
    }
}
