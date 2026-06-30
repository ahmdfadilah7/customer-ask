<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AirlineResource;
use App\Models\Airline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AirlineController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $airlines = Airline::query()
            ->with('regionScopes')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return AirlineResource::collection($airlines);
    }

    public function store(Request $request): AirlineResource
    {
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code', ''))),
        ]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:airlines,code'],
            'name' => ['required', 'string', 'max:255'],
            'region_scope_ids' => ['required', 'array', 'min:1'],
            'region_scope_ids.*' => ['integer', 'exists:region_scopes,id'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $airline = Airline::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? Airline::nextSortOrder(),
            'status' => $data['status'] ?? 'active',
        ]);

        $airline->regionScopes()->sync($data['region_scope_ids']);
        Airline::normalizeSortOrders();

        return new AirlineResource($airline->fresh()->load('regionScopes'));
    }

    public function show(Airline $airline): AirlineResource
    {
        return new AirlineResource($airline->load('regionScopes'));
    }

    public function update(Request $request, Airline $airline): AirlineResource
    {
        if ($request->has('code')) {
            $request->merge([
                'code' => strtoupper(trim((string) $request->input('code', ''))),
            ]);
        }

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', 'unique:airlines,code,'.$airline->id],
            'name' => ['sometimes', 'string', 'max:255'],
            'region_scope_ids' => ['sometimes', 'array', 'min:1'],
            'region_scope_ids.*' => ['integer', 'exists:region_scopes,id'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        $airline->update(collect($data)->except('region_scope_ids')->filter(fn ($v) => $v !== null)->all());

        if (array_key_exists('region_scope_ids', $data)) {
            $airline->regionScopes()->sync($data['region_scope_ids']);
        }

        if (array_key_exists('sort_order', $data)) {
            Airline::normalizeSortOrders();
        }

        return new AirlineResource($airline->fresh()->load('regionScopes'));
    }

    public function destroy(Airline $airline): JsonResponse
    {
        $airline->delete();

        return response()->json(['message' => 'Airline berhasil dihapus.']);
    }
}
