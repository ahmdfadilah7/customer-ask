<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_pricing_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pricing_version_id')->constrained()->cascadeOnDelete();

            $table->string('region_scope', 20)->default('all'); // all/domestic/international

            $table->text('raw_value');

            $table->string('fee_type', 50)->nullable(); // markup/service_fee/mixed/on_ticket/none
            $table->string('calculation_basis', 60)->nullable(); // from_total, from_basic, per_ticket, fixed, percentage, ...

            $table->decimal('percentage_value', 12, 4)->nullable();
            $table->decimal('fixed_amount', 15, 2)->nullable();
            $table->char('currency', 3)->default('IDR');

            $table->boolean('is_visible_to_client')->default(true);
            $table->boolean('hide_garuda_fee')->default(false);
            $table->boolean('separate_ga_non_ga')->default(false);

            $table->text('internal_note')->nullable();

            $table->timestamps();

            $table->unique(
                ['customer_id', 'service_category_id', 'pricing_version_id', 'region_scope'],
                'customer_pricing_rule_unique'
            );

            $table->index(
                ['customer_id', 'pricing_version_id', 'service_category_id'],
                'cpr_customer_version_category_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_pricing_rules');
    }
};

