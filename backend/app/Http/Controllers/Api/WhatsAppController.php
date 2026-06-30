<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\MessageTemplate;
use App\Services\FonnteService;
use App\Support\MessageTemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class WhatsAppController extends Controller
{
    public function status(): JsonResponse
    {
        $service = app(FonnteService::class);

        return response()->json([
            'configured' => $service->isConfigured(),
            'country_code' => config('services.fonnte.country_code', '62'),
        ]);
    }

    public function send(Request $request, FonnteService $fonnte): JsonResponse
    {
        $data = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'template_id' => ['nullable', 'integer', 'exists:message_templates,id'],
            'message' => ['nullable', 'string', 'max:5000'],
            'delay' => ['nullable', 'string', 'max:20'],
        ]);

        if (empty($data['template_id']) && empty(trim($data['message'] ?? ''))) {
            return response()->json([
                'message' => 'Pilih template atau isi pesan manual.',
            ], 422);
        }

        $templateBody = null;

        if (! empty($data['template_id'])) {
            $template = MessageTemplate::query()
                ->where('id', $data['template_id'])
                ->where('is_active', true)
                ->first();

            if (! $template) {
                return response()->json(['message' => 'Template tidak ditemukan atau nonaktif.'], 422);
            }

            $templateBody = $template->body;
        }

        $employees = Employee::query()
            ->with(['customer.branch', 'title', 'contact'])
            ->whereIn('id', $data['employee_ids'])
            ->get();

        $user = $request->user();
        if ($user && ! $user->hasFullBranchAccess()) {
            $allowed = $user->allowedBranchIds();
            $employees = $employees->filter(
                fn (Employee $employee) => in_array($employee->customer?->branch_id, $allowed, true)
            );
        }

        if ($employees->isEmpty()) {
            return response()->json(['message' => 'Employee tidak ditemukan.'], 422);
        }

        $entries = [];
        $skipped = [];

        foreach ($employees as $employee) {
            $phone = FonnteService::normalizePhone($employee->mobile);

            if (! $phone) {
                $skipped[] = [
                    'employee_id' => $employee->id,
                    'name' => $employee->full_name,
                    'reason' => 'Nomor mobile kosong atau tidak valid.',
                ];

                continue;
            }

            $message = $data['message'] ?? null;
            if ($message === null || trim($message) === '') {
                $message = MessageTemplateRenderer::render($templateBody, $employee);
            } else {
                $message = MessageTemplateRenderer::render($message, $employee);
            }

            $entry = [
                'target' => $phone,
                'message' => $message,
            ];

            if (! empty($data['delay'])) {
                $entry['delay'] = $data['delay'];
            }

            $entries[] = $entry;
        }

        if ($entries === []) {
            return response()->json([
                'message' => 'Tidak ada employee dengan nomor mobile valid.',
                'skipped' => $skipped,
            ], 422);
        }

        try {
            $response = $fonnte->sendBulk($entries);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'skipped' => $skipped,
            ], 502);
        }

        return response()->json([
            'message' => sprintf('Permintaan pengiriman WhatsApp untuk %d penerima berhasil dikirim ke Fonnte.', count($entries)),
            'sent_count' => count($entries),
            'skipped' => $skipped,
            'fonnte_response' => $response,
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'template_id' => ['nullable', 'integer', 'exists:message_templates,id'],
            'message' => ['nullable', 'string', 'max:5000'],
        ]);

        $employee = Employee::query()
            ->with(['customer.branch', 'title'])
            ->findOrFail($data['employee_id']);

        $user = $request->user();
        if ($user && ! $user->hasFullBranchAccess()) {
            $allowed = $user->allowedBranchIds();
            if (! in_array($employee->customer?->branch_id, $allowed, true)) {
                abort(403, 'Anda tidak memiliki akses ke employee ini.');
            }
        }

        $body = $data['message'] ?? null;

        if (($body === null || trim($body) === '') && ! empty($data['template_id'])) {
            $template = MessageTemplate::findOrFail($data['template_id']);
            $body = $template->body;
        }

        if ($body === null || trim($body) === '') {
            return response()->json(['message' => 'Template atau pesan wajib diisi.'], 422);
        }

        return response()->json([
            'data' => [
                'employee_id' => $employee->id,
                'name' => $employee->full_name,
                'mobile' => FonnteService::normalizePhone($employee->mobile),
                'message' => MessageTemplateRenderer::render($body, $employee),
            ],
        ]);
    }
}
