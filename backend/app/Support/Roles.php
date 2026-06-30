<?php

namespace App\Support;

final class Roles
{
    public const SUPERADMIN = 'superadmin';
    public const ADMIN = 'admin';
    public const MARKETING = 'marketing';
    public const TICKETING = 'tiketing';

    public static function all(): array
    {
        return [
            self::SUPERADMIN,
            self::ADMIN,
            self::MARKETING,
            self::TICKETING,
        ];
    }

    public static function labels(): array
    {
        return [
            self::SUPERADMIN => 'Super Admin',
            self::ADMIN => 'Admin',
            self::MARKETING => 'Marketing',
            self::TICKETING => 'Ticketing',
        ];
    }

    public static function descriptions(): array
    {
        return [
            self::SUPERADMIN => 'Akses penuh ke seluruh sistem termasuk role management.',
            self::ADMIN => 'Akses semua menu kecuali role management.',
            self::MARKETING => 'Kelola data pelanggan corporate dan service fee sesuai cabang yang ditugaskan.',
            self::TICKETING => 'Hanya melihat data pelanggan corporate, pegawai, dan service fee sesuai cabang yang diizinkan.',
        ];
    }

    public static function requiresBranch(): array
    {
        return [self::MARKETING, self::TICKETING];
    }
}
