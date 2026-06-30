<?php

namespace App\Services;

class PricingParser
{
    public function parse(string $rawValue): array
    {
        $value = trim($rawValue);

        if ($value === '' || $value === '-') {
            return [
                'fee_type' => 'none',
                'calculation_basis' => null,
                'percentage_value' => null,
                'fixed_amount' => null,
                'is_visible_to_client' => true,
                'hide_garuda_fee' => false,
                'separate_ga_non_ga' => false,
            ];
        }

        $upper = strtoupper($value);

        return [
            'fee_type' => $this->detectFeeType($upper),
            'calculation_basis' => $this->detectCalculationBasis($upper),
            'percentage_value' => $this->extractPercentage($value),
            'fixed_amount' => $this->extractFixedAmount($value),
            'is_visible_to_client' => ! str_contains($upper, 'JANGAN DISHOW') && ! str_contains($upper, 'JANGAN DI SHOW'),
            'hide_garuda_fee' => str_contains($upper, 'GA DITONGOLIN') || str_contains($upper, 'GAUSAH DITONGOLIN'),
            'separate_ga_non_ga' => str_contains($upper, 'GA & NON GA DIPISAH') || str_contains($upper, 'GA AND NON GA'),
        ];
    }

    public function detectRegionScope(string $rawValue, bool $isContinuationRow = false): string
    {
        $upper = strtoupper(trim($rawValue));

        if (str_contains($upper, 'DOMESTIC') || str_contains($upper, 'DOMESTIK')) {
            return 'domestic';
        }

        if (str_contains($upper, 'INTERNATIONAL') || str_contains($upper, ' INTL') || str_starts_with($upper, 'INTL')) {
            return 'international';
        }

        return $isContinuationRow ? 'international' : 'all';
    }

    private function detectFeeType(string $upper): string
    {
        $hasMarkup = str_contains($upper, 'MARKUP') || str_contains($upper, 'MARK UP');
        $hasServiceFee = str_contains($upper, 'SERVICE FEE') || str_contains($upper, '(SF)');

        if ($hasMarkup && $hasServiceFee) {
            return 'mixed';
        }

        if ($hasMarkup) {
            return 'markup';
        }

        if ($hasServiceFee) {
            return 'service_fee';
        }

        if (str_contains($upper, 'ON THE TICKET') || str_contains($upper, 'ON THE TICKET')) {
            return 'on_ticket';
        }

        if (str_contains($upper, 'STAMP DUTY') || str_contains($upper, 'MATERAI')) {
            return 'service_fee';
        }

        return 'mixed';
    }

    private function detectCalculationBasis(string $upper): ?string
    {
        if (str_contains($upper, 'FROM NETT') || str_contains($upper, 'FROM NET')) {
            return 'from_nett';
        }

        if (str_contains($upper, 'FROM BASIC')) {
            return 'from_basic';
        }

        if (str_contains($upper, 'FROM TOTAL') || preg_match('/\d+(\.\d+)?%/', $upper)) {
            return 'from_total';
        }

        if (str_contains($upper, 'PER ROOM') || str_contains($upper, '/R/N')) {
            return 'per_room_night';
        }

        if (str_contains($upper, 'PER DAY') || str_contains($upper, '/DAY') || str_contains($upper, '/HARI')) {
            return 'per_day';
        }

        if (str_contains($upper, 'PER WAY') || str_contains($upper, '/WAY')) {
            return 'per_way';
        }

        if (str_contains($upper, 'PER TIKET') || str_contains($upper, '/TIKET') || str_contains($upper, 'PER TICKET')) {
            return 'per_ticket';
        }

        if (str_contains($upper, 'PER PERSON') || str_contains($upper, '/ PERSON')) {
            return 'per_person';
        }

        if (preg_match('/UP\s+[\d,.]+/', $upper)) {
            return 'fixed';
        }

        return null;
    }

    private function extractPercentage(string $value): ?float
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*%/', $value, $matches)) {
            return (float) str_replace(',', '.', $matches[1]);
        }

        return null;
    }

    private function extractFixedAmount(string $value): ?float
    {
        if (preg_match('/UP\s+([\d,.]+)/i', $value, $matches)) {
            $amount = str_replace(',', '', $matches[1]);

            return (float) $amount;
        }

        if (preg_match('/([\d,.]+)\s*\/R\/N/i', $value, $matches)) {
            return (float) str_replace(',', '', $matches[1]);
        }

        return null;
    }
}
