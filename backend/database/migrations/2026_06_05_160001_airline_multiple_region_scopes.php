<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airline_region_scope', function (Blueprint $table) {
            $table->foreignId('airline_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_scope_id')->constrained()->cascadeOnDelete();

            $table->primary(['airline_id', 'region_scope_id']);
        });

        if (Schema::hasColumn('airlines', 'region_scope_id')) {
            $rows = DB::table('airlines')
                ->whereNotNull('region_scope_id')
                ->get(['id', 'region_scope_id']);

            foreach ($rows as $row) {
                DB::table('airline_region_scope')->insertOrIgnore([
                    'airline_id' => $row->id,
                    'region_scope_id' => $row->region_scope_id,
                ]);
            }

            Schema::table('airlines', function (Blueprint $table) {
                $table->dropForeign(['region_scope_id']);
                $table->dropColumn('region_scope_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('airlines', function (Blueprint $table) {
            $table->foreignId('region_scope_id')->nullable()->after('name')->constrained()->cascadeOnDelete();
        });

        $pivots = DB::table('airline_region_scope')->get();

        foreach ($pivots->groupBy('airline_id') as $airlineId => $scopes) {
            DB::table('airlines')
                ->where('id', $airlineId)
                ->update(['region_scope_id' => $scopes->first()->region_scope_id]);
        }

        Schema::dropIfExists('airline_region_scope');
    }
};
