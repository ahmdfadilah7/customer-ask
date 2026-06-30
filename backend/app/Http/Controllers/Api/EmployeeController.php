<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Models\Customer;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EmployeeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = QueryBuilder::for(Employee::class)
            ->allowedFilters([
                AllowedFilter::partial('full_name'),
                AllowedFilter::partial('email'),
                AllowedFilter::exact('customer_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('customer.branch_id'),
            ])
            ->allowedSorts(['full_name', 'created_at'])
            ->with([
                'title:id,name',
                'nationality:id,name',
                'contact:id,employee_id,is_primary,name,phone,email',
                'customer:id,name,branch_id',
                'customer.branch:id,code,name',
            ]);

        $query->whereHas('customer', function ($q) use ($user) {
            if ($user && ! $user->hasFullBranchAccess()) {
                $q->whereIn('branch_id', $user->allowedBranchIds());
            }
        });

        $employees = $query
            ->orderBy('full_name')
            ->paginate($request->integer('per_page', 50));

        return EmployeeResource::collection($employees);
    }

    public function byCustomer(Request $request, Customer $customer): AnonymousResourceCollection
    {
        $this->authorizeCustomer($request, $customer);

        $employees = $customer->employees()
            ->with([
                'title:id,name',
                'nationality:id,name',
                'contact:id,employee_id,is_primary,name,phone,email',
            ])
            ->orderBy('full_name')
            ->get();

        return EmployeeResource::collection($employees);
    }

    public function store(Request $request): EmployeeResource
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'title_id' => ['nullable', 'exists:titles,id'],
            'nationality_id' => ['nullable', 'exists:nationalities,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'passport_number' => ['nullable', 'string', 'max:50'],
            'passport_expiry' => ['nullable', 'date'],
            'ktp_number' => ['nullable', 'string', 'max:20'],
            'birthdate' => ['nullable', 'date'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'ticket_name_format' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $customer = Customer::findOrFail($data['customer_id']);
        $this->authorizeCustomer($request, $customer);

        $employee = Employee::create([
            ...$data,
            'status' => $data['status'] ?? 'active',
        ]);

        return new EmployeeResource($this->loadEmployeeRelations($employee));
    }

    public function show(Request $request, Employee $employee): EmployeeResource
    {
        $this->authorizeEmployee($request, $employee);

        return new EmployeeResource($this->loadEmployeeRelations($employee));
    }

    public function update(Request $request, Employee $employee): EmployeeResource
    {
        $this->authorizeEmployee($request, $employee);

        $data = $request->validate([
            'customer_id' => ['sometimes', 'exists:customers,id'],
            'title_id' => ['nullable', 'exists:titles,id'],
            'nationality_id' => ['nullable', 'exists:nationalities,id'],
            'full_name' => ['sometimes', 'string', 'max:255'],
            'passport_number' => ['nullable', 'string', 'max:50'],
            'passport_expiry' => ['nullable', 'date'],
            'ktp_number' => ['nullable', 'string', 'max:20'],
            'birthdate' => ['nullable', 'date'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'ticket_name_format' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        if (isset($data['customer_id'])) {
            $customer = Customer::findOrFail($data['customer_id']);
            $this->authorizeCustomer($request, $customer);
        }

        $employee->update($data);

        return new EmployeeResource($this->loadEmployeeRelations($employee->fresh()));
    }

    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        $this->authorizeEmployee($request, $employee);

        $employee->delete();

        return response()->json(['message' => 'Pegawai berhasil dihapus.']);
    }

    private function loadEmployeeRelations(Employee $employee): Employee
    {
        return $employee->load([
            'title:id,name',
            'nationality:id,name',
            'contact:id,employee_id,is_primary,name,phone,email',
            'customer:id,name,branch_id',
            'customer.branch:id,code,name',
        ]);
    }

    private function authorizeCustomer(Request $request, Customer $customer): void
    {
        $user = $request->user();

        if ($user && ! $user->hasFullBranchAccess() && ! in_array($customer->branch_id, $user->allowedBranchIds(), true)) {
            abort(403, 'Anda tidak memiliki akses ke pelanggan corporate ini.');
        }
    }

    private function authorizeEmployee(Request $request, Employee $employee): void
    {
        $employee->loadMissing('customer');

        if (! $employee->customer) {
            abort(404, 'Pelanggan corporate pegawai ini tidak ditemukan.');
        }

        $this->authorizeCustomer($request, $employee->customer);
    }
}
