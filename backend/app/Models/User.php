<?php

namespace App\Models;

use App\Support\Roles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Roles & permissions are registered under the web guard (see PermissionSeeder).
     * API auth uses Sanctum, but Spatie must still resolve roles on guard "web".
     */
    protected $guard_name = 'web';

    public function guardName(): string
    {
        return 'web';
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    public function hasFullBranchAccess(): bool
    {
        return $this->hasAnyRole([Roles::SUPERADMIN, Roles::ADMIN]);
    }

    public function requiresBranchScope(): bool
    {
        return $this->hasAnyRole(Roles::requiresBranch());
    }

    /** @return list<int> */
    public function allowedBranchIds(): array
    {
        if ($this->hasFullBranchAccess()) {
            return Branch::query()->pluck('id')->all();
        }

        return $this->branches()->pluck('branches.id')->all();
    }
}
