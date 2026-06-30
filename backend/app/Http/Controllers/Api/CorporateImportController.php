<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Airline;
use App\Models\RegionScope;
use App\Models\ServiceCategory;
use App\Services\CsvImport\CorporateImportColumnManifest;
use App\Services\CsvImport\CorporateImportTemplateBuilder;
use App\Services\CsvImport\CorporateMasterCsvImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CorporateImportController extends Controller
{
    public function reference(Request $request): JsonResponse
    {
        $type = $this->resolveImportType($request);

        $payload = [
            'format' => 'xlsx',
            'import_type' => $type,
            'layout' => 'wide_sheet',
            'sheet_name' => $type === CorporateImportColumnManifest::TYPE_SERVICE
                ? CorporateImportTemplateBuilder::SHEET_SERVICE
                : CorporateImportTemplateBuilder::SHEET_IMPORT,
            'header_rows' => [
                ['row' => 2, 'description' => 'Grup kolom'],
                ['row' => 3, 'description' => 'Label kolom'],
            ],
            'column_groups' => collect(CorporateImportColumnManifest::columns($type))
                ->pluck('row2')
                ->unique()
                ->values(),
            'region_scopes' => RegionScope::query()->orderBy('code')->get(['code', 'name']),
            'invoice_methods' => ['print', 'email', 'print_email', 'no'],
        ];

        if ($type === CorporateImportColumnManifest::TYPE_SERVICE) {
            $payload['airline_headers'] = CorporateImportColumnManifest::activeAirlines()
                ->map(fn ($airline) => [
                    'code' => $airline->code,
                    'name' => $airline->name,
                    'label' => "{$airline->name} ({$airline->code})",
                    'scopes' => $airline->regionScopes->pluck('code')->values(),
                ])
                ->values();
            $payload['service_categories'] = ServiceCategory::query()
                ->pricingSlots()
                ->where('code', '!=', 'MATERAI')
                ->orderBy('group_code')
                ->orderBy('sort_order')
                ->get(['code', 'name', 'group_code']);
            $payload['airlines'] = Airline::query()->orderBy('sort_order')->orderBy('code')->get(['code', 'name']);
        }

        return response()->json($payload);
    }

    public function template(Request $request): \Illuminate\Http\Response
    {
        $type = $this->resolveImportType($request);
        $builder = new CorporateImportTemplateBuilder;
        $binary = $builder->build($type);

        $filename = $type === CorporateImportColumnManifest::TYPE_SERVICE
            ? 'import-service-template.xlsx'
            : 'import-corporate-template.xlsx';

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function import(Request $request, CorporateMasterCsvImporter $importer): JsonResponse
    {
        $type = $this->resolveImportType($request);

        $rules = [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'import_type' => ['sometimes', Rule::in([
                CorporateImportColumnManifest::TYPE_CORPORATE,
                CorporateImportColumnManifest::TYPE_SERVICE,
            ])],
        ];

        if ($type === CorporateImportColumnManifest::TYPE_SERVICE) {
            $rules['version_name'] = ['required', 'string', 'max:120'];
        } else {
            $rules['version_name'] = ['nullable', 'string', 'max:120'];
        }

        $data = $request->validate($rules);

        $user = $request->user();
        $branchId = (int) $data['branch_id'];

        if ($user && ! $user->hasFullBranchAccess() && ! in_array($branchId, $user->allowedBranchIds(), true)) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke cabang ini.'], 403);
        }

        $result = $importer->import(
            $request->file('file')->getRealPath(),
            $branchId,
            $type,
            $data['version_name'] ?? null,
            $user?->id,
            $user,
        );

        $stats = $result['stats'];
        $message = $type === CorporateImportColumnManifest::TYPE_SERVICE
            ? sprintf(
                'Import service selesai: %d customer, %d pricing rule.',
                $stats['customers'],
                $stats['pricing_rules'],
            )
            : sprintf(
                'Import corporate selesai: %d customer, %d pricing rule (materai).',
                $stats['customers'],
                $stats['pricing_rules'],
            );

        return response()->json([
            'message' => $message,
            'data' => $result,
        ], empty($result['errors']) ? 200 : 207);
    }

    private function resolveImportType(Request $request): string
    {
        $type = $request->query('type') ?? $request->input('import_type', CorporateImportColumnManifest::TYPE_CORPORATE);

        if (! in_array($type, [
            CorporateImportColumnManifest::TYPE_CORPORATE,
            CorporateImportColumnManifest::TYPE_SERVICE,
        ], true)) {
            abort(422, 'Tipe import tidak valid.');
        }

        return $type;
    }
}
