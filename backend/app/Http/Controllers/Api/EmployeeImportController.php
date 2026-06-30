<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CsvImport\EmployeeCsvImporter;
use App\Services\CsvImport\EmployeeImportColumnManifest;
use App\Services\CsvImport\EmployeeImportTemplateBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmployeeImportController extends Controller
{
    public function reference(): JsonResponse
    {
        return response()->json([
            'format' => 'xlsx',
            'layout' => 'wide_sheet',
            'sheet_name' => EmployeeImportTemplateBuilder::SHEET_IMPORT,
            'header_rows' => [
                ['row' => 2, 'description' => 'Grup kolom'],
                ['row' => 3, 'description' => 'Label kolom'],
            ],
            'column_groups' => collect(EmployeeImportColumnManifest::columns())
                ->pluck('row2')
                ->unique()
                ->values(),
            'columns' => collect(EmployeeImportColumnManifest::columns())
                ->map(fn ($col) => [
                    'key' => $col['key'],
                    'label' => $col['label'],
                    'group' => $col['row2'] ?? $col['label'],
                ])
                ->values(),
            'legacy_format' => [
                'supported' => true,
                'description' => 'File export lama (Employee List - {Corporate}) dengan header No./Title/Name tetap didukung.',
                'required_form_field' => 'customer_name',
            ],
        ]);
    }

    public function template(): Response
    {
        $builder = new EmployeeImportTemplateBuilder;
        $binary = $builder->build();

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="import-employee-template.xlsx"',
        ]);
    }

    public function import(Request $request, EmployeeCsvImporter $importer): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $branchId = (int) $data['branch_id'];

        if ($user && ! $user->hasFullBranchAccess() && ! in_array($branchId, $user->allowedBranchIds(), true)) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke cabang ini.'], 403);
        }

        $result = $importer->import(
            $request->file('file')->getRealPath(),
            $branchId,
            $data['customer_name'] ?? null,
        );

        $stats = $result['stats'];
        $message = sprintf(
            'Import employee selesai: %d data (%d baru, %d diperbarui).',
            $stats['employees'],
            $stats['created'],
            $stats['updated'],
        );

        return response()->json([
            'message' => $message,
            'data' => $result,
        ], empty($result['errors']) ? 200 : 207);
    }
}
