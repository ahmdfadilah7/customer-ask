<?php

namespace App\Services\CsvImport;

use App\Models\Airline;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAirlineCode;
use App\Models\CustomerAlias;
use App\Models\CustomerContact;
use App\Models\CustomerEntity;
use App\Models\CustomerNote;
use App\Models\CustomerPricingRule;
use App\Models\PricingVersion;
use App\Models\RegionScope;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\PricingParser;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class CorporateMasterCsvImporter
{
    public function __construct(private PricingParser $parser) {}

    public function import(
        string $filePath,
        int $branchId,
        string $importType,
        ?string $versionName = null,
        ?int $userId = null,
        ?User $actor = null,
    ): array {
        $branch = Branch::findOrFail($branchId);

        if (! in_array($importType, [
            CorporateImportColumnManifest::TYPE_CORPORATE,
            CorporateImportColumnManifest::TYPE_SERVICE,
        ], true)) {
            throw new RuntimeException('Tipe import tidak valid.');
        }

        if ($actor && ! $actor->hasFullBranchAccess() && ! in_array($branchId, $actor->allowedBranchIds(), true)) {
            throw new RuntimeException('Anda tidak memiliki akses ke cabang ini.');
        }

        if ($importType === CorporateImportColumnManifest::TYPE_SERVICE && ! $versionName) {
            throw new RuntimeException('Nama versi pricing wajib untuk import Data Service.');
        }

        $scopes = RegionScope::query()->pluck('id', 'code');
        $categories = ServiceCategory::query()->pricingSlots()->pluck('id', 'code');
        $airlines = Airline::query()->pluck('id', 'code');

        $version = null;
        if ($importType === CorporateImportColumnManifest::TYPE_SERVICE) {
            $version = $this->resolveVersion($branch, $versionName, basename($filePath), $userId);
        }

        $stats = [
            'customers' => 0,
            'contacts' => 0,
            'entities' => 0,
            'airline_codes' => 0,
            'pricing_rules' => 0,
            'notes' => 0,
        ];
        $errors = [];
        $customerCache = [];

        $sheetMap = $this->loadSheetMap($filePath);

        DB::transaction(function () use (
            $sheetMap, $branch, $version, $importType, $scopes, $categories, $airlines,
            $userId, &$stats, &$errors, &$customerCache
        ) {
            if ($importType === CorporateImportColumnManifest::TYPE_SERVICE && $version) {
                $materaiCategoryId = $categories->get('MATERAI');
                CustomerPricingRule::query()
                    ->where('pricing_version_id', $version->id)
                    ->when($materaiCategoryId, fn ($q) => $q->where('service_category_id', '!=', $materaiCategoryId))
                    ->delete();
            }

            if ($wide = $this->findWideImportStructure($sheetMap, $importType)) {
                if ($importType === CorporateImportColumnManifest::TYPE_CORPORATE) {
                    $this->importCorporateWideSheet(
                        $wide, $branch, $scopes, $categories, $airlines, $stats, $errors, $customerCache, $userId
                    );
                } else {
                    $this->importServiceWideSheet(
                        $wide, $branch, $version, $scopes, $categories, $airlines, $stats, $errors, $customerCache
                    );
                }
            } else {
                throw new RuntimeException(
                    'Format file tidak dikenali. Unduh template '.($importType === CorporateImportColumnManifest::TYPE_SERVICE ? 'Data Service' : 'Data Corporate').' terbaru.'
                );
            }
        });

        $stats['customers'] = count($customerCache);

        return [
            'version_id' => $version?->id,
            'version_name' => $version?->name,
            'import_type' => $importType,
            'branch_code' => $branch->code,
            'stats' => $stats,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string, Collection<int, Collection<int, mixed>>>
     */
    private function loadSheetMap(string $filePath): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $spreadsheet = IOFactory::load($filePath);
            $map = [];

            foreach ($spreadsheet->getAllSheets() as $sheet) {
                $rows = collect($sheet->toArray())->map(fn ($row) => collect($row));
                $map[$this->normalizeSheetName($sheet->getTitle())] = $rows;
            }

            return $map;
        }

        $rows = Excel::toCollection(null, $filePath)->first() ?? collect();

        return ['data' => $rows];
    }

    /**
     * @param  array<string, Collection<int, Collection<int, mixed>>>  $sheetMap
     * @return array{rows: Collection<int, Collection<int, mixed>>, columnIndex: array<int, string>, manifest: Collection<string, array<string, mixed>>}|null
     */
    private function findWideImportStructure(array $sheetMap, string $importType): ?array
    {
        $preferred = $this->normalizeSheetName(
            $importType === CorporateImportColumnManifest::TYPE_SERVICE
                ? CorporateImportTemplateBuilder::SHEET_SERVICE
                : CorporateImportTemplateBuilder::SHEET_IMPORT
        );

        $candidates = isset($sheetMap[$preferred])
            ? [$sheetMap[$preferred]]
            : array_values($sheetMap);

        foreach ($candidates as $rows) {
            $structure = $this->parseWideSheetStructure($rows, $importType);
            if ($structure !== null) {
                return $structure;
            }
        }

        return null;
    }

    /**
     * @return array{rows: Collection<int, Collection<int, mixed>>, columnIndex: array<int, string>, manifest: Collection<string, array<string, mixed>>}|null
     */
    private function parseWideSheetStructure(Collection $rows, string $importType): ?array
    {
        $manifest = collect(CorporateImportColumnManifest::columns($importType))->keyBy(
            fn (array $col) => strtolower($col['key'])
        );

        foreach ($rows as $index => $row) {
            $labels = $row->map(fn ($v) => trim((string) $v))->all();

            if (
                strcasecmp($labels[0] ?? '', 'Cabang') === 0
                && strcasecmp($labels[1] ?? '', 'Nama Corporate') === 0
            ) {
                return [
                    'rows' => $rows->slice($index + 1)->values(),
                    'columnIndex' => $this->columnIndexFromManifest($importType),
                    'manifest' => $manifest,
                ];
            }
        }

        return null;
    }

    /** @return array<int, string> */
    private function columnIndexFromManifest(string $importType): array
    {
        $columnIndex = [];
        foreach (CorporateImportColumnManifest::columns($importType) as $index => $column) {
            $columnIndex[$index] = strtolower($column['key']);
        }

        return $columnIndex;
    }

    /**
     * @param  array{rows: Collection<int, Collection<int, mixed>>, columnIndex: array<int, string>, manifest: Collection<string, array<string, mixed>>}  $structure
     */
    private function importCorporateWideSheet(
        array $structure,
        Branch $branch,
        $scopes,
        $categories,
        $airlines,
        array &$stats,
        array &$errors,
        array &$customerCache,
        ?int $userId,
    ): void {
        $profileKeys = collect(CorporateImportColumnManifest::corporateColumns())
            ->reject(fn (array $col) => in_array($col['key'], ['materai'], true))
            ->pluck('key')
            ->flip();

        foreach ($structure['rows'] as $line => $row) {
            $lineNo = $line + CorporateImportTemplateBuilder::DATA_START_ROW;
            $data = $this->extractRowByIndex($row, $structure['columnIndex']);

            if ($this->isEmptyImportRow($data, ['branch_code', 'customer_name'])) {
                continue;
            }

            try {
                $customer = $this->resolveRowCustomer(
                    $branch, $data, $lineNo, $errors, $customerCache, 'Data Corporate'
                );
                if (! $customer) {
                    continue;
                }

                $profileData = collect($data)->filter(fn ($_, $key) => $profileKeys->has($key))->all();
                $this->importCustomerProfile($customer, $profileData, $stats);

                $materaiValue = trim($data['materai'] ?? '');
                if ($materaiValue !== '') {
                    $activeVersion = PricingVersion::query()
                        ->where('branch_id', $branch->id)
                        ->where('is_active', true)
                        ->first();

                    if ($activeVersion) {
                        $this->createPricingRule(
                            $customer,
                            $activeVersion,
                            'MATERAI',
                            'ALL',
                            null,
                            $materaiValue,
                            1,
                            $categories,
                            $scopes,
                            $airlines,
                            $stats,
                            $lineNo,
                            $errors,
                        );
                    } else {
                        $errors[] = "Baris {$lineNo}: materai tidak disimpan — belum ada versi pricing aktif di cabang ini.";
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = "Baris {$lineNo}: ".$e->getMessage();
            }
        }
    }

    /**
     * @param  array{rows: Collection<int, Collection<int, mixed>>, columnIndex: array<int, string>, manifest: Collection<string, array<string, mixed>>}  $structure
     */
    private function importServiceWideSheet(
        array $structure,
        Branch $branch,
        PricingVersion $version,
        $scopes,
        $categories,
        $airlines,
        array &$stats,
        array &$errors,
        array &$customerCache,
    ): void {
        $profileKeys = collect(['branch_code', 'customer_name'])->flip();

        foreach ($structure['rows'] as $line => $row) {
            $lineNo = $line + CorporateImportTemplateBuilder::DATA_START_ROW;
            $data = $this->extractRowByIndex($row, $structure['columnIndex']);

            if ($this->isEmptyImportRow($data, ['branch_code', 'customer_name'])) {
                continue;
            }

            try {
                $customer = $this->resolveRowCustomer(
                    $branch, $data, $lineNo, $errors, $customerCache, 'Data Service'
                );
                if (! $customer) {
                    continue;
                }

                foreach ($structure['columnIndex'] as $key) {
                    if ($profileKeys->has($key)) {
                        continue;
                    }

                    $rawValue = trim($data[$key] ?? '');
                    if ($rawValue === '') {
                        continue;
                    }

                    if (str_starts_with($key, 'airline_')) {
                        if (! preg_match('/^airline_([a-z0-9]+)_(dom|intr)$/i', $key, $matches)) {
                            $errors[] = "Baris {$lineNo}: kolom '{$key}' tidak valid.";

                            continue;
                        }

                        $this->createPricingRule(
                            $customer,
                            $version,
                            'AIRLINE',
                            strtoupper($matches[2]),
                            strtoupper($matches[1]),
                            $rawValue,
                            1,
                            $categories,
                            $scopes,
                            $airlines,
                            $stats,
                            $lineNo,
                            $errors,
                        );

                        continue;
                    }

                    if (str_starts_with($key, 'svc_')) {
                        $column = $structure['manifest']->get($key);
                        if (! $column) {
                            $errors[] = "Baris {$lineNo}: kolom layanan '{$key}' tidak dikenali.";

                            continue;
                        }

                        $this->createPricingRule(
                            $customer,
                            $version,
                            $column['service_category'],
                            $column['region_scope'],
                            null,
                            $rawValue,
                            1,
                            $categories,
                            $scopes,
                            $airlines,
                            $stats,
                            $lineNo,
                            $errors,
                        );
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = "Baris {$lineNo}: ".$e->getMessage();
            }
        }
    }

    private function extractRowByIndex($row, array $columnIndex): array
    {
        $data = [];
        foreach ($columnIndex as $index => $key) {
            $data[$key] = trim((string) ($row[$index] ?? ''));
        }

        return $data;
    }

    private function createPricingRule(
        Customer $customer,
        PricingVersion $version,
        string $categoryCode,
        string $scopeCode,
        ?string $airlineCode,
        string $rawValue,
        int $sourceRow,
        $categories,
        $scopes,
        $airlines,
        array &$stats,
        int $lineNo,
        array &$errors,
    ): void {
        $categoryId = $categories->get(strtoupper($categoryCode));
        if (! $categoryId) {
            $errors[] = "Baris {$lineNo}: kategori '{$categoryCode}' tidak ditemukan.";

            return;
        }

        $scopeId = $scopes->get(strtoupper($scopeCode));
        if (! $scopeId) {
            $errors[] = "Baris {$lineNo}: region_scope '{$scopeCode}' tidak valid.";

            return;
        }

        $airlineId = null;
        if ($airlineCode !== null && $airlineCode !== '') {
            $airlineId = $airlines->get(strtoupper($airlineCode));
            if (! $airlineId) {
                $errors[] = "Baris {$lineNo}: maskapai '{$airlineCode}' tidak ditemukan.";

                return;
            }
        }

        $parsed = $this->parser->parse($rawValue);

        CustomerPricingRule::updateOrCreate(
            [
                'customer_id' => $customer->id,
                'pricing_version_id' => $version->id,
                'service_category_id' => $categoryId,
                'region_scope_id' => $scopeId,
                'airline_id' => $airlineId,
                'source_row' => $sourceRow,
            ],
            array_merge($parsed, ['raw_value' => $rawValue])
        );

        $stats['pricing_rules']++;
    }

    private function findFlatImportRows(array $sheetMap): ?Collection
    {
        $preferred = $this->normalizeSheetName(CorporateImportTemplateBuilder::SHEET_IMPORT);
        if (isset($sheetMap[$preferred])) {
            $rows = $this->normalizeFlatSheet($sheetMap[$preferred]);
            if ($rows !== null) {
                return $rows;
            }
        }

        foreach ($sheetMap as $rows) {
            $normalized = $this->normalizeFlatSheet($rows);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeFlatSheet(Collection $rows): ?Collection
    {
        $headerIndex = $this->findHeaderRowIndex($rows);
        if ($headerIndex === null) {
            return null;
        }

        $header = $rows[$headerIndex]->map(fn ($v) => strtolower(trim((string) $v)))->all();
        if (! in_array('record_type', $header, true) || ! in_array('customer_name', $header, true)) {
            return null;
        }

        return $rows->slice($headerIndex)->values();
    }

    private function findHeaderRowIndex(Collection $rows): ?int
    {
        foreach ($rows as $index => $row) {
            $header = $row->map(fn ($v) => strtolower(trim((string) $v)))->all();
            if (in_array('customer_name', $header, true) && in_array('record_type', $header, true)) {
                return $index;
            }
        }

        return null;
    }

    private function isMultiSheetWorkbook(array $sheetMap): bool
    {
        return isset($sheetMap[$this->normalizeSheetName(CorporateImportTemplateBuilder::SHEET_PROFILE)])
            || isset($sheetMap[$this->normalizeSheetName(CorporateImportTemplateBuilder::SHEET_PRICING)]);
    }

    private function normalizeSheetName(string $name): string
    {
        return Str::lower(trim($name));
    }

    private function importProfiles(
        array $sheetMap,
        Branch $branch,
        array &$stats,
        array &$errors,
        array &$customerCache,
    ): void {
        $sheetName = $this->normalizeSheetName(CorporateImportTemplateBuilder::SHEET_PROFILE);
        $rows = $sheetMap[$sheetName] ?? collect();

        if ($rows->isEmpty()) {
            return;
        }

        $columnIndex = $this->mapColumns(
            $this->headerRow($rows),
            CorporateImportTemplateBuilder::PROFILE_HEADERS,
            'Profil Corporate',
        );

        foreach ($rows->slice(1) as $line => $row) {
            $lineNo = $line + 2;
            $data = $this->extractRow($row, $columnIndex);

            if ($this->isEmptyRow($data)) {
                continue;
            }

            try {
                $customer = $this->resolveRowCustomer($branch, $data, $lineNo, $errors, $customerCache);
                if (! $customer) {
                    continue;
                }

                $this->importCustomerProfile($customer, $data, $stats);
            } catch (\Throwable $e) {
                $errors[] = "Profil Corporate baris {$lineNo}: ".$e->getMessage();
            }
        }
    }

    private function importPicSheet(
        array $sheetMap,
        Branch $branch,
        array &$stats,
        array &$errors,
        array &$customerCache,
    ): void {
        $this->importSheetRows(
            $sheetMap,
            CorporateImportTemplateBuilder::SHEET_PIC,
            CorporateImportTemplateBuilder::PIC_HEADERS,
            $branch,
            $errors,
            $customerCache,
            function (Customer $customer, array $data) use (&$stats) {
                $this->importContact($customer, $data, $stats);
            },
        );
    }

    private function importEntitySheet(
        array $sheetMap,
        Branch $branch,
        array &$stats,
        array &$errors,
        array &$customerCache,
    ): void {
        $this->importSheetRows(
            $sheetMap,
            CorporateImportTemplateBuilder::SHEET_ENTITY,
            CorporateImportTemplateBuilder::ENTITY_HEADERS,
            $branch,
            $errors,
            $customerCache,
            function (Customer $customer, array $data) use (&$stats) {
                $this->importEntity($customer, $data, $stats);
            },
        );
    }

    private function importPricingSheet(
        array $sheetMap,
        Branch $branch,
        PricingVersion $version,
        $categories,
        $scopes,
        $airlines,
        array &$stats,
        array &$errors,
        array &$customerCache,
    ): void {
        $this->importSheetRows(
            $sheetMap,
            CorporateImportTemplateBuilder::SHEET_PRICING,
            CorporateImportTemplateBuilder::PRICING_HEADERS,
            $branch,
            $errors,
            $customerCache,
            function (Customer $customer, array $data, int $lineNo) use (
                $version, $categories, $scopes, $airlines, &$stats, &$errors
            ) {
                $this->importPricing(
                    $customer, $data, $version, $categories, $scopes, $airlines, $stats, $lineNo, $errors
                );
            },
        );
    }

    private function importAirlineCodeSheet(
        array $sheetMap,
        Branch $branch,
        $airlines,
        array &$stats,
        array &$errors,
        array &$customerCache,
    ): void {
        $this->importSheetRows(
            $sheetMap,
            CorporateImportTemplateBuilder::SHEET_AIRLINE_CODE,
            CorporateImportTemplateBuilder::AIRLINE_CODE_HEADERS,
            $branch,
            $errors,
            $customerCache,
            function (Customer $customer, array $data, int $lineNo) use ($airlines, &$stats, &$errors) {
                $this->importAirlineCode($customer, $data, $airlines, $stats, $lineNo, $errors);
            },
        );
    }

    private function importNoteSheet(
        array $sheetMap,
        Branch $branch,
        array &$stats,
        array &$errors,
        array &$customerCache,
    ): void {
        $this->importSheetRows(
            $sheetMap,
            CorporateImportTemplateBuilder::SHEET_NOTE,
            CorporateImportTemplateBuilder::NOTE_HEADERS,
            $branch,
            $errors,
            $customerCache,
            function (Customer $customer, array $data) use (&$stats) {
                $this->importNote($customer, $data, $stats, $data['is_important'] ?? null);
            },
        );
    }

    /**
     * @param  callable(Customer, array<string, string>, int): void  $handler
     */
    private function importSheetRows(
        array $sheetMap,
        string $sheetTitle,
        array $expectedHeaders,
        Branch $branch,
        array &$errors,
        array &$customerCache,
        callable $handler,
    ): void {
        $rows = $sheetMap[$this->normalizeSheetName($sheetTitle)] ?? collect();

        if ($rows->isEmpty()) {
            return;
        }

        $columnIndex = $this->mapColumns($this->headerRow($rows), $expectedHeaders, $sheetTitle, false);

        foreach ($rows->slice(1) as $line => $row) {
            $lineNo = $line + 2;
            $data = $this->extractRow($row, $columnIndex);

            if ($this->isEmptyImportRow($data, ['branch_code', 'customer_name'])) {
                continue;
            }

            try {
                $customer = $this->resolveRowCustomer($branch, $data, $lineNo, $errors, $customerCache, $sheetTitle);
                if (! $customer) {
                    continue;
                }

                $handler($customer, $data, $lineNo);
            } catch (\Throwable $e) {
                $errors[] = "{$sheetTitle} baris {$lineNo}: ".$e->getMessage();
            }
        }
    }

    private function importFlatSheet(
        Collection $rows,
        Branch $branch,
        PricingVersion $version,
        $scopes,
        $categories,
        $airlines,
        array &$stats,
        array &$errors,
        array &$customerCache,
    ): void {
        $header = $this->headerRow($rows);
        $columnIndex = $this->mapColumns($header, CorporateImportTemplateBuilder::HEADERS, 'Data Import');

        foreach ($rows->slice(1) as $line => $row) {
            $lineNo = $line + 2;
            $data = $this->extractRow($row, $columnIndex);

            if ($this->isEmptyRow($data)) {
                continue;
            }

            $rowBranchCode = strtoupper($data['branch_code'] ?? '');
            if ($rowBranchCode !== '' && $rowBranchCode !== strtoupper($branch->code)) {
                $errors[] = "Baris {$lineNo}: branch_code harus {$branch->code}.";

                continue;
            }

            $customerName = trim($data['customer_name'] ?? '');
            if ($customerName === '') {
                $errors[] = "Baris {$lineNo}: customer_name wajib diisi.";

                continue;
            }

            $recordType = strtoupper(trim($data['record_type'] ?? 'PRICING'));

            try {
                $customerKey = $branch->id.'|'.Str::lower($customerName);
                if (! isset($customerCache[$customerKey])) {
                    $customerCache[$customerKey] = $this->resolveCustomer($branch->id, $customerName);
                }
                $customer = $customerCache[$customerKey];

                match ($recordType) {
                    'CUSTOMER' => $this->importCustomerProfile($customer, $data, $stats),
                    'CONTACT' => $this->importContact($customer, $data, $stats),
                    'ENTITY' => $this->importEntity($customer, $data, $stats),
                    'AIRLINE_CODE' => $this->importAirlineCode($customer, $data, $airlines, $stats, $lineNo, $errors),
                    'NOTE' => $this->importNote($customer, $data, $stats),
                    'PRICING' => $this->importPricing(
                        $customer, $data, $version, $categories, $scopes, $airlines, $stats, $lineNo, $errors
                    ),
                    default => $errors[] = "Baris {$lineNo}: record_type '{$recordType}' tidak dikenali.",
                };
            } catch (\Throwable $e) {
                $errors[] = "Baris {$lineNo}: ".$e->getMessage();
            }
        }
    }

    private function headerRow(Collection $rows): array
    {
        return $rows->first()
            ->map(fn ($v) => strtolower(trim((string) $v)))
            ->all();
    }

    private function mapColumns(array $header, array $expected, string $sheetLabel, bool $strict = true): array
    {
        $map = [];
        foreach ($expected as $col) {
            $index = array_search($col, $header, true);
            if ($index !== false) {
                $map[$col] = $index;
            }
        }

        if ($strict && ! isset($map['customer_name'])) {
            throw new RuntimeException("Sheet '{$sheetLabel}' tidak valid. Unduh template Excel terbaru.");
        }

        return $map;
    }

    private function extractRow($row, array $columnIndex): array
    {
        $data = [];
        foreach ($columnIndex as $key => $index) {
            $data[$key] = trim((string) ($row[$index] ?? ''));
        }

        return $data;
    }

    private function isEmptyRow(array $data): bool
    {
        return collect($data)->filter(fn ($v) => $v !== '')->isEmpty();
    }

    /**
     * @param  array<int, string>  $requiredKeys
     */
    private function isEmptyImportRow(array $data, array $requiredKeys): bool
    {
        foreach ($requiredKeys as $key) {
            if (trim($data[$key] ?? '') !== '') {
                return false;
            }
        }

        return collect($data)->filter(fn ($v) => $v !== '')->isEmpty();
    }

    private function resolveRowCustomer(
        Branch $branch,
        array $data,
        int $lineNo,
        array &$errors,
        array &$customerCache,
        string $sheetLabel = 'Profil Corporate',
    ): ?Customer {
        $rowBranchCode = strtoupper($data['branch_code'] ?? '');
        if ($rowBranchCode !== '' && $rowBranchCode !== strtoupper($branch->code)) {
            $errors[] = "{$sheetLabel} baris {$lineNo}: branch_code harus {$branch->code}.";

            return null;
        }

        $customerName = trim($data['customer_name'] ?? '');
        if ($customerName === '') {
            $errors[] = "{$sheetLabel} baris {$lineNo}: customer_name wajib diisi.";

            return null;
        }

        $customerKey = $branch->id.'|'.Str::lower($customerName);
        if (! isset($customerCache[$customerKey])) {
            $customerCache[$customerKey] = $this->resolveCustomer($branch->id, $customerName);
        }

        return $customerCache[$customerKey];
    }

    private function resolveVersion(Branch $branch, string $name, string $filename, ?int $userId): PricingVersion
    {
        PricingVersion::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return PricingVersion::updateOrCreate(
            ['branch_id' => $branch->id, 'name' => $name],
            [
                'effective_from' => Carbon::today(),
                'effective_to' => null,
                'is_active' => true,
                'imported_from' => $filename,
                'imported_at' => now(),
                'imported_by' => $userId,
            ]
        );
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

    private function importCustomerProfile(Customer $customer, array $data, array &$stats): void
    {
        $customer->update(array_filter([
            'corp_mode' => $this->parseBool($data['corp_mode'] ?? null),
            'handler' => $data['handler'] ?? null,
            'faktur_pajak' => $this->parseNullableBool($data['faktur_pajak'] ?? null),
            'show_service_fee' => $this->parseNullableBool($data['show_service_fee'] ?? null),
            'invoice_method' => $this->normalizeInvoiceMethod($data['invoice_method'] ?? null),
            'cn_percentage' => $this->parseDecimal($data['cn_percentage'] ?? null),
            'invoice_per_person' => $this->parseBool($data['invoice_per_person'] ?? null),
            'kick_off_date' => $this->parseDate($data['contract_start'] ?? $data['kick_off_date'] ?? null),
            'contract_end_date' => $this->parseDate($data['contract_end'] ?? null),
            'contract_period' => $this->nullableString($data['contract_period'] ?? null),
            'general_note' => $data['general_note'] ?? null,
        ], fn ($v) => $v !== null));

        if (! empty($data['aliases'])) {
            foreach (array_map('trim', preg_split('/\s*\/\s*/', $data['aliases'])) as $alias) {
                if ($alias === '') {
                    continue;
                }
                CustomerAlias::firstOrCreate([
                    'customer_id' => $customer->id,
                    'alias_name' => $alias,
                ]);
            }
        }
    }

    private function importContact(Customer $customer, array $data, array &$stats): void
    {
        $name = trim($data['pic_name'] ?? '');
        if ($name === '') {
            return;
        }

        CustomerContact::create([
            'customer_id' => $customer->id,
            'name' => $name,
            'phone' => $data['pic_phone'] ?? null,
            'email' => $data['pic_email'] ?? null,
            'is_primary' => $stats['contacts'] === 0,
        ]);
        $stats['contacts']++;
    }

    private function importEntity(Customer $customer, array $data, array &$stats): void
    {
        $name = trim($data['entity_name'] ?? '');
        if ($name === '') {
            return;
        }

        CustomerEntity::create([
            'customer_id' => $customer->id,
            'entity_code' => $data['entity_code'] ?? null,
            'entity_name' => $name,
            'inherits_pricing' => true,
            'sort_order' => $stats['entities'] + 1,
        ]);
        $stats['entities']++;
    }

    private function importAirlineCode(
        Customer $customer,
        array $data,
        $airlines,
        array &$stats,
        int $lineNo,
        array &$errors,
    ): void {
        $airlineCode = strtoupper($data['airline_code'] ?? '');
        if ($airlineCode === '') {
            $errors[] = "Baris {$lineNo}: airline_code wajib untuk Kode Maskapai.";

            return;
        }

        $airlineId = $airlines->get($airlineCode);
        if (! $airlineId) {
            $errors[] = "Baris {$lineNo}: maskapai '{$airlineCode}' tidak ditemukan di master.";

            return;
        }

        CustomerAirlineCode::updateOrCreate(
            ['customer_id' => $customer->id, 'airline_id' => $airlineId],
            [
                'corporate_code' => $data['corporate_code'] ?? null,
                'tour_code' => $data['tour_code'] ?? null,
                'access_code' => $data['access_code'] ?? null,
                'corporate_id' => $data['corporate_id'] ?? null,
                'notes' => $data['notes'] ?? $data['note'] ?? null,
            ]
        );
        $stats['airline_codes']++;
    }

    private function importNote(Customer $customer, array $data, array &$stats, ?string $isImportant = null): void
    {
        $note = trim($data['note'] ?? '');
        if ($note === '') {
            return;
        }

        $important = $isImportant !== null && $isImportant !== ''
            ? $this->parseBool($isImportant)
            : str_contains(strtoupper($note), 'INVOICE');

        CustomerNote::create([
            'customer_id' => $customer->id,
            'note' => $note,
            'is_important' => (bool) $important,
        ]);
        $stats['notes']++;
    }

    private function importPricing(
        Customer $customer,
        array $data,
        PricingVersion $version,
        $categories,
        $scopes,
        $airlines,
        array &$stats,
        int $lineNo,
        array &$errors,
    ): void {
        $categoryCode = strtoupper($data['service_category'] ?? '');
        $scopeCode = strtoupper($data['region_scope'] ?? 'ALL');
        $rawValue = trim($data['raw_value'] ?? '');

        if ($categoryCode === '' || $rawValue === '') {
            $errors[] = "Baris {$lineNo}: service_category dan raw_value wajib untuk Service Fee.";

            return;
        }

        $categoryId = $categories->get($categoryCode);
        if (! $categoryId) {
            $errors[] = "Baris {$lineNo}: kategori '{$categoryCode}' tidak ditemukan.";

            return;
        }

        $scopeId = $scopes->get($scopeCode);
        if (! $scopeId) {
            $errors[] = "Baris {$lineNo}: region_scope '{$scopeCode}' tidak valid (ALL/DOM/INTR).";

            return;
        }

        $airlineId = null;
        $pricingAirline = strtoupper($data['pricing_airline_code'] ?? '');
        if ($pricingAirline !== '') {
            $airlineId = $airlines->get($pricingAirline);
            if (! $airlineId) {
                $errors[] = "Baris {$lineNo}: pricing_airline_code '{$pricingAirline}' tidak ditemukan.";

                return;
            }
        }

        $sourceRow = max(1, (int) ($data['source_row'] ?? 1));
        $parsed = $this->parser->parse($rawValue);

        CustomerPricingRule::updateOrCreate(
            [
                'customer_id' => $customer->id,
                'pricing_version_id' => $version->id,
                'service_category_id' => $categoryId,
                'region_scope_id' => $scopeId,
                'airline_id' => $airlineId,
                'source_row' => $sourceRow,
            ],
            array_merge($parsed, ['raw_value' => $rawValue])
        );

        $stats['pricing_rules']++;
    }

    private function parseBool(?string $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return in_array(strtolower($value), ['1', 'yes', 'y', 'true', 'ya', '✓'], true);
    }

    private function parseNullableBool(?string $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array(strtolower($value), ['tbc', '-'], true)) {
            return null;
        }

        return $this->parseBool($value);
    }

    private function parseDecimal(?string $value): ?float
    {
        if ($value === null || $value === '' || $value === '-') {
            return null;
        }

        return (float) str_replace(',', '.', str_replace('%', '', $value));
    }

    private function parseDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function nullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeInvoiceMethod(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $v = strtolower(str_replace([' ', '&'], ['_', ''], $value));

        return match (true) {
            str_contains($v, 'print') && str_contains($v, 'email') => 'print_email',
            str_contains($v, 'email') => 'email',
            str_contains($v, 'print') => 'print',
            in_array($v, ['no', 'tidak'], true) => 'no',
            default => $v,
        };
    }
}
