<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegionScopeResource;
use App\Models\RegionScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RegionScopeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $scopes = RegionScope::query()
            ->withCount('airlines')
            ->orderBy('name')
            ->get();

        return RegionScopeResource::collection($scopes);
    }

    public function store(Request $request): RegionScopeResource
    {
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code', ''))),
        ]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:region_scopes,code'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $scope = RegionScope::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'status' => $data['status'] ?? 'active',
        ]);

        return new RegionScopeResource($scope);
    }

    public function show(RegionScope $regionScope): RegionScopeResource
    {
        return new RegionScopeResource($regionScope->loadCount('airlines'));
    }

    public function update(Request $request, RegionScope $regionScope): RegionScopeResource
    {
        if ($request->has('code')) {
            $request->merge([
                'code' => strtoupper(trim((string) $request->input('code', ''))),
            ]);
        }

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:30', 'unique:region_scopes,code,'.$regionScope->id],
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        $regionScope->update($data);

        return new RegionScopeResource($regionScope->loadCount('airlines'));
    }

    public function destroy(RegionScope $regionScope): JsonResponse
    {
        if ($regionScope->airlines()->exists()) {
            return response()->json([
                'message' => 'Scope wilayah tidak dapat dihapus karena masih digunakan oleh airline.',
            ], 422);
        }

        $regionScope->delete();

        return response()->json(['message' => 'Scope wilayah berhasil dihapus.']);
    }
}
