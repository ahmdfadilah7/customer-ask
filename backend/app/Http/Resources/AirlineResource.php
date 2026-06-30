<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AirlineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'status' => $this->status,
            'region_scopes' => RegionScopeResource::collection($this->whenLoaded('regionScopes')),
            'region_scope_ids' => $this->whenLoaded('regionScopes', fn () => $this->regionScopes->pluck('id')->values()->all()),
            'created_at' => $this->created_at,
        ];
    }
}
