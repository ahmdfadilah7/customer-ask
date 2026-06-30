<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->label ?? $this->name,
            'description' => $this->description,
            'requires_branch' => (bool) $this->requires_branch,
            'is_system' => (bool) $this->is_system,
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')->values()),
            'users_count' => $this->whenCounted('users'),
        ];
    }
}
