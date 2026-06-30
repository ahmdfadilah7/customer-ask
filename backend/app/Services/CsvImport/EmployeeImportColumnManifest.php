<?php

namespace App\Services\CsvImport;

class EmployeeImportColumnManifest
{
    /** @return array<int, array<string, mixed>> */
    public static function columns(): array
    {
        return [
            self::col('branch_code', 'Cabang', 'wajib', ['row2' => 'Identitas']),
            self::col('customer_name', 'Nama Corporate', 'wajib', ['width' => 30, 'row2' => 'Identitas']),
            self::col('title', 'Title', 'employee', ['width' => 10, 'row2' => 'Data Employee']),
            self::col('full_name', 'Name', 'employee', ['width' => 28, 'row2' => 'Data Employee']),
            self::col('nationality', 'Nationality', 'employee', ['width' => 14, 'row2' => 'Data Employee']),
            self::col('passport_number', 'Passport No.', 'employee', ['width' => 14, 'row2' => 'Data Employee']),
            self::col('passport_expiry', 'Passport Exp Date', 'employee', ['width' => 16, 'row2' => 'Data Employee']),
            self::col('ktp_number', 'KTP No.', 'employee', ['width' => 18, 'row2' => 'Data Employee']),
            self::col('birthdate', 'Birthdate', 'employee', ['width' => 14, 'row2' => 'Data Employee']),
            self::col('mobile', 'Mobile No.', 'employee', ['width' => 16, 'row2' => 'Data Employee']),
            self::col('email', 'Email', 'employee', ['width' => 24, 'row2' => 'Data Employee']),
            self::col('ticket_name_format', 'Reservation/Ticket Name', 'employee', [
                'width' => 30,
                'row2' => 'Data Employee',
            ]),
        ];
    }

    /** @param  array<string, mixed>  $extra */
    private static function col(string $key, string $label, string $group, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'label' => $label,
            'group' => $group,
            'width' => 13,
            'row2' => $label,
        ], $extra);
    }
}
