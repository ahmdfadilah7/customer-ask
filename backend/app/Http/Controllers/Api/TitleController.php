<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TitleResource;
use App\Models\Title;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TitleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $titles = Title::query()->orderBy('name')->get();

        return TitleResource::collection($titles);
    }

    public function store(Request $request): TitleResource
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:titles,code'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $title = Title::create([
            'code' => strtolower($data['code']),
            'name' => $data['name'],
        ]);

        return new TitleResource($title);
    }

    public function show(Title $title): TitleResource
    {
        return new TitleResource($title);
    }

    public function update(Request $request, Title $title): TitleResource
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', 'unique:titles,code,'.$title->id],
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        if (isset($data['code'])) {
            $data['code'] = strtolower($data['code']);
        }

        $title->update($data);

        return new TitleResource($title);
    }

    public function destroy(Title $title): JsonResponse
    {
        $title->delete();

        return response()->json(['message' => 'Title berhasil dihapus.']);
    }
}
