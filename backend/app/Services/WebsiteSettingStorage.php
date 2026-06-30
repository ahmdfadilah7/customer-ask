<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WebsiteSettingStorage
{
    public const DIRECTORY = 'website-settings';

    public function storeUploadedFile(UploadedFile $file, string $prefix): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';
        $filename = $prefix.'-'.now()->format('YmdHis').'.'.strtolower($extension);

        return $file->storeAs(self::DIRECTORY, $filename, 'public');
    }

    public function deleteStoredFile(?string $path): void
    {
        if (! $path || $this->isExternalUrl($path)) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public function resolveUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if ($this->isExternalUrl($path)) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    public function isExternalUrl(string $path): bool
    {
        return Str::startsWith($path, ['http://', 'https://', '//']);
    }
}
