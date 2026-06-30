<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->with(['roles', 'branches'])
            ->when(
                $request->user()->hasRole(Roles::ADMIN) && ! $request->user()->hasRole(Roles::SUPERADMIN),
                fn ($query) => $query->whereDoesntHave('roles', fn ($q) => $q->where('name', Roles::SUPERADMIN))
            )
            ->orderBy('name')
            ->get();

        return UserResource::collection($users);
    }

    public function store(Request $request): UserResource|JsonResponse
    {
        $data = $this->validateUser($request);

        if ($response = $this->guardRoleAssignment($request, $data['role'])) {
            return $response;
        }

        if ($response = $this->guardBranchAssignment($data['role'], $data['branch_ids'] ?? [])) {
            return $response;
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => $data['is_active'] ?? true,
        ]);

        $user->syncRoles($this->resolveRole($data['role']));
        $user->branches()->sync($data['branch_ids'] ?? []);

        return new UserResource($user->load(['roles', 'branches']));
    }

    public function show(User $user): UserResource|JsonResponse
    {
        if ($response = $this->guardUserAccess(request(), $user)) {
            return $response;
        }

        return new UserResource($user->load(['roles', 'branches']));
    }

    public function update(Request $request, User $user): UserResource|JsonResponse
    {
        if ($response = $this->guardUserAccess($request, $user)) {
            return $response;
        }

        $data = $this->validateUser($request, $user, false);

        if (isset($data['role']) && ($response = $this->guardRoleAssignment($request, $data['role']))) {
            return $response;
        }

        $role = $data['role'] ?? $user->roles->first()?->name;

        if ($response = $this->guardBranchAssignment($role, $data['branch_ids'] ?? $user->branches->pluck('id')->all())) {
            return $response;
        }

        $user->fill(collect($data)->only(['name', 'email', 'is_active'])->filter(fn ($v) => $v !== null)->all());

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        if (isset($data['role'])) {
            $user->syncRoles($this->resolveRole($data['role']));
        }

        if (array_key_exists('branch_ids', $data)) {
            $user->branches()->sync($data['branch_ids'] ?? []);
        }

        return new UserResource($user->load(['roles', 'branches']));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($response = $this->guardUserAccess($request, $user)) {
            return $response;
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Tidak dapat menghapus akun sendiri.'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'User berhasil dihapus.']);
    }

    private function validateUser(Request $request, ?User $user = null, bool $requirePassword = true): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($requirePassword) {
            $rules['password'] = ['required', 'string', 'min:8'];
        } else {
            $rules['password'] = ['nullable', 'string', 'min:8'];
            $rules['name'] = ['sometimes', 'string', 'max:255'];
            $rules['email'] = ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)];
            $rules['role'] = ['sometimes', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')];
        }

        return $request->validate($rules);
    }

    private function guardRoleAssignment(Request $request, string $role): ?JsonResponse
    {
        if ($request->user()->hasRole(Roles::ADMIN) && ! $request->user()->hasRole(Roles::SUPERADMIN)) {
            if ($role === Roles::SUPERADMIN) {
                return response()->json(['message' => 'Admin tidak dapat menetapkan role superadmin.'], 403);
            }
        }

        return null;
    }

    private function guardBranchAssignment(?string $role, array $branchIds): ?JsonResponse
    {
        $roleModel = $role ? Role::query()->where('name', $role)->first() : null;

        if ($roleModel?->requires_branch && empty($branchIds)) {
            return response()->json([
                'message' => 'Role ini wajib memiliki minimal satu cabang.',
            ], 422);
        }

        return null;
    }

    private function guardUserAccess(Request $request, User $user): ?JsonResponse
    {
        if ($request->user()->hasRole(Roles::ADMIN) && ! $request->user()->hasRole(Roles::SUPERADMIN)) {
            if ($user->hasRole(Roles::SUPERADMIN)) {
                return response()->json(['message' => 'Admin tidak dapat mengelola user superadmin.'], 403);
            }
        }

        return null;
    }

    private function resolveRole(string $roleName): Role
    {
        return Role::query()
            ->where('name', $roleName)
            ->where('guard_name', 'web')
            ->firstOrFail();
    }
}
