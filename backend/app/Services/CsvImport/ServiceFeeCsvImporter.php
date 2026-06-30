<?php

namespace App\Services\CsvImport;

use App\Models\Customer;
use App\Models\CustomerAlias;
use App\Models\CustomerNote;
use App\Models\CustomerPricingRule;
use App\Models\PricingVersion;
use App\Models\ServiceCategory;
use App\Services\PricingParser;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ServiceFeeCsvImporter
{
    private const COLUMN_MAP = [
        1 => 'AIRLINE_INTL',
        2 => 'AIRLINE_DOM_GA',
        3 => 'AIRLINE_DOM_OTHER',
        4 => 'AIRLINE_DOM_LCC',
        5 => 'AIRLINE_DOM_PERINTIS',
        6 => 'ISSUE_24JAM',
        7 => 'HOTEL',
        8 => 'TRAIN_BUS_TRAVEL',
        9 => 'RENT_CAR',
        10 => 'REISSUE_FEE',
        11 => 'MATERAI',
        12 => 'TAKEOVER_PAYMENT',
        13 => 'INSURANCE',
    ];

    public function __construct(private PricingParser $parser) {}

    public function import(string $filePath, string $versionName, ?int $userId = null): array
    {
        $rows = Excel::toCollection(null, $filePath)->first();
        $version = $this->resolveVersion($versionName, basename($filePath), $userId);

        $categories = ServiceCategory::all()->keyBy('code');
        $importedCustomers = 0;
        $importedRules = 0;
        $errors = [];

        $currentCustomer = null;

        CustomerPricingRule::where('pricing_version_id', $version->id)->delete();

        DB::transaction(function () use ($rows, $version, $categories, &$currentCustomer, &$importedCustomers, &$importedRules, &$errors) {
            foreach ($rows->slice(3) as $index => $row) {
                $customerName = trim((string) ($row[0] ?? ''));
                $isContinuation = $customerName === '';

                if (! $isContinuation) {
                    $currentCustomer = $this->resolveCustomer($customerName);
                    $importedCustomers++;

                    $note = trim((string) ($row[14] ?? ''));
                    if ($note !== '' && $note !== '-') {
                        CustomerNote::create([
                            'customer_id' => $currentCustomer->id,
                            'note' => $note,
                            'is_important' => str_contains(strtoupper($note), 'INVOICE'),
                        ]);
                    }

                    if (str_contains(strtoupper($note), 'INVOICE DIBUAT PERNAMA')) {
                        $currentCustomer->update(['invoice_per_person' => true]);
                    }
                }

                if (! $currentCustomer) {
                    continue;
                }

                foreach (self::COLUMN_MAP as $colIndex => $categoryCode) {
                    $rawValue = trim((string) ($row[$colIndex] ?? ''));

                    if ($rawValue === '') {
                        continue;
                    }

                    $category = $categories->get($categoryCode);

                    if (! $category) {
                        $errors[] = 'Baris '.($index + 4).": kategori {$categoryCode} tidak ditemukan.";

                        continue;
                    }

                    try {
                        $scope = $category->requires_scope
                            ? $this->parser->detectRegionScope($rawValue, $isContinuation)
                            : 'all';

                        $parsed = $this->parser->parse($rawValue);

                        CustomerPricingRule::updateOrCreate(
                            [
                                'customer_id' => $currentCustomer->id,
                                'service_category_id' => $category->id,
                                'pricing_version_id' => $version->id,
                                'region_scope' => $scope,
                            ],
                            array_merge($parsed, ['raw_value' => $rawValue])
                        );

                        $importedRules++;
                    } catch (\Throwable $e) {
                        $errors[] = 'Baris '.($index + 4).", kolom {$categoryCode}: ".$e->getMessage();
                    }
                }
            }
        });

        return [
            'version_id' => $version->id,
            'version_name' => $version->name,
            'imported_customers' => $importedCustomers,
            'imported_rules' => $importedRules,
            'errors' => $errors,
        ];
    }

    private function resolveVersion(string $name, string $filename, ?int $userId): PricingVersion
    {
        PricingVersion::where('is_active', true)->update(['is_active' => false]);

        return PricingVersion::updateOrCreate(
            ['name' => $name],
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

    private function resolveCustomer(string $rawName): Customer
    {
        $parts = array_map('trim', preg_split('/\s*\/\s*/', $rawName));
        $primaryName = $parts[0];
        $slug = Str::slug($primaryName);

        $customer = Customer::firstOrCreate(
            ['slug' => $slug],
            ['name' => $primaryName, 'status' => 'active']
        );

        if ($customer->name !== $primaryName) {
            $customer->update(['name' => $primaryName]);
        }

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
}
