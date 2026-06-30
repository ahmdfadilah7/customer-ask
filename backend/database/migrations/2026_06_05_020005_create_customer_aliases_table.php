<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('alias_name');
            $table->timestamps();

            $table->unique(['customer_id', 'alias_name'], 'customer_alias_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_aliases');
    }
};

