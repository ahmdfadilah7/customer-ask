<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            ['code' => 'JKT', 'name' => 'Jakarta'],
            ['code' => 'SBY', 'name' => 'Surabaya'],
            ['code' => 'BDG', 'name' => 'Bandung'],
            ['code' => 'MKS', 'name' => 'Makassar'],
        ];

        foreach ($branches as $branch) {
            Branch::firstOrCreate(
                ['code' => $branch['code']],
                ['name' => $branch['name'], 'status' => 'active']
            );
        }
    }
}
