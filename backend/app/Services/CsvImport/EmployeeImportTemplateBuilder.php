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

class EmployeeImportTemplateBuilder
{
    public const SHEET_IMPORT = 'Import Employee';

    public const INSTRUCTION_ROW = 1;

    public const GROUP_ROW = 2;

    public const LABEL_ROW = 3;

    public const DATA_START_ROW = 4;

    /** @var array<string, string> */
    private const GROUP_COLORS = [
        'wajib' => '1E3A8A',
        'employee' => '059669',
    ];

    /** @var array<int, array<string, mixed>> */
    private array $columns = [];

    public function build(): string
    {
        $this->columns = EmployeeImportColumnManifest::columns();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET_IMPORT);

        $this->writeInstructionRow($sheet);
        $this->writeGroupRow($sheet);
        $this->writeLabelRow($sheet);
        $this->writeExamples($sheet);
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

    private function writeInstructionRow(Worksheet $sheet): void
    {
        $lastCol = $this->columnLetter(count($this->columns));
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue(
            'A1',
            'Satu baris = satu employee. Isi Cabang + Nama Corporate, lalu data passport/traveler. Title: Mr./Mrs./Ms. Kosongkan dengan "-" jika tidak ada.'
        );
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(self::INSTRUCTION_ROW)->setRowHeight(36);
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
            $group = $labelResolver($this->columns[$start]);
            $end = $start;

            while ($end + 1 < count($this->columns) && $labelResolver($this->columns[$end + 1]) === $group) {
                $end++;
            }

            $startCol = $this->columnLetter($start + 1);
            $endCol = $this->columnLetter($end + 1);
            $sheet->mergeCells("{$startCol}{$row}:{$endCol}{$row}");
            $sheet->setCellValue("{$startCol}{$row}", $group);
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
        $examples = [
            [
                'branch_code' => 'PTJ',
                'customer_name' => 'PT. Sol Melia Indonesia',
                'title' => 'Mr.',
                'full_name' => 'GONZALO MACEDA ARRANZ',
                'nationality' => 'SPANISH',
                'passport_number' => 'XDF092390',
                'passport_expiry' => 'February 25, 2030',
                'ktp_number' => '-',
                'birthdate' => '-',
                'mobile' => '+6281260139523',
                'email' => 'gonzalo.maceda@melia.com',
                'ticket_name_format' => 'MACEDA ARRANZ / GONZALO',
            ],
            [
                'branch_code' => 'PTJ',
                'customer_name' => 'PT. Sol Melia Indonesia',
                'title' => 'Mrs.',
                'full_name' => 'MARIA MELANIA TRI KARTIKA',
                'nationality' => 'INDONESIA',
                'passport_number' => 'X1173221',
                'passport_expiry' => 'October 11, 2026',
                'ktp_number' => '3515135805760001',
                'birthdate' => '-',
                'mobile' => '+62811336518',
                'email' => 'maria.melania@melia.com',
                'ticket_name_format' => 'TRI KARTIKA / MARIA MELANIA',
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
                $line[$index] = $value;
            }
        }

        return $line;
    }

    private function applyTableStyle(Worksheet $sheet): void
    {
        $lastCol = $this->columnLetter(count($this->columns));
        $lastRow = $this->lastDataRow();

        $sheet->getStyle('A'.self::DATA_START_ROW.":{$lastCol}{$lastRow}")->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
            'font' => ['size' => 10],
        ]);
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
        return self::DATA_START_ROW + 1 + 50;
    }

    private function applyValidations(Worksheet $sheet): void
    {
        $branches = Branch::query()->orderBy('code')->pluck('code')->implode(',');
        $branchCol = $this->columnLetter(1);
        $titleCol = $this->columnLetter(3);
        $lastRow = $this->lastDataRow();

        for ($row = self::DATA_START_ROW; $row <= $lastRow; $row++) {
            $this->setListValidation($sheet, "{$branchCol}{$row}", $branches);
            $this->setListValidation($sheet, "{$titleCol}{$row}", 'Mr.,Mrs.,Ms.');
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
