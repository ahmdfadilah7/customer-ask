<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Support\Permissions;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $roles = Role::query()
            ->with('permissions')
            ->withCount('users')
            ->orderByDesc('is_system')
            ->orderBy('label')
            ->orderBy('name')
            ->get();

        return RoleResource::collection($roles);
    }

    public function show(Role $role): RoleResource
    {
        return new RoleResource($role->load('permissions')->loadCount('users'));
    }

    public function permissions(): JsonResponse
    {
        return response()->json([
            'groups' => Permissions::groups(),
            'roles' => Role::query()
                ->orderByDesc('is_system')
                ->orderBy('label')
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role) => [
                    'name' => $role->name,
                    'label' => $role->label ?? $role->name,
                    'description' => $role->description,
                    'requires_branch' => (bool) $role->requires_branch,
                    'is_system' => (bool) $role->is_system,
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): RoleResource
    {
        $data = $this->validateRole($request);

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'requires_branch' => $data['requires_branch'] ?? false,
            'is_system' => false,
        ]);

        $role->syncPermissions($data['permissions']);

        return new RoleResource($role->load('permissions')->loadCount('users'));
    }

    public function update(Request $request, Role $role): RoleResource
    {
        $data = $this->validateRole($request, $role);

        $role->fill([
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'requires_branch' => $data['requires_branch'] ?? false,
        ]);

        if (! $role->isSuperadmin() && isset($data['name'])) {
            $role->name = $data['name'];
        }

        $role->save();
        $role->syncPermissions($data['permissions']);

        return new RoleResource($role->load('permissions')->loadCount('users'));
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->isSuperadmin()) {
            throw ValidationException::withMessages([
                'role' => 'Role superadmin tidak dapat dihapus.',
            ]);
        }

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => 'Role masih digunakan oleh user. Pindahkan user terlebih dahulu.',
            ]);
        }

        $role->delete();

        return response()->json(['message' => 'Role berhasil dihapus.']);
    }

    private function validateRole(Request $request, ?Role $role = null): array
    {
        $nameRule = ['required', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/'];

        if ($role && $role->isSuperadmin()) {
            $nameRule = ['sometimes', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/'];
        } elseif ($role) {
            $nameRule[] = Rule::unique('roles', 'name')->ignore($role->id);
        } else {
            $nameRule[] = Rule::unique('roles', 'name');
        }

        return $request->validate([
            'name' => $nameRule,
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'requires_branch' => ['nullable', 'boolean'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(Permissions::all())],
        ]);
    }
}
