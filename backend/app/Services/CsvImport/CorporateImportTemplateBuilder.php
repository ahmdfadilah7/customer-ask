<?php

namespace App\Services\CsvImport;

use App\Models\Branch;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CorporateImportTemplateBuilder
{
    public const SHEET_IMPORT = 'Import Corporate';

    public const SHEET_SERVICE = 'Import Service';

    /** Legacy multi-sheet names (importer still accepts old templates) */
    public const SHEET_PROFILE = 'Profil Corporate';

    public const SHEET_PIC = 'PIC';

    public const SHEET_ENTITY = 'Entitas';

    public const SHEET_PRICING = 'Service Fee';

    public const SHEET_AIRLINE_CODE = 'Kode Maskapai';

    public const SHEET_NOTE = 'Catatan';

    public const INSTRUCTION_ROW = 1;

    public const GROUP_ROW = 2;

    public const LABEL_ROW = 3;

    public const DATA_START_ROW = 4;

    /** @deprecated Legacy templates with technical key row */
    public const KEY_ROW = 4;

    /** Legacy flat format */
    public const HEADER_ROW = 2;

    public const HEADERS = [
        'branch_code', 'customer_name', 'record_type', 'corp_mode', 'handler',
        'faktur_pajak', 'show_service_fee', 'invoice_method', 'cn_percentage',
        'invoice_per_person', 'kick_off_date', 'aliases', 'general_note',
        'pic_name', 'pic_phone', 'pic_email', 'entity_code', 'entity_name',
        'airline_code', 'corporate_code', 'tour_code', 'access_code', 'corporate_id',
        'service_category', 'region_scope', 'pricing_airline_code', 'raw_value',
        'source_row', 'note',
    ];

    /** @var array<string, string> */
    public const COLUMN_HINTS = [
        'branch_code' => 'Cabang: HO / PTJ / TB / VNT',
        'customer_name' => 'Nama corporate (wajib)',
    ];

    public const PROFILE_HEADERS = [
        'branch_code', 'customer_name', 'aliases', 'corp_mode', 'handler',
        'faktur_pajak', 'show_service_fee', 'invoice_method', 'cn_percentage',
        'invoice_per_person', 'kick_off_date', 'general_note',
    ];

    public const PIC_HEADERS = ['branch_code', 'customer_name', 'pic_name', 'pic_phone', 'pic_email'];

    public const ENTITY_HEADERS = ['branch_code', 'customer_name', 'entity_code', 'entity_name'];

    public const PRICING_HEADERS = [
        'branch_code', 'customer_name', 'service_category', 'region_scope',
        'pricing_airline_code', 'raw_value', 'source_row', 'sumber_kolom',
    ];

    public const AIRLINE_CODE_HEADERS = [
        'branch_code', 'customer_name', 'airline_code', 'corporate_code',
        'tour_code', 'access_code', 'corporate_id', 'notes',
    ];

    public const NOTE_HEADERS = ['branch_code', 'customer_name', 'note', 'is_important'];

    /** @var array<string, string> */
    private const GROUP_COLORS = [
        'wajib' => '1E3A8A',
        'profil' => '2563EB',
        'pic' => '059669',
        'note' => '64748B',
        'materai' => 'B45309',
        'kontrak' => '0369A1',
        'airline' => '0F766E',
        'service' => '7C3AED',
    ];

    /** @var array<int, array<string, mixed>> */
    private array $columns = [];

    private string $importType = CorporateImportColumnManifest::TYPE_CORPORATE;

    public function build(string $type = CorporateImportColumnManifest::TYPE_CORPORATE): string
    {
        $this->importType = $type;
        $this->columns = CorporateImportColumnManifest::columns($type);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(
            $type === CorporateImportColumnManifest::TYPE_SERVICE
                ? self::SHEET_SERVICE
                : self::SHEET_IMPORT
        );

        $this->writeInstructionRow($sheet);
        $this->writeGroupRow($sheet);
        $this->writeLabelRow($sheet);
        $this->writeExamples($sheet);
        $this->writeEmptyRows($sheet, 40);
        $this->applyTableStyle($sheet);
        $this->applyBorders($sheet);
        $this->applyValidations($sheet);

        $handle = fopen('php://temp', 'r+');
        (new Xlsx($spreadsheet))->save($handle);
        rewind($handle);
        $binary = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $binary;
    }

    /** @return array<int, array<string, mixed>> */
    public function columnManifest(): array
    {
        return $this->columns ?: CorporateImportColumnManifest::columns($this->importType);
    }

    private function writeInstructionRow(Worksheet $sheet): void
    {
        $lastCol = $this->columnLetter(count($this->columns));
        $sheet->mergeCells("A1:{$lastCol}1");

        $text = $this->importType === CorporateImportColumnManifest::TYPE_SERVICE
            ? 'Satu baris = satu corporate. Isi service fee per maskapai (International/Domestic) dan layanan lainnya. Materai diimport lewat Data Corporate.'
            : 'Satu baris = satu corporate. Isi identitas, profil operasional, materai, periode kontrak (teks bebas), dan catatan.';

        $sheet->setCellValue('A1', $text);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(self::INSTRUCTION_ROW)->setRowHeight(30);
    }

    private function writeGroupRow(Worksheet $sheet): void
    {
        $this->writeMergedGroups($sheet, self::GROUP_ROW, fn (array $col) => $col['row2'] ?? $col['label']);
    }

    private function writeLabelRow(Worksheet $sheet): void
    {
        foreach ($this->columns as $index => $column) {
            $col = $this->columnLetter($index + 1);
            $cell = $col.self::LABEL_ROW;
            $sheet->setCellValue($cell, $column['label']);
            $this->styleHeaderCell($sheet, $cell, $column['group']);
            $sheet->getColumnDimension($col)->setWidth($column['width'] ?? 13);
        }

        $sheet->getRowDimension(self::LABEL_ROW)->setRowHeight(32);
        $sheet->freezePane('C'.self::DATA_START_ROW);
        $lastCol = $this->columnLetter(count($this->columns));
        $sheet->setAutoFilter('A'.self::LABEL_ROW.":{$lastCol}".self::LABEL_ROW);
    }

    private function writeMergedGroups(Worksheet $sheet, int $row, callable $labelResolver): void
    {
        $start = 0;
        while ($start < count($this->columns)) {
            $groupLabel = $labelResolver($this->columns[$start]);
            $end = $start;

            while ($end + 1 < count($this->columns) && ($labelResolver($this->columns[$end + 1]) === $groupLabel)) {
                $end++;
            }

            $startCol = $this->columnLetter($start + 1);
            $endCol = $this->columnLetter($end + 1);
            $range = "{$startCol}{$row}:{$endCol}{$row}";

            if ($startCol !== $endCol) {
                $sheet->mergeCells($range);
            }

            $sheet->setCellValue("{$startCol}{$row}", $groupLabel);
            $this->styleHeaderCell($sheet, "{$startCol}{$row}", $this->columns[$start]['group'], true);

            $start = $end + 1;
        }

        $sheet->getRowDimension($row)->setRowHeight(28);
    }

    private function styleHeaderCell(Worksheet $sheet, string $cell, string $group, bool $isGroup = false): void
    {
        $sheet->getStyle($cell)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => $isGroup ? 10 : 9,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::GROUP_COLORS[$group] ?? '475569'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
    }

    private function writeExamples(Worksheet $sheet): void
    {
        $examples = $this->importType === CorporateImportColumnManifest::TYPE_SERVICE
            ? [
                [
                    'branch_code' => 'PTJ',
                    'customer_name' => 'AGUNG AUTO MALL',
                    'airline_GA_INTR' => 'UP 3% (FROM TOTAL - MARKUP) GA & NON GA',
                    'airline_GA_DOM' => 'UP 3% FROM BASIC (MARK UP)',
                    'airline_JT_DOM' => 'ON THE TICKET DOMESTIK',
                    'airline_QZ_DOM' => 'UP 50,000 PER TIKET (MARK UP)',
                    'svc_HOTEL_DOM' => 'UP 30,000 (MARKUP)',
                ],
                [
                    'branch_code' => 'VNT',
                    'customer_name' => 'ABC PRESIDENT INDONESIA',
                    'svc_TICKET_ALL' => 'OTT + 3% + 1.1%',
                    'svc_HOTEL_DOM' => 'OTT + 3% atau min. 50k + 1.1%',
                    'svc_HOTEL_INTR' => 'OTT + 3% atau min. 100k + 1.1%',
                ],
            ]
            : [
                [
                    'branch_code' => 'PTJ',
                    'customer_name' => 'AGUNG AUTO MALL',
                    'corp_mode' => 'yes',
                    'faktur_pajak' => 'yes',
                    'show_service_fee' => 'yes',
                    'invoice_method' => 'print',
                    'cn_percentage' => '2.5',
                    'materai' => 'STAMP DUTY',
                    'contract_period' => 'Kontrak seumur hidup atau 02-09-2020 (perpanjang otomatis selama masih ada transaksi)',
                    'general_note' => 'Contoh catatan corporate',
                ],
                [
                    'branch_code' => 'VNT',
                    'customer_name' => 'ABC PRESIDENT INDONESIA',
                    'corp_mode' => 'yes',
                    'faktur_pajak' => 'yes',
                    'show_service_fee' => 'no',
                    'invoice_method' => 'print',
                    'cn_percentage' => '1',
                    'materai' => 'YES (STAMP DUTY)',
                    'contract_period' => '01-06-2025 s/d 31-05-2026',
                ],
            ];

        $row = self::DATA_START_ROW;
        foreach ($examples as $example) {
            $sheet->fromArray($this->exampleRow($example), null, 'A'.$row);
            $row++;
        }
    }

    /** @param  array<string, string>  $values */
    private function exampleRow(array $values): array
    {
        $line = array_fill(0, count($this->columns), '');
        $keys = array_column($this->columns, 'key');

        foreach ($values as $key => $value) {
            $index = array_search($key, $keys, true);
            if ($index !== false) {
                $line[$index] = (string) $value;
            }
        }

        return $line;
    }

    private function writeEmptyRows(Worksheet $sheet, int $count): void
    {
        // Placeholder rows — borders applied in applyBorders().
    }

    private function applyTableStyle(Worksheet $sheet): void
    {
        $lastCol = $this->columnLetter(count($this->columns));
        $lastRow = $this->lastDataRow();

        $sheet->getStyle('A'.self::DATA_START_ROW.":{$lastCol}{$lastRow}")->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
        ]);

        for ($row = self::DATA_START_ROW; $row <= $lastRow; $row++) {
            if (($row - self::DATA_START_ROW) % 2 === 1) {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8FAFC'],
                    ],
                ]);
            }
        }
    }

    private function applyBorders(Worksheet $sheet): void
    {
        $lastCol = $this->columnLetter(count($this->columns));
        $lastRow = $this->lastDataRow();

        $sheet->getStyle('A'.self::GROUP_ROW.":{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
        ]);

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '94A3B8'],
                ],
            ],
        ]);

        $sheet->getStyle('A'.self::LABEL_ROW.":{$lastCol}".self::LABEL_ROW)->applyFromArray([
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['rgb' => '334155'],
                ],
            ],
        ]);

        $sheet->getStyle('A'.self::GROUP_ROW.":{$lastCol}".self::GROUP_ROW)->applyFromArray([
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '475569'],
                ],
            ],
        ]);

        foreach ($this->columnGroupBoundaries() as $colIndex) {
            if ($colIndex === 0) {
                continue;
            }

            $col = $this->columnLetter($colIndex + 1);
            $sheet->getStyle("{$col}".self::GROUP_ROW.":{$col}{$lastRow}")->applyFromArray([
                'borders' => [
                    'left' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['rgb' => '64748B'],
                    ],
                ],
            ]);
        }

        $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['rgb' => '94A3B8'],
                ],
            ],
        ]);
    }

    /** @return array<int, int> */
    private function columnGroupBoundaries(): array
    {
        $boundaries = [0];
        $previousGroup = null;

        foreach ($this->columns as $index => $column) {
            $group = $column['row2'] ?? $column['label'];
            if ($previousGroup !== null && $group !== $previousGroup) {
                $boundaries[] = $index;
            }
            $previousGroup = $group;
        }

        return $boundaries;
    }

    private function lastDataRow(): int
    {
        return self::DATA_START_ROW + 1 + 40;
    }

    private function applyValidations(Worksheet $sheet): void
    {
        $branches = Branch::query()->orderBy('code')->pluck('code')->implode(',');
        $invoiceMethods = 'print,email,print_email,no';
        $branchCol = $this->columnLetter(1);
        $invoiceCol = null;

        foreach ($this->columns as $index => $column) {
            if ($column['key'] === 'invoice_method') {
                $invoiceCol = $this->columnLetter($index + 1);
            }
        }

        $lastRow = $this->lastDataRow();
        for ($row = self::DATA_START_ROW; $row <= $lastRow; $row++) {
            $this->setListValidation($sheet, "{$branchCol}{$row}", $branches);
            if ($invoiceCol) {
                $this->setListValidation($sheet, "{$invoiceCol}{$row}", $invoiceMethods);
            }
        }
    }

    private function setListValidation(Worksheet $sheet, string $cell, string $list): void
    {
        if ($list === '') {
            return;
        }

        $validation = $sheet->getCell($cell)->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setFormula1('"'.$list.'"');
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }
}
