<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('customer_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('active'); // active/inactive
            $table->text('general_note')->nullable();
            $table->boolean('invoice_per_person')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};

