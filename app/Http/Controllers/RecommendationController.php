<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecommendationController extends Controller
{
    public function getRecommendation(Request $request)
    {
        $keyword = trim($request->input('keyword') ?? $request->input('minat') ?? '');

        if (!$keyword) {
            return response()->json(['error' => 'Keyword wajib diisi'], 400);
        }

        $systemPrompt = 'Pakar rekomendasi jurusan SMK PGRI 3 Malang.
Tugas: Cocokkan minat user (Indo/Eng/singkatan) ke 2 jurusan paling relevan (utama & alternatif).

Daftar Jurusan:
- TIK: RPL, DKV, BP, NIMA, BDP, TKJ (Hacking/Cybersecurity -> TKJ/RPL)
- Kelistrikan: TE & AV (Audio/Sound -> TE & AV), PB, EI, KI
- Otomotif: TP, TL, TBSM, TKR, BO

Aturan:
1. Jika tidak relevan dengan sekolah, jurusan_utama.name="Tidak ditemukan", alternatif=null.
2. Output WAJIB JSON murni tanpa markdown.

JSON Format:
{
  "jurusan_utama": {"name": "", "department": "", "description": ""},
  "jurusan_alternatif": {"name": "", "department": "", "description": ""}
}';

        $userPrompt = "Minat saya: {$keyword}";

        $aiText = $this->callGroq($systemPrompt, $userPrompt);

        if (!$aiText) {
            $aiText = $this->callGemini($systemPrompt, $userPrompt);
        }

        if (!$aiText) {
            return response()->json(['error' => 'Gagal menghubungi AI service'], 500);
        }

        $cleanText = trim(preg_replace('/```(json)?|```/', '', $aiText));

        if (preg_match('/\{[\s\S]*\}/', $cleanText, $matches)) {
            $parsed = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                return response()->json($parsed);
            }
        }

        Log::error('JSON Parse Error:', ['raw_text' => $cleanText, 'error' => json_last_error_msg()]);

        return response()->json([
            'error' => 'Gagal parsing JSON dari AI',
            'json_error' => json_last_error_msg(),
        ], 500);
    }

    private function callGroq(string $systemPrompt, string $userPrompt): ?string
    {
        $apiKey = $this->sanitizeKey(config('services.groq.api_key', env('GROQ_API_KEY')));
        if (empty($apiKey)) {
            return null;
        }

        $model = config('services.groq.model', env('GROQ_MODEL', 'openai/gpt-oss-120b'));

        try {
            $response = $this->getHttpClient([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 1024,
                'response_format' => ['type' => 'json_object'],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['choices'][0]['message']['content'] ?? null;
            }

            Log::warning('Groq recommendation failed: ' . $response->body());
        } catch (\Throwable $e) {
            Log::warning('Groq recommendation exception: ' . $e->getMessage());
        }

        return null;
    }

    private function callGemini(string $systemPrompt, string $userPrompt): ?string
    {
        $keys = array_filter([
            $this->sanitizeKey(config('services.gemini.api_key', env('GEMINI_API_KEY'))),
            $this->sanitizeKey(config('services.gemini.backup_key', env('GEMINI_BACKUP_API_KEY'))),
            'AIzaSyDsyQUaUf6zE7py7ppxqIIx41a_4npFoSw',
        ]);

        if (empty($keys)) {
            return null;
        }

        $endpoint = config('services.gemini.endpoint', env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key='));

        foreach ($keys as $key) {
            $url = str_contains($endpoint, 'key=') ? $endpoint . $key : $endpoint . '?key=' . $key;

            try {
                $response = $this->getHttpClient()->post($url, [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $systemPrompt . "\n\n" . $userPrompt],
                            ],
                        ],
                    ],
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    if ($text) {
                        return $text;
                    }
                }

                Log::warning('Gemini recommendation failed: ' . $response->body());
            } catch (\Throwable $e) {
                Log::warning('Gemini recommendation exception: ' . $e->getMessage());
            }
        }

        return null;
    }

    private function sanitizeKey(?string $key): ?string
    {
        if (!$key) {
            return null;
        }

        $cleaned = trim(preg_replace('/[\x00-\x1F\x7F\xA0"\']/u', '', $key));

        return !empty($cleaned) ? $cleaned : null;
    }

    private function getHttpClient(array $headers = []): PendingRequest
    {
        $http = Http::timeout(25)->withHeaders($headers);

        if (!app()->environment('production')) {
            $http->withoutVerifying();
        }

        return $http;
    }
}
