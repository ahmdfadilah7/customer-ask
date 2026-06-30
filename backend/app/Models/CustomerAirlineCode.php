<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAirlineCode extends Model
{
    protected $fillable = [
        'customer_id', 'airline_id', 'corporate_code', 'tour_code',
        'access_code', 'corporate_id', 'notes',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class);
    }
}
