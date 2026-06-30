<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MessageTemplateResource;
use App\Models\MessageTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MessageTemplateController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = MessageTemplate::query()->with('creator')->orderBy('name');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return MessageTemplateResource::collection($query->get());
    }

    public function store(Request $request): MessageTemplateResource
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $template = MessageTemplate::create([
            ...$data,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        return new MessageTemplateResource($template->load('creator'));
    }

    public function show(MessageTemplate $messageTemplate): MessageTemplateResource
    {
        return new MessageTemplateResource($messageTemplate->load('creator'));
    }

    public function update(Request $request, MessageTemplate $messageTemplate): MessageTemplateResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $messageTemplate->update($data);

        return new MessageTemplateResource($messageTemplate->load('creator'));
    }

    public function destroy(MessageTemplate $messageTemplate): JsonResponse
    {
        $messageTemplate->delete();

        return response()->json(['message' => 'Template pesan berhasil dihapus.']);
    }

    public function placeholders(): JsonResponse
    {
        return response()->json([
            'data' => collect(\App\Support\MessageTemplateRenderer::placeholders())
                ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                ->values(),
        ]);
    }
}
