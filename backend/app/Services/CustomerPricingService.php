<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPricingRule;
use App\Models\PricingVersion;
use App\Models\RegionScope;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

class CustomerPricingService
{
    private const MANUAL_SOURCE_ROW = 1;
    public function getOrCreateActiveVersion(int $branchId, ?User $user = null, string $name = 'Manual'): PricingVersion
    {
        $existing = PricingVersion::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->first();

        if ($existing) {
            return $existing;
        }

        return PricingVersion::create([
            'branch_id' => $branchId,
            'name' => $name,
            'effective_from' => Carbon::today(),
            'effective_to' => null,
            'is_active' => true,
            'imported_from' => 'manual',
            'imported_at' => now(),
            'imported_by' => $user?->id,
        ]);
    }

    public function upsertMaterai(Customer $customer, ?string $rawValue, ?User $user = null): ?CustomerPricingRule
    {
        $category = ServiceCategory::query()->where('code', 'MATERAI')->first();
        if (! $category) {
            throw new RuntimeException('Kategori MATERAI tidak ditemukan.');
        }

        $allScopeId = $this->allScopeId();
        $version = $this->getOrCreateActiveVersion($customer->branch_id, $user);
        $value = trim((string) $rawValue);

        if ($value === '') {
            CustomerPricingRule::query()
                ->where('customer_id', $customer->id)
                ->where('pricing_version_id', $version->id)
                ->where('service_category_id', $category->id)
                ->where('region_scope_id', $allScopeId)
                ->delete();

            return null;
        }

        return CustomerPricingRule::updateOrCreate(
            [
                'customer_id' => $customer->id,
                'pricing_version_id' => $version->id,
                'service_category_id' => $category->id,
                'region_scope_id' => $allScopeId,
                'airline_id' => null,
            ],
            [
                'raw_value' => $value,
                'source_row' => self::MANUAL_SOURCE_ROW,
            ],
        );
    }

    /** @param array<string, mixed> $data */
    public function upsertRule(Customer $customer, array $data, ?User $user = null): CustomerPricingRule
    {
        $version = $this->getOrCreateActiveVersion($customer->branch_id, $user);

        $category = ServiceCategory::query()->findOrFail($data['service_category_id']);
        if (! $category->is_pricing_slot) {
            throw new RuntimeException('Kategori layanan tidak valid untuk service fee.');
        }

        $regionScopeId = $data['region_scope_id'] ?? null;
        $airlineId = $data['airline_id'] ?? null;

        if ($category->requires_scope && ! $regionScopeId) {
            throw new RuntimeException('Scope wilayah wajib untuk kategori ini.');
        }

        if ($category->requires_airline && ! $airlineId) {
            $scopeCode = $regionScopeId
                ? RegionScope::query()->whereKey($regionScopeId)->value('code')
                : null;

            if ($scopeCode !== 'ALL') {
                throw new RuntimeException('Maskapai wajib untuk kategori ini.');
            }
        } elseif (! $category->requires_airline) {
            $airlineId = null;
        }

        if (! $category->requires_scope && ! $regionScopeId) {
            $regionScopeId = $this->allScopeId();
        }

        $attributes = [
            'customer_id' => $customer->id,
            'pricing_version_id' => $version->id,
            'service_category_id' => $category->id,
            'region_scope_id' => $regionScopeId,
            'airline_id' => $airlineId,
        ];

        if (! empty($data['id'])) {
            $rule = CustomerPricingRule::query()
                ->where('customer_id', $customer->id)
                ->whereKey($data['id'])
                ->firstOrFail();

            $rule->update([
                ...$attributes,
                'raw_value' => $data['raw_value'],
                'source_row' => self::MANUAL_SOURCE_ROW,
            ]);

            return $rule->fresh(['serviceCategory', 'regionScope', 'airline']);
        }

        return CustomerPricingRule::updateOrCreate(
            $attributes,
            [
                'raw_value' => $data['raw_value'],
                'source_row' => self::MANUAL_SOURCE_ROW,
            ],
        )->fresh(['serviceCategory', 'regionScope', 'airline']);
    }

    public function deleteRule(Customer $customer, CustomerPricingRule $rule): void
    {
        if ((int) $rule->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $rule->delete();
    }

    private function allScopeId(): int
    {
        $id = RegionScope::query()->where('code', 'ALL')->value('id');

        if (! $id) {
            throw new RuntimeException('Region scope ALL tidak ditemukan.');
        }

        return (int) $id;
    }
}
