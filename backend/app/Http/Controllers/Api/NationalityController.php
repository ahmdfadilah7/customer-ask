<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NationalityResource;
use App\Models\Nationality;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NationalityController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $nationalities = Nationality::query()->orderBy('name')->get();

        return NationalityResource::collection($nationalities);
    }

    public function store(Request $request): NationalityResource
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:nationalities,code'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $nationality = Nationality::create([
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
        ]);

        return new NationalityResource($nationality);
    }

    public function show(Nationality $nationality): NationalityResource
    {
        return new NationalityResource($nationality);
    }

    public function update(Request $request, Nationality $nationality): NationalityResource
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:10', 'unique:nationalities,code,'.$nationality->id],
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $nationality->update($data);

        return new NationalityResource($nationality);
    }

    public function destroy(Nationality $nationality): JsonResponse
    {
        $nationality->delete();

        return response()->json(['message' => 'Nationality berhasil dihapus.']);
    }
}
