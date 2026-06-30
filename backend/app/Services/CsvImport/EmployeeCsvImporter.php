<?php

namespace App\Services\CsvImport;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAlias;
use App\Models\Employee;
use App\Models\Nationality;
use App\Models\Title;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeCsvImporter
{
    public const FORMAT_TEMPLATE = 'template';

    public const FORMAT_LEGACY = 'legacy';

    public function import(string $filePath, int $branchId, ?string $fallbackCustomerName = null): array
    {
        $branch = Branch::query()->findOrFail($branchId);
        $rows = Excel::toCollection(null, $filePath)->first() ?? collect();

        $stats = [
            'employees' => 0,
            'created' => 0,
            'updated' => 0,
            'customers' => 0,
        ];
        $errors = [];
        $imported = 0;
        $skipped = 0;
        $customerCache = [];

        if ($templateStructure = $this->findTemplateStructure($rows)) {
            $format = self::FORMAT_TEMPLATE;
            $columnIndex = $this->columnIndexFromManifest();

            foreach ($rows->slice($templateStructure['dataStartRow']) as $index => $row) {
                $lineNo = $templateStructure['dataStartRow'] + $index + 1;
                $data = $this->rowToAssoc($row, $columnIndex);

                if ($this->isEmptyImportRow($data, ['branch_code', 'customer_name', 'full_name'])) {
                    continue;
                }

                $rowBranchCode = strtoupper(trim((string) ($data['branch_code'] ?? '')));
                if ($rowBranchCode !== $branch->code) {
                    $errors[] = "Baris {$lineNo}: branch_code harus {$branch->code}.";
                    $skipped++;

                    continue;
                }

                try {
                    $customer = $this->resolveCachedCustomer(
                        $customerCache,
                        $branch->id,
                        (string) ($data['customer_name'] ?? ''),
                        $stats
                    );
                    $result = $this->upsertEmployee($customer, $data);
                    $stats['employees']++;
                    $result === 'created' ? $stats['created']++ : $stats['updated']++;
                    $imported++;
                } catch (\Throwable $e) {
                    $skipped++;
                    $errors[] = "Baris {$lineNo}: ".$e->getMessage();
                }
            }
        } elseif ($legacyHeader = $this->findLegacyHeaderIndex($rows)) {
            $format = self::FORMAT_LEGACY;
            $customerName = trim($fallbackCustomerName ?? '') ?: $this->extractLegacyCustomerName($rows);

            if ($customerName === '') {
                throw new \RuntimeException(
                    'Nama corporate tidak ditemukan. Isi field Nama Corporate pada form import atau gunakan template baru.'
                );
            }

            $customer = $this->resolveCachedCustomer($customerCache, $branch->id, $customerName, $stats);

            foreach ($rows->slice($legacyHeader + 1) as $index => $row) {
                $lineNo = $legacyHeader + $index + 2;
                $fullName = trim((string) ($row[2] ?? ''));

                if ($fullName === '') {
                    continue;
                }

                $data = [
                    'title' => (string) ($row[1] ?? ''),
                    'full_name' => $fullName,
                    'nationality' => (string) ($row[3] ?? ''),
                    'passport_number' => (string) ($row[4] ?? ''),
                    'passport_expiry' => (string) ($row[5] ?? ''),
                    'ktp_number' => (string) ($row[6] ?? ''),
                    'birthdate' => (string) ($row[7] ?? ''),
                    'mobile' => (string) ($row[8] ?? ''),
                    'email' => (string) ($row[9] ?? ''),
                    'ticket_name_format' => (string) ($row[10] ?? ''),
                ];

                try {
                    $result = $this->upsertEmployee($customer, $data);
                    $stats['employees']++;
                    $result === 'created' ? $stats['created']++ : $stats['updated']++;
                    $imported++;
                } catch (\Throwable $e) {
                    $skipped++;
                    $errors[] = "Baris {$lineNo}: ".$e->getMessage();
                }
            }
        } else {
            throw new \RuntimeException('Format file tidak dikenali. Unduh template Data Employee terbaru.');
        }

        return [
            'format' => $format,
            'branch_code' => $branch->code,
            'customer_name' => $fallbackCustomerName,
            'imported' => $imported,
            'skipped' => $skipped,
            'stats' => $stats,
            'errors' => $errors,
        ];
    }

    /** @return array{dataStartRow: int}|null */
    private function findTemplateStructure(Collection $rows): ?array
    {
        foreach ($rows as $index => $row) {
            $first = trim((string) ($row[0] ?? ''));
            $second = trim((string) ($row[1] ?? ''));

            if (strcasecmp($first, 'Cabang') === 0 && strcasecmp($second, 'Nama Corporate') === 0) {
                return ['dataStartRow' => $index + 1];
            }
        }

        return null;
    }

    private function findLegacyHeaderIndex(Collection $rows): ?int
    {
        foreach ($rows as $index => $row) {
            $firstCol = strtoupper(trim((string) ($row[0] ?? '')));

            if ($firstCol === 'NO.' || $firstCol === 'NO') {
                return $index;
            }
        }

        return null;
    }

    private function extractLegacyCustomerName(Collection $rows): string
    {
        foreach ($rows->take(5) as $row) {
            $text = trim((string) ($row[0] ?? ''));

            if (preg_match('/Employee\s+List\s*-\s*(.+)$/iu', $text, $matches)) {
                return trim($matches[1]);
            }
        }

        return '';
    }

    /** @return array<string, int> */
    private function columnIndexFromManifest(): array
    {
        $map = [];
        foreach (EmployeeImportColumnManifest::columns() as $index => $column) {
            $map[$column['key']] = $index;
        }

        return $map;
    }

    /** @param  array<string, int>  $columnIndex */
    private function rowToAssoc(Collection $row, array $columnIndex): array
    {
        $data = [];
        foreach ($columnIndex as $key => $index) {
            $data[$key] = trim((string) ($row[$index] ?? ''));
        }

        return $data;
    }

    /** @param  array<string>  $requiredKeys */
    private function isEmptyImportRow(array $data, array $requiredKeys): bool
    {
        foreach ($requiredKeys as $key) {
            if (trim((string) ($data[$key] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    /** @param  array<string, Customer>  $cache */
    private function resolveCachedCustomer(array &$cache, int $branchId, string $rawName, array &$stats): Customer
    {
        $name = trim($rawName);
        if ($name === '') {
            throw new \InvalidArgumentException('Nama corporate wajib diisi.');
        }

        $key = $branchId.'|'.Str::slug($name);

        if (! isset($cache[$key])) {
            $cache[$key] = $this->resolveCustomer($branchId, $name);
            $stats['customers']++;
        }

        return $cache[$key];
    }

    private function resolveCustomer(int $branchId, string $rawName): Customer
    {
        $parts = array_map('trim', preg_split('/\s*\/\s*/', $rawName));
        $primaryName = $parts[0];
        $slug = Str::slug($primaryName);

        $customer = Customer::updateOrCreate(
            ['branch_id' => $branchId, 'slug' => $slug],
            ['name' => $primaryName, 'status' => 'active']
        );

        foreach (array_slice($parts, 1) as $alias) {
            if ($alias === '') {
                continue;
            }
            CustomerAlias::firstOrCreate([
                'customer_id' => $customer->id,
                'alias_name' => $alias,
            ]);
        }

        return $customer;
    }

    /** @param  array<string, string>  $data */
    private function upsertEmployee(Customer $customer, array $data): string
    {
        $title = $this->resolveTitle($data['title'] ?? '');
        $nationality = $this->resolveNationality($data['nationality'] ?? '');
        $email = $this->nullIfEmpty($data['email'] ?? '');

        $attributes = [
            'customer_id' => $customer->id,
            'title_id' => $title?->id,
            'nationality_id' => $nationality?->id,
            'full_name' => trim($data['full_name'] ?? ''),
            'passport_number' => $this->nullIfEmpty($data['passport_number'] ?? ''),
            'passport_expiry' => $this->parseDate($data['passport_expiry'] ?? ''),
            'ktp_number' => $this->nullIfEmpty($data['ktp_number'] ?? ''),
            'birthdate' => $this->parseDate($data['birthdate'] ?? ''),
            'mobile' => $this->nullIfEmpty($data['mobile'] ?? ''),
            'email' => $email,
            'ticket_name_format' => $this->nullIfEmpty($data['ticket_name_format'] ?? ''),
            'status' => 'active',
        ];

        if ($attributes['full_name'] === '') {
            throw new \InvalidArgumentException('Nama employee wajib diisi.');
        }

        if ($email) {
            $employee = Employee::withTrashed()
                ->where('customer_id', $customer->id)
                ->where('email', $email)
                ->first();

            if ($employee?->trashed()) {
                $employee->restore();
            }

            $created = ! $employee;
            $employee = Employee::updateOrCreate(
                ['customer_id' => $customer->id, 'email' => $email],
                $attributes
            );

            return $created ? 'created' : 'updated';
        }

        $employee = Employee::withTrashed()
            ->where('customer_id', $customer->id)
            ->where('full_name', $attributes['full_name'])
            ->where('passport_number', $attributes['passport_number'])
            ->first();

        if ($employee?->trashed()) {
            $employee->restore();
        }

        $created = ! $employee;

        Employee::updateOrCreate(
            [
                'customer_id' => $customer->id,
                'full_name' => $attributes['full_name'],
                'passport_number' => $attributes['passport_number'],
            ],
            $attributes
        );

        return $created ? 'created' : 'updated';
    }

    private function resolveTitle(string $value): ?Title
    {
        $normalized = strtolower(rtrim(trim($value), '.'));

        if ($normalized === '' || $normalized === '-') {
            return null;
        }

        return Title::firstOrCreate(
            ['code' => $normalized],
            ['name' => ucfirst($normalized).'.']
        );
    }

    private function resolveNationality(string $value): ?Nationality
    {
        $name = trim($value);

        if ($name === '' || $name === '-') {
            return null;
        }

        $normalized = strtolower($name);

        /** @var array<string, string> $aliases */
        $aliases = [
            'indonesia' => 'ID',
            'indonesian' => 'ID',
            'spanish' => 'ES',
            'spain' => 'ES',
        ];

        if (isset($aliases[$normalized])) {
            return Nationality::query()->where('code', $aliases[$normalized])->first();
        }

        $byName = Nationality::query()
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->first();

        if ($byName) {
            return $byName;
        }

        $byLike = Nationality::query()
            ->whereRaw('LOWER(name) LIKE ?', ['%'.$normalized.'%'])
            ->first();

        if ($byLike) {
            return $byLike;
        }

        return Nationality::firstOrCreate(
            ['code' => strtoupper(substr($name, 0, 2))],
            ['name' => ucwords(strtolower($name))]
        );
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '' || $value === '-') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function nullIfEmpty(string $value): ?string
    {
        $value = trim($value);

        return ($value === '' || $value === '-') ? null : $value;
    }
}
