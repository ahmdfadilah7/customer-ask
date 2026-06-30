<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'site_name' => $this->site_name,
            'tagline' => $this->tagline,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'meta_author' => $this->meta_author,
            'logo_url' => $this->logo_url,
            'favicon_url' => $this->favicon_url,
            'footer_text' => $this->footer_text,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'updated_at' => $this->updated_at,
        ];
    }
}
