<?php

use App\Models\Customer;
use App\Models\Employee;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $customerIds = Customer::onlyTrashed()->pluck('id');

        if ($customerIds->isEmpty()) {
            return;
        }

        Employee::query()
            ->whereIn('customer_id', $customerIds)
            ->whereNull('deleted_at')
            ->each(fn (Employee $employee) => $employee->delete());
    }

    public function down(): void
    {
        // Data cleanup migration — no rollback.
    }
};
