<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_groups')) {
            Schema::create('customer_groups', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50)->nullable();
            $table->string('name');
            $table->string('slug');
            $table->foreignId('customer_group_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('corp_mode')->default(false);
            $table->string('handler', 50)->nullable();
            $table->boolean('faktur_pajak')->nullable();
            $table->boolean('show_service_fee')->nullable();
            $table->string('invoice_method', 30)->nullable();
            $table->decimal('cn_percentage', 6, 2)->nullable();
            $table->boolean('invoice_per_person')->default(false);
            $table->date('kick_off_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('general_note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'code']);
            $table->unique(['branch_id', 'slug']);
            $table->index(['branch_id', 'name']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('customer_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('alias_name');
            $table->timestamps();

            $table->index('alias_name');
        });

        Schema::create('customer_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('entity_code', 20)->nullable();
            $table->string('entity_name');
            $table->boolean('inherits_pricing')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('customer_airline_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('airline_id')->constrained()->cascadeOnDelete();
            $table->string('corporate_code', 50)->nullable();
            $table->string('tour_code', 50)->nullable();
            $table->string('access_code', 100)->nullable();
            $table->string('corporate_id', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'airline_id']);
        });

        Schema::create('customer_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->text('note');
            $table->boolean('is_important')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pricing_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('imported_from')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'is_active']);
        });

        Schema::create('customer_pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pricing_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_scope_id')->constrained()->cascadeOnDelete();
            $table->foreignId('airline_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('source_row')->default(1);
            $table->text('raw_value');
            $table->string('fee_type', 50)->nullable();
            $table->string('calculation_basis', 60)->nullable();
            $table->decimal('percentage_value', 12, 4)->nullable();
            $table->decimal('fixed_amount', 15, 2)->nullable();
            $table->decimal('minimum_amount', 15, 2)->nullable();
            $table->char('currency', 3)->default('IDR');
            $table->boolean('is_visible_to_client')->default(true);
            $table->boolean('hide_garuda_fee')->default(false);
            $table->boolean('separate_ga_non_ga')->default(false);
            $table->text('internal_note')->nullable();
            $table->timestamps();

            $table->unique(
                ['customer_id', 'pricing_version_id', 'service_category_id', 'region_scope_id', 'airline_id', 'source_row'],
                'customer_pricing_rule_unique'
            );
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('title_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('nationality_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name');
            $table->string('passport_number', 50)->nullable();
            $table->date('passport_expiry')->nullable();
            $table->string('ktp_number', 20)->nullable();
            $table->date('birthdate')->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('ticket_name_format')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index('customer_id');
            $table->index('full_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
        Schema::dropIfExists('customer_pricing_rules');
        Schema::dropIfExists('pricing_versions');
        Schema::dropIfExists('customer_notes');
        Schema::dropIfExists('customer_airline_codes');
        Schema::dropIfExists('customer_contacts');
        Schema::dropIfExists('customer_entities');
        Schema::dropIfExists('customer_aliases');
        Schema::dropIfExists('customers');
    }
};
