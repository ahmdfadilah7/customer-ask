<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            TitleSeeder::class,
            NationalitySeeder::class,
            ServiceCategorySeeder::class,
            BranchSeeder::class,
            RegionScopeSeeder::class,
            AirlineSeeder::class,
            PermissionSeeder::class,
            WebsiteSettingsSeeder::class,
        ]);

        $superadmin = User::updateOrCreate(
            ['email' => 'admin@customer.local'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        $superadmin->syncRoles([Roles::SUPERADMIN]);
    }
}
