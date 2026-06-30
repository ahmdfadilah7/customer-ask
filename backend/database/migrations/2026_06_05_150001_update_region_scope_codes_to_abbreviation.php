<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('region_scopes')->where('code', 'international')->update(['code' => 'INTR']);
        DB::table('region_scopes')->where('code', 'domestic')->update(['code' => 'DOM']);
    }

    public function down(): void
    {
        DB::table('region_scopes')->where('code', 'INTR')->update(['code' => 'international']);
        DB::table('region_scopes')->where('code', 'DOM')->update(['code' => 'domestic']);
    }
};
