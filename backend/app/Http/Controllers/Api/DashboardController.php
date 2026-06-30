<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerPricingRule;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchIds = $user && ! $user->hasFullBranchAccess()
            ? $user->allowedBranchIds()
            : null;

        $scopeCustomer = function ($query) use ($branchIds) {
            if ($branchIds !== null) {
                $query->whereIn('branch_id', $branchIds);
            }
        };

        $scopeActiveCustomerRelation = function ($query) use ($branchIds) {
            $query->whereNull('deleted_at');
            if ($branchIds !== null) {
                $query->whereIn('branch_id', $branchIds);
            }
        };

        $corporates = Customer::query()->tap($scopeCustomer)->count();
        $corporatesTrashed = Customer::onlyTrashed()->tap($scopeCustomer)->count();

        $employees = Employee::query()
            ->whereHas('customer', $scopeActiveCustomerRelation)
            ->count();

        $pics = CustomerContact::query()
            ->whereHas('customer', $scopeActiveCustomerRelation)
            ->count();

        $pricingRules = $this->activePricingRulesQuery($branchIds)->count();

        $branches = Branch::query()
            ->when($branchIds !== null, fn ($q) => $q->whereIn('id', $branchIds))
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(function (Branch $branch) {
                $customerIds = Customer::query()
                    ->where('branch_id', $branch->id)
                    ->pluck('id');

                return [
                    'id' => $branch->id,
                    'code' => $branch->code,
                    'name' => $branch->name,
                    'corporates' => $customerIds->count(),
                    'employees' => Employee::query()
                        ->whereIn('customer_id', $customerIds)
                        ->count(),
                    'pics' => CustomerContact::query()
                        ->whereIn('customer_id', $customerIds)
                        ->count(),
                    'pricing_rules' => $this->activePricingRulesQuery([$branch->id])->count(),
                ];
            })
            ->values();

        return response()->json([
            'data' => [
                'corporates' => $corporates,
                'corporates_trashed' => $corporatesTrashed,
                'employees' => $employees,
                'pics' => $pics,
                'pricing_rules' => $pricingRules,
                'branches' => $branches->count(),
            ],
            'by_branch' => $branches,
        ]);
    }

    /** @param  list<int>|null  $branchIds */
    private function activePricingRulesQuery(?array $branchIds)
    {
        return CustomerPricingRule::query()
            ->join('customers', 'customers.id', '=', 'customer_pricing_rules.customer_id')
            ->join('pricing_versions', 'pricing_versions.id', '=', 'customer_pricing_rules.pricing_version_id')
            ->whereNull('customers.deleted_at')
            ->where('pricing_versions.is_active', true)
            ->whereColumn('pricing_versions.branch_id', 'customers.branch_id')
            ->when($branchIds !== null, fn ($q) => $q->whereIn('customers.branch_id', $branchIds))
            ->select('customer_pricing_rules.id');
    }
}
