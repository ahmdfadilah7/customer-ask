<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BranchController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $branches = Branch::query()
            ->when(
                $user && ! $user->hasFullBranchAccess(),
                fn ($query) => $query->whereIn('id', $user->allowedBranchIds()),
            )
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return BranchResource::collection($branches);
    }

    public function store(Request $request): BranchResource
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:branches,code'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $branch = Branch::create([
            ...$data,
            'status' => $data['status'] ?? 'active',
        ]);

        return new BranchResource($branch);
    }

    public function show(Branch $branch): BranchResource
    {
        return new BranchResource($branch->loadCount('users'));
    }

    public function update(Request $request, Branch $branch): BranchResource
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:20', 'unique:branches,code,'.$branch->id],
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        $branch->update($data);

        return new BranchResource($branch->loadCount('users'));
    }

    public function destroy(Branch $branch): JsonResponse
    {
        if ($branch->users()->exists()) {
            return response()->json([
                'message' => 'Cabang tidak dapat dihapus karena masih digunakan oleh user.',
            ], 422);
        }

        $branch->delete();

        return response()->json(['message' => 'Cabang berhasil dihapus.']);
    }
}
