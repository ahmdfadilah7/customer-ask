<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FonnteService
{
    public function isConfigured(): bool
    {
        return filled(config('services.fonnte.token'));
    }

    /**
     * @param  array<int, array{target: string, message: string, delay?: string}>  $entries
     */
    public function sendBulk(array $entries): array
    {
        $token = config('services.fonnte.token');

        if (! $token) {
            throw new RuntimeException('Token Fonnte belum dikonfigurasi. Set FONNTE_TOKEN di file .env backend.');
        }

        if ($entries === []) {
            throw new RuntimeException('Tidak ada penerima pesan.');
        }

        $defaultDelay = config('services.fonnte.default_delay', '1-3');

        $payload = array_map(function (array $entry) use ($defaultDelay) {
            return [
                'target' => $entry['target'],
                'message' => $entry['message'],
                'delay' => $entry['delay'] ?? $defaultDelay,
            ];
        }, $entries);

        try {
            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->asForm()->post(config('services.fonnte.api_url'), [
                'data' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'countryCode' => config('services.fonnte.country_code', '62'),
            ]);
        } catch (RequestException $exception) {
            throw new RuntimeException('Gagal menghubungi API Fonnte: '.$exception->getMessage(), 0, $exception);
        }

        $body = $response->json() ?? ['raw' => $response->body()];

        if (! $response->successful()) {
            $message = is_array($body) ? ($body['reason'] ?? $body['message'] ?? $response->body()) : $response->body();
            throw new RuntimeException('Fonnte API error: '.$message);
        }

        return is_array($body) ? $body : ['raw' => $body];
    }

    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '62')) {
            $digits = '0'.substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            return $digits;
        }

        return '0'.$digits;
    }
}
