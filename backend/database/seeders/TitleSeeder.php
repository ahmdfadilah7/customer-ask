<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TitleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('titles')->updateOrInsert(['code' => 'mr'], ['name' => 'Mr.', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('titles')->updateOrInsert(['code' => 'mrs'], ['name' => 'Mrs.', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('titles')->updateOrInsert(['code' => 'ms'], ['name' => 'Ms.', 'created_at' => now(), 'updated_at' => now()]);
    }
}
