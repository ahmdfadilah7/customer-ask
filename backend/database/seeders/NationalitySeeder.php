<?php

namespace Database\Seeders;

use App\Models\Nationality;
use Illuminate\Database\Seeder;
use RuntimeException;

class NationalitySeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/countries.json');

        if (! file_exists($path)) {
            throw new RuntimeException('File countries.json tidak ditemukan di database/data/.');
        }

        $countries = json_decode(file_get_contents($path), true);

        if (! is_array($countries)) {
            throw new RuntimeException('Format countries.json tidak valid.');
        }

        foreach ($countries as $country) {
            $code = strtoupper($country['alpha-2'] ?? '');
            $name = trim($country['name'] ?? '');

            if ($code === '' || $name === '') {
                continue;
            }

            Nationality::updateOrCreate(
                ['code' => $code],
                ['name' => $name]
            );
        }
    }
}
