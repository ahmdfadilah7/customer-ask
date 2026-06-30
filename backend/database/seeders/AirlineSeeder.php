<?php

namespace Database\Seeders;

use App\Models\Airline;
use Illuminate\Database\Seeder;

class AirlineSeeder extends Seeder
{
    /**
     * Maskapai dikelola per-airline lewat menu Master Data.
     * Seeder ini hanya membersihkan kode grup/agregat yang sudah tidak dipakai.
     */
    public function run(): void
    {
        $obsoleteGroupCodes = [
            'ANY',
            'OTHER',
            'LCC',
            'PERINTIS',
            'AIRLINE_INTL',
            'AIRLINE_DOM_GA',
            'AIRLINE_DOM_OTHER',
            'AIRLINE_DOM_LCC',
            'AIRLINE_DOM_PERINTIS',
        ];

        Airline::query()
            ->whereIn('code', $obsoleteGroupCodes)
            ->each(fn (Airline $airline) => $airline->delete());

        Airline::normalizeSortOrders();
    }
}
