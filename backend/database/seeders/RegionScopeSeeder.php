<?php

namespace Database\Seeders;

use App\Models\RegionScope;
use Illuminate\Database\Seeder;

class RegionScopeSeeder extends Seeder
{
    public function run(): void
    {
        $scopes = [
            ['code' => 'ALL', 'name' => 'Semua Wilayah'],
            ['code' => 'INTR', 'name' => 'International'],
            ['code' => 'DOM', 'name' => 'Domestic'],
        ];

        foreach ($scopes as $scope) {
            RegionScope::updateOrCreate(
                ['code' => $scope['code']],
                ['name' => $scope['name'], 'status' => 'active']
            );
        }

        RegionScope::query()
            ->whereIn('code', ['international', 'domestic'])
            ->delete();
    }
}
