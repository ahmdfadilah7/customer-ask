<?php

namespace App\Models;

use App\Services\WebsiteSettingStorage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class WebsiteSetting extends Model
{
    protected $fillable = [
        'site_name',
        'tagline',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'meta_author',
        'logo_url',
        'favicon_url',
        'footer_text',
        'contact_email',
        'contact_phone',
    ];

    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => app(WebsiteSettingStorage::class)->resolveUrl($value),
        );
    }

    protected function faviconUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => app(WebsiteSettingStorage::class)->resolveUrl($value),
        );
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'site_name' => config('app.name', 'Astrindo Travel Services'),
            'tagline' => 'Portal Corporate & Pegawai',
            'meta_title' => config('app.name', 'Astrindo Travel Services'),
            'meta_description' => 'Portal internal Astrindo Travel Services untuk mengelola corporate, pegawai, dan service fee.',
            'meta_keywords' => 'astrindo, corporate, pegawai, travel',
            'meta_author' => 'Astrindo Travel Services',
        ]);
    }
}
