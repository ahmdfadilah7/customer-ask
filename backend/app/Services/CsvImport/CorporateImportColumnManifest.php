<?php

namespace App\Services\CsvImport;

use App\Models\Airline;
use Illuminate\Support\Collection;

class CorporateImportColumnManifest
{
    public const TYPE_CORPORATE = 'corporate';

    public const TYPE_SERVICE = 'service';

    /** @return array<int, array<string, mixed>> */
    public static function columns(string $type): array
    {
        return match ($type) {
            self::TYPE_CORPORATE => self::corporateColumns(),
            self::TYPE_SERVICE => self::serviceColumns(),
            default => throw new \InvalidArgumentException("Tipe import tidak valid: {$type}"),
        };
    }

    /** @return array<int, array<string, mixed>> */
    public static function corporateColumns(): array
    {
        return [
            self::col('branch_code', 'Cabang', 'wajib', ['row2' => 'Identitas']),
            self::col('customer_name', 'Nama Corporate', 'wajib', ['width' => 28, 'row2' => 'Identitas']),
            self::col('corp_mode', 'Corp Mode', 'profil', ['row2' => 'Profil Operasional']),
            self::col('faktur_pajak', 'Faktur Pajak', 'profil', ['row2' => 'Profil Operasional']),
            self::col('show_service_fee', 'Service Fee', 'profil', ['row2' => 'Profil Operasional']),
            self::col('invoice_method', 'Invoice', 'profil', ['row2' => 'Profil Operasional']),
            self::col('cn_percentage', 'CN %', 'profil', ['row2' => 'Profil Operasional']),
            self::col('materai', 'Materai', 'materai', [
                'width' => 18,
                'row2' => 'Materai',
                'service_category' => 'MATERAI',
                'region_scope' => 'ALL',
            ]),
            self::col('contract_period', 'Periode Kontrak', 'kontrak', [
                'width' => 36,
                'row2' => 'Periode Kontrak',
            ]),
            self::col('general_note', 'Catatan', 'note', ['width' => 28, 'row2' => 'Catatan']),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function serviceColumns(): array
    {
        return array_merge(
            [
                self::col('branch_code', 'Cabang', 'wajib', ['row2' => 'Identitas']),
                self::col('customer_name', 'Nama Corporate', 'wajib', ['width' => 28, 'row2' => 'Identitas']),
            ],
            self::airlineColumns(),
            self::servicePricingColumns(),
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function airlineColumns(): array
    {
        $columns = [];

        foreach (self::activeAirlines() as $airline) {
            $scopes = $airline->regionScopes
                ->sortBy(fn ($scope) => match ($scope->code) {
                    'INTR' => 0,
                    'DOM' => 1,
                    default => 2,
                })
                ->values();

            foreach ($scopes as $scope) {
                if ($scope->code === 'ALL') {
                    continue;
                }

                $columns[] = self::col(
                    "airline_{$airline->code}_{$scope->code}",
                    $scope->code === 'INTR' ? 'International' : 'Domestic',
                    'airline',
                    [
                        'width' => 15,
                        'row2' => "{$airline->name} ({$airline->code})",
                        'airline_code' => $airline->code,
                        'airline_name' => $airline->name,
                        'region_scope' => $scope->code,
                        'service_category' => 'AIRLINE',
                    ]
                );
            }
        }

        return $columns;
    }

    /** @return array<int, array<string, mixed>> */
    public static function servicePricingColumns(): array
    {
        $services = [
            ['key' => 'svc_ISSUE_24JAM', 'label' => 'Issue 24 Jam', 'group' => 'Issue 24 Jam', 'category' => 'ISSUE_24JAM', 'scope' => 'ALL'],
            ['key' => 'svc_HOTEL_DOM', 'label' => 'Domestic', 'group' => 'Hotel', 'category' => 'HOTEL', 'scope' => 'DOM'],
            ['key' => 'svc_HOTEL_INTR', 'label' => 'International', 'group' => 'Hotel', 'category' => 'HOTEL', 'scope' => 'INTR'],
            ['key' => 'svc_TRAIN_DOM', 'label' => 'Domestic', 'group' => 'Kereta / Bus / Travel', 'category' => 'TRAIN_BUS_TRAVEL', 'scope' => 'DOM'],
            ['key' => 'svc_TRAIN_INTR', 'label' => 'International', 'group' => 'Kereta / Bus / Travel', 'category' => 'TRAIN_BUS_TRAVEL', 'scope' => 'INTR'],
            ['key' => 'svc_RENT_DOM', 'label' => 'Domestic', 'group' => 'Rent Car', 'category' => 'RENT_CAR', 'scope' => 'DOM'],
            ['key' => 'svc_RENT_INTR', 'label' => 'International', 'group' => 'Rent Car', 'category' => 'RENT_CAR', 'scope' => 'INTR'],
            ['key' => 'svc_REISSUE_DOM', 'label' => 'Domestic', 'group' => 'Reissue Fee', 'category' => 'REISSUE_FEE', 'scope' => 'DOM'],
            ['key' => 'svc_REISSUE_INTR', 'label' => 'International', 'group' => 'Reissue Fee', 'category' => 'REISSUE_FEE', 'scope' => 'INTR'],
            ['key' => 'svc_REFUND_DOM', 'label' => 'Domestic', 'group' => 'Refund', 'category' => 'REFUND', 'scope' => 'DOM'],
            ['key' => 'svc_REFUND_INTR', 'label' => 'International', 'group' => 'Refund', 'category' => 'REFUND', 'scope' => 'INTR'],
            ['key' => 'svc_DOC_VISA', 'label' => 'Doc / Visa', 'group' => 'Doc / Visa', 'category' => 'DOC_VISA', 'scope' => 'ALL'],
            ['key' => 'svc_TAKEOVER', 'label' => 'Takeover', 'group' => 'Takeover Payment', 'category' => 'TAKEOVER_PAYMENT', 'scope' => 'ALL'],
            ['key' => 'svc_INSURANCE', 'label' => 'Asuransi', 'group' => 'Asuransi', 'category' => 'INSURANCE', 'scope' => 'ALL'],
            ['key' => 'svc_OTHERS', 'label' => 'Lain-lain', 'group' => 'Others', 'category' => 'OTHERS', 'scope' => 'ALL'],
            ['key' => 'svc_TICKET_ALL', 'label' => 'Dom & Intl', 'group' => 'Ticket (Gabungan)', 'category' => 'AIRLINE', 'scope' => 'ALL'],
        ];

        return array_map(
            fn (array $service) => self::col(
                $service['key'],
                $service['label'],
                'service',
                [
                    'width' => 15,
                    'row2' => $service['group'],
                    'service_category' => $service['category'],
                    'region_scope' => $service['scope'],
                ]
            ),
            $services
        );
    }

    /** @return Collection<int, Airline> */
    public static function activeAirlines(): Collection
    {
        return Airline::query()
            ->with(['regionScopes:id,code,name'])
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();
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
