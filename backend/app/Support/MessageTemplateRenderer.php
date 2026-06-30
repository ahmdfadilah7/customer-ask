<?php

namespace App\Support;

use App\Models\Employee;

final class MessageTemplateRenderer
{
    public static function placeholders(): array
    {
        return [
            '{name}' => 'Nama lengkap pegawai',
            '{corporate}' => 'Nama pelanggan corporate',
            '{cabang}' => 'Kode cabang Astrindo',
            '{mobile}' => 'Nomor mobile pegawai',
            '{email}' => 'Email pegawai',
            '{title}' => 'Title/gelar pegawai',
        ];
    }

    public static function render(string $template, Employee $employee): string
    {
        $employee->loadMissing(['customer.branch', 'title']);

        $replacements = [
            '{name}' => $employee->full_name ?? '',
            '{corporate}' => $employee->customer?->name ?? '',
            '{cabang}' => $employee->customer?->branch?->code ?? '',
            '{mobile}' => $employee->mobile ?? '',
            '{email}' => $employee->email ?? '',
            '{title}' => $employee->title?->name ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}
