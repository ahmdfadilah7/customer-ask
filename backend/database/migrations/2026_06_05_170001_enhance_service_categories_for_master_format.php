<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->string('group_code', 30)->nullable()->after('name');
            $table->text('description')->nullable()->after('group_code');
            $table->boolean('is_pricing_slot')->default(true)->after('requires_scope');
            $table->boolean('requires_airline')->default(false)->after('is_pricing_slot');
            $table->string('status', 20)->default('active')->after('requires_airline');
        });
    }

    public function down(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropColumn([
                'group_code',
                'description',
                'is_pricing_slot',
                'requires_airline',
                'status',
            ]);
        });
    }
};
