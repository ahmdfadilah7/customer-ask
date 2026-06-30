<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()->all()),
            'role_label' => $this->whenLoaded('roles', fn () => $this->roles->first()?->label ?? $this->roles->first()?->name),
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
            'branches' => BranchResource::collection($this->whenLoaded('branches')),
            'has_full_branch_access' => $this->hasFullBranchAccess(),
            'created_at' => $this->created_at,
        ];
    }
}
