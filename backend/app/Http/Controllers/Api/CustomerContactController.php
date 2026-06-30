<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerContactResource;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerContactController extends Controller
{
    public function index(Request $request, Customer $customer): AnonymousResourceCollection
    {
        $this->authorizeCustomerAccess($request, $customer);

        $contacts = $customer->contacts()
            ->with('employee:id,full_name')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();

        return CustomerContactResource::collection($contacts);
    }

    public function store(Request $request, Customer $customer): CustomerContactResource
    {
        $this->authorizeCustomerAccess($request, $customer);

        $data = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        $payload = $this->buildContactPayload($customer, $data);
        $isPrimary = $payload['is_primary'] ?? ! $customer->contacts()->exists();

        if ($isPrimary) {
            $customer->contacts()->update(['is_primary' => false]);
        }

        $contact = $customer->contacts()->create([
            ...$payload,
            'is_primary' => $isPrimary,
        ]);

        return new CustomerContactResource($contact->load('employee:id,full_name'));
    }

    public function update(Request $request, Customer $customer, CustomerContact $contact): CustomerContactResource
    {
        $this->authorizeCustomerAccess($request, $customer);
        $this->ensureContactBelongsToCustomer($customer, $contact);

        $data = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        $payload = $this->buildContactPayload($customer, $data, $contact);

        if (! empty($payload['is_primary'])) {
            $customer->contacts()
                ->where('id', '!=', $contact->id)
                ->update(['is_primary' => false]);
        }

        $contact->update($payload);

        return new CustomerContactResource($contact->fresh()->load('employee:id,full_name'));
    }

    public function destroy(Request $request, Customer $customer, CustomerContact $contact): JsonResponse
    {
        $this->authorizeCustomerAccess($request, $customer);
        $this->ensureContactBelongsToCustomer($customer, $contact);

        $wasPrimary = $contact->is_primary;
        $contact->delete();

        if ($wasPrimary) {
            $next = $customer->contacts()->orderBy('name')->first();
            $next?->update(['is_primary' => true]);
        }

        return response()->json(['message' => 'PIC berhasil dihapus.']);
    }

    private function authorizeCustomerAccess(Request $request, Customer $customer): void
    {
        $user = $request->user();

        if ($user && ! $user->hasFullBranchAccess() && ! in_array($customer->branch_id, $user->allowedBranchIds(), true)) {
            abort(403, 'Anda tidak memiliki akses ke corporate ini.');
        }
    }

    private function ensureContactBelongsToCustomer(Customer $customer, CustomerContact $contact): void
    {
        if ($contact->customer_id !== $customer->id) {
            abort(404);
        }
    }

    /** @param  array<string, mixed>  $data */
    private function buildContactPayload(Customer $customer, array $data, ?CustomerContact $existing = null): array
    {
        $employeeId = array_key_exists('employee_id', $data)
            ? ($data['employee_id'] ?: null)
            : $existing?->employee_id;

        $employee = null;
        if ($employeeId) {
            $employee = Employee::query()
                ->where('customer_id', $customer->id)
                ->find($employeeId);

            if (! $employee) {
                abort(422, 'Employee tidak ditemukan pada corporate ini.');
            }
        }

        $name = isset($data['name']) ? trim((string) $data['name']) : ($existing?->name ?? $employee?->full_name ?? '');
        $phone = array_key_exists('phone', $data) ? $data['phone'] : ($existing?->phone ?? $employee?->mobile);
        $email = array_key_exists('email', $data) ? $data['email'] : ($existing?->email ?? $employee?->email);

        if ($name === '') {
            abort(422, 'Nama PIC wajib diisi.');
        }

        $payload = [
            'employee_id' => $employee?->id,
            'name' => $name,
            'phone' => $phone ?: null,
            'email' => $email ?: null,
        ];

        if (array_key_exists('is_primary', $data)) {
            $payload['is_primary'] = (bool) $data['is_primary'];
        }

        return $payload;
    }
}
