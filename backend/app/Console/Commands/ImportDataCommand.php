<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Services\CsvImport\CorporateMasterCsvImporter;
use App\Services\CsvImport\EmployeeCsvImporter;
use App\Services\CsvImport\ServiceFeeCsvImporter;
use Illuminate\Console\Command;

class ImportDataCommand extends Command
{
    protected $signature = 'data:import
                            {type : employees|service-fees|corporate}
                            {file : Path to CSV/Excel file}
                            {--customer= : Customer name for employee import}
                            {--version-name= : Version name for service fee import}
                            {--branch=JKT : Branch code for corporate import}';

    protected $description = 'Import data from CSV/Excel files';

    public function handle(
        EmployeeCsvImporter $employeeImporter,
        ServiceFeeCsvImporter $serviceFeeImporter,
        CorporateMasterCsvImporter $corporateImporter,
    ): int
    {
        $type = $this->argument('type');
        $file = $this->argument('file');

        if (! file_exists($file)) {
            $this->error("File tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        if ($type === 'employees') {
            $branch = Branch::where('code', strtoupper($this->option('branch')))->first();
            if (! $branch) {
                $this->error('Cabang tidak ditemukan.');

                return self::FAILURE;
            }

            $customer = $this->option('customer') ?: null;
            $result = $employeeImporter->import($file, $branch->id, $customer);
            $this->info("Imported {$result['imported']} employees (format: {$result['format']})");

            if ($result['skipped'] > 0) {
                $this->warn("Skipped {$result['skipped']} rows");
            }

            return self::SUCCESS;
        }

        if ($type === 'service-fees') {
            $version = $this->option('version-name') ?? 'SF CORP UPDATE MEI 2025';
            $result = $serviceFeeImporter->import($file, $version);
            $this->info("Imported {$result['imported_customers']} customers, {$result['imported_rules']} pricing rules");
            $this->info("Version: {$result['version_name']}");

            return self::SUCCESS;
        }

        if ($type === 'corporate') {
            $branch = Branch::where('code', strtoupper($this->option('branch')))->first();
            if (! $branch) {
                $this->error('Cabang tidak ditemukan.');

                return self::FAILURE;
            }

            $importType = \App\Services\CsvImport\CorporateImportColumnManifest::TYPE_CORPORATE;
            $result = $corporateImporter->import($file, $branch->id, $importType);
            $this->info(json_encode($result['stats'], JSON_PRETTY_PRINT));
            if (! empty($result['errors'])) {
                $this->warn(implode(PHP_EOL, $result['errors']));
            }

            return self::SUCCESS;
        }

        if ($type === 'corporate-service') {
            $branch = Branch::where('code', strtoupper($this->option('branch')))->first();
            if (! $branch) {
                $this->error('Cabang tidak ditemukan.');

                return self::FAILURE;
            }

            $version = $this->option('version-name') ?? 'Service Import '.now()->format('Y-m-d');
            $importType = \App\Services\CsvImport\CorporateImportColumnManifest::TYPE_SERVICE;
            $result = $corporateImporter->import($file, $branch->id, $importType, $version);
            $this->info(json_encode($result['stats'], JSON_PRETTY_PRINT));
            if (! empty($result['errors'])) {
                $this->warn(implode(PHP_EOL, $result['errors']));
            }

            return self::SUCCESS;
        }

        $this->error('Type harus employees, service-fees, atau corporate');

        return self::FAILURE;
    }
}
