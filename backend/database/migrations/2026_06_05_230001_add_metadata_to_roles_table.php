<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('label')->nullable()->after('guard_name');
            $table->text('description')->nullable()->after('label');
            $table->boolean('requires_branch')->default(false)->after('description');
            $table->boolean('is_system')->default(false)->after('requires_branch');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['label', 'description', 'requires_branch', 'is_system']);
        });
    }
};
