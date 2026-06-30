<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Airline extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'sort_order',
        'status',
    ];

    public function regionScopes(): BelongsToMany
    {
        return $this->belongsToMany(RegionScope::class);
    }

    public static function nextSortOrder(): int
    {
        return ((int) self::query()->max('sort_order')) + 1;
    }

    /** Renumber sort_order menjadi 1..n tanpa duplikat. */
    public static function normalizeSortOrders(): void
    {
        $airlines = self::query()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        foreach ($airlines as $index => $airline) {
            $order = $index + 1;
            if ((int) $airline->sort_order !== $order) {
                $airline->update(['sort_order' => $order]);
            }
        }
    }
}
