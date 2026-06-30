<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerPricingRuleResource;
use App\Models\Airline;
use App\Models\Customer;
use App\Models\CustomerPricingRule;
use App\Models\RegionScope;
use App\Models\ServiceCategory;
use App\Services\CsvImport\CorporateImportColumnManifest;
use App\Services\CustomerPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerPricingRuleController extends Controller
{
    public function __construct(
        private readonly CustomerPricingService $pricingService,
    ) {}

    public function reference(): JsonResponse
    {
        $categories = ServiceCategory::query()
            ->pricingSlots()
            ->where('code', '!=', 'MATERAI')
            ->orderBy('sort_order')
            ->get(['id', 'code', 'name', 'requires_scope', 'requires_airline']);

        $regionScopes = RegionScope::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $airlines = Airline::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $categoriesByCode = ServiceCategory::query()
            ->whereIn('code', collect(CorporateImportColumnManifest::serviceColumns())
                ->pluck('service_category')
                ->filter()
                ->unique())
            ->get(['id', 'code'])
            ->keyBy('code');

        $scopesByCode = RegionScope::query()
            ->where('status', 'active')
            ->get(['id', 'code'])
            ->keyBy('code');

        $airlinesByCode = Airline::query()
            ->where('status', 'active')
            ->get(['id', 'code'])
            ->keyBy('code');

        $pricingSlots = [];

        foreach (CorporateImportColumnManifest::serviceColumns() as $column) {
            if (! isset($column['service_category']) || $column['service_category'] === 'MATERAI') {
                continue;
            }

            $category = $categoriesByCode->get($column['service_category']);
            $scope = $scopesByCode->get($column['region_scope'] ?? 'ALL');

            if (! $category || ! $scope) {
                continue;
            }

            $airlineId = null;
            if (! empty($column['airline_code'])) {
                $airlineId = $airlinesByCode->get($column['airline_code'])?->id;
            }

            $pricingSlots[] = [
                'key' => $column['key'],
                'label' => $column['label'],
                'group' => $column['row2'],
                'service_category_id' => $category->id,
                'region_scope_id' => $scope->id,
                'airline_id' => $airlineId,
            ];
        }

        return response()->json([
            'pricing_slots' => $pricingSlots,
            'service_categories' => $categories,
            'region_scopes' => $regionScopes,
            'airlines' => $airlines,
        ]);
    }

    public function store(Request $request, Customer $customer): CustomerPricingRuleResource
    {
        $this->authorizeCustomerAccess($request, $customer);

        $data = $request->validate([
            'service_category_id' => ['required', 'exists:service_categories,id'],
            'region_scope_id' => ['nullable', 'exists:region_scopes,id'],
            'airline_id' => ['nullable', 'exists:airlines,id'],
            'raw_value' => ['required', 'string', 'max:255'],
        ]);

        try {
            $rule = $this->pricingService->upsertRule($customer, $data, $request->user());
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return new CustomerPricingRuleResource($rule);
    }

    public function update(Request $request, Customer $customer, CustomerPricingRule $pricingRule): CustomerPricingRuleResource
    {
        $this->authorizeCustomerAccess($request, $customer);

        $data = $request->validate([
            'service_category_id' => ['sometimes', 'exists:service_categories,id'],
            'region_scope_id' => ['nullable', 'exists:region_scopes,id'],
            'airline_id' => ['nullable', 'exists:airlines,id'],
            'raw_value' => ['sometimes', 'string', 'max:255'],
        ]);

        $data['id'] = $pricingRule->id;
        if (! isset($data['service_category_id'])) {
            $data['service_category_id'] = $pricingRule->service_category_id;
        }
        if (! isset($data['raw_value'])) {
            $data['raw_value'] = $pricingRule->raw_value;
        }

        try {
            $rule = $this->pricingService->upsertRule($customer, $data, $request->user());
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return new CustomerPricingRuleResource($rule);
    }

    public function destroy(Request $request, Customer $customer, CustomerPricingRule $pricingRule): JsonResponse
    {
        $this->authorizeCustomerAccess($request, $customer);

        $this->pricingService->deleteRule($customer, $pricingRule);

        return response()->json(['message' => 'Service fee berhasil dihapus.']);
    }

    private function authorizeCustomerAccess(Request $request, Customer $customer): void
    {
        $user = $request->user();

        if ($user && ! $user->hasFullBranchAccess() && ! in_array($customer->branch_id, $user->allowedBranchIds(), true)) {
            abort(403, 'Anda tidak memiliki akses ke corporate ini.');
        }
    }
}
