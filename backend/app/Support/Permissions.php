<?php

namespace App\Support;

final class Permissions
{
    public static function groups(): array
    {
        return [
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'permissions' => [
                    ['name' => 'dashboard-view', 'label' => 'Lihat Dashboard', 'action' => 'view'],
                ],
            ],
            [
                'key' => 'cabang',
                'label' => 'Cabang',
                'permissions' => self::crud('cabang'),
            ],
            [
                'key' => 'kebangsaan',
                'label' => 'Kebangsaan',
                'permissions' => self::crud('kebangsaan'),
            ],
            [
                'key' => 'gelar',
                'label' => 'Gelar',
                'permissions' => self::crud('gelar'),
            ],
            [
                'key' => 'scope-wilayah',
                'label' => 'Scope Wilayah',
                'permissions' => self::crud('scope-wilayah'),
            ],
            [
                'key' => 'maskapai',
                'label' => 'Maskapai',
                'permissions' => self::crud('maskapai'),
            ],
            [
                'key' => 'template-pesan',
                'label' => 'Template Pesan',
                'permissions' => self::crud('template-pesan'),
            ],
            [
                'key' => 'corporate',
                'label' => 'Corporate',
                'permissions' => self::crud('corporate'),
            ],
            [
                'key' => 'pegawai',
                'label' => 'Pegawai',
                'permissions' => self::crud('pegawai'),
            ],
            [
                'key' => 'import',
                'label' => 'Import Data',
                'permissions' => [
                    ['name' => 'import-corporate', 'label' => 'Import Corporate', 'action' => 'create'],
                    ['name' => 'import-service', 'label' => 'Import Service', 'action' => 'create'],
                    ['name' => 'import-pegawai', 'label' => 'Import Pegawai', 'action' => 'create'],
                ],
            ],
            [
                'key' => 'whatsapp',
                'label' => 'WhatsApp',
                'permissions' => [
                    ['name' => 'whatsapp-kirim', 'label' => 'Kirim Pesan', 'action' => 'create'],
                ],
            ],
            [
                'key' => 'user',
                'label' => 'User',
                'permissions' => self::crud('user'),
            ],
            [
                'key' => 'role',
                'label' => 'Role & Permission',
                'permissions' => self::crud('role'),
            ],
            [
                'key' => 'setting-website',
                'label' => 'Setting Website',
                'permissions' => [
                    ['name' => 'setting-website-view', 'label' => 'Lihat', 'action' => 'view'],
                    ['name' => 'setting-website-update', 'label' => 'Kelola', 'action' => 'update'],
                ],
            ],
        ];
    }

  /**
   * @return array<int, array{name: string, label: string, action: string}>
   */
    private static function crud(string $feature): array
    {
        return [
            ['name' => "{$feature}-view", 'label' => 'Lihat', 'action' => 'view'],
            ['name' => "{$feature}-create", 'label' => 'Tambah', 'action' => 'create'],
            ['name' => "{$feature}-update", 'label' => 'Edit', 'action' => 'update'],
            ['name' => "{$feature}-delete", 'label' => 'Hapus', 'action' => 'delete'],
        ];
    }

    public static function all(): array
    {
        return collect(self::groups())
            ->flatMap(fn (array $group) => collect($group['permissions'])->pluck('name'))
            ->values()
            ->all();
    }

    public static function labels(): array
    {
        $labels = [];

        foreach (self::groups() as $group) {
            foreach ($group['permissions'] as $permission) {
                $labels[$permission['name']] = $group['label'].' — '.$permission['label'];
            }
        }

        return $labels;
    }

    public static function forRole(string $role): array
    {
        return match ($role) {
            Roles::SUPERADMIN => self::all(),
            Roles::ADMIN => array_values(array_filter(
                self::all(),
                fn (string $name) => ! str_starts_with($name, 'role-'),
            )),
            Roles::MARKETING => [
                'dashboard-view',
                'corporate-view',
                'corporate-create',
                'corporate-update',
                'pegawai-view',
                'pegawai-create',
                'pegawai-update',
                'import-corporate',
                'import-service',
                'import-pegawai',
                'template-pesan-view',
                'whatsapp-kirim',
            ],
            Roles::TICKETING => [
                'dashboard-view',
                'corporate-view',
                'pegawai-view',
                'template-pesan-view',
            ],
            default => [],
        };
    }

    public static function managePermissions(string $feature): array
    {
        return [
            "{$feature}-create",
            "{$feature}-update",
            "{$feature}-delete",
        ];
    }

    /** Permissions that may read branch list as reference (filter dropdowns, import, dll.). */
    public static function branchReferenceMiddleware(): string
    {
        return 'role_or_permission:'.implode('|', [
            'cabang-view',
            'corporate-view',
            'pegawai-view',
            'import-corporate',
            'import-service',
            'import-pegawai',
            'user-view',
        ]);
    }

    public static function titleReferenceMiddleware(): string
    {
        return 'role_or_permission:'.implode('|', [
            'gelar-view',
            'pegawai-view',
            'pegawai-create',
            'pegawai-update',
        ]);
    }

    public static function nationalityReferenceMiddleware(): string
    {
        return 'role_or_permission:'.implode('|', [
            'kebangsaan-view',
            'pegawai-view',
            'pegawai-create',
            'pegawai-update',
        ]);
    }
}
