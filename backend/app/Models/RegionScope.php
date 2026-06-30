<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RegionScope extends Model
{
    protected $fillable = [
        'code',
        'name',
        'status',
    ];

    public function airlines(): BelongsToMany
    {
        return $this->belongsToMany(Airline::class);
    }
}
