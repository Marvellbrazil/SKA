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
        $parsed = json_decode($cleanText, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
            return response()->json($parsed);
        }

        Log::error('JSON Parse Error:', ['raw_text' => $cleanText, 'error' => json_last_error_msg()]);

        return response()->json([
            'error' => 'Gagal parsing JSON dari AI',
            'json_error' => json_last_error_msg(),
        ], 500);
    }

    private function callGroq(string $systemPrompt, string $userPrompt): ?string
    {
        $apiKey = config('services.groq.api_key');
        if (empty($apiKey)) {
            return null;
        }

        $model = config('services.groq.model', 'llama-3.3-70b-versatile');

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
        $apiKey = config('services.gemini.api_key');
        if (empty($apiKey)) {
            return null;
        }

        $endpoint = config('services.gemini.endpoint', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=');
        $url = str_contains($endpoint, 'key=') ? $endpoint . $apiKey : $endpoint . '?key=' . $apiKey;

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
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            }

            Log::warning('Gemini recommendation failed: ' . $response->body());
        } catch (\Throwable $e) {
            Log::warning('Gemini recommendation exception: ' . $e->getMessage());
        }

        return null;
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
