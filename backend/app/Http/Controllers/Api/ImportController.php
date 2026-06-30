<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CsvImport\EmployeeCsvImporter;
use App\Services\CsvImport\ServiceFeeCsvImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function importEmployees(Request $request, EmployeeCsvImporter $importer): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $importer->import(
            $request->file('file')->getRealPath(),
            (int) $data['branch_id'],
            $data['customer_name'] ?? null,
        );

        return response()->json([
            'message' => "Import employee selesai. {$result['imported']} data berhasil diimport.",
            'data' => $result,
        ]);
    }

    public function importServiceFees(Request $request, ServiceFeeCsvImporter $importer): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls'],
            'version_name' => ['required', 'string', 'max:120'],
        ]);

        $result = $importer->import(
            $request->file('file')->getRealPath(),
            $request->string('version_name')->toString(),
            $request->user()?->id
        );

        return response()->json([
            'message' => "Import service fee selesai. {$result['imported_customers']} customer, {$result['imported_rules']} rules.",
            'data' => $result,
        ]);
    }
}
