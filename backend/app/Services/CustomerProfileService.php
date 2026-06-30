<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAlias;
use App\Models\User;
use Illuminate\Support\Str;

class CustomerProfileService
{
    public function __construct(
        private readonly CustomerPricingService $pricingService,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $user = null): Customer
    {
        [$primaryName, $aliasesFromName] = $this->parseCustomerName((string) ($data['name'] ?? ''));
        $slug = $this->resolveUniqueSlug((int) $data['branch_id'], $primaryName);

        $customer = Customer::create([
            'branch_id' => $data['branch_id'],
            'name' => $primaryName,
            'slug' => $slug,
            'status' => 'active',
            'corp_mode' => $data['corp_mode'] ?? false,
            'faktur_pajak' => $data['faktur_pajak'] ?? null,
            'show_service_fee' => $data['show_service_fee'] ?? null,
            'invoice_method' => $data['invoice_method'] ?? null,
            'cn_percentage' => $data['cn_percentage'] ?? null,
            'contract_period' => $data['contract_period'] ?? null,
            'general_note' => $data['general_note'] ?? null,
        ]);

        $aliases = array_merge($aliasesFromName, $data['aliases'] ?? []);
        $this->syncAliases($customer, $aliases);
        $this->pricingService->upsertMaterai($customer, $data['materai'] ?? null, $user);

        return $customer->load(['branch:id,code,name', 'aliases']);
    }

    /** @param array<string, mixed> $data */
    public function update(Customer $customer, array $data, ?User $user = null): Customer
    {
        $payload = [];

        if (array_key_exists('name', $data)) {
            [$primaryName, $aliasesFromName] = $this->parseCustomerName((string) $data['name']);
            $payload['name'] = $primaryName;
            if ($primaryName !== $customer->name) {
                $payload['slug'] = $this->resolveUniqueSlug($customer->branch_id, $primaryName, $customer->id);
            }
            if (array_key_exists('aliases', $data)) {
                $this->syncAliases($customer, array_merge($aliasesFromName, $data['aliases'] ?? []));
            } else {
                $this->syncAliases($customer, $aliasesFromName);
            }
        } elseif (array_key_exists('aliases', $data)) {
            $this->syncAliases($customer, $data['aliases'] ?? []);
        }

        foreach (['corp_mode', 'faktur_pajak', 'show_service_fee', 'invoice_method', 'cn_percentage', 'contract_period', 'general_note'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if ($payload !== []) {
            $customer->update($payload);
        }

        if (array_key_exists('materai', $data)) {
            $this->pricingService->upsertMaterai($customer, $data['materai'], $user);
        }

        return $customer->load(['branch:id,code,name', 'aliases']);
    }

    /** @return array{0: string, 1: array<int, string>} */
    private function parseCustomerName(string $raw): array
    {
        $parts = array_values(array_filter(
            array_map('trim', preg_split('/\s*\/\s*/', $raw)),
            fn (string $part) => $part !== '',
        ));

        $primary = array_shift($parts) ?? '';

        return [$primary, $parts];
    }

    /** @param array<int, string> $aliases */
    private function syncAliases(Customer $customer, array $aliases): void
    {
        $names = collect($aliases)
            ->map(fn ($alias) => trim((string) $alias))
            ->filter()
            ->unique()
            ->values();

        $customer->aliases()->whereNotIn('alias_name', $names->all())->delete();

        foreach ($names as $alias) {
            CustomerAlias::firstOrCreate([
                'customer_id' => $customer->id,
                'alias_name' => $alias,
            ]);
        }
    }

    private function resolveUniqueSlug(int $branchId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'corporate';
        $slug = $base;
        $suffix = 1;

        while (
            Customer::query()
                ->where('branch_id', $branchId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
