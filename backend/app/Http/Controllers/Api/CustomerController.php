<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\CustomerPricingRule;
use App\Models\PricingVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CustomerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->customerListing($request, onlyTrashed: false);
    }

    public function trashed(Request $request): AnonymousResourceCollection
    {
        return $this->customerListing($request, onlyTrashed: true);
    }

    private function customerListing(Request $request, bool $onlyTrashed): AnonymousResourceCollection
    {
        $user = $request->user();
        $baseQuery = $onlyTrashed ? Customer::onlyTrashed() : Customer::query();

        $query = QueryBuilder::for($baseQuery)
            ->allowedFilters([
                AllowedFilter::partial('name'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('branch_id'),
            ])
            ->allowedSorts(['name', 'created_at', 'updated_at', 'deleted_at'])
            ->with(['branch:id,code,name'])
            ->withCount($this->customerCountRelations($onlyTrashed));

        if ($user && ! $user->hasFullBranchAccess()) {
            $query->whereIn('branch_id', $user->allowedBranchIds());
        }

        if ($onlyTrashed) {
            $query->orderByDesc('deleted_at');
        } else {
            $query->orderBy('name');
        }

        $customers = $query->paginate($request->integer('per_page', 50));

        return CustomerResource::collection($customers);
    }

    /** @return array<int|string, mixed> */
    private function customerCountRelations(bool $onlyTrashed): array
    {
        $relations = [
            'contacts',
            'employees as pic_employees_count' => function ($q) use ($onlyTrashed) {
                if ($onlyTrashed) {
                    $q->withTrashed();
                }
                $q->whereHas('contact');
            },
            'pricingRules as active_pricing_rules_count' => function ($q) {
                $q->whereHas('pricingVersion', function ($version) {
                    $version->where('is_active', true)
                        ->whereColumn('pricing_versions.branch_id', 'customers.branch_id');
                });
            },
        ];

        $relations['employees'] = $onlyTrashed
            ? fn ($q) => $q->withTrashed()
            : fn ($q) => $q;

        return $relations;
    }

    public function show(Request $request, Customer $customer): CustomerResource
    {
        $user = $request->user();

        $this->authorizeCustomerAccess($request, $customer);

        $customer->load([
            'branch:id,code,name',
            'aliases',
            'contacts' => fn ($q) => $q->with('employee:id,full_name')
                ->orderByDesc('is_primary')
                ->orderBy('name'),
            'employees' => fn ($q) => $q->with([
                'title:id,name',
                'nationality:id,name',
                'contact:id,employee_id,is_primary,name',
            ])->orderBy('full_name'),
        ]);

        $version = PricingVersion::query()
            ->where('branch_id', $customer->branch_id)
            ->where('is_active', true)
            ->first();

        $customer->active_pricing_version = $version ? [
            'id' => $version->id,
            'name' => $version->name,
            'effective_from' => $version->effective_from?->toDateString(),
            'imported_at' => $version->imported_at,
        ] : null;

        $customer->pricing_groups = $this->groupPricingRules($customer, $version?->id);

        return new CustomerResource($customer);
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeCustomerAccess($request, $customer);

        $name = $customer->name;
        $customer->delete();

        return response()->json([
            'message' => "Corporate \"{$name}\" berhasil dihapus.",
        ]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $user = $request->user();
        $deleted = 0;
        $failed = [];

        $customers = Customer::query()
            ->whereIn('id', $data['ids'])
            ->get();

        foreach ($customers as $customer) {
            if ($user && ! $user->hasFullBranchAccess() && ! in_array($customer->branch_id, $user->allowedBranchIds(), true)) {
                $failed[] = [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'reason' => 'Tidak memiliki akses ke cabang corporate ini.',
                ];

                continue;
            }

            $customer->delete();
            $deleted++;
        }

        $notFound = array_values(array_diff(
            $data['ids'],
            $customers->pluck('id')->all()
        ));

        foreach ($notFound as $id) {
            $failed[] = [
                'id' => $id,
                'name' => null,
                'reason' => 'Corporate tidak ditemukan.',
            ];
        }

        $message = $deleted === 1
            ? '1 corporate berhasil dihapus.'
            : "{$deleted} corporate berhasil dihapus.";

        if ($failed !== []) {
            $message .= ' '.count($failed).' gagal dihapus.';
        }

        return response()->json([
            'message' => $message,
            'deleted' => $deleted,
            'failed' => $failed,
        ], $deleted > 0 ? 200 : 422);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $customer = Customer::onlyTrashed()->findOrFail($id);
        $this->authorizeCustomerAccess($request, $customer);

        $name = $customer->name;
        $customer->restore();

        return response()->json([
            'message' => "Corporate \"{$name}\" berhasil dipulihkan.",
        ]);
    }

    public function bulkRestore(Request $request): JsonResponse
    {
        return $this->bulkTrashedAction($request, 'restore');
    }

    public function forceDestroy(Request $request, int $id): JsonResponse
    {
        $customer = Customer::withTrashed()->findOrFail($id);

        if (! $customer->trashed()) {
            return response()->json(['message' => 'Corporate masih aktif. Hapus terlebih dahulu sebelum force delete.'], 422);
        }

        $this->authorizeCustomerAccess($request, $customer);

        $name = $customer->name;
        $customer->forceDelete();

        return response()->json([
            'message' => "Corporate \"{$name}\" dihapus permanen.",
        ]);
    }

    public function bulkForceDestroy(Request $request): JsonResponse
    {
        return $this->bulkTrashedAction($request, 'forceDelete');
    }

    private function bulkTrashedAction(Request $request, string $action): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $user = $request->user();
        $processed = 0;
        $failed = [];

        $customers = Customer::onlyTrashed()
            ->whereIn('id', $data['ids'])
            ->get();

        foreach ($customers as $customer) {
            if ($user && ! $user->hasFullBranchAccess() && ! in_array($customer->branch_id, $user->allowedBranchIds(), true)) {
                $failed[] = [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'reason' => 'Tidak memiliki akses ke cabang corporate ini.',
                ];

                continue;
            }

            $action === 'restore' ? $customer->restore() : $customer->forceDelete();
            $processed++;
        }

        $notFound = array_values(array_diff(
            $data['ids'],
            $customers->pluck('id')->all()
        ));

        foreach ($notFound as $id) {
            $failed[] = [
                'id' => $id,
                'name' => null,
                'reason' => 'Corporate tidak ditemukan di sampah.',
            ];
        }

        $verb = $action === 'restore' ? 'dipulihkan' : 'dihapus permanen';
        $message = $processed === 1
            ? "1 corporate berhasil {$verb}."
            : "{$processed} corporate berhasil {$verb}.";

        if ($failed !== []) {
            $message .= ' '.count($failed).' gagal diproses.';
        }

        return response()->json([
            'message' => $message,
            'processed' => $processed,
            'failed' => $failed,
        ], $processed > 0 ? 200 : 422);
    }

    private function authorizeCustomerAccess(Request $request, Customer $customer): void
    {
        $user = $request->user();

        if ($user && ! $user->hasFullBranchAccess() && ! in_array($customer->branch_id, $user->allowedBranchIds(), true)) {
            abort(403, 'Anda tidak memiliki akses ke corporate ini.');
        }
    }

    /**
     * @return array{airlines: array<int, array<string, mixed>>, services: array<int, array<string, mixed>>, materai: ?string}
     */
    private function groupPricingRules(Customer $customer, ?int $versionId): array
    {
        $groups = [
            'airlines' => [],
            'services' => [],
            'materai' => null,
        ];

        if (! $versionId) {
            return $groups;
        }

        $rules = CustomerPricingRule::query()
            ->where('customer_id', $customer->id)
            ->where('pricing_version_id', $versionId)
            ->with(['serviceCategory:id,code,name,group_code', 'regionScope:id,code,name', 'airline:id,code,name'])
            ->orderBy('service_category_id')
            ->orderBy('region_scope_id')
            ->orderBy('airline_id')
            ->get();

        foreach ($rules as $rule) {
            $categoryCode = $rule->serviceCategory?->code ?? '';

            if ($categoryCode === 'MATERAI') {
                $groups['materai'] = $rule->raw_value;

                continue;
            }

            $item = [
                'id' => $rule->id,
                'label' => $this->pricingLabel($rule),
                'service_category' => $categoryCode,
                'region_scope' => $rule->regionScope?->code,
                'airline_code' => $rule->airline?->code,
                'raw_value' => $rule->raw_value,
            ];

            if ($categoryCode === 'AIRLINE' && $rule->airline_id) {
                $groups['airlines'][] = $item;
            } else {
                $groups['services'][] = $item;
            }
        }

        return $groups;
    }

    private function pricingLabel(CustomerPricingRule $rule): string
    {
        $parts = array_filter([
            $rule->serviceCategory?->name,
            $rule->airline ? "{$rule->airline->name} ({$rule->airline->code})" : null,
            $rule->regionScope?->code,
        ]);

        return implode(' · ', $parts);
    }
}
