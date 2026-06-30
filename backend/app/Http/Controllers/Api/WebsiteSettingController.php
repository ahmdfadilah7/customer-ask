<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WebsiteSettingResource;
use App\Models\WebsiteSetting;
use App\Services\WebsiteSettingStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteSettingController extends Controller
{
    public function show(): WebsiteSettingResource
    {
        return new WebsiteSettingResource(WebsiteSetting::current());
    }

    public function update(Request $request, WebsiteSettingStorage $storage): JsonResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
            'meta_author' => ['nullable', 'string', 'max:255'],
            'footer_text' => ['nullable', 'string', 'max:1000'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'logo' => ['nullable', 'file', 'mimes:jpeg,jpg,png,gif,webp,svg', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:jpeg,jpg,png,gif,webp,svg,ico', 'max:512'],
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_favicon' => ['sometimes', 'boolean'],
        ]);

        $settings = WebsiteSetting::current();
        $update = collect($data)->except(['logo', 'favicon', 'remove_logo', 'remove_favicon'])->all();

        if ($request->boolean('remove_logo')) {
            $storage->deleteStoredFile($settings->getRawOriginal('logo_url'));
            $update['logo_url'] = null;
        }

        if ($request->boolean('remove_favicon')) {
            $storage->deleteStoredFile($settings->getRawOriginal('favicon_url'));
            $update['favicon_url'] = null;
        }

        if ($request->hasFile('logo')) {
            $storage->deleteStoredFile($settings->getRawOriginal('logo_url'));
            $update['logo_url'] = $storage->storeUploadedFile($request->file('logo'), 'logo');
        }

        if ($request->hasFile('favicon')) {
            $storage->deleteStoredFile($settings->getRawOriginal('favicon_url'));
            $update['favicon_url'] = $storage->storeUploadedFile($request->file('favicon'), 'favicon');
        }

        $settings->update($update);

        return response()->json([
            'message' => 'Pengaturan website berhasil disimpan.',
            'data' => new WebsiteSettingResource($settings->fresh()),
        ]);
    }
}
