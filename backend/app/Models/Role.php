<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use App\Support\Roles;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'label',
        'description',
        'requires_branch',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'requires_branch' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    /**
     * Users assigned to this role.
     *
     * Spatie's default uses getModelForGuard() which can return null during API requests.
     */
    public function users(): MorphToMany
    {
        return $this->morphedByMany(
            User::class,
            'model',
            config('permission.table_names.model_has_roles'),
            app(PermissionRegistrar::class)->pivotRole,
            config('permission.column_names.model_morph_key'),
        );
    }

    public function isSuperadmin(): bool
    {
        return $this->name === Roles::SUPERADMIN;
    }
}
