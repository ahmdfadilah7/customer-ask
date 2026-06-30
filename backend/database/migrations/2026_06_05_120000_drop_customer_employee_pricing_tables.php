<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('customer_pricing_rules');
        Schema::dropIfExists('customer_notes');
        Schema::dropIfExists('customer_aliases');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('pricing_versions');
        Schema::dropIfExists('customers');
    }

    public function down(): void
    {
        // Tables will be recreated via original migrations when schema is redesigned.
    }
};
