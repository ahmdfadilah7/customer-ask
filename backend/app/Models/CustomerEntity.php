<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerEntity extends Model
{
    protected $fillable = [
        'customer_id', 'entity_code', 'entity_name', 'inherits_pricing', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['inherits_pricing' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
